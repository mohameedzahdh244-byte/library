<?php
// massage/subscription.php
// إشعار انتهاء/اقتراب انتهاء الاشتراك (مرة واحدة لكل انتهاء)

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/DB.php';
// تمت إزالة الاعتمادات المفقودة واستبدالها بدوال محلية كما في باقي سكربتات الرسائل

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

// ========== قالب رسالة الاشتراك ==========
if (!function_exists('tpl_subscription_expiring')) {
    function tpl_subscription_expiring($name, $daysLeft) {
        // الصياغة المطلوبة: "مشتركنا العزيز الاسم، لاستمرار تقديم خدماتنا لك، قم بتجديد اشتراكك لدينا"
        $name = trim($name);
        return sprintf('مشتركنا العزيز %s، لاستمرار تقديم خدماتنا لك، قم بتجديد اشتراكك لدينا.', $name);
    }
}

header('Content-Type: application/json; charset=utf-8');

$out = ['success' => true, 'rows' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => []];

try {
    // المدة قبل الانتهاء (افتراضي 7 أيام)
    $days_ahead = 7;
    try {
        global $settings;
        if (isset($settings)) {
            $days_ahead = (int)$settings->get('subscription_reminder_days', 7);
        } else {
            $q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='subscription_reminder_days' LIMIT 1");
            if ($row = $q->fetch_assoc()) { $days_ahead = (int)$row['setting_value']; }
        }
    } catch (Exception $e) {}

    $today = date('Y-m-d');
    $sent = state_read('subscription_once');
    if (!is_array($sent)) { $sent = []; }

    $sql = "
        SELECT x.mem_no, x.end_date, c.mem_name, 
               (SELECT GROUP_CONCAT(mp.mem_phone SEPARATOR ', ') 
                FROM mem_phone mp 
                WHERE mp.mem_no = c.mem_no 
                LIMIT 1) AS mem_phone
        FROM (
            SELECT ms.mem_no, MAX(ms.end_date) AS end_date
            FROM member_subscription ms
            GROUP BY ms.mem_no
        ) x
        JOIN customer c ON x.mem_no = c.mem_no
        WHERE (x.end_date IS NULL OR DATE(x.end_date) <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $days_ahead);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = method_exists($res, 'num_rows') ? $res->num_rows : (property_exists($res, 'num_rows') ? $res->num_rows : 0);
    $out['rows'] = (int)$rows;

    // عنوان خدمة الإرسال والتوكن المحلية
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/massage/subscription.php')), '/');
    $sendUrl = $scheme . '://' . $host . $basePath . '/send.php';
    $tokenUrl = $scheme . '://' . $host . $basePath . '/token.php';

    // احصل على توكن واحد لعملية الإرسال
    $tokCtx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true]]);
    $tokRaw = @file_get_contents($tokenUrl, false, $tokCtx);
    $tokData = json_decode($tokRaw ?: 'null', true);
    $sessionToken = '';
    if (is_array($tokData)) { $sessionToken = $tokData['data']['token'] ?? $tokData['data']['ResponseObject'] ?? ''; }
    if ($sessionToken === '') { throw new Exception('فشل الحصول على التوكن'); }

    // تجهيز دفعات للإرسال المتوازي
    $batch = [];
    $sentCnt = 0; $skipped = 0;
    while ($row = $res->fetch_assoc()) {
        $key = $row['mem_no'] . '_' . $row['end_date'];
        if (isset($sent[$key])) { $skipped++; continue; }

        $daysLeft = 0;
        if (!empty($row['end_date'])) {
            $daysLeft = (int)((strtotime($row['end_date']) - strtotime($today)) / 86400);
        } else { $daysLeft = -1; }
        if ($daysLeft < -30) { continue; }

        $msg = tpl_subscription_expiring($row['mem_name'], $daysLeft);
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
        $batch[] = ['key' => $key, 'row' => $row, 'msg' => $msg, 'phone' => $picked];
    }

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
        do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);
        foreach ($hs as $h) {
            $ch = $h['ch']; $it = $h['it'];
            $resp = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data = json_decode($resp ?: 'null', true);
            $ok = ($code >= 200 && $code < 300);
            if (is_array($data) && array_key_exists('success', $data)) { $ok = $ok && (bool)$data['success']; }
            sms_log('subscription', $it['row']['mem_no'], $it['row']['mem_phone'], $it['msg'], ['status' => $code, 'data' => $data, 'raw' => $resp]);
            if ($ok) { $sent[$it['key']] = ['t' => time()]; $sentCnt++; }
            curl_multi_remove_handle($mh, $ch); curl_close($ch);
        }
        curl_multi_close($mh);
    }

    state_write('subscription_once', $sent);
    $out['sent'] = $sentCnt; $out['skipped'] = $skipped;

} catch (Throwable $e) {
    http_response_code(500);
    $out['success'] = false;
    $out['errors'][] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
