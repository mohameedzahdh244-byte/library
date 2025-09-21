<?php
// massage/send.php
// API بسيط لإرسال SMS عبر EGATE

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$phone   = $_REQUEST['phone']   ?? '';
$message = $_REQUEST['message'] ?? '';
$token   = $_REQUEST['token']   ?? '';

if ($phone === '' || trim($message) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_params', 'hint' => 'phone and message are required']);
    exit;
}

$base = 'https://egate.hebron-city.ps/api/api';
$sendUrl = $base . '/SendTestSMS';

// احصل على التوكن من API المحلي token.php إن لم يُمرر
if ($token === '') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/massage/send.php')), '/');
    $localTokenUrl = $scheme . '://' . $host . $basePath . '/token.php';

    $opts = [ 'http' => [ 'method' => 'GET', 'timeout' => 8, 'ignore_errors' => true ] ];
    $ctx  = stream_context_create($opts);
    $raw  = @file_get_contents($localTokenUrl, false, $ctx);
    $status = 0;
    if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0] ?? '', $m)) {
        $status = (int)$m[1];
    }
    $data = json_decode($raw ?: 'null', true);
    // توقع أحد الشكلين:
    // 1) { success: true, data: { token: '...' } }
    // 2) { success: true, data: { HttpStatus:200, ResponseObject:'<JWT>' } }
    $tokenVal = '';
    if (is_array($data)) {
        $tokenVal = $data['data']['token'] ?? $data['data']['ResponseObject'] ?? '';
    }
    if (!($status >= 200 && $status < 300) || $tokenVal === '') {
        http_response_code($status ?: 502);
        echo json_encode(['success' => false, 'error' => 'token_fetch_failed', 'status' => $status, 'resp' => $data, 'raw' => $raw, 'from' => $localTokenUrl]);
        exit;
    }
    $token = $tokenVal;
}

// إرسال الرسالة
$q = http_build_query(['message' => $message, 'phone' => $phone]);
$url = $sendUrl . '?' . $q;
$opts = [ 'http' => [
    'method' => 'GET',
    'timeout' => 12,
    'ignore_errors' => true,
    'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
]];
$ctx = stream_context_create($opts);
$raw = @file_get_contents($url, false, $ctx);

$status = 0;
if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0] ?? '', $m)) {
    $status = (int)$m[1];
}
$data = json_decode($raw ?: 'null', true);
$success = ($status >= 200 && $status < 300);
if (is_array($data) && array_key_exists('success', $data)) {
    $success = $success && (bool)$data['success'];
}

echo json_encode([
    'success' => $success,
    'status'  => $status,
    'data'    => $data,
    'raw'     => $raw,
    'message' => $success ? 'تم الإرسال بنجاح' : 'فشل الإرسال',
]);
