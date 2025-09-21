<?php
// تضمين نظام المسارات

require_once '../config/init.php';


// التحقق من صلاحيات الموظف
checkStaffPermission();

// الحصول على إحصائيات سريعة
$stats = getDashboardStats($conn);

// الحصول على آخر العمليات
$recentActivities = getRecentActivities($conn);
// استبعاد حجوزات الأعضاء من "آخر العمليات"
if (is_array($recentActivities)) {
    $recentActivities = array_values(array_filter($recentActivities, function($a) {
        $table = $a['table_name'] ?? '';
        $act   = trim((string)($a['action_type'] ?? ''));
        // استبعاد حجوزات الأعضاء
        if ($table === 'book_reservation' || $act === 'حجز كتاب') { return false; }
        // استبعاد عمليات تسجيل الدخول والخروج
        $lowAct = mb_strtolower($act, 'UTF-8');
        if ($lowAct === 'login_success' || $lowAct === 'login_failed' || $lowAct === 'logout' || mb_strpos($lowAct, 'login') !== false) {
            return false;
        }
        // استبعاد حذف كتاب وحذف مشترك (السماح بعرض تعديل كتاب)
        $low = mb_strtolower($act, 'UTF-8');
        if ($table === 'book') {
            // لا نستبعد التعديلات على الكتب كي تظهر كـ "تعديل كتاب"
            if (in_array($low, ['delete','remove','حذف'], true)) { return false; }
        }
        if ($table === 'customer') {
            if (in_array($low, ['delete','remove','حذف'], true)) { return false; }
        }
        return true;
    }));
    // تطبيع تسمية العملية إلى العربية بشكل موحّد (حتى لو كانت CREATE/UPDATE...)
    foreach ($recentActivities as &$a) {
        $raw = trim((string)($a['action_type'] ?? ''));
        $tbl = $a['table_name'] ?? '';
        $low = mb_strtolower($raw, 'UTF-8');
        // حجز محذوف أصلاً بالأعلى
        if ($tbl === 'customer_transaction') {
            $a['action_type'] = 'إعارة كتاب';
        } elseif ($tbl === 'return_transaction') {
            $a['action_type'] = 'إرجاع كتاب';
        } elseif ($tbl === 'borrow_transaction' && (str_contains($low, 'renew') || str_contains($low, 'تجديد'))) {
            // تجديد إعارة (قد يُسجَّل كـ renew_book على نفس جدول borrow_transaction)
            $a['action_type'] = 'تجديد إعارة';
        } elseif ($tbl === 'borrow_transaction' && (str_contains($low, 'borrow'))) {
            // إعارة كتاب (قد تظهر ك borrow_book)
            $a['action_type'] = 'إعارة كتاب';
        } elseif ($tbl === 'member_subscription' && (str_contains($low, 'renew') || str_contains($low, 'تجديد'))) {
            // تجديد اشتراك المشترك
            $a['action_type'] = 'تجديد الاشتراك';
        } elseif ($tbl === 'report' && ($low === 'export_report' || str_contains($low, 'export') || str_contains($low, 'تصدير'))) {
            // تصدير تقرير
            $a['action_type'] = 'تصدير تقرير';
        } elseif ($tbl === 'report' && ($low === 'print_report' || str_contains($low, 'print') || str_contains($low, 'طباعة'))) {
            // طباعة تقرير
            $a['action_type'] = 'طباعة تقرير';
        } elseif (str_contains($low, 'export_suggestions')) {
            // تطبيع سجلات قديمة: تصدير اقتراحات -> تصدير تقرير
            $a['action_type'] = 'تصدير تقرير';
            $a['table_name']  = 'report';
        } elseif (str_contains($low, 'print_suggestions')) {
            // تطبيع سجلات قديمة: طباعة اقتراحات -> طباعة تقرير
            $a['action_type'] = 'طباعة تقرير';
            $a['table_name']  = 'report';
        } elseif (in_array($low, ['create','insert','add','إنشاء','اضافة','إضافة'], true)) {
            if ($tbl === 'book') $a['action_type'] = 'إضافة كتاب';
            elseif ($tbl === 'customer') $a['action_type'] = 'إضافة مشترك';
            elseif ($tbl === 'users' || $tbl === 'user') $a['action_type'] = 'إضافة موظف';
            else $a['action_type'] = 'عملية إضافة';
        } elseif (in_array($low, ['update','edit','تعديل'], true)) {
            // تخصيص تعديل موظف، وبخلاف ذلك عملية تعديل عامة
            if ($tbl === 'users' || $tbl === 'user') {
                $a['action_type'] = 'تعديل موظف';
            } elseif ($tbl === 'book') {
                $a['action_type'] = 'تعديل كتاب';
            } else {
                $a['action_type'] = 'عملية تعديل';
            }
        } elseif (in_array($low, ['delete','remove','حذف'], true)) {
            // تخصيص حذف موظف
            if ($tbl === 'users' || $tbl === 'user') $a['action_type'] = 'حذف موظف';
            else $a['action_type'] = 'عملية حذف';
        } elseif ($low === 'borrow' || str_contains($low, 'borrow_book')) {
            $a['action_type'] = 'إعارة كتاب';
        } elseif ($low === 'return' || str_contains($low, 'return_book')) {
            $a['action_type'] = 'إرجاع كتاب';
        } elseif ($low === 'renew' || str_contains($low, 'renew_book') || str_contains($low, 'renewal')) {
            $a['action_type'] = 'تجديد إعارة';
        } elseif ($raw === '') {
            // fallback أخير عندما لا توجد قيمة
            $a['action_type'] = 'عملية نظام';
        }
    }
    unset($a);
    // إخفاء العناصر ذات التسمية العامة "عملية تعديل" من آخر العمليات
    $recentActivities = array_values(array_filter($recentActivities, function($a){
        $label = trim((string)($a['action_type'] ?? ''));
        // استثناء "عملية تعديل" و"عملية إضافة" (مع تنويع الهمزات)
        $labelNorm = str_replace(['أ','إ','آ'], 'ا', $label);
        if ($labelNorm === 'عملية تعديل') return false;
        if ($labelNorm === 'عملية اضافة') return false;
        return true;
    }));
}

