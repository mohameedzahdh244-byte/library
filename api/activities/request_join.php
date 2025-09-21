<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
  exit;
}

// السماح فقط للمشتركين
if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'غير مصرح: هذه الواجهة خاصة بالمشتركين.']);
  exit;
}

$member_id = (int)$_SESSION['user_no'];
$activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if ($activity_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'معرف النشاط غير صالح']);
  exit;
}

// تأكد أن النشاط منشور ولم ينتهِ
$sqlChk = "SELECT id FROM activities WHERE id = ? AND status = 'published' AND end_datetime > NOW() LIMIT 1";
$st = $conn->prepare($sqlChk);
$st->bind_param('i', $activity_id);
$st->execute();
$r = $st->get_result();
if (!$r || $r->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'هذا النشاط غير متاح للتسجيل حالياً']);
  exit;
}
$st->close();

// إدراج الطلب (مع التعامل مع التكرار)
try {
  $sql = "INSERT INTO activity_requests (activity_id, member_id, reason, status, created_at) VALUES (?,?,?,?, NOW())";
  $stmt = $conn->prepare($sql);
  $status = 'pending';
  $stmt->bind_param('iiss', $activity_id, $member_id, $reason, $status);
  if (!$stmt->execute()) {
    throw new Exception($stmt->error);
  }
  $id = $stmt->insert_id;
  $stmt->close();
  echo json_encode(['success' => true, 'message' => 'تم إرسال طلب الانضمام بنجاح', 'data' => ['id' => $id]]);
} catch (Throwable $e) {
  // في حال التكرار (unique activity_id, member_id)
  if (strpos($conn->error, 'Duplicate') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
    echo json_encode(['success' => false, 'message' => 'لقد قدّمت طلباً لهذا النشاط مسبقاً']);
  } else {
    echo json_encode(['success' => false, 'message' => 'فشل إرسال الطلب: ' . $e->getMessage()]);
  }
}
