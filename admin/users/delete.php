<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../auth/check_auth.php';

// السماح فقط للمديرين
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$embed = (isset($_GET['embed']) && $_GET['embed'] == '1');
$suffix = $embed ? '&embed=1' : '';

$user_no = $_GET['user_no'] ?? '';
if ($user_no === '' || !ctype_digit((string)$user_no)) {
    header('Location: index.php?error=' . urlencode('رقم غير صالح') . $suffix);
    exit;
}

// منع حذف المستخدم الحالي اختيارياً (تعديل سريع، يمكن إزالته إذا لا يلزم)
if (isset($_SESSION['user_no']) && (string)$_SESSION['user_no'] === (string)$user_no) {
    header('Location: index.php?error=' . urlencode('لا يمكنك حذف حسابك الحالي') . $suffix);
    exit;
}

try {
    // جلب البيانات القديمة قبل الحذف
    $userRow = null;
    $sel = $conn->prepare('SELECT user_no, user_name, user_address, user_tel, user_type FROM user WHERE user_no = ? LIMIT 1');
    $user_no_int = (int)$user_no;
    $sel->bind_param('i', $user_no_int);
    $sel->execute();
    $res = $sel->get_result();
    if ($res && $res->num_rows === 1) { $userRow = $res->fetch_assoc(); }
    if ($res) { $res->free(); }

    // تنفيذ الحذف
    $stmt = $conn->prepare('DELETE FROM user WHERE user_no = ?');
    $stmt->bind_param('i', $user_no_int);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // تسجيل العملية
        try {
            if (isset($auditLogger)) {
                $auditLogger->logDelete(null, 'user', (string)$user_no_int, $userRow ?: ['user_no' => $user_no_int]);
            }
        } catch (Throwable $e2) { /* تجاهل */ }
        header('Location: index.php?msg=' . urlencode('تم حذف الموظف بنجاح') . $suffix);
    } else {
        header('Location: index.php?error=' . urlencode('الموظف غير موجود') . $suffix);
    }
    exit;
} catch (Throwable $e) {
    // قد تفشل العملية بسبب قيود مراجع في جداول أخرى
    header('Location: index.php?error=' . urlencode('تعذر الحذف: ' . $e->getMessage()) . $suffix);
    exit;
}
