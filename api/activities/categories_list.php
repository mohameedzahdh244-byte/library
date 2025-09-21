<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';

header('Content-Type: application/json; charset=utf-8');

$data = [];
try {
  $sql = "SELECT id, name_ar AS name FROM activity_categories WHERE is_active = 1 ORDER BY name_ar ASC";
  if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
  }
  echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'فشل جلب التصنيفات: '.$e->getMessage()]);
}
