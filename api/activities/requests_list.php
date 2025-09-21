<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// السماح فقط للموظف/المدير
if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type']) || 
    ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin')) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'غير مصرح: هذه الواجهة خاصة بالموظفين']);
  exit;
}

$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
if ($activity_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'معرّف النشاط غير صالح']);
  exit;
}

$sql = "SELECT r.id, r.activity_id, r.member_id, r.reason, r.status, r.admin_note, r.created_at, r.decided_at,
               m.mem_name AS member_name
        FROM activity_requests r
        LEFT JOIN customer m ON m.mem_no = r.member_id
        WHERE r.activity_id = ?
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $activity_id);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($res && $row = $res->fetch_assoc()) { $data[] = $row; }
$stmt->close();

echo json_encode(['success' => true, 'data' => $data]);
