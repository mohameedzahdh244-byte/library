<?php
/**
 * نظام إدارة المسارات المرن
 * يعمل محلياً وعلى أي سيرفر تلقائياً
 */

// كشف المسار الأساسي تلقائياً
$script_path = $_SERVER['SCRIPT_NAME']; // مثل: /Librarysystem/admin/customer/addcustomer.php
$path_parts = explode('/', trim($script_path, '/'));

// إزالة اسم الملف والمجلدات الفرعية للوصول لجذر المشروع
// نفترض أن المشروع في مجلد Librarysystem
if (count($path_parts) >= 3 && $path_parts[0] === 'Librarysystem') {
    $base_path = '/Librarysystem';
} else {
    // إذا كان في الجذر مباشرة
    $base_path = '';
}

// تعريف الثوابت العامة
define('BASE_URL', $base_path);
define('ASSETS_URL', BASE_URL . '/assets');
define('PUBLIC_URL', BASE_URL . '/public');

// مسارات محددة للموارد
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMG_URL', ASSETS_URL . '/img');

/**
 * دالة مساعدة لإنشاء مسارات الموارد
 */
function asset($path) {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * دالة مساعدة لإنشاء مسارات عامة
 */
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}
?>
