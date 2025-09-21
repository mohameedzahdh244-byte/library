<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// السماح فقط للطلبات عبر AJAX/POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit;
}

// تحقق صلاحيات الموظف بدون تحويل حتى لا نفقد JSON
if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type']) || 
    ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح: الرجاء تسجيل الدخول كموظف ثم المحاولة مجدداً.']);
    exit;
}

function respond($ok, $msg, $data = null){
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

// التقاط المدخلات
$type_id         = isset($_POST['type_id']) && $_POST['type_id'] !== '' ? (int)$_POST['type_id'] : null;
// نعتمد التصنيفات عبر FK. نجعل free_text_type غير مستخدم حالياً
$free_text_type  = null;
$title           = isset($_POST['title']) ? trim($_POST['title']) : '';
$location        = isset($_POST['location']) ? trim($_POST['location']) : '';
$start_dt        = isset($_POST['start_datetime']) ? str_replace('T', ' ', trim($_POST['start_datetime'])) : '';
$end_dt          = isset($_POST['end_datetime']) ? str_replace('T', ' ', trim($_POST['end_datetime'])) : '';
$supervisors     = isset($_POST['supervisors']) ? trim($_POST['supervisors']) : null;
$is_paid         = isset($_POST['is_paid']) ? (int)$_POST['is_paid'] : 0;
$fee_amount_raw  = isset($_POST['fee_amount']) ? trim($_POST['fee_amount']) : '';
$description     = isset($_POST['description']) ? trim($_POST['description']) : null;
$publish         = isset($_POST['publish']) ? (int)$_POST['publish'] : 0;

if ($title === '' || $location === '' || $start_dt === '' || $end_dt === '') {
    respond(false, 'الرجاء تعبئة الحقول المطلوبة');
}

// تحويل الرسوم
$fee_amount = null;
if ($is_paid === 1) {
    if ($fee_amount_raw === '') {
        $fee_amount = 0.00; // مسموح عدم الإدخال ونعتبرها 0
    } else if (!is_numeric($fee_amount_raw) || $fee_amount_raw < 0) {
        respond(false, 'قيمة الرسوم غير صحيحة');
    } else {
        $fee_amount = (float)$fee_amount_raw;
    }
}

// تحقق من الوقت
$start_ts = strtotime($start_dt);
$end_ts   = strtotime($end_dt);
if ($start_ts === false || $end_ts === false) {
    respond(false, 'صيغة التاريخ/الوقت غير صحيحة');
}
if ($end_ts <= $start_ts) {
    respond(false, 'تاريخ/وقت النهاية يجب أن يكون بعد البداية');
}

$status = $publish === 1 ? 'published' : 'draft';
$created_by = isset($_SESSION['user_no']) ? (int)$_SESSION['user_no'] : 0;

// لا مزيد من تجاوزات على type_id: سيُستخدم مباشرة كمرجع إلى activity_categories(id)

$sql = "INSERT INTO activities (type_id, title, location, start_datetime, end_datetime, supervisors, is_paid, fee_amount, description, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    respond(false, 'خطأ في التحضير: ' . $conn->error);
}
// types: i s s s s s i s s s i (ربط الرسوم كسلسلة لسهولة تمرير NULL)
$type_id_param = $type_id; // قد يكون NULL إذا لم يختر تصنيفاً
$supervisors_param = $supervisors !== '' ? $supervisors : null;
$fee_amount_param = isset($fee_amount) ? (string)$fee_amount : null; // NULL أو قيمة نصية
$types = 'isssssisssi';
$stmt->bind_param(
    $types,
    $type_id_param,
    $title,
    $location,
    $start_dt,
    $end_dt,
    $supervisors_param,
    $is_paid,
    $fee_amount_param,
    $description,
    $status,
    $created_by
);

if (!$stmt->execute()) {
    respond(false, 'فشل التنفيذ: ' . $stmt->error);
}

$insert_id = $stmt->insert_id;
$stmt->close();

respond(true, $publish === 1 ? 'تم الحفظ والنشر بنجاح' : 'تم حفظ النشاط كمسودة', ['id' => $insert_id]);
