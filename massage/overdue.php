<?php
// massage/overdue.php
// إرسال رسائل أسبوعية للمتأخرين بإرجاع الكتب

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/DB.php';
// تم حذف notifications/sms_service.php، لذلك نضمّن الدوال الضرورية هنا مباشرة
// ========== التخزين البسيط للحالة (ملف JSON واحد) ==========
if (!function_exists('state_read')) {
    function __state_file() { return __DIR__ . '/state.json'; }

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
        // الآن حالات 970...
        if (strpos($s, '97059') === 0 && strlen($s) >= 13) {
            // 97059 + 7 أرقام => خذ آخر 9 وسبقها 0
            $last9 = substr($s, 3); // 059xxxxxxx
            if (preg_match('/^(059\d{7})/', $last9, $m)) { return $m[1]; }
        }
        if (strpos($s, '97056') === 0 && strlen($s) >= 13) {
            $last9 = substr($s, 3);
            if (preg_match('/^(056\d{7})/', $last9, $m)) { return $m[1]; }
        }
        // لو بدأ 970 فقط ومن ثم 59/56 بدون الصفر
        if (strpos($s, '970') === 0 && strlen($s) >= 12) {
            $rest = substr($s, 3);
            if (preg_match('/^(59\d{7})$/', $rest)) return '0' . $rest; // 059xxxxxxx أو 056xxxxxxx
            if (preg_match('/^(56\d{7})$/', $rest)) return '0' . $rest;
        }
        // صيغة محلية صحيحة مسبقًا
        if (preg_match('/^(059|056)\d{7}$/', $s)) return $s;
        // أحيانًا تُحفظ بدون صفر البداية
        if (preg_match('/^(59|56)\d{7}$/', $s)) return '0' . $s;
        return '';
    }
}
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

// ========== قالب الرسالة ==========
if (!function_exists('tpl_overdue')) {
    function tpl_overdue($name, $title, $days) {
        $days = max(0, (int)$days);
        $name  = trim($name);
        $title = trim($title);
        if ($days === 0) {
            return sprintf('عزيزنا المشترك %s، موعد إرجاع كتاب "%s" اليوم. نرجو إعادته خلال يومين.', $name, $title);
        }
        return sprintf('عزيزنا المشترك %s، تأخر إرجاع كتاب "%s" منذ %d أيام. نرجو إعادته خلال يومين.', $name, $title, $days);
    }
}

header('Content-Type: application/json; charset=utf-8');

$result = ['success' => true, 'rows' => 0, 'sent' => 0, 'skipped' => 0, 'invalid' => 0, 'errors' => []];

try {
    $sent = state_read('overdue_weekly');
    if (!is_array($sent)) { $sent = []; }

    $sql = "
        SELECT 
            bt.borrow_detail_id,
            bt.serialnum_book,
            bt.boro_exp_ret_date,
            b.book_title,
            c.mem_no,
            c.mem_name,
            (SELECT GROUP_CONCAT(mp.mem_phone SEPARATOR ', ') 
             FROM mem_phone mp 
             WHERE mp.mem_no = c.mem_no 
             LIMIT 1) AS mem_phone,
            DATEDIFF(CURDATE(), bt.boro_exp_ret_date) AS days_overdue
        FROM borrow_transaction bt
        LEFT JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
        LEFT JOIN customer c ON ct.mem_no = c.mem_no
        LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
        LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book
        WHERE rb.return_date IS NULL
          AND DATE(bt.boro_exp_ret_date) < CURDATE()
        ORDER BY bt.boro_exp_ret_date ASC
    ";

    $res = $conn->query($sql);
    $rows = method_exists($res, 'num_rows') ? $res->num_rows : (property_exists($res, 'num_rows') ? $res->num_rows : 0);
    $result['rows'] = (int)$rows;

    $one_week = 7 * 24 * 60 * 60;
    $sentCnt = 0; $skipped = 0; $invalid = 0;

    // الحصول على توكن واحد خارج اللوب
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/massage/overdue.php')), '/');
    $tokenUrl = $scheme . '://' . $host . $basePath . '/token.php';
    
    $opts = ['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true]];
    $ctx = stream_context_create($opts);
    $tokenRaw = @file_get_contents($tokenUrl, false, $ctx);
    $tokenData = json_decode($tokenRaw ?: 'null', true);
    $sessionToken = '';
    if (is_array($tokenData)) {
        $sessionToken = $tokenData['data']['token'] ?? $tokenData['data']['ResponseObject'] ?? '';
    }
    
    if ($sessionToken === '') {
        throw new Exception('فشل في الحصول على التوكن');
    }
    
    $sendUrl = $scheme . '://' . $host . $basePath . '/send.php';
    $batch = []; // تجميع الرسائل للإرسال المتوازي
    
    // تحضير جميع الرسائل أولاً
    while ($row = $res->fetch_assoc()) {
        $key = (string)$row['borrow_detail_id'];
        $last = isset($sent[$key]['t']) ? (int)$sent[$key]['t'] : 0;
        if ($last && (time() - $last) < $one_week) { $skipped++; continue; }

        $msg = tpl_overdue($row['mem_name'], $row['book_title'] ?? $row['serialnum_book'], max(0, (int)$row['days_overdue']));
        
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
        if ($picked === '') { $invalid++; continue; }
        
        $batch[] = [
            'key' => $key,
            'phone' => $picked,
            'message' => $msg,
            'row' => $row
        ];
    }
    
    // إرسال جميع الرسائل بشكل متوازي مع تحديد حد التوازي وإعادة المحاولة مرة واحدة للفشل
    $concurrency = 25;
    for ($i = 0; $i < count($batch); $i += $concurrency) {
        $currentBatch = array_slice($batch, $i, $concurrency);
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        foreach ($currentBatch as $item) {
            // إرسال عبر الخدمة المحلية send.php مع تمرير التوكن
            $smsUrl = $sendUrl . '?' . http_build_query([
                'message' => $item['message'],
                'phone'   => $item['phone'],
                'token'   => $sessionToken,
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $smsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[] = ['handle' => $ch, 'item' => $item];
        }

        // تنفيذ الطلبات المتوازية
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        // معالجة النتائج مع إعادة المحاولة لمرة واحدة عند الفشل
        foreach ($curlHandles as $handleData) {
            $ch = $handleData['handle'];
            $item = $handleData['item'];

            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data = json_decode($response ?: 'null', true);

            $ok = ($httpCode >= 200 && $httpCode < 300);
            if (is_array($data) && array_key_exists('success', $data)) {
                $ok = $ok && (bool)$data['success'];
            }

            // لا إعادة محاولة تلقائية: الفشل يُعرض للمستخدم ويمكنه إعادة المحاولة يدويًا من الواجهة

            sms_log('overdue', $item['row']['mem_no'], $item['row']['mem_phone'], $item['message'],
                   ['status' => $httpCode, 'data' => $data, 'raw' => $response, 'normalized' => $item['phone']]);

            if ($ok) {
                $sent[$item['key']] = ['t' => time()];
                $sentCnt++;
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
    }

    state_write('overdue_weekly', $sent);
    $result['sent'] = $sentCnt;
    $result['skipped'] = $skipped;
    $result['invalid'] = $invalid;

} catch (Throwable $e) {
    http_response_code(500);
    $result['success'] = false;
    $result['errors'][] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