// الحصول على الكتب المتأخرة
$overdueBooks = getOverdueBooks($conn);

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
?>

<?php
// دوال خاصة بلوحة التحكم لتلوين/تحديد أيقونات العمليات بدون تعارض أسماء
function dashActivityColor(string $label): string {
    $l = trim($label);
    $low = mb_strtolower($l, 'UTF-8');
    $isAdd   = (str_contains($low, 'إضافة') || str_contains($low, 'أضافة') || str_contains($low, 'اضافة'));
    $isEdit  = str_contains($low, 'تعديل');
    $isDel   = str_contains($low, 'حذف');
    $isBorrow= (str_contains($low, 'إعارة') || str_contains($low, 'أعارة') || str_contains($low, 'اعارة'));
    $isReturn= str_contains($low, 'إرجاع');
    $isRenew = (str_contains($low, 'تجديد'));
    $hasBook = str_contains($low, 'كتاب');
    $hasCust = (str_contains($low, 'مشترك') || str_contains($low, 'مستخدم'));

    if ($isAdd && ($hasBook || $hasCust)) return 'primary'; // إضافة كتاب/مشترك = أزرق
    if ($isBorrow)          return 'success';    // إعارة = أخضر
    if ($isReturn)          return 'warning';    // إرجاع = أصفر
    if ($isRenew)           return 'info';       // تجديد = أزرق فاتح
    if ($isEdit)            return 'info';       // تعديل = أزرق فاتح
    if ($isDel)             return 'danger';     // حذف = أحمر
    return 'secondary';
}

function dashActivityIcon(string $label): string {
    $l = trim($label);
    $low = mb_strtolower($l, 'UTF-8');
    $isAdd   = (str_contains($low, 'إضافة') || str_contains($low, 'أضافة') || str_contains($low, 'اضافة'));
    $isEdit  = str_contains($low, 'تعديل');
    $isDel   = str_contains($low, 'حذف');
    $isBorrow= (str_contains($low, 'إعارة') || str_contains($low, 'أعارة') || str_contains($low, 'اعارة'));
    $isReturn= str_contains($low, 'إرجاع');
    $isRenew = str_contains($low, 'تجديد');
    $hasBook = str_contains($low, 'كتاب');
    $hasCust = (str_contains($low, 'مشترك') || str_contains($low, 'مستخدم'));

    if ($isAdd && $hasBook) return 'book-medical';     // إضافة كتاب
    if ($isAdd && $hasCust) return 'user-plus';         // إضافة مشترك
    if ($isBorrow)          return 'arrow-up-right-from-square';
    if ($isReturn)          return 'arrow-rotate-left';
    if ($isRenew)           return 'arrows-rotate';
    if ($isEdit)            return 'pen-to-square';
    if ($isDel)             return 'trash';
    return 'tasks';
}

// تلوين/أيقونة اعتماداً على table_name أولاً ثم التسمية (موضع صحيح عام)
function dashColorForActivity(array $activity): string {
    $tbl = $activity["table_name"] ?? '';
    $label = (string)($activity['action_type'] ?? '');
    $labLow = mb_strtolower($label,'UTF-8');
    if ($tbl === 'customer_transaction') return 'success';   // إعارة = أخضر
    if ($tbl === 'return_transaction')   return 'warning';   // إرجاع = أصفر
    if ($tbl === 'borrow_transaction' && str_contains($labLow,'تجديد')) return 'info'; // تجديد إعارة
    if ($tbl === 'member_subscription' && (str_contains($labLow,'تجديد') || str_contains($labLow,'renew'))) return 'warning'; // تجديد اشتراك = أصفر
    if ($tbl === 'report') {
        // عمليات التقارير: طباعة/تصدير
        if (str_contains($labLow,'طباعة') || str_contains($labLow,'print')) return 'secondary';
        if (str_contains($labLow,'تصدير') || str_contains($labLow,'export')) return 'secondary';
        return 'secondary';
    }
    if ($tbl === 'book')                 return 'primary';   // كتاب = أزرق
    if ($tbl === 'customer')             return 'primary';   // مشترك = أزرق
    // تخصيص ألوان عمليات جدول المستخدمين
    if ($tbl === 'users' || $tbl === 'user') {
        if (str_contains($labLow,'تعديل')) return 'info';     // تعديل موظف
        if (str_contains($labLow,'حذف'))   return 'danger';   // حذف موظف
        return 'success'; // إضافة موظف أو أخرى
    }
    return dashActivityColor($label);
}

