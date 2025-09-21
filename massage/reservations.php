<?php
// massage/reservations.php
// إرسال إشعار توفّر الحجز مرة واحدة لكل حجز

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/DB.php';
// تم حذف notifications/sms_service.php، لذلك نضمّن الدوال الضرورية هنا مباشرة

// ========== التخزين البسيط للحالة (ملف JSON واحد) ==========
if (!function_exists('state_read')) {
    function __state_file() { return __DIR__ . '/state.json'; }
    function state_read($key) {
        $f = __state_file();
        if (!is_file($f)) return [];
        $json = @file_get_contents($f);
        $all  = json_decode($json ?: '[]', true);
        return is_array($all[$key] ?? null) ? $all[$key] : [];
    }
    function state_write($key, $data) {
        $f = __state_file();
        $all = [];
        if (is_file($f)) {
            $json = @file_get_contents($f);
            $all  = json_decode($json ?: '[]', true);
            if (!is_array($all)) $all = [];
        }
        $all[$key] = $data;
        @file_put_contents($f, json_encode($all, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }
}

// ========== تطبيع/فلترة أرقام الهاتف (فلسطين) ==========
if (!function_exists('normalize_local_phone')) {
    /**
     * يحول صيغ مثل +97059xxxxxxx أو 0097059xxxxxxx أو 97059xxxxxxx إلى صيغة محلية 059xxxxxxx
     * ويسمح فقط بالأرقام التي تبدأ 059 أو 056 ويتأكد أن الطول 10 أرقام.
     */
    function normalize_local_phone($raw) {
        $s = trim((string)$raw);
        if ($s === '') return '';
        // أزل كل ما عدا الأرقام وعلامة + في البداية
        $s = preg_replace('/[^+\d]/', '', $s);
        // أزل +
        $s = ltrim($s, '+');
        // أزل 00 الدولية إن وجدت
        if (strpos($s, '00') === 0) { $s = substr($s, 2); }
        // حالات 970...
        if (strpos($s, '97059') === 0 && strlen($s) >= 13) {
            $last9 = substr($s, 3);
            if (preg_match('/^(059\d{7})/', $last9, $m)) { return $m[1]; }
        }
        if (strpos($s, '97056') === 0 && strlen($s) >= 13) {
            $last9 = substr($s, 3);
            if (preg_match('/^(056\d{7})/', $last9, $m)) { return $m[1]; }
        }
        if (strpos($s, '970') === 0 && strlen($s) >= 12) {
            $rest = substr($s, 3);
            if (preg_match('/^(59\d{7})$/', $rest)) return '0' . $rest;
            if (preg_match('/^(56\d{7})$/', $rest)) return '0' . $rest;
        }
        if (preg_match('/^(059|056)\d{7}$/', $s)) return $s;
        if (preg_match('/^(59|56)\d{7}$/', $s)) return '0' . $s;
        return '';
    }
}

// ========== لوج بسيط إلى ملف ==========
if (!function_exists('sms_log')) {
    function sms_log($type, $mem_no, $raw_phone, $message, $providerResp) {
        $line = [
            'ts' => date('c'),
            'type' => $type,
            'mem_no' => $mem_no,
            'phone' => $raw_phone,
            'message' => $message,
            'provider' => $providerResp,
        ];
        $logDir = __DIR__;
        $log = $logDir . '/sms.log';
        @file_put_contents($log, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }
}

// ========== قوالب رسائل ==========
if (!function_exists('tpl_reservation_available')) {
    function tpl_reservation_available($name, $title, $hours) {
        $hours = max(1, (int)$hours);
        $name  = trim($name);
        $title = trim($title);
        // تنسيق المدة: 48 ساعة => يومين، مضاعفات 24 => أيام، غير ذلك => ساعات
        if ($hours === 48) {
            $deadline = 'يومين';
        } elseif ($hours % 24 === 0) {
            $d = (int)($hours / 24);
            $deadline = $d . ' يوم';
        } else {
            $deadline = $hours . ' ساعة';
        }
        return sprintf('مشتركنا العزيز %s، الكتاب الذي طلبت حجزه "%s" أصبح متوفرًا. يمكنك استعارته خلال %s، أو سنعيره لمن يطلبه.', $name, $title, $deadline);
    }
}

header('Content-Type: application/json; charset=utf-8');

$out = ['success' => true, 'rows' => 0, 'sent' => 0, 'skipped' => 0, 'made_available' => 0, 'errors' => []];

try {
    $sent = state_read('reservation_available');
    if (!is_array($sent)) { $sent = []; }

    // مدة صلاحية الاستلام (افتراضي 48 ساعة)
    $hours = 48;
    try {
        global $settings;
        if (isset($settings)) {
            $conf = $settings->getBorrowingSettings();
            if (!empty($conf['reservation_expiry_hours'])) { $hours = (int)$conf['reservation_expiry_hours']; }
        } else {
            $q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='reservation_expiry_hours' LIMIT 1");
            if ($row = $q->fetch_assoc()) { $hours = (int)$row['setting_value']; }
        }
    } catch (Exception $e) {}

    // أولاً: تنفيذ منطق الفحص (ترقية أقدم pending لكل كتاب إلى available إذا لم توجد إعارة نشطة ولا available فعّال)
    try {
        $sqlScan = "
            SELECT r.reservation_id, r.serialnum_book
            FROM book_reservation r
            LEFT JOIN (
                SELECT bt.serialnum_book
                FROM borrow_transaction bt
                LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                WHERE rb.return_date IS NULL
                GROUP BY bt.serialnum_book
            ) open_b ON open_b.serialnum_book = r.serialnum_book
            WHERE open_b.serialnum_book IS NULL
              AND r.status = 'pending'
              AND NOT EXISTS (
                  SELECT 1 FROM book_reservation r2
                  WHERE r2.serialnum_book = r.serialnum_book
                    AND r2.status = 'available'
                    AND (r2.expiry_date IS NULL OR r2.expiry_date > NOW())
              )
            ORDER BY r.serialnum_book, r.reservation_date ASC
        ";

        $resScan = $conn->query($sqlScan);
        $picked = []; $ids = [];
        while ($row = $resScan->fetch_assoc()) {
            $book = $row['serialnum_book'];
            if (!isset($picked[$book])) { $picked[$book] = true; $ids[] = (int)$row['reservation_id']; }
        }
        if (!empty($ids)) {
            $idsList = implode(',', $ids);
            $exp = date('Y-m-d H:i:s', time() + $hours * 3600);
            $upd = $conn->query("UPDATE book_reservation SET status='available', expiry_date='" . $conn->real_escape_string($exp) . "' WHERE reservation_id IN ($idsList)");
            if ($upd) { $out['made_available'] = (int)$conn->affected_rows; }
        }
    } catch (Throwable $e) {
        // لا توقف الإرسال إن فشل الفحص
        $out['errors'][] = 'scan: ' . $e->getMessage();
    }

    $sql = "
        SELECT r.reservation_id, r.mem_no, r.serialnum_book, r.status, r.expiry_date,
               c.mem_name, 
               (SELECT GROUP_CONCAT(mp.mem_phone SEPARATOR ', ') 
                FROM mem_phone mp 
                WHERE mp.mem_no = c.mem_no 
                LIMIT 1) AS mem_phone, 
               b.book_title
        FROM book_reservation r
        JOIN customer c ON r.mem_no = c.mem_no
        LEFT JOIN book b ON r.serialnum_book = b.serialnum_book
        WHERE r.status = 'available'
          AND (r.expiry_date IS NULL OR r.expiry_date > NOW())
        ORDER BY r.reservation_date ASC
    ";

    $res = $conn->query($sql);
    $rows = method_exists($res, 'num_rows') ? $res->num_rows : (property_exists($res, 'num_rows') ? $res->num_rows : 0);
    $out['rows'] = (int)$rows;

    // عنوان خدمة التوكن والإرسال المحلية
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/massage/reservations.php')), '/');
    $sendUrl = $scheme . '://' . $host . $basePath . '/send.php';
    $tokenUrl = $scheme . '://' . $host . $basePath . '/token.php';

    // احصل على توكن واحد لعملية الإرسال كلها
    $tokCtx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true]]);
    $tokRaw = @file_get_contents($tokenUrl, false, $tokCtx);
    $tokData = json_decode($tokRaw ?: 'null', true);
    $sessionToken = '';
    if (is_array($tokData)) {
        $sessionToken = $tokData['data']['token'] ?? $tokData['data']['ResponseObject'] ?? '';
    }
    if ($sessionToken === '') { throw new Exception('فشل الحصول على التوكن'); }

    // تجهيز الدفعات للإرسال المتوازي
    $batch = [];
    $sentCnt = 0; $skipped = 0;
    while ($row = $res->fetch_assoc()) {
        $rid = (string)$row['reservation_id'];
        if (isset($sent[$rid])) { $skipped++; continue; }

        $msg = tpl_reservation_available($row['mem_name'], $row['book_title'] ?? $row['serialnum_book'], $hours);
        // التقط أول رقم صحيح بصيغة 059/056 من القائمة (قد تكون مفصولة بفواصل)
        $picked = '';
        $rawPhones = preg_split('/[,;]+/', (string)$row['mem_phone']);
        if (is_array($rawPhones)) {
            foreach ($rawPhones as $rp) {
                $n = normalize_local_phone($rp);
                if ($n !== '' && preg_match('/^(059|056)\d{7}$/', $n)) { $picked = $n; break; }
            }
        } else {
            $n = normalize_local_phone($row['mem_phone']);
            if ($n !== '' && preg_match('/^(059|056)\d{7}$/', $n)) { $picked = $n; }
        }
        if ($picked === '') { continue; }
        $batch[] = ['rid' => $rid, 'row' => $row, 'msg' => $msg, 'phone' => $picked];
    }

    // حد أقصى للتوازي لتحسين الاستقرار
    $concurrency = 25;
    for ($i = 0; $i < count($batch); $i += $concurrency) {
        $chunk = array_slice($batch, $i, $concurrency);
        $mh = curl_multi_init();
        $hs = [];
        foreach ($chunk as $it) {
            $q = http_build_query(['phone' => $it['phone'], 'message' => $it['msg'], 'token' => $sessionToken]);
            $url = $sendUrl . '?' . $q;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_multi_add_handle($mh, $ch);
            $hs[] = ['ch' => $ch, 'it' => $it];
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        foreach ($hs as $h) {
            $ch = $h['ch'];
            $it = $h['it'];
            $resp = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data = json_decode($resp ?: 'null', true);
            $ok = ($code >= 200 && $code < 300);
            if (is_array($data) && array_key_exists('success', $data)) { $ok = $ok && (bool)$data['success']; }
            sms_log('reservation_available', $it['row']['mem_no'], $it['row']['mem_phone'], $it['msg'], ['status' => $code, 'data' => $data, 'raw' => $resp]);
            if ($ok) { $sent[$it['rid']] = ['t' => time()]; $sentCnt++; }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    state_write('reservation_available', $sent);
    $out['sent'] = $sentCnt; $out['skipped'] = $skipped;

} catch (Throwable $e) {
    http_response_code(500);
    $out['success'] = false;
    $out['errors'][] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
