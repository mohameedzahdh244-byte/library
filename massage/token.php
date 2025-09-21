<?php
// massage/token.php
// API بسيط للحصول على Token من EGATE عبر username/password

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// بيانات الدخول ثابتة كما طُلِب (بدون قراءة من request)
$u = '888888';
$p = 'Biblio@2025$';

$base = 'https://egate.hebron-city.ps/api/api';
$tokenUrl = $base . '/Token?username=' . urlencode($u) . '&password=' . urlencode($p);

$opts = [
    'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'ignore_errors' => true,
    ]
];
$ctx = stream_context_create($opts);
$raw = @file_get_contents($tokenUrl, false, $ctx);

$status = 0;
if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0] ?? '', $m)) {
    $status = (int)$m[1];
}

$data = json_decode($raw ?: 'null', true);
if ($status >= 200 && $status < 300) {
    echo json_encode(['success' => true, 'status' => $status, 'data' => $data]);
} else {
    http_response_code($status ?: 502);
    echo json_encode(['success' => false, 'status' => $status, 'data' => $data, 'raw' => $raw]);
}
