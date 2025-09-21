<?php
/**
 * ملف تهيئة النظام
 * System Initialization
 */

// تفعيل إعدادات كوكي الجلسة قبل البدء
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// تضمين ملفات الإعدادات
require_once 'DB.php';
require_once 'session.php';
require_once 'settings.php';
require_once 'audit.php';

// تهيئة الإعدادات والسجلات
global $settings, $auditLogger;

// تعيين المنطقة الزمنية
date_default_timezone_set('Asia/Hebron');

// تعيين ترميز UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// فرض HTTPS باستثناء بيئات التطوير (localhost و *.test و *.local)
$host = $_SERVER['HTTP_HOST'] ?? '';
$httpsOn = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$devHosts = ['localhost', '127.0.0.1', '::1'];
$isDevTld = (bool)preg_match('/\.(test|local)(:\\d+)?$/i', $host);
$isLocalhost = in_array($host, $devHosts, true) || $isDevTld;
if (!$isLocalhost && !$httpsOn) {
    $redirectUrl = 'https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

// تعيين headers الأمان
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
// HSTS فقط عند HTTPS
if ($httpsOn && !$isLocalhost) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
// CSP خفيفة لمنع تضمين الإطارات الخارجية
header("Content-Security-Policy: frame-ancestors 'self'");

// تسجيل تسجيل الدخول إذا كان المستخدم مسجل
if (isset($_SESSION['user_no'])) {
    $timeout = 30 * 60; // 30 دقيقة خمول
    $absoluteTimeout = 8 * 60 * 60; // 8 ساعات كمهلة مطلقة

    if (!isset($_SESSION['session_start_time'])) {
        $_SESSION['session_start_time'] = time();
    }
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    $isIdleExpired = (time() - ($_SESSION['last_activity'] ?? 0)) > $timeout;
    $isAbsoluteExpired = (time() - ($_SESSION['session_start_time'] ?? time())) > $absoluteTimeout;

    if ($isIdleExpired || $isAbsoluteExpired) {
        if (isset($auditLogger) && isset($_SESSION['user_no'])) {
            $auditLogger->logLogout($_SESSION['user_no']);
        }
        // إنهاء الجلسة
        session_unset();
        session_destroy();

        // منع الرجوع باستخدام الكاش
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

        // إن كان الطلب AJAX أرجع 401
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] === '1');

        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'reason' => 'session_expired']);
        } else {
            header('Location: ../auth/loginform.php');
        }
        exit;
    }

    // تحديث آخر نشاط
    $_SESSION['last_activity'] = time();

    // منع الكاش للصفحات المحمية فقط
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
}
?>
