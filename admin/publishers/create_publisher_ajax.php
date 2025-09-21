<?php
require_once '../../config/init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff','admin'])) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'صلاحيات غير كافية']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'GET' && $action === 'next_no') {
    try {
        $res = $conn->query("SELECT MAX(pub_no) AS max_no FROM publisher");
        $row = $res ? $res->fetch_assoc() : null;
        $next = (int)($row && $row['max_no'] ? $row['max_no'] : 0) + 1;
        echo json_encode(['success'=>true, 'next_no'=>$next]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'خطأ في جلب الرقم التالي']);
    }
    exit;
}

if ($method === 'POST') {
    $pub_no = isset($_POST['pub_no']) ? trim($_POST['pub_no']) : '';
    $pub_name = isset($_POST['pub_name']) ? trim($_POST['pub_name']) : '';
    $pub_address = isset($_POST['pub_address']) ? trim($_POST['pub_address']) : '';
    $pub_email = isset($_POST['pub_email']) ? trim($_POST['pub_email']) : '';
    $pub_tel = isset($_POST['pub_tel']) ? trim($_POST['pub_tel']) : '';

    if ($pub_no === '' || $pub_name === '') {
        http_response_code(422);
        echo json_encode(['success'=>false, 'message'=>'الرجاء تعبئة رقم واسم الناشر']);
        exit;
    }

    // تأكد أن pub_no فريد
    $stmt = $conn->prepare('SELECT 1 FROM publisher WHERE pub_no = ?');
    $stmt->bind_param('s', $pub_no);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        http_response_code(409);
        echo json_encode(['success'=>false, 'message'=>'رقم الناشر مستخدم مسبقاً']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO publisher (pub_no, pub_name, pub_address, pub_email, pub_tel) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'تعذر تحضير الاستعلام']);
        exit;
    }
    $stmt->bind_param('sssss', $pub_no, $pub_name, $pub_address, $pub_email, $pub_tel);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'publisher'=>[
            'pub_no'=>$pub_no,
            'pub_name'=>$pub_name,
            'pub_address'=>$pub_address,
            'pub_email'=>$pub_email,
            'pub_tel'=>$pub_tel
        ]]);
    } else {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'فشل حفظ الناشر']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success'=>false, 'message'=>'طريقة غير مدعومة']);
