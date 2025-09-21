<?php
require_once __DIR__ . '/../../config/init.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// جلب الرقم التالي sup_no (مسموح بدون صلاحيات مشددة لسهولة الاستخدام)
if ($method === 'GET' && $action === 'next_no') {
    try {
        $res = $conn->query("SELECT MAX(sup_no) AS max_no FROM supplier");
        $row = $res ? $res->fetch_assoc() : null;
        $next = (int)($row && $row['max_no'] ? $row['max_no'] : 0) + 1;
        echo json_encode(['success'=>true, 'next_no'=>$next]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'خطأ في جلب الرقم التالي']);
    }
    exit;
}

// حماية بقية العمليات (مثل الحفظ) بصلاحيات staff/admin
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff','admin'])) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'صلاحيات غير كافية']);
    exit;
}

if ($method === 'POST') {
    $sup_no = isset($_POST['sup_no']) ? trim($_POST['sup_no']) : '';
    $sup_name = isset($_POST['sup_name']) ? trim($_POST['sup_name']) : '';
    $sup_address = isset($_POST['sup_address']) ? trim($_POST['sup_address']) : '';
    $sup_email = isset($_POST['sup_email']) ? trim($_POST['sup_email']) : '';
    $sup_tel = isset($_POST['sup_tel']) ? trim($_POST['sup_tel']) : '';

    if ($sup_no === '' || $sup_name === '') {
        http_response_code(422);
        echo json_encode(['success'=>false, 'message'=>'الرجاء تعبئة رقم واسم المورد']);
        exit;
    }

    // تأكد أن sup_no فريد
    $stmt = $conn->prepare('SELECT 1 FROM supplier WHERE sup_no = ?');
    $stmt->bind_param('s', $sup_no);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        http_response_code(409);
        echo json_encode(['success'=>false, 'message'=>'رقم المورد مستخدم مسبقاً']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO supplier (sup_no, sup_name, sup_address, sup_email, sup_tel) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'تعذر تحضير الاستعلام']);
        exit;
    }
    $stmt->bind_param('sssss', $sup_no, $sup_name, $sup_address, $sup_email, $sup_tel);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'supplier'=>[
            'sup_no'=>$sup_no,
            'sup_name'=>$sup_name,
            'sup_address'=>$sup_address,
            'sup_email'=>$sup_email,
            'sup_tel'=>$sup_tel
        ]]);
    } else {
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'فشل حفظ المورد']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success'=>false, 'message'=>'طريقة غير مدعومة']);
