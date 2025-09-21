<?php
// إعدادات المكتبة المبسطة - حد موحد لجميع المستخدمين
// Simplified Library Configuration - Unified Limits for All Users

// الحدود الافتراضية (يمكن للموظف تجاوزها مع تحذير)
// Default limits (staff can override with warnings)
define('DEFAULT_MAX_BOOKS', 5);         // الحد الافتراضي للكتب
define('ABSOLUTE_MAX_BOOKS', 10);       // الحد الأقصى المطلق
define('DEFAULT_LOAN_PERIOD_DAYS', 14); // فترة الإعارة الافتراضية

// إعدادات التجديد المرنة
// Flexible renewal settings
define('DEFAULT_MAX_RENEWALS', 2);      // عدد التجديدات الافتراضي
define('ABSOLUTE_MAX_RENEWALS', 5);     // الحد الأقصى المطلق للتجديدات
define('RENEWAL_PERIOD_DAYS', 14);      // فترة التجديد بالأيام

// إعدادات الغرامات (مرنة - يحددها الموظف يدوياً)
// Flexible fine settings (manually determined by staff)
// لا توجد غرامة تلقائية - الموظف يدخل المبلغ الذي يراه مناسباً
// No automatic fine calculation - staff enters the amount they see fit

// حد الغرامات للتعليق (مرن)
// Fine threshold for suspension (flexible)
define('SUGGESTED_SUSPENSION_THRESHOLD', 20.00);

// ==================================================
// الدوال المساعدة - Helper Functions
// ==================================================

/**
 * حساب تاريخ الاستحقاق
 * Calculate due date for borrowed book
 */
function calculateDueDate($borrow_date, $loan_period_days = DEFAULT_LOAN_PERIOD_DAYS) {
    $due_date = new DateTime($borrow_date);
    $due_date->add(new DateInterval('P' . $loan_period_days . 'D'));
    return $due_date->format('Y-m-d');
}

/**
 * حساب عدد الأيام المتأخرة فقط (بدون غرامة تلقائية)
 * Calculate overdue days only (no automatic fine calculation)
 */
function calculateOverdueDays($due_date, $return_date = null) {
    if ($return_date === null) {
        $return_date = date('Y-m-d');
    }
    
    $due = new DateTime($due_date);
    $returned = new DateTime($return_date);
    
    if ($returned <= $due) {
        return 0; // لا توجد أيام تأخير
    }
    
    return $returned->diff($due)->days;
}

/**
 * دالة مساعدة لعرض معلومات التأخير للموظف
 * Helper function to display overdue information for staff
 */
function getOverdueInfo($due_date, $return_date = null) {
    $overdue_days = calculateOverdueDays($due_date, $return_date);
    
    return [
        'overdue_days' => $overdue_days,
        'is_overdue' => ($overdue_days > 0),
        'message' => $overdue_days > 0 ? "متأخر {$overdue_days} يوم" : "غير متأخر"
    ];
}

/**
 * فحص حالة المستخدم للاستعارة (مع التحذيرات)
 * Check user borrowing status with warnings
 */
