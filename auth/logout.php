
<?php
require_once __DIR__ . '/../config/init.php';
session_unset();  // إزالة جميع متغيرات الجلسة
session_destroy(); // تدمير الجلسة تمامًا

// منع الرجوع باستخدام الكاش
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// إعادة التوجيه لصفحة تسجيل الدخول
header("Location: loginform.php");
exit();
?>