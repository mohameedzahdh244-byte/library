<?php
require_once '../../config/init.php';
mb_internal_encoding('UTF-8');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff','admin'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = 20;

// Require at least 2 UTF-8 characters like borrow test2 behavior
if ($q === '' || mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(['results'=>[]]);
    exit;
}

// بحث مرن: يبدأ من أول حرف، ويبحث باحتواء
$stmt = $conn->prepare("SELECT pub_no, pub_name FROM publisher WHERE pub_name LIKE CONCAT('%', ?, '%') ORDER BY pub_name LIMIT ?");
$stmt->bind_param('si', $q, $limit);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'id' => (string)$row['pub_no'],
        'text' => $row['pub_name']
    ];
}

echo json_encode(['results' => $items]);
