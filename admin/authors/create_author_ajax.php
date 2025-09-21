<?php
require_once __DIR__ . '/../../config/init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff','admin'])) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'صلاحيات غير كافية']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// جلب الرقم التالي ANO
if ($method === 'GET' && $action === 'next_no') {
    try {
        $res = $conn->query("SELECT MAX(ANO) AS max_no FROM authors");
        $row = $res ? $res->fetch_assoc() : null;
        $next = (int)($row && $row['max_no'] ? $row['max_no'] : 0) + 1;
        echo json_encode(['success'=>true, 'next_no'=>$next]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'خطأ في جلب الرقم التالي']);
    }
    exit;
}

// إنشاء مؤلف جديد
if ($method === 'POST') {
    $ANO = isset($_POST['ANO']) ? trim($_POST['ANO']) : '';
    $Aname = isset($_POST['Aname']) ? trim($_POST['Aname']) : '';

    if ($ANO === '' || $Aname === '') {
        http_response_code(422);
        echo json_encode(['success'=>false, 'message'=>'الرجاء تعبئة رقم واسم المؤلف']);
        exit;
    }

    // تأكد أن ANO فريد
    $stmt = $conn->prepare('SELECT 1 FROM authors WHERE ANO = ?');
    $stmt->bind_param('s', $ANO);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        http_response_code(409);
        echo json_encode(['success'=>false, 'message'=>'رقم المؤلف مستخدم مسبقاً']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO authors (ANO, Aname) VALUES (?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'تعذر تحضير الاستعلام']);
        exit;
    }
    $stmt->bind_param('ss', $ANO, $Aname);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'author'=>[
            'ANO'=>$ANO,
            'Aname'=>$Aname
        ]]);
    } else {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'فشل حفظ المؤلف']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success'=>false, 'message'=>'طريقة غير مدعومة']);
