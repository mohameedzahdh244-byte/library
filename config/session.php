<?php
/**
 * ملف إدارة الجلسات
 * Session management file
 */

// ملاحظة: بدء الجلسة يتم حصرياً من خلال config/init.php لتجنب التكرار

/**
 * فحص صلاحيات المشترك
 * Check member permissions
 */
function checkMemberPermission() {
    if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type'])) {
        header("Location: ../auth/loginform.php");
        exit;
    }
    
    // التأكد من أن المستخدم مشترك وليس موظف
    if ($_SESSION['user_type'] !== 'member') {
        header("Location: ../admin/dashboard.php");
        exit;
    }
}

/**
 * فحص صلاحيات الموظف
 * Check staff permissions
 */
function checkStaffPermission() {
    if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type'])) {
        header("Location: ../auth/loginform.php");
        exit;
    }
    
    // التأكد من أن المستخدم موظف أو مدير
    if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
        header("Location: ../member/dashboard.php");
        exit;
    }
}

/**
 * فحص صلاحيات المدير
 * Check admin permissions
 */
function checkAdminPermission() {
    if (!isset($_SESSION['user_no']) || !isset($_SESSION['user_type'])) {
        header("Location: ../auth/loginform.php");
        exit;
    }
    
    // التأكد من أن المستخدم مدير
    if ($_SESSION['user_type'] !== 'admin') {
        header("Location: ../admin/dashboard.php");
        exit;
    }
}

?>