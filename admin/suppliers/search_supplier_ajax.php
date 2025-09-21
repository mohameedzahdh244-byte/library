<?php
require_once __DIR__ . '/../../config/init.php';
mb_internal_encoding('UTF-8');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff','admin'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = 20;

if ($q === '' || mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(['results'=>[]]);
    exit;
}

$sql = "SELECT sup_no, sup_name FROM supplier WHERE sup_name LIKE CONCAT('%', ?, '%') ORDER BY sup_name LIMIT ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'فشل تحضير الاستعلام عن الموردين']);
    exit;
}
$stmt->bind_param('si', $q, $limit);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'فشل تنفيذ الاستعلام عن الموردين']);
    exit;
}
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'sup_no' => $row['sup_no'],
        'sup_name' => $row['sup_name']
    ];
}

echo json_encode(['results'=>$items]);
exit;
