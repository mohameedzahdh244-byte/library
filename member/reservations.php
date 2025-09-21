<?php
// تضمين نظام المسارات

require_once '../config/init.php';

// التحقق من صلاحيات المشترك
checkMemberPermission();

$mem_no = $_SESSION['user_no'];
// تحديد ما إذا كان الطلب AJAX أو iframe لعرض المحتوى فقط بدون الهيكل الكامل
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] === '1') || (isset($_REQUEST['iframe']) && $_REQUEST['iframe'] === '1');

// الحصول على معلومات المشترك
$stmt = $conn->prepare("SELECT * FROM customer WHERE mem_no = ?");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

// تنظيف تلقائي: أي حجوزات أصبحت منتهية الصلاحية
try {
    $cleanup = $conn->prepare("UPDATE book_reservation SET status = 'expired' WHERE status = 'available' AND expiry_date IS NOT NULL AND expiry_date < NOW() AND mem_no = ?");
    $cleanup->bind_param('s', $mem_no);
    $cleanup->execute();
} catch (Exception $e) {
    // تجاهل أي خطأ في التنظيف حتى لا يؤثر على عرض الصفحة
}

// معالجة إلغاء الحجز
if (isset($_POST['cancel_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    // التحقق من ملكية الحجز
    $check_stmt = $conn->prepare("SELECT * FROM book_reservation WHERE reservation_id = ? AND mem_no = ?");
    $check_stmt->bind_param("is", $reservation_id, $mem_no);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // إلغاء الحجز
        $update_stmt = $conn->prepare("UPDATE book_reservation SET status = 'cancelled' WHERE reservation_id = ?");
        $update_stmt->bind_param("i", $reservation_id);
        
        if ($update_stmt->execute()) {
            // تسجيل العملية
            $auditLogger->logUpdate($mem_no, 'book_reservation', $reservation_id, null, [
                'status' => 'cancelled'
            ]);
            
            $success_message = 'تم إلغاء الحجز بنجاح!';
        } else {
            $error_message = 'حدث خطأ أثناء إلغاء الحجز.';
        }
    } else {
        $error_message = 'لا يمكن إلغاء هذا الحجز.';
    }
}

// إعداد الفلاتر والترقيم
$status_filter = $_GET['status'] ?? '';
$limit = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $limit;

// بناء استعلام الحجوزات مع الفلتر
$whereClause = "WHERE br.mem_no = ?";
$params = [$mem_no];
$paramTypes = "s";

if (!empty($status_filter)) {
    $whereClause .= " AND br.status = ?";
    $params[] = $status_filter;
    $paramTypes .= "s";
}

// حساب العدد الإجمالي
$countSql = "SELECT COUNT(*) AS cnt FROM book_reservation br $whereClause";
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    $countStmt->bind_param($paramTypes, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalReservations = ($countResult && ($row = $countResult->fetch_assoc())) ? intval($row['cnt']) : 0;
    $countStmt->close();
}

// الحجوزات مع الفلتر والترقيم
$stmt = $conn->prepare("
    SELECT br.*, b.book_title, b.serialnum_book, b.classification_num,
           (
               SELECT GROUP_CONCAT(a.Aname ORDER BY a.Aname SEPARATOR ', ')
               FROM book_authors ba
               JOIN authors a ON ba.ANO = a.ANO
               WHERE ba.serialnum_book = b.serialnum_book
           ) AS author,
           CASE 
               WHEN br.status = 'pending' THEN 'في الانتظار'
               WHEN br.status = 'available' THEN 'متوفر للاستلام'
               WHEN br.status = 'borrowed' THEN 'تم الاستلام'
               WHEN br.status = 'expired' THEN 'منتهي الصلاحية'
               WHEN br.status = 'cancelled' THEN 'ملغي'
           END as status_arabic
    FROM book_reservation br
    LEFT JOIN book b ON br.serialnum_book = b.serialnum_book
    $whereClause
    ORDER BY br.reservation_date DESC
    LIMIT ? OFFSET ?
");

$params[] = $limit;
$params[] = $offset;
$paramTypes .= "ii";
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$reservations = $result->fetch_all(MYSQLI_ASSOC);

// إزالة استعلام الحجوزات الملغية المنفصل - سيتم عرضها مع باقي الحجوزات

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
$borrowingSettings = $settings->getBorrowingSettings();

// حساب إحصائيات الحجوزات (بدون فلتر)
$statsStmt = $conn->prepare("SELECT status, COUNT(*) as count FROM book_reservation WHERE mem_no = ? GROUP BY status");
$statsStmt->bind_param("s", $mem_no);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = ['pending' => 0, 'available' => 0, 'borrowed' => 0, 'expired' => 0, 'cancelled' => 0, 'total' => 0];
while ($row = $statsResult->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}
$statsStmt->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isAjax ? 'حجوزاتي' : 'حجوزاتي - ' . $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    <!-- Page specific CSS -->
    <link href="../assets/css/member-reservations.css" rel="stylesheet">
    <link href="../assets/css/member-common.css" rel="stylesheet">
    
    <!-- Font Awesome (Local) -->
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <?php if (!$isAjax): ?>
    <!-- Bootstrap Icons (Local) -->
    <link href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <?php endif; ?>
    <!-- Google Fonts Cairo (Local) -->
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <?php if (!$isAjax): ?>
    <!-- Member Dashboard CSS -->
    <link href="../assets/css/member-dashboard.css" rel="stylesheet">
    <!-- Analytics -->
    <script src="../assets/js/analytics.js"></script>
    <?php endif; ?>
    
    <!-- Reservations Page Isolated CSS -->
    <style>
        /* Reset and Base Styles for Reservations Page Only */
        <?php if ($isAjax): ?>
        body { 
            padding: 1rem !important; 
            background: #f8f9fc !important;
            font-family: 'Cairo', sans-serif !important;
            margin: 0 !important;
        }
        .reservations-container {
            max-width: 100% !important;
            padding: 0 !important;
        }
        <?php else: ?>
        .reservations-container {
            max-width: 1200px !important;
            margin: 0 auto !important;
            padding: 2rem 0 !important;
        }
        <?php endif; ?>
        
        /* Page Header - Isolated */
        .reservations-page .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            border-radius: 15px !important;
            padding: 2rem !important;
            margin-bottom: 2rem !important;
            text-align: center !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
        }
        
        /* Statistics Cards - Completely Isolated */
        .reservations-page .stats-card {
            background: white !important;
            border-radius: 15px !important;
            padding: 1.5rem !important;
            text-align: center !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
            transition: all 0.3s ease !important;
            border: none !important;
            margin-bottom: 1rem !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
        }
        
        .reservations-page .stats-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 12px 35px rgba(0,0,0,0.15) !important;
        }
        
        .reservations-page .stats-icon {
            width: 56px !important;
            height: 56px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.25rem !important;
            color: #fff !important;
            margin: 0 auto 1rem !important;
            transition: transform 0.2s ease !important;
        }
        
        .reservations-page .stats-card:hover .stats-icon {
            transform: scale(1.05) !important;
        }
        
        .reservations-page .stats-number {
            font-size: 2rem !important;
            font-weight: 700 !important;
            margin-bottom: 0.5rem !important;
            color: #2c3e50 !important;
            display: block !important;
            text-align: center !important;
        }
        
        .reservations-page .stats-label {
            color: #6c757d !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
            display: block !important;
            text-align: center !important;
        }
        
        /* Filter Tabs - Completely Isolated */
        .reservations-page .filter-tabs {
            background: white !important;
            border-radius: 15px !important;
            padding: 1rem !important;
            margin-bottom: 2rem !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
            width: 100% !important;
            overflow: hidden !important;
        }
        
        .reservations-page .filter-tab {
            display: inline-block !important;
            padding: 0.75rem 1.5rem !important;
            margin: 0.25rem !important;
            border-radius: 25px !important;
            text-decoration: none !important;
            color: #6c757d !important;
            background: #f8f9fc !important;
            border: 2px solid transparent !important;
            transition: all 0.3s ease !important;
            font-weight: 500 !important;
            font-size: 0.9rem !important;
            white-space: nowrap !important;
        }
        
        .reservations-page .filter-tab:hover {
            color: #667eea !important;
            background: rgba(102, 126, 234, 0.1) !important;
            border-color: rgba(102, 126, 234, 0.2) !important;
            transform: translateY(-1px) !important;
            text-decoration: none !important;
        }
        
        .reservations-page .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            border-color: transparent !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
        }
        
        /* Reservation Cards - Isolated */
        .reservations-page .reservation-card {
            background: white !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
            border: none !important;
            margin-bottom: 1.5rem !important;
            transition: all 0.3s ease !important;
            overflow: hidden !important;
        }
        
        .reservations-page .reservation-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 12px 35px rgba(0,0,0,0.15) !important;
        }
        
        .reservations-page .reservation-header {
            padding: 1.5rem !important;
            border-bottom: 1px solid #f1f3f4 !important;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%) !important;
        }
        
        .reservations-page .reservation-body {
            padding: 1.5rem !important;
        }
        
        .reservations-page .book-title {
            font-size: 1.2rem !important;
            font-weight: 700 !important;
            color: #2c3e50 !important;
            margin-bottom: 0.5rem !important;
        }
        
        .reservations-page .book-author {
            color: #6c757d !important;
            margin-bottom: 0.5rem !important;
            font-weight: 500 !important;
        }
        
        /* Status Badges - Isolated */
        .reservations-page .status-badge {
            padding: 0.5rem 1rem !important;
            border-radius: 20px !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.3px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
            display: inline-block !important;
        }
        
        .reservations-page .status-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: white !important;
        }
        
        .reservations-page .status-available {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: white !important;
        }
        
        .reservations-page .status-borrowed {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
            color: white !important;
        }
        
        .reservations-page .status-expired {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            color: white !important;
        }
        
        .reservations-page .status-cancelled {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%) !important;
            color: white !important;
        }
        
        /* Buttons - Isolated */
        .reservations-page .btn-cancel {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            border: none !important;
            border-radius: 20px !important;
            padding: 0.5rem 1.5rem !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            text-transform: uppercase !important;
            letter-spacing: 0.3px !important;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3) !important;
            color: white !important;
        }
        
        .reservations-page .btn-cancel:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4) !important;
            color: white !important;
        }
        
        /* No Reservations - Isolated */
        .reservations-page .no-reservations {
            text-align: center !important;
            padding: 4rem 2rem !important;
            color: #6c757d !important;
            background: white !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
        }
        
        .reservations-page .no-reservations i {
            font-size: 4rem !important;
            margin-bottom: 1.5rem !important;
            opacity: 0.5 !important;
            color: #667eea !important;
        }
        
        .reservations-page .no-reservations h5 {
            color: #2c3e50 !important;
            margin-bottom: 1rem !important;
        }
        
        /* Responsive Design - Mobile First */
        @media (max-width: 575.98px) {
            /* Container adjustments for mobile */
            .reservations-page .container-fluid {
                padding: 0.5rem !important;
            }
            
            /* Statistics Cards - All in one row on mobile */
            .reservations-page .row.g-3 {
                margin: 0 !important;
                gap: 0.25rem !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                padding-bottom: 0.5rem !important;
            }
            
            .reservations-page .col-6.col-md-2 {
                padding: 0 !important;
                flex: 0 0 calc(20% - 0.2rem) !important;
                max-width: calc(20% - 0.2rem) !important;
                min-width: 70px !important;
            }
            
            .reservations-page .stats-card {
                padding: 0.5rem 0.25rem !important;
                margin-bottom: 0 !important;
                min-height: 100px !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                border-radius: 12px !important;
            }
            
            .reservations-page .stats-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 0.9rem !important;
                margin: 0 auto 0.375rem !important;
            }
            
            .reservations-page .stats-number {
                font-size: 1rem !important;
                margin-bottom: 0.125rem !important;
                line-height: 1.1 !important;
                font-weight: 800 !important;
            }
            
            .reservations-page .stats-label {
                font-size: 0.65rem !important;
                line-height: 1.1 !important;
                margin: 0 !important;
                word-break: break-word !important;
                hyphens: auto !important;
            }
            
            /* Filter tabs for mobile */
            .reservations-page .filter-tabs {
                padding: 0.5rem !important;
                margin-bottom: 1.5rem !important;
            }
            
            .reservations-page .filter-tab {
                display: block !important;
                margin: 0.25rem 0 !important;
                text-align: center !important;
                padding: 0.6rem 1rem !important;
                font-size: 0.8rem !important;
                width: 100% !important;
            }
            
            /* Reservation cards for mobile */
            .reservations-page .reservation-card {
                margin-bottom: 1rem !important;
            }
            
            .reservations-page .reservation-header,
            .reservations-page .reservation-body {
                padding: 1rem !important;
            }
            
            .reservations-page .book-title {
                font-size: 1rem !important;
            }
            
            .reservations-page .book-author {
                font-size: 0.85rem !important;
            }
            
            /* No reservations for mobile */
            .reservations-page .no-reservations {
                padding: 2rem 1rem !important;
            }
            
            .reservations-page .no-reservations i {
                font-size: 3rem !important;
            }
        }
        
        /* Extra small phones */
        @media (max-width: 375px) {
            .reservations-page .container-fluid {
                padding: 0.25rem !important;
            }
            
            .reservations-page .row.g-3 {
                gap: 0.2rem !important;
            }
            
            .reservations-page .col-6.col-md-2 {
                flex: 0 0 calc(20% - 0.16rem) !important;
                max-width: calc(20% - 0.16rem) !important;
                min-width: 60px !important;
            }
            
            .reservations-page .stats-card {
                padding: 0.375rem 0.125rem !important;
                min-height: 85px !important;
                border-radius: 10px !important;
            }
            
            .reservations-page .stats-icon {
                width: 28px !important;
                height: 28px !important;
                font-size: 0.8rem !important;
                margin: 0 auto 0.25rem !important;
            }
            
            .reservations-page .stats-number {
                font-size: 0.9rem !important;
                margin-bottom: 0.125rem !important;
                font-weight: 800 !important;
            }
            
            .reservations-page .stats-label {
                font-size: 0.6rem !important;
                line-height: 1 !important;
                word-break: break-word !important;
            }
            
            .reservations-page .filter-tab {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.75rem !important;
            }
        }
        
        /* Very small phones (iPhone SE, etc.) */
        @media (max-width: 320px) {
            .reservations-page .row.g-3 {
                gap: 0.15rem !important;
            }
            
            .reservations-page .col-6.col-md-2 {
                flex: 0 0 calc(20% - 0.12rem) !important;
                max-width: calc(20% - 0.12rem) !important;
                min-width: 55px !important;
            }
            
            .reservations-page .stats-card {
                min-height: 75px !important;
                padding: 0.25rem 0.125rem !important;
                border-radius: 8px !important;
            }
            
            .reservations-page .stats-icon {
                width: 24px !important;
                height: 24px !important;
                font-size: 0.7rem !important;
                margin: 0 auto 0.2rem !important;
            }
            
            .reservations-page .stats-number {
                font-size: 0.8rem !important;
                margin-bottom: 0.1rem !important;
                font-weight: 800 !important;
            }
            
            .reservations-page .stats-label {
                font-size: 0.55rem !important;
                line-height: 0.9 !important;
                word-break: break-word !important;
            }
        }
        
        @media (min-width: 576px) and (max-width: 767.98px) {
            .reservations-page .filter-tab {
                padding: 0.65rem 1.2rem !important;
                font-size: 0.85rem !important;
            }
            
            .reservations-page .stats-number {
                font-size: 1.75rem !important;
            }
        }
        
        @media (min-width: 768px) and (max-width: 991.98px) {
            .reservations-page .filter-tab {
                padding: 0.7rem 1.3rem !important;
                font-size: 0.9rem !important;
            }
        }
        
        @media (min-width: 992px) {
            .reservations-page .filter-tab {
                padding: 0.75rem 1.5rem !important;
                font-size: 0.9rem !important;
            }
        }
    </style>
