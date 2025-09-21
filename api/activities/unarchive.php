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

// صلاحيات موظف/مدير فقط
if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type']) || 
    ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin')) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'غير مصرح: هذه الواجهة خاصة بالموظفين']);
  exit;
}

$activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
if ($activity_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'معرّف النشاط غير صالح']);
  exit;
}

// تحقق أن النشاط موجود ومؤرشف
$chk = $conn->prepare("SELECT id, status FROM activities WHERE id = ? LIMIT 1");
$chk->bind_param('i', $activity_id);
$chk->execute();
$res = $chk->get_result();
if (!$res || $res->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'النشاط غير موجود']);
  exit;
}
$act = $res->fetch_assoc();
$chk->close();

// تحديث الحالة إلى published
$up = $conn->prepare("UPDATE activities SET status = 'published', updated_at = NOW() WHERE id = ?");
$up->bind_param('i', $activity_id);
if (!$up->execute()) {
  echo json_encode(['success' => false, 'message' => 'فشل إلغاء الأرشفة: ' . $up->error]);
  exit;
}
$up->close();

echo json_encode(['success' => true, 'message' => 'تم إلغاء أرشفة النشاط بنجاح']);
?>
