<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';

header('Content-Type: application/json; charset=utf-8');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(30, (int)$_GET['per_page'])) : 9;
$offset = ($page - 1) * $per_page;

$type_id = isset($_GET['type_id']) && $_GET['type_id'] !== '' ? (int)$_GET['type_id'] : null;

$where = "WHERE status = 'published' AND end_datetime > NOW()";
$params = [];
$types = '';

if (!is_null($type_id)) {
  $where .= " AND a.type_id = ?";
  $types .= 'i';
  $params[] = $type_id;
}

// العد الكلي
$sql_count = "SELECT COUNT(*) AS cnt FROM activities a $where";
$stmt = $conn->prepare($sql_count);
if ($types !== '') {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$total = 0;
if ($res && $row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
$stmt->close();

// جلب البيانات
$sql = "SELECT a.id,
               COALESCE(c.name_ar, '') AS type_name,
               a.title, a.location, a.start_datetime, a.end_datetime, a.supervisors, a.is_paid, a.fee_amount, a.description
        FROM activities a
        LEFT JOIN activity_categories c ON c.id = a.type_id
        $where
        ORDER BY a.start_datetime ASC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $per_page;
$params2[] = $offset;

$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($res && $row = $res->fetch_assoc()) {
  $data[] = $row;
}
$stmt->close();

echo json_encode([
  'success' => true,
  'page' => $page,
  'per_page' => $per_page,
  'total' => $total,
  'data' => $data
]);
