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

// السماح فقط للموظف/المدير
if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type']) || 
    ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin')) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'غير مصرح: هذه الواجهة خاصة بالموظفين']);
  exit;
}

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$decision = isset($_POST['decision']) ? trim($_POST['decision']) : '';
$admin_note = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : null;
$decider = (int)$_SESSION['user_no'];

if ($request_id <= 0 || !in_array($decision, ['approved','rejected'], true)) {
  echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
  exit;
}

$sql = "UPDATE activity_requests SET status = ?, admin_note = ?, decided_at = NOW(), decided_by = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssii', $decision, $admin_note, $decider, $request_id);
if (!$stmt->execute()) {
  echo json_encode(['success' => false, 'message' => 'فشل التحديث: '.$stmt->error]);
  exit;
}

if ($stmt->affected_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'لم يتم العثور على الطلب أو لم يحدث تغيير']);
  exit;
}

echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الطلب']);
