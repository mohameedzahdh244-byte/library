<?php
require_once '../../config/init.php';

// التحقق من صلاحيات الموظف
checkStaffPermission();

$success = '';
$error = '';

// معالجة إلغاء الحجز
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    $stmt = $conn->prepare("UPDATE book_reservation SET status = 'cancelled' WHERE reservation_id = ?");
    $stmt->bind_param('i', $reservation_id);
    if ($stmt->execute()) {
        $success = 'تم إلغاء الحجز بنجاح';
        
        // تسجيل العملية
        $auditLogger->logUpdate($_SESSION['user_no'], 'book_reservation', $reservation_id, 
            ['status' => 'pending'], ['status' => 'cancelled']);
    } else {
        $error = 'حدث خطأ أثناء إلغاء الحجز';
    }
}

// الحصول على الحجوزات
$status_filter = $_GET['status'] ?? '';
// إعداد الترقيم: 10 عناصر في الصفحة
$limit = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $limit;
$sql = "
    SELECT 
        br.*, 
        b.book_title,
        c.mem_name, COALESCE(mp.mem_phone,'') AS mem_phone,
        bauth.authors AS authors
    FROM book_reservation br
    LEFT JOIN book b ON br.serialnum_book = b.serialnum_book
    LEFT JOIN customer c ON br.mem_no = c.mem_no
    LEFT JOIN (
        SELECT mem_no, GROUP_CONCAT(DISTINCT mem_phone ORDER BY mem_phone SEPARATOR ', ') AS mem_phone
        FROM mem_phone
        GROUP BY mem_no
    ) mp ON mp.mem_no = c.mem_no
    LEFT JOIN (
        SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
        FROM book_authors ba
        JOIN authors au ON au.ANO = ba.ANO
        GROUP BY ba.serialnum_book
    ) bauth ON bauth.serialnum_book = b.serialnum_book
    WHERE 1=1
";

if (!empty($status_filter)) {
    $sql .= " AND br.status = ?";
    // سيتم ربط المعامل لاحقًا عبر bind_param
}

// احسب العدد الكلي للحجوزات (مع الفلتر إن وُجد)
$countSql = "SELECT COUNT(*) AS cnt FROM book_reservation br WHERE 1=1";
if (!empty($status_filter)) { $countSql .= " AND br.status = ?"; }
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if (!empty($status_filter)) { $countStmt->bind_param('s', $status_filter); }
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $rowCnt = $countRes ? $countRes->fetch_assoc() : ['cnt'=>0];
    $totalReservations = intval($rowCnt['cnt'] ?? 0);
    $countStmt->close();
} else {
    $totalReservations = 0;
}

