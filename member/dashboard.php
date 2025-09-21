<?php
// تضمين نظام المسارات

require_once '../config/init.php';

// التحقق من صلاحيات المشترك
checkMemberPermission();

$mem_no = $_SESSION['user_no'];

// الحصول على معلومات المشترك
$stmt = $conn->prepare("
    SELECT c.*, ms.atatus AS subscription_status, ms.end_date AS subscription_end
    FROM customer c
    LEFT JOIN (
        SELECT t1.mem_no, t1.end_date, t2.atatus
        FROM (
            SELECT mem_no, MAX(end_date) AS end_date
            FROM member_subscription
            GROUP BY mem_no
        ) t1
        LEFT JOIN member_subscription t2
          ON t2.mem_no = t1.mem_no AND t2.end_date = t1.end_date
    ) ms ON c.mem_no = ms.mem_no
    WHERE c.mem_no = ?
");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

// الحصول على الكتب المستعارة حالياً
$stmt = $conn->prepare("
    SELECT bt.*, b.book_title
    FROM borrow_transaction bt
    JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
    JOIN book b ON bt.serialnum_book = b.serialnum_book
    WHERE ct.mem_no = ? 
      AND NOT EXISTS (
          SELECT 1 
          FROM return_book rb 
          WHERE rb.serialnum_book = bt.serialnum_book 
          AND rb.boro_no = bt.boro_no
      )
    ORDER BY bt.boro_exp_ret_date ASC
");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);

// إعادة استخدام نفس الاستعلام لعدد الكتب المستعارة
$stmt = $conn->prepare("
    SELECT bt.*, b.book_title
    FROM borrow_transaction bt
    JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
    JOIN book b ON bt.serialnum_book = b.serialnum_book
    WHERE ct.mem_no = ? 
      AND NOT EXISTS (
          SELECT 1 
          FROM return_book rb 
          WHERE rb.serialnum_book = bt.serialnum_book 
          AND rb.boro_no = bt.boro_no
      )
    ORDER BY bt.boro_exp_ret_date ASC
");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$borrowed_books = $result->fetch_all(MYSQLI_ASSOC);

// الحصول على الحجوزات النشطة
$stmt = $conn->prepare("
    SELECT br.*, b.book_title
    FROM book_reservation br
    LEFT JOIN book b ON br.serialnum_book = b.serialnum_book
    WHERE br.mem_no = ? AND br.status IN ('pending', 'available')
    ORDER BY br.reservation_date DESC
");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$reservations = $result->fetch_all(MYSQLI_ASSOC);

// سجل الإعارات المنتهية (آخر 5)
$stmt = $conn->prepare("
    SELECT bt.*, b.book_title, rb.return_date
    FROM borrow_transaction bt
    JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
    JOIN book b ON bt.serialnum_book = b.serialnum_book
    JOIN return_book rb ON rb.boro_no = bt.boro_no AND rb.serialnum_book = bt.serialnum_book
    WHERE ct.mem_no = ?
    ORDER BY rb.return_date DESC
    LIMIT 5
");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$borrow_history = $result->fetch_all(MYSQLI_ASSOC);


// حساب إحصائيات سريعة
$stats = [
    'borrowed_books' => count($borrowed_books),
    'reservations' => count($reservations),
    'total_borrowed' => count($borrow_history),
];

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
$borrowingSettings = $settings->getBorrowingSettings();
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
    
    <!-- Font Awesome -->
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <!-- Member Dashboard CSS -->
    <link href="../assets/css/member-dashboard.css" rel="stylesheet">
    <!-- Analytics -->
    <script src="../assets/js/analytics.js"></script>
</head>
<body data-analytics-page="member">
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar show" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand d-flex align-items-center">
                <img src="../public/logo.png" alt="شعار المكتبة" class="me-2" style="margin-left: 10px; height: 28px; width: auto;">
                <span class="brand-text"><?php echo $libraryInfo['name']; ?></span>
            </a>
            <!-- Close button for mobile -->
            <button class="sidebar-close d-md-none" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">لوحة العضو</div>
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="#" class="nav-link" onclick="openPanel('search.php', 'البحث عن الكتب')">
                    <i class="fas fa-search nav-icon"></i>
                    <span>البحث عن الكتب</span>
                </a>
                <a href="#" class="nav-link" onclick="openPanel('activities.php?iframe=1&ajax=1', 'الأنشطة')">
                    <i class="fas fa-people-group nav-icon"></i>
                    <span>الأنشطة</span>
                </a>
                <a href="#" class="nav-link" onclick="openPanel('reservations.php', 'حجوزاتي')">
                    <i class="fas fa-calendar-check nav-icon"></i>
                    <span>حجوزاتي</span>
                </a>
                <a href="#" class="nav-link" onclick="openPanel('history.php', 'تاريخ الإعارات')">
                    <i class="fas fa-history nav-icon"></i>
                    <span>تاريخ الإعارات</span>
                </a>
                <a href="#" class="nav-link" onclick="openPanel('suggest.php', 'اقترح كتاب')">
                    <i class="fas fa-lightbulb nav-icon"></i>
                    <span>اقترح كتاب</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">الحساب</div>
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Top Bar -->
    <div class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="topbar-title">
            <h4 class="mb-0" id="pageTitle">لوحة التحكم</h4>
        </div>
        
        <div class="topbar-user">
            <span class="me-2">مرحباً، <?php echo htmlspecialchars($member['mem_name']); ?></span>
            <i class="fas fa-user-circle fa-lg"></i>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper" id="contentWrapper">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">مرحباً، <?php echo htmlspecialchars($member['mem_name']); ?>!</h2>
                        <p class="mb-3">مرحباً بك في لوحة تحكم المشترك. يمكنك من هنا إدارة إعاراتك وحجوزاتك.</p>
                        
                        <?php
                            $today = date('Y-m-d');
                            $subEnd = $member['subscription_end'] ?? null;
                            $subStat = $member['subscription_status'] ?? '';
                            $isActive = ($subEnd && $subEnd >= $today && $subStat !== 'موقوف');
                        ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill <?php echo $isActive ? 'bg-success' : 'bg-danger'; ?> px-3 py-2 fs-6">
                                <?php echo $isActive ? 'اشتراك ساري' : 'اشتراك منتهي'; ?>
                            </span>
                            <?php if (!empty($subEnd)): ?>
                                <small class="text-white-50">
                                    ينتهي في: <?php echo date('Y/m/d', strtotime($subEnd)); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <?php
                          $photo = isset($member['personal_photo']) ? trim((string)$member['personal_photo']) : '';
                          if ($photo !== ''): ?>
                            <img src="<?php echo htmlspecialchars($photo); ?>" alt="صورة العضو" class="rounded-circle border" style="width:128px;height:128px;object-fit:cover;"/>
                        <?php else: ?>
                            <i class="fas fa-user-circle fa-6x text-white-50"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4 justify-content-center">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['borrowed_books']; ?></div>
                        <div class="stats-label">كتب مستعارة</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-warning">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['reservations']; ?></div>
                        <div class="stats-label">حجوزات نشطة</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-success">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_borrowed']; ?></div>
                        <div class="stats-label"> كتب مرجعة</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- الكتب المستعارة -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="fas fa-book me-2"></i>
                                الكتب المستعارة حالياً
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($borrowed_books)): ?>
                                <div class="no-items">
                                    <img src="../public/logo.png" alt="شعار المكتبة" style="height: 48px; width: auto;">
                                    <h6>لا توجد كتب مستعارة</h6>
                                    <p>يمكنك زيارة صفحة البحث لحجز الكتب أو استعارة الكتب عند توفرها</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($borrowed_books as $book): ?>
                                    <div class="book-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($book['book_title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($book['author'] ?? 'غير محدد'); ?>
                                                </small>
                                            </div>
                                            <?php
                                            $due_date = new DateTime($book['boro_exp_ret_date']);
                                            $today = new DateTime();
                                            $diff = $today->diff($due_date);
                                            $days_left = $diff->invert ? -$diff->days : $diff->days;
                                            
                                            if ($days_left < 0) {
                                                $due_class = 'due-overdue';
                                                $due_text = 'متأخر ' . abs($days_left) . ' يوم';
                                            } elseif ($days_left <= 3) {
                                                $due_class = 'due-soon';
                                                $due_text = 'ينتهي خلال ' . $days_left . ' يوم';
                                            } else {
                                                $due_class = 'due-normal';
                                                $due_text = 'ينتهي في ' . $days_left . ' يوم';
                                            }
                                            ?>
                                            <span class="due-date <?php echo $due_class; ?>">
                                                <?php echo $due_text; ?>
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                تاريخ الإعارة: <?php echo date('Y/m/d', strtotime($book['boro_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- الحجوزات النشطة -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-check me-2"></i>
                                الحجوزات النشطة
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($reservations)): ?>
                                <div class="no-items">
                                    <i class="fas fa-calendar-times"></i>
                                    <h6>لا توجد حجوزات</h6>
                                    <p>يمكنك حجز الكتب غير المتوفرة من صفحة البحث</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($reservations as $reservation): ?>
                                    <div class="book-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($reservation['book_title']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($reservation['author'] ?? 'غير محدد'); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $reservation['status'] === 'available' ? 'success' : 'warning'; ?>">
                                                <?php echo $reservation['status'] === 'available' ? 'متوفر' : 'في الانتظار'; ?>
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                تاريخ الحجز: <?php echo date('Y/m/d', strtotime($reservation['reservation_date'])); ?>
                                            </small>
                                        </div>
                                        <?php if ($reservation['status'] === 'available'): ?>
                                            <div class="mt-2">
                                                <small class="text-warning">
                                                    <i class="fas fa-clock me-1"></i>
                                                    متوفر لمدة <?php echo $borrowingSettings['reservation_expiry_hours']; ?> ساعة
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

<?php if (!empty($borrow_history)): ?>
                <div class="content-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            آخر الإعارات
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($borrow_history as $book): ?>
                            <div class="book-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($book['book_title']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($book['author'] ?? 'غير محدد'); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-success">تم الإرجاع</span>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        تاريخ الإرجاع: <?php echo date('Y/m/d', strtotime($book['return_date'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center p-3">
                            <a href="history.php" class="btn btn-outline-primary">
                                <i class="fas fa-history me-2"></i>
                                عرض جميع الإعارات
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Offcanvas Panel for Page Content -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="panelOffcanvas" aria-labelledby="panelOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="panelTitle">لوحة</h5>
            <button type="button" class="btn-close ms-2 m-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <iframe id="panelFrame" src="" frameborder="0" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/member-dashboard.js"></script>
    <script>
        // تم إلغاء إعادة التحميل التلقائي نهائياً للحفاظ على تجربة المستخدم
    </script>
</body>
</html>