function dashIconForActivity(array $activity): string {
    $tbl = $activity['table_name'] ?? '';
    $label = (string)($activity['action_type'] ?? '');
    $labLow = mb_strtolower($label,'UTF-8');
    if ($tbl === 'customer_transaction') return 'arrow-up-right-from-square';
    if ($tbl === 'return_transaction')   return 'arrow-rotate-left';
    if ($tbl === 'borrow_transaction' && str_contains($labLow,'تجديد')) return 'arrows-rotate';
    if ($tbl === 'member_subscription' && (str_contains($labLow,'تجديد') || str_contains($labLow,'renew'))) return 'arrows-rotate';
    if ($tbl === 'report') {
        if (str_contains($labLow,'طباعة') || str_contains($labLow,'print')) return 'print';
        if (str_contains($labLow,'تصدير') || str_contains($labLow,'export')) return 'file-export';
        return 'file';
    }
    if ($tbl === 'book' && (str_contains($labLow, 'إضافة') || str_contains($labLow, 'أضافة') || str_contains($labLow, 'اضافة'))) return 'book-medical';
    if ($tbl === 'customer') return 'user-plus';
    // تخصيص أيقونات عمليات جدول المستخدمين
    if ($tbl === 'users' || $tbl === 'user') {
        if (str_contains($labLow,'تعديل')) return 'pen-to-square'; // تعديل موظف
        if (str_contains($labLow,'حذف'))   return 'trash';         // حذف موظف
        return 'user-plus'; // إضافة موظف
    }
    return dashActivityIcon($label);
}
?>

