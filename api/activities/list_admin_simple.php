<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// صلاحيات موظف/مدير
if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type']) || ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin')) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'غير مصرح']);
  exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
if ($per_page < 1) $per_page = 15;
if ($per_page > 15) $per_page = 15; // الحد الأعلى 15
$offset = ($page - 1) * $per_page;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$all_status = isset($_GET['all_status']) && $_GET['all_status'] == '1';
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;

// تحديد شرط الحالة
if ($activity_id > 0) {
  $where = "WHERE id = ?";
  $params = [$activity_id];
  $types = 'i';
} elseif ($all_status) {
  $where = "WHERE status IN ('published', 'draft', 'archived')";
  $params = [];
  $types = '';
} else {
  $where = "WHERE status = 'published'";
  $params = [];
  $types = '';
}

if ($q !== '' && $activity_id == 0) {
  $where .= " AND title LIKE ?";
  $types .= 's';
  $params[] = '%'.$q.'%';
}

// العد الكلي
$sql_count = "SELECT COUNT(*) AS cnt FROM activities $where";
$stmt = $conn->prepare($sql_count);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$total = 0; if ($res && $row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
$stmt->close();

// جلب البيانات
$sql = "SELECT id, title, start_datetime, end_datetime, location, status
        FROM activities
        $where
        ORDER BY start_datetime DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$types2 = $types . 'ii';
$params2 = $params; $params2[] = $per_page; $params2[] = $offset;
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($res && $row = $res->fetch_assoc()) { $data[] = $row; }
$stmt->close();

echo json_encode([
  'success' => true,
  'page' => $page,
  'per_page' => $per_page,
  'total' => $total,
  'data' => $data
]);