$sql .= " ORDER BY br.reservation_date DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($status_filter)) {
        $types = 'sii';
        $stmt->bind_param($types, $status_filter, $limit, $offset);
    } else {
        $types = 'ii';
        $stmt->bind_param($types, $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $reservations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $reservations = [];
    $error = 'فشل تحضير استعلام الحجوزات';
}

// حسبة عدد الصفحات
$totalPages = ($totalReservations > 0) ? (int)ceil($totalReservations / $limit) : 1;

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
$borrowingSettings = $settings->getBorrowingSettings();

// إحصائيات الحجوزات
$stats = [
    'total' => 0,
    'pending' => 0,
    'available' => 0,
    'expired' => 0,
    'cancelled' => 0
];

$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM book_reservation GROUP BY status");
$stmt->execute();
$result = $stmt->get_result();
$status_counts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

foreach ($status_counts as $count) {
    $stats[$count['status']] = $count['count'];
    $stats['total'] += $count['count'];
}
// وضع التضمين داخل Offcanvas لإخفاء عناصر الملاحة
$embed = isset($_GET['embed']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الحجوزات - <?php echo $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="/assets/css/bootstrap.css">
    <!-- Font Awesome (Local) -->
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Global styles -->
    <link href="../../assets/css/style.css" rel="stylesheet">
    <!-- Google Fonts Cairo (Local) -->
    <link href="/assets/fonts/cairo/cairo.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/reservations.css">
</head>
<body class="<?php echo $embed ? 'embedded' : ''; ?>">
    <div class="<?php echo $embed ? 'container-fluid px-0' : 'container'; ?>">
        <div class="reservations-container">
            <!-- Page Header (exactly like reports header) -->
            <div class="page-header position-relative d-print-none">
                <h2 class="mb-2">
                    <i class="fas fa-calendar-check me-2"></i>
                    إدارة الحجوزات
                </h2>
                <p class="mb-0">إدارة ومتابعة حجوزات الكتب</p>
            </div>
            
            <!-- Toasts Container (top-right) -->
            <div id="toastContainer" style="position: fixed; top: 1rem; right: 1rem; z-index: 1080;">
                <?php if ($success): ?>
                <div class="toast align-items-center text-bg-success border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" data-bs-autohide="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="إغلاق"></button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="toast align-items-center text-bg-danger border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" data-bs-autohide="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="إغلاق"></button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Statistics -->
            <div class="row g-3 mb-4">
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card card-glass text-center fade-in-up" style="animation-delay:.02s">
                        <div class="stats-icon bg-primary mx-auto mt-3 shadow-sm">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="card-body pt-3 pb-4">
                            <div class="stats-number"><?php echo $stats['total']; ?></div>
                            <div class="stats-label">إجمالي الحجوزات</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card card-glass text-center fade-in-up" style="animation-delay:.04s">
                        <div class="stats-icon bg-warning mx-auto mt-3 shadow-sm">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-body pt-3 pb-4">
                            <div class="stats-number"><?php echo $stats['pending']; ?></div>
                            <div class="stats-label">في الانتظار</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card card-glass text-center fade-in-up" style="animation-delay:.06s">
                        <div class="stats-icon bg-success mx-auto mt-3 shadow-sm">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="card-body pt-3 pb-4">
                            <div class="stats-number"><?php echo $stats['available']; ?></div>
                            <div class="stats-label">متوفر</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card card-glass text-center fade-in-up" style="animation-delay:.08s">
                        <div class="stats-icon bg-danger mx-auto mt-3 shadow-sm">
                            <i class="fas fa-times"></i>
                        </div>
                        <div class="card-body pt-3 pb-4">
                            <div class="stats-number"><?php echo $stats['expired']; ?></div>
                            <div class="stats-label">منتهي الصلاحية</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card card-glass text-center fade-in-up" style="animation-delay:.1s">
                        <div class="stats-icon bg-secondary mx-auto mt-3 shadow-sm">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="card-body pt-3 pb-4">
                            <div class="stats-number"><?php echo $stats['cancelled']; ?></div>
                            <div class="stats-label">ملغي</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="card card-glass text-center fade-in-up" style="animation-delay:.12s">
                        <div class="stats-icon bg-info mx-auto mt-3 shadow-sm">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="card-body pt-3 pb-4">
                            <div class="stats-number"><?php echo $borrowingSettings['reservation_expiry_hours']; ?></div>
                            <div class="stats-label">ساعة صلاحية</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="d-flex flex-wrap justify-content-center">
                    <a href="?status=" class="filter-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">
                        جميع الحجوزات <i class="fas fa-list ms-1"></i>
                    </a>
                    <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        في الانتظار <i class="fas fa-clock ms-1"></i>
                    </a>
                    <a href="?status=available" class="filter-tab <?php echo $status_filter === 'available' ? 'active' : ''; ?>">
                        متوفر <i class="fas fa-check ms-1"></i>
                    </a>
                    <a href="?status=borrowed" class="filter-tab <?php echo $status_filter === 'borrowed' ? 'active' : ''; ?>">
                        تم الاستلام <i class="fas fa-check-double ms-1"></i>
                    </a>
                    <a href="?status=expired" class="filter-tab <?php echo $status_filter === 'expired' ? 'active' : ''; ?>">
                        منتهي الصلاحية <i class="fas fa-times ms-1"></i>
                    </a>
                    <a href="?status=cancelled" class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                        ملغي <i class="fas fa-ban ms-1"></i>
                    </a>
                </div>
            </div>
            
            <!-- Reservations List -->
            <?php if (empty($reservations)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">لا توجد حجوزات</h4>
                    <p class="text-muted">لم يتم العثور على حجوزات تطابق المعايير المحددة</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($reservations as $reservation): ?>
                        <div class="col-lg-6 col-xl-4 mb-3">
                            <div class="card card-glass reservation-card h-100 fade-in-up">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="card-title mb-0">
                                            <small class="text-muted me-2">عنوان الكتاب:</small>
                                            <?php echo htmlspecialchars($reservation['book_title']); ?>
                                        </h6>
                                        <?php
                                            // Bootstrap badge class per status
                                            $status = $reservation['status'];
                                            $badgeClass = 'bg-secondary';
                                            $label = $status;
                                            $icon = 'fa-info-circle';
                                            switch ($status) {
                                                case 'pending':
                                                    $badgeClass = 'bg-warning text-dark';
                                                    $label = 'في الانتظار';
                                                    $icon = 'fa-clock';
                                                    break;
                                                case 'available':
                                                    $badgeClass = 'bg-success';
                                                    $label = 'متوفر';
                                                    $icon = 'fa-check';
                                                    break;
                                                case 'borrowed':
                                                    $badgeClass = 'bg-success';
                                                    $label = 'تم الاستلام';
                                                    $icon = 'fa-check-circle';
                                                    break;
                                                case 'expired':
                                                    $badgeClass = 'bg-danger';
                                                    $label = 'منتهي الصلاحية';
                                                    $icon = 'fa-times';
                                                    break;
                                                case 'cancelled':
                                                    $badgeClass = 'bg-danger';
                                                    $label = 'ملغي';
                                                    $icon = 'fa-ban';
                                                    break;
                                            }
                                        ?>
                                        <span class="badge rounded-pill badge-glass fw-semibold <?php echo $badgeClass; ?>">
                                            <?php echo $label; ?> <i class="fas <?php echo $icon; ?> ms-1"></i>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($reservation['authors'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-feather me-1"></i>
                                            المؤلف: <?php echo htmlspecialchars($reservation['authors']); ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($reservation['mem_name']); ?>
                                        </small>
                                    </div>

                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($reservation['mem_phone']); ?>
                                        </small>
                                    </div>

                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            تاريخ الحجز: <?php echo date('Y/m/d H:i', strtotime($reservation['reservation_date'])); ?>
                                        </small>
                                    </div>

                                    <?php if ($reservation['expiry_date']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                ينتهي في: <?php echo date('Y/m/d H:i', strtotime($reservation['expiry_date'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-end gap-2">
                                        <?php if ($reservation['status'] === 'available'): ?>
                                            <a href="../borrowing/borrow.php?embed=1&mem_no=<?php echo urlencode($reservation['mem_no']); ?>&serial=<?php echo urlencode($reservation['serialnum_book']); ?>&title=<?php echo urlencode($reservation['book_title']); ?>" 
                                               class="btn btn-sm btn-glass btn-glass-success">
                                                إعارة
                                                <img src="../../public/logo.png" alt="شعار المكتبة" class="me-1" style="height: 16px; width: auto;">
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($reservation['status'] === 'pending' || $reservation['status'] === 'available'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                                <button type="submit" name="cancel_reservation" class="btn btn-sm btn-glass btn-glass-danger"
                                                        onclick="return confirm('هل أنت متأكد من إلغاء هذا الحجز؟')">
                                                    إلغاء الحجز
                                                    <i class="fas fa-ban me-1"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                <?php
                    // بناء الروابط مع الحفاظ على معلمات الاستعلام
                    $qs = [];
                    if (!empty($status_filter)) $qs['status'] = $status_filter;
                    if ($embed) $qs['embed'] = '1';
                    $makeUrl = function($page) use ($qs) {
                        $qs['page'] = $page;
                        return '?' . http_build_query($qs);
                    };
                    $start = $offset + 1;
                    $end = min($offset + $limit, $totalReservations);
                ?>
                <div class="d-flex flex-column flex-md-row align-items-center justify-content-between mt-3 gap-2">
                    <div class="small text-muted order-2 order-md-1">عرض <?php echo $start; ?>–<?php echo $end; ?> من <?php echo $totalReservations; ?></div>
                    <nav aria-label="ترقيم الصفحات" class="order-1 order-md-2 w-100 w-md-auto">
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <li class="page-item <?php echo ($currentPage <= 1 ? 'disabled' : ''); ?>">
                                <a class="page-link d-inline-flex align-items-center" href="<?php echo $currentPage>1 ? $makeUrl($currentPage-1) : '#'; ?>">
                                    <i class="fas fa-chevron-right ms-1"></i>
                                    <span>السابق</span>
                                </a>
                            </li>
                            <?php
                            // إظهار نطاق صغير من الأرقام حول الصفحة الحالية
                            $from = max(1, $currentPage - 2);
                            $to = min($totalPages, $currentPage + 2);
                            for ($p=$from; $p<=$to; $p++): ?>
                                <li class="page-item <?php echo ($p==$currentPage?'active':''); ?>">
                                    <a class="page-link" href="<?php echo $makeUrl($p); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
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

    <!-- Bootstrap JS -->
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <!-- Reservations JS -->
    <script src="../../assets/js/reservations.js"></script>
</body>
</html> 