<?php
// بيانات نشاط الشهر من قاعدة البيانات (عدد الإعارات لكل أسبوع من الشهر الحالي)
$activityWeekLabels = ['الأسبوع 1','الأسبوع 2','الأسبوع 3','الأسبوع 4','الأسبوع 5'];
$activityWeekData = [0,0,0,0,0];
try {
    $sql = "SELECT CEIL(DAY(created_at)/7) AS wk, COUNT(*) AS cnt
            FROM audit_log
            WHERE table_name = 'customer_transaction'
              AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
              AND created_at <  DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
            GROUP BY wk
            ORDER BY wk";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $w = (int)$row['wk'];
            if ($w >= 1 && $w <= 5) {
                $activityWeekData[$w-1] = (int)$row['cnt'];
            }
        }
        $res->free();
    }
} catch (Throwable $e) {
    // تجاهل الخطأ وعرض 0 كقيمة افتراضية
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo $libraryInfo['name']; ?></title>
     <link rel="icon" type="image/x-icon" href="../public/logo.ico">
    <!-- Bootstrap 5 RTL -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">

    <!-- Font Awesome (Local) -->
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (Local) -->
    <link href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts Cairo (Local) -->
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    <!-- Chart.js (Local) -->
    <script src="../assets/js/chart.umd.min.js"></script>

</head>
<body>
    <!-- Sidebar Overlay للموبايل -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand d-flex align-items-center">
                <img src="../public/logo.png" alt="شعار المكتبة" class="me-2" style="margin-left: 10px; height: 28px; width: auto;">
                <span class="brand-text">مكتبة البلدية</span>
            </a>
            <!-- زر الإغلاق للموبايل -->
            <button class="sidebar-close d-lg-none" id="sidebarClose" aria-label="إغلاق القائمة">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-nav">
             <div class="nav-section">
                <div class="nav-section-title">الرئيسية</div>
                        <a href="dashboard.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt nav-icon"></i>
                            <span>لوحة التحكم</span>
                        </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">إدارة المحتوى</div>
                <div class="accordion-header nav-link">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#booksCollapse">
                        <i class="fas fa-book nav-icon"></i>
                        <span class="nav-text">إدارة الكتب</span>
                    </button>
                </div>
                <div id="booksCollapse" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <a href="#" class="nav-link border-0 ps-5" onclick="openAddBook()">
                            <i class="bi bi-plus-circle me-3"></i>إضافة كتاب
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openSearchBooks()">
                            <i class="bi bi-search me-3"></i>بحث عن كتاب
                        </a>
                    </div>
                </div>

                <div class="accordion-header nav-link">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#membersCollapse">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-text">إدارة المشتركين</span>
                    </button>
                </div>
                <div id="membersCollapse" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <a href="#" class="nav-link border-0 ps-5" onclick="openAddCustomer()">
                            <i class="bi bi-plus-circle me-3"></i>إضافة مشترك
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openSearchCustomer()">
                            <i class="bi bi-search me-3"></i>بحث عن مشترك
                        </a>
                    </div>
                </div>


                <a href="#" class="nav-link" onclick="openPanel('borrowing/borrow.php?embed=1','إدارة الإعارة - إضافة إعارة','bg-primary text-white'); return false;">
                    <i class="fas fa-hand-holding-usd nav-icon"></i>
                     <span>الإعارة</span>
                </a>
         
                <a href="#" class="nav-link" onclick="openPanel('borrowing/return.php?embed=1','إدارة الإرجاع','bg-warning text-dark'); return false;">
                    <i class="fas fa-undo nav-icon"></i>
                    <span>الإرجاع</span>
                </a>
                
                <a href="#" class="nav-link" onclick="openPanel('reservations/index.php?embed=1','الحجوزات','bg-info text-white'); return false;">
                    <i class="fas fa-calendar-check nav-icon"></i>
                    <span>الحجوزات</span>
                </a>
                <a href="#" class="nav-link" onclick="openPanel('suggestions/index.php?embed=1','اقتراحات الكتب','bg-secondary text-white'); return false;">
                    <i class="fas fa-lightbulb nav-icon"></i>
                    <span>اقتراحات الكتب</span>
                </a>
                
                <!-- إدارة الأنشطة -->
                <div class="accordion-header nav-link">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#activitiesCollapse">
                        <i class="fas fa-bullhorn nav-icon"></i>
                        <span class="nav-text">إدارة الأنشطة</span>
                    </button>
                </div>
                <div id="activitiesCollapse" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('activities/index.php?embed=1','نشر إعلان نشاط','bg-success text-white'); return false;">
                            <i class="bi bi-megaphone me-3"></i>نشر إعلان
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('activities/requests.php?embed=1','إدارة طلبات الانضمام','bg-secondary text-white'); return false;">
                            <i class="bi bi-people me-3"></i>إدارة طلبات الانضمام
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('activities/archive.php?embed=1','أرشفة الإعلانات','bg-dark text-white'); return false;">
                            <i class="bi bi-archive me-3"></i>أرشفة الإعلانات
                        </a>
                    </div>
                </div>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">التقارير</div>
                <div class="accordion-header nav-link">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#reportsCollapse">
                        <i class="fas fa-chart-bar nav-icon"></i>
                        <span class="nav-text">التقارير</span>
                    </button>
                </div>
                <div id="reportsCollapse" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('reports/overdue.php?embed=1','تقارير التأخير','bg-danger text-white'); return false;">
                            <i class="bi bi-exclamation-triangle me-3"></i>تقارير التأخير
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('reports/borrowing_activity.php?embed=1','نشاط الإعارة','bg-primary text-white'); return false;">
                            <i class="bi bi-book me-3"></i>نشاط الإعارة
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('reports/top_books.php?embed=1','الكتب الأكثر إعارة','bg-success text-white'); return false;">
                            <i class="bi bi-trophy me-3"></i>الكتب الأكثر إعارة
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('reports/member_activity.php?embed=1','المشتركون النشطون','bg-info text-white'); return false;">
                            <i class="bi bi-people me-3"></i>المشتركون النشطون
                        </a>
                        <a href="#" class="nav-link border-0 ps-5" onclick="openPanel('reports/analytics.php?embed=1','تحليلات الزيارات','bg-secondary text-white'); return false;">
                            <i class="bi bi-graph-up me-3"></i>تحليلات الزيارات
                        </a>
                    </div>
                </div>
            </div>
                <?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">النظام</div>
                        <a href="#" class="nav-link" onclick="openPanel('settings/index.php?embed=1','الإعدادات','bg-secondary text-white'); return false;">
                            <i class="fas fa-cog nav-icon"></i>
                            <span class="nav-text">الإعدادات</span>
                        </a>
                        
                        <a href="#" class="nav-link" onclick="openUsers(); return false;">
                            <i class="fas fa-user-shield nav-icon"></i>
                            <span class="nav-text">إدارة المستخدمين</span>
                        </a>
                    </div>  
                <?php endif; ?>  
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="row align-items-center">
                <div class="col d-flex align-items-center gap-2">
                    <!-- زر القائمة: يظهر على جميع المقاسات -->
                    <button class="btn btn-primary" id="sidebarToggleTop" aria-label="فتح القائمة">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="d-flex flex-column">
                        <h5 class="mb-0 fw-bold dashboard-welcome-user">
                            مرحباً، <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'المدير'); ?> 
                        </h5>
                        <small class="dashboard-welcome-msg text-muted small mt-1">نتمنى لك يوماً رائعاً في إدارة المكتبة</small>
                    </div>
                </div>
                <div class="col-auto">
    <div class="d-flex align-items-center flex-row-reverse gap-3">
        <button class="btn btn-danger btn-modern" onclick="window.location.href='../auth/logout.php'">
        تسجيل الخروج
            <i class="fas fa-sign-out-alt me-2"></i>
            
        </button>
        <div class="d-flex align-items-center">
            <i class="fas fa-bell text-muted"></i>
        </div>
        <div class="d-flex align-items-center" dir="ltr">
            <i class="fas fa-clock text-muted"></i>
            <span class="ms-1" id="currentTime"></span>
        </div>
        
    </div>