function checkUserBorrowingStatus($user_id) {
    global $conn;
    
    // عد الكتب المستعارة حالياً
    $stmt = $conn->prepare("
        SELECT COUNT(*) as current_books 
        FROM borrow_transaction 
        WHERE mem_no = ? AND return_date IS NULL
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $current_books = $row['current_books'] ?? 0;
    
    // فحص الغرامات المعلقة
    $pending_fines = 0;
    
    // تحديد حالة الاستعارة
    $status = [
        'can_borrow' => true,
        'current_books' => $current_books,
        'pending_fines' => $pending_fines,
        'warnings' => [],
        'blocked' => false
    ];
    
    // تحذيرات عند تجاوز الحدود (بدون منع)
    if ($current_books >= ABSOLUTE_MAX_BOOKS) {
        // تحذير شديد عند الوصول للحد الأقصى
        $status['warnings'][] = [
            'type' => 'danger',
            'message' => "تحذير شديد: وصل للحد الأقصى ({$current_books}/{ABSOLUTE_MAX_BOOKS}) - يرجى الحذر الشديد"
        ];
    } elseif ($current_books >= DEFAULT_MAX_BOOKS) {
        // تحذير عند تجاوز الحد الافتراضي
        $status['warnings'][] = [
            'type' => 'warning',
            'message' => "تجاوز الحد الافتراضي ({$current_books}/{DEFAULT_MAX_BOOKS}) - يمكن المتابعة بحذر"
        ];
    }
    
    // تحذيرات الغرامات (بدون منع)
    if ($pending_fines >= SUGGESTED_SUSPENSION_THRESHOLD) {
        $status['warnings'][] = [
            'type' => 'danger',
            'message' => "تحذير شديد: غرامات عالية ({$pending_fines} شيكل) - ينصح بتسديد الغرامات"
        ];
    } elseif ($pending_fines > 0) {
        $status['warnings'][] = [
            'type' => 'info',
            'message' => "غرامات معلقة: {$pending_fines} شيكل"
        ];
    }
    
    return $status;
}

/**
 * إنشاء إعارة جديدة مع التواريخ التلقائية
 * Create new borrowing transaction with automatic dates
 */
function createBorrowTransaction($user_id, $book_id, $staff_override = false) {
    global $conn;
    
    // فحص حالة المستخدم (للحصول على التحذيرات فقط)
    $user_status = checkUserBorrowingStatus($user_id);
    
    // لا يوجد منع - فقط تحذيرات
    
    // فحص توفر الكتاب
    if (!isBookAvailable($book_id)) {
        return [
            'success' => false,
            'message' => 'الكتاب غير متاح للاستعارة',
            'warnings' => []
        ];
    }
    
    // التواريخ التلقائية
    $borrow_date = date('Y-m-d');  // اليوم
    $due_date = calculateDueDate($borrow_date);  // بعد 14 يوم
    
    // إنشاء رقم المعاملة
    $transaction_id = 'BT' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
    
    // إدراج الإعارة
    $stmt = $conn->prepare("
        INSERT INTO borrow_transaction 
        (transaction_id, mem_no, book_id, borrow_date, due_date, max_renewals, book_condition) 
        VALUES (?, ?, ?, ?, ?, ?, 'good')
    ");
    
    $stmt->bind_param("sssssi", 
        $transaction_id, 
        $user_id, 
        $book_id, 
        $borrow_date, 
        $due_date, 
        DEFAULT_MAX_RENEWALS
    );
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'تمت الإعارة بنجاح',
            'transaction_id' => $transaction_id,
            'borrow_date' => $borrow_date,
            'due_date' => $due_date,
            'warnings' => $user_status['warnings']
        ];
    } else {
        return [
            'success' => false,
            'message' => 'خطأ في إنشاء الإعارة: ' . $conn->error,
            'warnings' => []
        ];
    }
}

/**
 * التحقق من توفر الكتاب
 * Check if book is available for borrowing
 */
function isBookAvailable($book_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as borrowed_count 
        FROM borrow_transaction 
        WHERE serialnum_book = ? AND return_date IS NULL
    ");
    $stmt->bind_param("s", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['borrowed_count'] == 0);
}

/**
 * الحصول على معلومات المستخدم
 * Get user information including current borrowings and fines
 */
function getUserBorrowingInfo($user_id) {
    global $conn;
    
    // الكتب المستعارة حالياً
    $stmt1 = $conn->prepare("
        SELECT COUNT(*) as current_books 
        FROM borrow_transaction 
        WHERE mem_no = ? AND return_date IS NULL
    ");
    $stmt1->bind_param("s", $user_id);
    $stmt1->execute();
    $current_books = $stmt1->get_result()->fetch_assoc()['current_books'] ?? 0;
    
    // الغرامات المعلقة
    $pending_fines = 0;
    
    return [
        'current_books' => $current_books,
        'max_books' => DEFAULT_MAX_BOOKS,
        'can_borrow_more' => ($current_books < DEFAULT_MAX_BOOKS),
        'pending_fines' => $pending_fines,
        'is_suspended' => ($pending_fines >= SUGGESTED_SUSPENSION_THRESHOLD)
    ];
}

/**
 * الحصول على معلومات الإعارة المفصلة مع التحذيرات
 * Get detailed borrowing information with warnings
 */
function getBorrowingInfoWithWarnings($user_id) {
    $user_info = getUserBorrowingInfo($user_id);
    $user_status = checkUserBorrowingStatus($user_id);
    
    return [
        'user_info' => $user_info,
        'status' => $user_status,
        'limits' => [
            'default_max_books' => DEFAULT_MAX_BOOKS,
            'absolute_max_books' => ABSOLUTE_MAX_BOOKS,
            'default_max_renewals' => DEFAULT_MAX_RENEWALS,
            'absolute_max_renewals' => ABSOLUTE_MAX_RENEWALS,
            'loan_period_days' => DEFAULT_LOAN_PERIOD_DAYS,
            'suspension_threshold' => SUGGESTED_SUSPENSION_THRESHOLD
        ]
    ];
}

/**
 * دالة مساعدة لعرض التحذيرات في واجهة المستخدم
 * Helper function to display warnings in UI
 */
function formatWarningsForUI($warnings) {
    if (empty($warnings)) {
        return '';
    }
    
    $html = '';
    foreach ($warnings as $warning) {
        $alert_class = '';
        switch ($warning['type']) {
            case 'danger':
                $alert_class = 'alert-danger';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                break;
            case 'info':
                $alert_class = 'alert-info';
                break;
            default:
                $alert_class = 'alert-secondary';
        }
        
        $html .= '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
        $html .= '<i class="fas fa-exclamation-triangle me-2"></i>';
        $html .= htmlspecialchars($warning['message']);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        $html .= '</div>';
    }
    
    return $html;
}

?>
