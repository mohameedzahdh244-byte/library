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

checkStaffPermission();

function respond($ok, $msg, $data=null){ echo json_encode(['success'=>$ok,'message'=>$msg,'data'=>$data]); exit; }

$name_ar = isset($_POST['name_ar']) ? trim($_POST['name_ar']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : null;
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

if ($name_ar === '') {
  respond(false, 'الاسم بالعربية مطلوب');
}

$sql = "INSERT INTO activity_categories (name_ar, description, is_active, created_at) VALUES (?,?,?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) respond(false, 'خطأ في التحضير: '.$conn->error);
$stmt->bind_param('ssi', $name_ar, $description, $is_active);
if (!$stmt->execute()) {
  respond(false, 'فشل التنفيذ: '.$stmt->error);
}
$id = $stmt->insert_id;
$stmt->close();

respond(true, 'تم إضافة التصنيف بنجاح', [ 'id' => $id, 'display_name' => $name_ar ]);
