<?php
require_once __DIR__ . '/../../config/init.php';// تأكد من بدء الجلسة
include_once __DIR__ . '/../../config/DB.php';

$message = "";
$message_type = ""; // success, warning, danger
$user_no = $_SESSION['user_no'];  

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mem_no           = $_POST['mem_no'];
    $mem_name         = $_POST['mem_name'];
    $mem_id           = $_POST['mem_id']?? '';
    // معالجة رقم الهوية الفارغ
    if ($mem_id === '' || $mem_id === null) {
        $mem_id = null;
    }
    $mem_gender       = $_POST['mem_gender'] ?? '';
    $mem_phone        = $_POST['mem_phone'];
    $mem_type         = $_POST['mem_type'];
    $mem_birth        = $_POST['mem_birth'] ?? '';
    // معالجة التاريخ الفارغ
    if ($mem_birth === '' || $mem_birth === null) {
        $mem_birth = null;
    }
    $personal_photo   = $_POST['personal_photo'] ?? '';
    $mem_password     = $_POST['mem_password'];
    $start_date       = $_POST['start_date'];
    $end_date         = $_POST['end_date'];
    $amount           = $_POST['amount'] ?? '';
    // معالجة المبلغ الفارغ
    if ($amount === '' || $amount === null) {
        $amount = null;
    }
    $currency         = $_POST['currency'] ?? '';
    $atatus           = $_POST['atatus'];
    // حقول جديدة: مكان السكن والعمل (استبدال mem_marital المحذوف)
    $mem_residence    = $_POST['mem_residence'] ?? '';
    $mem_work         = $_POST['mem_work'] ?? '';
    
    
    // تحقق من الحقول المطلوبة أولاً
    if ($mem_name === '' || $mem_phone === '' || $start_date === '' || $end_date === '' || $atatus === '') {
        $message = "⚠️ الرجاء تعبئة جميع الحقول المطلوبة أولاً.";
        $message_type = "warning";
    } else {
        // تحقق من عدم تكرار رقم المشترك أو رقم الهوية
        $check = $conn->query("SELECT mem_no, mem_id FROM customer WHERE mem_no='$mem_no' OR mem_id='$mem_id' ");
        if ($check && $check->num_rows > 0) {
            $message = "⚠️ رقم المشترك أو رقم الهوية موجود مسبقاً، الرجاء استخدام رقم مختلف.";
            $message_type = "warning";
        } elseif ($mem_password === '' || $mem_password === null) {
            $message = "⚠️ الرجاء إدخال كلمة مرور للمشترك.";
            $message_type = "warning";
        } else {
            // معالجة صورة الكاميرا Base64 إن وجدت
            $photoPathForDB = '';
            if (is_string($personal_photo) && strlen($personal_photo) > 0) {
                // صيغة data URL: data:image/png;base64,AAAA
                if (preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $personal_photo, $m)) {
                    $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                    $data = base64_decode($m[2]);
                    if ($data !== false) {
                        $uploadDir = realpath(__DIR__ . '/../../public');
                        if ($uploadDir === false) { $uploadDir = __DIR__ . '/../../public'; }
                        $membersDir = $uploadDir . '/uploads/members';
                        if (!is_dir($membersDir)) { @mkdir($membersDir, 0775, true); }
                        $fileName = 'mem_' . preg_replace('/[^0-9A-Za-z_-]/','', (string)$mem_no) . '_' . time() . '.' . $ext;
                        $fullPath = $membersDir . '/' . $fileName;
                        if (@file_put_contents($fullPath, $data) !== false) {
                            // مسار الوصول عبر الويب (يتماشى مع بنية المشروع الحالية)
                            $photoPathForDB = '/public/uploads/members/' . $fileName;
                        }
                    }
                }
            }
            // إدخال بيانات المشترك (بدون mem_phone، لأنه محفوظ في جدول mem_phone)
            $sql1 = "INSERT INTO customer (
                mem_no, mem_name, mem_id, mem_gender, mem_type, mem_birth, personal_photo, mem_password, mem_residence, mem_work
            ) VALUES (
                '$mem_no', '$mem_name', " .
                ($mem_id === null ? "NULL" : "'$mem_id'") . ", '$mem_gender', '$mem_type', " .
                ($mem_birth === null ? "NULL" : "'$mem_birth'") . ", '" . $conn->real_escape_string($photoPathForDB) . "', '$mem_password', '" . $conn->real_escape_string($mem_residence) . "', '" . $conn->real_escape_string($mem_work) . "'
            )";
            // إدخال بيانات الاشتراك
            $sql2 = "INSERT INTO member_subscription (
               user_no, mem_no, start_date, end_date, amount, currency, atatus
            ) VALUES (
                $user_no,'$mem_no', '$start_date', '$end_date', " . ($amount === null ? "NULL" : "'$amount'") . ", '$currency', '$atatus'
            )";
            try {
                $conn->query($sql1);
                $conn->query($sql2);

                // إدراج أرقام الهواتف في جدول mem_phone
                $phones = isset($_POST['phones']) && is_array($_POST['phones']) ? $_POST['phones'] : [];
                // أضف mem_phone إذا لم يكن ضمن المصفوفة لضمان حفظ أول رقم
                if (!empty($mem_phone)) { $phones[] = $mem_phone; }
                // تنظيف وتفريد
                $phones = array_values(array_unique(array_filter(array_map(function($p){ return trim((string)$p); }, $phones), function($p){ return $p !== ''; })));

                if (!empty($phones)) {
                    // استخدم INSERT IGNORE لاحترام القيد الفريد (mem_no, mem_phone)
                    $stmtPhone = $conn->prepare("INSERT IGNORE INTO mem_phone (mem_no, mem_phone) VALUES (?, ?)");
                    foreach ($phones as $p) {
                        $stmtPhone->bind_param('is', $mem_no, $p);
                        $stmtPhone->execute();
                    }
                    $stmtPhone->close();
                }

                $message = "✔️ تم إضافة المشترك والاشتراك بنجاح.";
                $message_type = "success";
                // Audit log (مركزي)
                if (isset($auditLogger)) {
                    $auditLogger->logCreate(null, 'customer', $mem_no, [
                        'mem_no' => $mem_no,
                        'mem_name' => $mem_name,
                    ]);
                }
            } catch (mysqli_sql_exception $e) {
                $message = "❌ حدث خطأ غير متوقع: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'message' => $message,
    'type' => $message_type
]);
exit;
?>