</head>
<body data-analytics-page="member">
    <div class="container-fluid px-4 reservations-page">
        <div class="reservations-container">
            <!-- Page Header (إظهار فقط في العرض الكامل وليس AJAX) -->
            <?php if (!$isAjax): ?>
                <div class="page-header">
                    <h2 class="mb-2">
                        <i class="fas fa-calendar-check me-2"></i>
                        حجوزاتي
                    </h2>
                    <p class="mb-0">إدارة حجوزات الكتب الخاصة بك</p>
                </div>
            <?php endif; ?>

            <!-- Toasts Container (top-right) -->
            <div id="toastContainer" style="position: fixed; top: 5rem; right: 1rem; z-index: 1080;">
                <?php if (isset($success_message)): ?>
                <div class="toast align-items-center text-bg-success border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" data-bs-autohide="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="إغلاق"></button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                <div class="toast align-items-center text-bg-danger border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" data-bs-autohide="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="إغلاق"></button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Statistics -->
            <div class="row row-cols-5 g-3 mb-4">
                <div class="col">
                    <div class="stats-card">
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total']; ?></div>
                        <div class="stats-label">إجمالي الحجوزات</div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="stats-card">
                        <div class="stats-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['pending']; ?></div>
                        <div class="stats-label">في الانتظار</div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="stats-card">
                        <div class="stats-icon bg-success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['available']; ?></div>
                        <div class="stats-label">متوفر للاستلام</div>
                    </div>
                </div>
                
                <div class="col">
                    <div class="stats-card">
                        <div class="stats-icon bg-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['expired']; ?></div>
                        <div class="stats-label">منتهي الصلاحية</div>
                    </div>
                </div>

                <div class="col">
                    <div class="stats-card">
                        <div class="stats-icon bg-secondary">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['cancelled']; ?></div>
                        <div class="stats-label">ملغي</div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs mb-4">
                <div class="d-flex flex-wrap justify-content-center">
                    <a href="<?php echo $isAjax ? '?ajax=1&iframe=1&status=' : '?status='; ?>" class="filter-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">
                        <i class="fas fa-list me-1"></i>
                        جميع الحجوزات
                    </a>
                    <a href="<?php echo $isAjax ? '?ajax=1&iframe=1&status=pending' : '?status=pending'; ?>" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock me-1"></i>
                        في الانتظار
                    </a>
                    <a href="<?php echo $isAjax ? '?ajax=1&iframe=1&status=available' : '?status=available'; ?>" class="filter-tab <?php echo $status_filter === 'available' ? 'active' : ''; ?>">
                        <i class="fas fa-check me-1"></i>
                        متوفر للاستلام
                    </a>
                    <a href="<?php echo $isAjax ? '?ajax=1&iframe=1&status=borrowed' : '?status=borrowed'; ?>" class="filter-tab <?php echo $status_filter === 'borrowed' ? 'active' : ''; ?>">
                        <i class="fas fa-check-double me-1"></i>
                        تم الاستلام
                    </a>
                    <a href="<?php echo $isAjax ? '?ajax=1&iframe=1&status=expired' : '?status=expired'; ?>" class="filter-tab <?php echo $status_filter === 'expired' ? 'active' : ''; ?>">
                        <i class="fas fa-times me-1"></i>
                        منتهي الصلاحية
                    </a>
                    <a href="<?php echo $isAjax ? '?ajax=1&iframe=1&status=cancelled' : '?status=cancelled'; ?>" class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                        <i class="fas fa-ban me-1"></i>
                        ملغي
                    </a>
                </div>
            </div>

            <!-- Active Reservations -->
            <?php if (empty($reservations)): ?>
                <div class="no-reservations">
                    <i class="fas fa-calendar-times"></i>
                    <h5>لا توجد حجوزات نشطة</h5>
                    <p>يمكنك حجز الكتب من صفحة البحث</p>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        البحث عن الكتب
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        <?php 
                        switch($status_filter) {
                            case 'pending': echo 'الحجوزات في الانتظار'; break;
                            case 'available': echo 'الحجوزات المتوفرة للاستلام'; break;
                            case 'borrowed': echo 'الحجوزات المستلمة'; break;
                            case 'expired': echo 'الحجوزات منتهية الصلاحية'; break;
                            case 'cancelled': echo 'الحجوزات الملغية'; break;
                            default: echo 'جميع الحجوزات'; break;
                        }
                        ?>
                    </h4>
                    <small class="text-muted">
                        عرض <?php echo count($reservations); ?> من <?php echo $totalReservations; ?> حجز
                    </small>
                </div>
                
                <?php foreach ($reservations as $reservation): ?>
                    <div class="reservation-card">
                        <div class="reservation-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="book-title"><?php echo htmlspecialchars($reservation['book_title']); ?></h5>
                                    <div class="book-author">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($reservation['author'] ?? 'غير محدد'); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                    <?php echo $reservation['status_arabic']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="reservation-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <strong>الرقم التسلسلي:</strong> <?php echo htmlspecialchars($reservation['serialnum_book']); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>رقم التصنيف:</strong> <?php echo htmlspecialchars($reservation['classification_num'] ?? 'غير محدد'); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>تاريخ الحجز:</strong> <?php echo date('Y/m/d H:i', strtotime($reservation['reservation_date'])); ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <strong>تاريخ انتهاء الصلاحية:</strong> <?php echo date('Y/m/d H:i', strtotime($reservation['expiry_date'])); ?>
                                    </div>
                                    
                                    <?php if ($reservation['status'] === 'available'): ?>
                                        <div class="expiry-warning">
                                            <i class="fas fa-clock me-1"></i>
                                            <span class="time-remaining">
                                                متوفر للاستلام لمدة <?php echo $borrowingSettings['reservation_expiry_hours']; ?> ساعة
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($reservation['status'] === 'pending'): ?>
                                        <div class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            سيتم إشعارك عند توفر الكتاب
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">
                                    رقم الحجز: #<?php echo $reservation['reservation_id']; ?>
                                </small>
                                
                                <?php if (in_array($reservation['status'], ['pending', 'available'])): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من إلغاء هذا الحجز؟')">
                                        <input type="hidden" name="cancel_reservation" value="1">
                                        <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                        <button type="submit" class="btn btn-cancel text-white">
                                            <i class="fas fa-times me-1"></i>
                                            إلغاء الحجز
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php
                    // ترقيم الصفحات
                    $totalPages = ($totalReservations > 0) ? (int)ceil($totalReservations / $limit) : 1;
                    if ($totalPages > 1):
                        $qs = [];
                        if (!empty($status_filter)) {
                            $qs['status'] = $status_filter;
                        }
                        $makeUrl = function($page) use ($qs) {
                            $qs['page'] = $page;
                            return '?' . http_build_query($qs);
                        };
                        $start = $offset + 1;
                        $end = min($offset + $limit, $totalReservations);
                ?>
                <div class="d-flex flex-column flex-md-row align-items-center justify-content-between mt-4 gap-2">
                    <div class="small text-muted order-2 order-md-1">
                        <i class="fas fa-info-circle me-1"></i>
                        عرض <?php echo $start; ?>–<?php echo $end; ?> من <?php echo $totalReservations; ?> حجز
                    </div>
                    <nav aria-label="ترقيم صفحات الحجوزات" class="order-1 order-md-2 w-100 w-md-auto">
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <li class="page-item <?php echo ($currentPage <= 1 ? 'disabled' : ''); ?>">
                                <a class="page-link d-inline-flex align-items-center" href="<?php echo $currentPage>1 ? $makeUrl($currentPage-1) : '#'; ?>">
                                    <i class="fas fa-chevron-right ms-1"></i>
                                    <span>السابق</span>
                                </a>
                            </li>
                            <?php
                            $from = max(1, $currentPage - 2);
                            $to = min($totalPages, $currentPage + 2);
                            
                            if ($from > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $makeUrl(1); ?>">1</a>
                                </li>
                                <?php if ($from > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif;
                            
                            for ($p=$from; $p<=$to; $p++): ?>
                                <li class="page-item <?php echo ($p==$currentPage?'active':''); ?>">
                                    <a class="page-link" href="<?php echo $makeUrl($p); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor;
                            
                            if ($to < $totalPages): ?>
                                <?php if ($to < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $makeUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item <?php echo ($currentPage >= $totalPages ? 'disabled' : ''); ?>">
                                <a class="page-link d-inline-flex align-items-center" href="<?php echo $currentPage<$totalPages ? $makeUrl($currentPage+1) : '#'; ?>">
                                    <span>التالي</span>
                                    <i class="fas fa-chevron-left me-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

    <?php if (!$isAjax): ?>
    <!-- Bootstrap JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <!-- Page specific JS -->
    <script src="../assets/js/member-reservations.js"></script>
    <?php endif; ?>
</body>
</html>