</div>
            </div>
        </div>

        <div class="container-fluid">
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card bg-primary text-white">
                        <div class="stats-icon text-white-50">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stats-info text-end">
                            <div class="stats-number"><?php echo number_format($stats['total_books']); ?></div>
                            <div class="stats-label">إجمالي الكتب</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card bg-success text-white">
                        <div class="stats-icon text-white-50">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-info text-end">
                            <div class="stats-number"><?php echo number_format($stats['total_members']); ?></div>
                            <div class="stats-label">المشتركين</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card bg-warning text-dark">
                        <div class="stats-icon text-dark opacity-75">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stats-info text-end">
                            <div class="stats-number"><?php echo number_format($stats['active_borrows']); ?></div>
                            <div class="stats-label">الإعارات النشطة</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stats-card bg-danger text-white">
                        <div class="stats-icon text-white-50">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stats-info text-end">
                            <div class="stats-number"><?php echo number_format($stats['overdue_books']); ?></div>
                            <div class="stats-label">الإعارات المتأخرة</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Activities -->
                <div class="col-lg-8 mb-4">
                    <div id="recentActivities" class="activity-card recent-activities">
                        <div class="card-header bg-transparent border-0">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <h5 class="mb-0 d-flex align-items-center gap-2">
                                    <i class="fas fa-history me-2"></i>
                                    آخر العمليات
                                </h5>
                                <?php
                                // جمع أنواع العمليات ديناميكيًا بدون تكرار عبر مفتاح عربي موحّد
                                $actionTypesMap = [];
                                if (!empty($recentActivities)) {
                                    foreach ($recentActivities as $ra) {
                                        $t = trim((string)($ra['action_type'] ?? ''));
                                        if ($t === '') continue;
                                        $k = mb_strtolower($t, 'UTF-8');
                                        // تطبيع عربي بسيط: توحيد همزات الألف + المسافات + المرادفات (مستخدم/عضو -> مشترك)
                                        $k = str_replace(['أ','إ','آ'], 'ا', $k);
                                        $k = preg_replace('/\s+/u', ' ', $k);
                                        $k = str_replace([' مستخدم',' عضو'], ' مشترك', $k);
                                        if (!isset($actionTypesMap[$k])) {
                                            $actionTypesMap[$k] = $t; // احتفظ بأول صياغة للعرض
                                        }
                                    }
                                }
                                ?>
                                <?php if (!empty($actionTypesMap)): ?>
                                <?php
                                        // دالة تطبيع المفاتيح العربية
                                        $normalizeKey = function($t) {
                                            $k = mb_strtolower(trim((string)$t), 'UTF-8');
                                            $k = str_replace(['أ','إ','آ'], 'ا', $k);
                                            $k = preg_replace('/\s+/u', ' ', $k);
                                            $k = str_replace([' مستخدم',' عضو'], ' مشترك', $k);
                                            return $k;
                                        };
                                        // المرشّحات المجمّعة: نعرض فقط ما هو موجود فعليًا ضمن actionTypesMap باستثناء التقارير (ثابت)
                                        $groups = [
                                            'الموظف' => ['إضافة موظف','تعديل موظف','حذف موظف'],
                                            'المشترك' => ['إضافة مشترك','تعديل مشترك','حذف مشترك','تجديد الاشتراك','إضافة عضو','تعديل عضو','حذف عضو'],
                                            'الكتب' => ['إضافة كتاب','اضافة كتاب','تعديل كتاب','حذف كتاب'],
                                            // نضيف تنويعات للفظ "تجديد إعارة" لضمان التطابق
                                            'الإعارة' => [
                                                'إعارة كتاب','إرجاع كتاب',
                                                'تجديد الاعارة','تجديد الإعارة','تجديد اعارة',
                                                'تجديد اعاره','تجديد إعاره',
                                                'تجديد الاستعارة','تجديد استعارة'
                                            ],
                                        ];
                                        // بناء مصفوفة المفاتيح المطبّعة لكل مجموعة
                                        $groupKeys = [];
                                        foreach ($groups as $gName => $labels) {
                                            $groupKeys[$gName] = array_map($normalizeKey, $labels);
                                        }
                                        // تجميع مفاتيح موجودة فعلًا + إزالة التكرار
                                        $presentGroupKeys = [];
                                        foreach ($groupKeys as $gName => $keys) {
                                            $present = [];
                                            foreach ($keys as $k) {
                                                if (isset($actionTypesMap[$k])) {
                                                    $present[] = $k;
                                                }
                                            }
                                            // إزالة التكرارات والحفاظ على الترتيب
                                            $present = array_values(array_unique($present));
                                            $presentGroupKeys[$gName] = $present;
                                        }
                                        // توسيع مجموعة الإعارة ديناميكيًا لالتقاط صيغ متنوعة من النصوص دون التقاط "تجديد الاشتراك"
                                        if (!isset($presentGroupKeys['الإعارة'])) { $presentGroupKeys['الإعارة'] = []; }
                                        $borrowTerms = ['اعاره','اعارة','إعارة','الاعارة','الإعارة','استعارة','الاستعارة','ارجاع','إرجاع'];
                                        foreach ($actionTypesMap as $k => $lbl) {
                                            foreach ($borrowTerms as $t) {
                                                if (mb_strpos($k, $t) !== false) {
                                                    if (!in_array($k, $presentGroupKeys['الإعارة'], true)) {
                                                        $presentGroupKeys['الإعارة'][] = $k;
                                                    }
                                                    break;
                                                }
                                            }
                                        }
                                        // نجهّز مجموعات منسدلة مع تسميات العناصر
                                        $presentDropdownGroups = [];
                                        foreach ($presentGroupKeys as $gName => $arr) {
                                            $shouldDropdown = ($gName === 'الإعارة') ? (count($arr) >= 1) : (count($arr) > 1);
                                            if ($shouldDropdown) {
                                                $presentDropdownGroups[$gName] = array_map(function($k) use ($actionTypesMap){
                                                    return ['key' => $k, 'label' => $actionTypesMap[$k]];
                                                }, $arr);
                                            }
                                        }
                                        // التقارير: نقل الفلاتر الموجودة فعليًا (طباعة تقرير/تصدير التقرير) إلى قائمة منسدلة واحدة
                                        $reportItems = [];
                                        foreach ($actionTypesMap as $k => $lbl) {
                                            // نعدّ كل مفتاح يحتوي كلمة "تقرير" ضمن التقارير
                                            if (mb_strpos($k, 'تقرير') !== false) {
                                                // ننقل فقط العناصر التي تتضمن طباعة أو تصدير
                                                if (mb_strpos($k, 'طباعة') !== false || mb_strpos($k, 'تصدير') !== false) {
                                                    $reportItems[] = ['key' => $k, 'label' => $lbl];
                                                }
                                            }
                                        }
                                        if (count($reportItems) > 0) {
                                            $presentDropdownGroups['التقارير'] = $reportItems;
                                        }
                                        // قائمة بكل المفاتيح المجمّعة لإخفائها من الأزرار الفردية (القوائم المنسدلة فقط)
                                        $allGroupedKeys = [];
                                        foreach ($presentDropdownGroups as $items) {
                                            foreach ($items as $it) { $allGroupedKeys[] = $it['key']; }
                                        }
                                    ?>
                                    <div class="d-flex align-items-center gap-2 small" id="activityFilters">
                                        <button type="button" class="btn btn-sm btn-light border active" data-filter="*">الكل</button>

                                        <?php foreach ($presentDropdownGroups as $groupTitle => $items): if (empty($items)) continue; ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-light border dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <?php echo htmlspecialchars($groupTitle); ?>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php foreach ($items as $it): ?>
                                                        <li><a class="dropdown-item" href="#" data-filter="<?php echo htmlspecialchars($it['key']); ?>"><?php echo htmlspecialchars($it['label']); ?></a></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php foreach ($actionTypesMap as $k => $t): ?>
                                            <?php if (in_array($k, $allGroupedKeys, true)) continue; // لا نكرر العناصر التي أصبحت ضمن مجموعات ?>
                                            <?php
                                                // إخفاء أي مفاتيح متعلقة بالإعارة إذا كانت مجموعة "الإعارة" موجودة كقائمة منسدلة
                                                if (isset($presentDropdownGroups['الإعارة'])) {
                                                    $kBorrow = ['اعاره','اعارة','الإعارة','الاعارة','إعارة','استعارة','الاستعارة','ارجاع','إرجاع'];
                                                    foreach ($kBorrow as $term) {
                                                        if (mb_strpos($k, $term) !== false) { continue 2; }
                                                    }
                                                }
                                            ?>
                                            <button type="button" class="btn btn-sm btn-light border" data-filter="<?php echo htmlspecialchars($k); ?>">
                                                <?php echo htmlspecialchars($t); ?>
                                            </button>
                                        <?php endforeach; ?>

                                        <button type="button" id="recentExpandToggle" class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="tooltip" data-bs-title="عرض/إخفاء باقي العمليات">
                                            عرض الكل
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentActivities)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">لا توجد عمليات حديثة</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <?php 
                                        $label = trim((string)($activity['action_type'] ?? ''));
                                        $key = mb_strtolower($label,'UTF-8');
                                        $key = str_replace(['أ','إ','آ'], 'ا', $key);
                                        $key = preg_replace('/\s+/u', ' ', $key);
                                        $key = str_replace([' مستخدم',' عضو'], ' مشترك', $key);
                                    ?>
                                    <div class="activity-item" data-action="<?php echo htmlspecialchars($label); ?>" data-key="<?php echo htmlspecialchars($key); ?>">
                                        <div class="d-flex align-items-center">
                                            <div class="activity-icon bg-<?php echo dashColorForActivity($activity); ?> me-3" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars($label); ?>">
                                                <i class="fas fa-<?php echo dashIconForActivity($activity); ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <span class="fw-bold"><?php echo htmlspecialchars($label); ?></span>
                                                    <span class="badge rounded-pill text-bg-<?php echo dashColorForActivity($activity); ?>">
                                                        عملية
                                                    </span>
                                                </div>
                                                 <div class="text-muted small mt-1 d-flex align-items-center gap-2 flex-wrap">
                                                    <span><i class="fas fa-user ms-1"></i><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                                    <span class="text-muted">•</span>
                                                    <span><i class="fas fa-clock ms-1"></i><?php echo formatDate($activity['created_at']); ?></span>
                                                 </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Overdue Books -->
                <div class="col-lg-4 mb-4">
                <div class="activity-card overdue-books">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="mb-0 d-flex align-items-center gap-2">
                                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                                الكتب المتأخرة
                                <?php $overdueCount = is_array($overdueBooks) ? count($overdueBooks) : 0; ?>
                                <span class="badge rounded-pill text-bg-warning text-dark">
                                    <?php echo (int)$overdueCount; ?>
                                </span>
                                <?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?>
                                <button id="overdueRemindAllBtn" type="button" class="btn btn-sm btn-outline-danger ms-auto"
                                        data-bs-toggle="tooltip" data-bs-title="إرسال تذكير SMS لكل المتأخرين"
                                        onclick="window.sendOverdueReminders && window.sendOverdueReminders();">
                                    <i class="fas fa-paper-plane"></i>
                                    تذكير للجميع
                                </button>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($overdueBooks)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <p class="text-muted">لا توجد كتب متأخرة</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($overdueBooks as $book): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <div class="activity-icon bg-danger me-3" data-bs-toggle="tooltip" data-bs-title="كتاب متأخر">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                                    <span class="fw-bold text-truncate" style="max-width: 70%">
                                                        <?php echo htmlspecialchars($book['book_title']); ?>
                                                    </span>
                                                    <span class="badge rounded-pill text-bg-warning">
                                                        +<?php echo (int)$book['days_overdue']; ?> يوم
                                                    </span>
                                                </div>
                                                <div class="text-muted small mt-1">
                                                    <?php echo htmlspecialchars($book['mem_name'] ?? 'غير معروف'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="activity-card borrow-stats">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                إحصائيات الإعارة
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="borrowChart" class="w-100" style="height:320px;"
                                data-active-borrows="<?php echo (int)$stats['active_borrows']; ?>"
                                data-completed-borrows="<?php echo (int)$stats['completed_borrows']; ?>"
                                data-overdue-books="<?php echo (int)$stats['overdue_books']; ?>"
                            ></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="activity-card borrow-stats">
                        <div class="card-header bg-transparent border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                نشاط الشهر
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="activityChart" class="w-100" style="height:320px;"
                                data-week-labels='<?= json_encode($activityWeekLabels, JSON_UNESCAPED_UNICODE) ?>'
                                data-week-data='<?= json_encode($activityWeekData, JSON_UNESCAPED_UNICODE) ?>'
                            ></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- لوحة جانبية عامة Offcanvas -->
    <!-- ملاحظة: لجعل الشريط الجانبي (يمين) ظاهر، نفتح اللوحة من الجهة المقابلة (اليسار) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="panelOffcanvas" data-bs-scroll="false" data-bs-backdrop="false" aria-labelledby="panelTitle" style="--bs-offcanvas-width: min(1000px, 92vw);">
        <div class="offcanvas-header bg-primary text-white justify-content-between px-2">
            <h5 class="offcanvas-title mb-0" id="panelTitle">لوحة</h5>
            <button type="button" class="btn-close btn-close-white m-0" data-bs-dismiss="offcanvas" aria-label="إغلاق"></button>
        </div>
        <div class="offcanvas-body p-0" style="overflow:hidden;">
            <iframe id="panelFrame" src="" class="w-100 border-0" style="height: calc(100vh - 56px);"></iframe>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="../assets/js/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
    // إدارة الشريط الجانبي المخصص للهاتف
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarToggle = document.getElementById('sidebarToggleTop');
        const sidebarClose = document.getElementById('sidebarClose');
        const mainContent = document.getElementById('mainContent');
        
        // فتح الشريط الجانبي
        function openSidebar() {
            sidebar.classList.add('show');
            if (window.innerWidth <= 991.98) {
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                mainContent.classList.add('shifted');
            }
        }
        
        // إغلاق الشريط الجانبي
        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            mainContent.classList.remove('shifted');
            document.body.style.overflow = '';
        }
        
        // تبديل حالة الشريط الجانبي
        function toggleSidebar() {
            if (window.innerWidth > 991.98) {
                // على الكمبيوتر: تبديل عادي
                if (sidebar.classList.contains('show')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            } else {
                // على الموبايل: تبديل عادي
                if (sidebar.classList.contains('show')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
        }
        
        // معالجة تغيير حجم الشاشة
        function handleResponsiveSidebar() {
            if (window.innerWidth > 991.98) {
                // شاشات الكمبيوتر: الشريط الجانبي مفتوح تلقائياً
                sidebar.classList.add('show');
                mainContent.classList.add('shifted');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            } else {
                // شاشات الموبايل: الشريط الجانبي مغلق افتراضياً
                sidebar.classList.remove('show');
                mainContent.classList.remove('shifted');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        
        // أحداث النقر
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // إغلاق عند الضغط على ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                closeSidebar();
            }
        });
        
        // إغلاق تلقائي عند النقر على الروابط الفعلية فقط (ليس القوائم المنسدلة)
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 991.98) {
                    // لا نغلق إذا كان العنصر يحتوي على accordion-button (قائمة منسدلة)
                    const isAccordion = link.querySelector('.accordion-button');
                    // لا نغلق إذا كان الرابط يحتوي على data-bs-toggle (قائمة منسدلة)
                    const hasToggle = link.hasAttribute('data-bs-toggle') || link.querySelector('[data-bs-toggle]');
                    
                    // نغلق فقط إذا كان رابط فعلي (يحتوي على onclick أو href حقيقي)
                    const hasOnClick = link.hasAttribute('onclick');
                    const hasRealHref = link.hasAttribute('href') && link.getAttribute('href') !== '#';
                    
                    if (!isAccordion && !hasToggle && (hasOnClick || hasRealHref)) {
                        setTimeout(closeSidebar, 300);
                    }
                }
            });
        });
        
        // إغلاق عند النقر على الروابط داخل القوائم المنسدلة
        const accordionLinks = sidebar.querySelectorAll('.accordion-body .nav-link');
        accordionLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 991.98) {
                    // هذه روابط فعلية داخل القوائم المنسدلة
                    const hasOnClick = link.hasAttribute('onclick');
                    const hasRealHref = link.hasAttribute('href') && link.getAttribute('href') !== '#';
                    
                    if (hasOnClick || hasRealHref) {
                        setTimeout(closeSidebar, 300);
                    }
                }
            });
        });
        
        // إدارة offcanvas للشاشة الكاملة على الموبايل
        const panelOffcanvas = document.getElementById('panelOffcanvas');
        if (panelOffcanvas) {
            panelOffcanvas.addEventListener('show.bs.offcanvas', function() {
                if (window.innerWidth <= 991.98) {
                    // إخفاء الشريط الجانبي والمحتوى الرئيسي على الموبايل
                    setTimeout(() => {
                        document.body.classList.add('offcanvas-open');
                        closeSidebar();
                    }, 50);
                }
            });
            
            panelOffcanvas.addEventListener('shown.bs.offcanvas', function() {
                if (window.innerWidth <= 991.98) {
                    // التأكد من إخفاء العناصر بعد اكتمال العرض
                    document.body.classList.add('offcanvas-open');
                }
            });
            
            panelOffcanvas.addEventListener('hide.bs.offcanvas', function() {
                // استعادة العناصر عند إغلاق الـ offcanvas
                document.body.classList.remove('offcanvas-open');
            });
        }
        
        // معالجة تغيير حجم الشاشة
        window.addEventListener('resize', handleResponsiveSidebar);
        
        // إعداد أولي - فتح الشريط الجانبي تلقائياً على الكمبيوتر
        handleResponsiveSidebar();
        
        // التأكد من فتح الشريط الجانبي على شاشات الكمبيوتر عند التحميل
        if (window.innerWidth > 991.98) {
            setTimeout(() => {
                sidebar.classList.add('show');
                mainContent.classList.add('shifted');
            }, 100);
        }
    });
    </script>
    
