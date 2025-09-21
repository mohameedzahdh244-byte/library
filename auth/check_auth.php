<?php
#التأكد من تسجيل الدخول قبل الوصول لأي صفحة

require_once __DIR__ . '/../config/init.php';

if (!isset($_SESSION['user_no'])) {
    header("Location: ../auth/loginform.php");
    exit;
}
?>