</body>
</html>


<?php
function getDashboardStats($conn) {
    $stats = [];
    
    // إجمالي الكتب
    $query = "SELECT COUNT(*) as count FROM book";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $stats['total_books'] = $row['count'];

    // إجمالي المشتركين
    $query = "SELECT COUNT(*) as count FROM customer";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $stats['total_members'] = $row['count'];

    // الكتب النشطة (نشطة ولم تتجاوز التاريخ المتوقع)
    $loans_active = $conn->query("
        SELECT COUNT(*) FROM borrow_transaction bb
        LEFT JOIN return_book rb ON bb.borrow_detail_id = rb.borrow_detail_id
        WHERE rb.return_date IS NULL
        AND CURDATE() <= bb.boro_exp_ret_date
    ");
    $stats['active_borrows'] = $loans_active->fetch_row()[0];

    // الكتب المتأخرة (لم يتم إرجاعها بعد وتجاوزت التاريخ المتوقع)
    $loans_late = $conn->query("
        SELECT COUNT(*) FROM borrow_transaction bb
        LEFT JOIN return_book rb ON bb.borrow_detail_id = rb.borrow_detail_id
        WHERE rb.return_date IS NULL
        AND CURDATE() > bb.boro_exp_ret_date
    ");
    $stats['overdue_books'] = $loans_late->fetch_row()[0];

    // الكتب المعادة (تم إرجاعها فعليًا)
    $loans_returned = $conn->query("
        SELECT COUNT(*) FROM borrow_transaction bb
        INNER JOIN return_book rb ON bb.borrow_detail_id = rb.borrow_detail_id
        WHERE rb.return_date IS NOT NULL
    ");
    $stats['completed_borrows'] = $loans_returned->fetch_row()[0];

    return $stats;
}


/**
 * الحصول على آخر العمليات
 */
function getRecentActivities($conn) {
    $stmt = $conn->prepare("
        SELECT al.*, u.user_name 
        FROM audit_log al 
        LEFT JOIN user u ON al.user_no = u.user_no 
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY al.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * الحصول على الكتب المتأخرة
 */
function getOverdueBooks($conn) {
    $stmt = $conn->prepare("
        SELECT bb.*, b.book_title, ct.mem_no, m.mem_name AS mem_name,
               DATEDIFF(CURDATE(), bb.boro_exp_ret_date) as days_overdue
        FROM borrow_transaction bb
        LEFT JOIN book b ON bb.serialnum_book = b.serialnum_book
        LEFT JOIN return_book rb ON bb.borrow_detail_id = rb.borrow_detail_id
        LEFT JOIN customer_transaction ct ON bb.boro_no = ct.boro_no
        LEFT JOIN customer m ON ct.mem_no = m.mem_no
        WHERE bb.boro_exp_ret_date < CURDATE()
          AND rb.borrow_detail_id IS NULL
        ORDER BY bb.boro_exp_ret_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


/**
 * تنسيق التاريخ
 */
function formatDate($date) {
    return date('Y/m/d H:i', strtotime($date));
}
?>