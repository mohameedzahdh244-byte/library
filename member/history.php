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
$stmt1 = $conn->prepare("SELECT * FROM customer WHERE mem_no = ?");
$stmt1->bind_param("s", $mem_no);
$stmt1->execute();
$member = $stmt1->get_result()->fetch_assoc();
$stmt1->close();

// معالجة البحث والتصفية
$filter_status = $_GET['status'] ?? 'all';
$filter_year = $_GET['year'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// بناء استعلام SQL
// بناء استعلام SQL
$sql = "SELECT 
            bb.borrow_detail_id,
            bb.serialnum_book,
            bb.boro_no,
            bb.boro_date,
            bb.boro_exp_ret_date,
            rb.return_date,
            b.book_title,
            b.classification_num,
            ct.mem_no,
            CASE 
                WHEN rb.return_date IS NOT NULL THEN 'مكتملة'
                WHEN CURDATE() <= bb.boro_exp_ret_date THEN 'نشطة'
                WHEN CURDATE() > bb.boro_exp_ret_date THEN 'متأخرة'
            END as status_arabic
        FROM borrow_transaction bb
        INNER JOIN customer_transaction ct ON bb.boro_no = ct.boro_no
        LEFT JOIN return_book rb ON bb.borrow_detail_id = rb.borrow_detail_id
        LEFT JOIN book b ON bb.serialnum_book = b.serialnum_book
        WHERE ct.mem_no = ?";

$params = [$mem_no];

// إضافة شروط الفلترة قبل التنفيذ
if ($filter_status !== 'all') {
    // دعم القيم بالعربية والإنجليزية
    if (in_array($filter_status, ['active', 'نشطة'], true)) {
        $sql .= " AND rb.return_date IS NULL AND CURDATE() <= bb.boro_exp_ret_date";
    } elseif (in_array($filter_status, ['returned', 'مكتملة'], true)) {
        $sql .= " AND rb.return_date IS NOT NULL";
    } elseif (in_array($filter_status, ['overdue', 'متأخرة'], true)) {
        $sql .= " AND rb.return_date IS NULL AND CURDATE() > bb.boro_exp_ret_date";
    }
}

if ($filter_year !== 'all') {
    $sql .= " AND YEAR(bb.boro_date) = ?";
    $params[] = $filter_year;
}

if (!empty($search_query)) {
    $sql .= " AND (b.book_title LIKE ? OR b.serialnum_book LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY bb.boro_date DESC";

// الآن حضّر ونفذ الاستعلام
$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat("s", count($params)), ...$params);
$stmt->execute();
$borrow_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$years_stmt = $conn->prepare("
    SELECT DISTINCT YEAR(bb.boro_date) as year 
    FROM borrow_transaction bb
    INNER JOIN customer_transaction ct ON bb.boro_no = ct.boro_no
    WHERE ct.mem_no = ?
    ORDER BY year DESC
");
$years_stmt->bind_param("s", $mem_no);
$years_stmt->execute();
$years_res = $years_stmt->get_result();
$available_years = $years_res->fetch_all(MYSQLI_ASSOC);
$years_stmt->close();


// إزالة كل ما يتعلق بجلب أو عرض الغرامات للمشترك
// احذف أو علق أي كود متعلق بجدول fines أو متغيرات الغرامات أو أقسام الغرامات المدفوعة

// حساب إحصائيات
$stats = [
    'total_borrows' => 0,
    'active_borrows' => 0,
    'completed_borrows' => 0,
    'overdue_borrows' => 0,
    // تمت إزالة إحصائيات الغرامات المدفوعة
];

foreach (
    $borrow_history as $borrow) {
    $stats['total_borrows']++;
    switch ($borrow['status_arabic']) {
        case 'نشطة':
            $stats['active_borrows']++;
            break;
        case 'مكتملة':
            $stats['completed_borrows']++;
            break;
        case 'متأخرة':
            $stats['overdue_borrows']++;
            break;
    }
}

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
?>
<?php if (!$isAjax): ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تاريخ الإعارات - <?php echo $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    <!-- Page specific CSS -->
    <link href="../assets/css/member-history.css" rel="stylesheet">
    <link href="../assets/css/member-common.css" rel="stylesheet">
    
    <!-- Font Awesome (Local) -->
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (Local) -->
    <link href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts Cairo (Local) -->
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <!-- Member Dashboard CSS -->
    <link href="../assets/css/member-dashboard.css" rel="stylesheet">
    <!-- Analytics -->
    <script src="../assets/js/analytics.js"></script>
</head>
<body data-analytics-page="member">
<?php endif; ?>
<?php if ($isAjax): ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تاريخ الإعارات</title>
    <!-- CSS for iframe mode -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    <link href="../assets/css/member-history.css" rel="stylesheet">
    <link href="../assets/css/member-common.css" rel="stylesheet">
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    <style>
        body { 
            padding: 1rem; 
            background: #f8f9fc;
            font-family: 'Cairo', sans-serif;
        }
        .history-container {
            max-width: 100%;
        }
    </style>
</head>
<body data-analytics-page="member">
<?php endif; ?>
    <div class="container-fluid px-4">
        <div class="history-container">
            <!-- Page Header (إظهار فقط في العرض الكامل وليس AJAX) -->
            <?php if (!$isAjax): ?>
                <div class="page-header">
                    <h2 class="mb-2">
                        <i class="fas fa-history me-2"></i>
                        تاريخ الإعارات
                    </h2>
                    <p class="mb-0">سجل كامل لإعاراتك في المكتبة</p>
                </div>
            <?php endif; ?>

              <!-- Stats Cards (Centered) -->
            <?php
                $totalBorrows = isset($borrow_history) ? count($borrow_history) : 0;
                $completedCount = 0; $overdueCount = 0;
                if (!empty($borrow_history)) {
                    foreach ($borrow_history as $bh) {
                        $st = $bh['status_arabic'] ?? '';
                        if ($st === 'مكتملة') $completedCount++;
                        if ($st === 'متأخرة') $overdueCount++;
                    }
                }
            ?>
            <div class="row mb-4 justify-content-center">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stats-number"><?php echo $totalBorrows; ?></div>
                        <div class="stats-label">إجمالي الإعارات</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stats-number"><?php echo $completedCount; ?></div>
                        <div class="stats-label">إعارات مكتملة</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stats-number"><?php echo $overdueCount; ?></div>
                        <div class="stats-label">إعارات متأخرة</div>
                    </div>
                </div>
            </div>
                
                
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <div class="filter-header">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>
                        تصفية النتائج
                    </h5>
                </div>
                
                <div class="filter-body">
                    <form method="GET" id="filterForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="search" class="form-label">البحث في الكتب</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="عنوان الكتاب، المؤلف، أو الرقم التسلسلي"
                                           value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="status" class="form-label">حالة الإعارة</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>جميع الحالات</option>
                                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>نشطة</option>
                                        <option value="returned" <?php echo $filter_status === 'returned' ? 'selected' : ''; ?>>مكتملة</option>
                                        <option value="overdue" <?php echo $filter_status === 'overdue' ? 'selected' : ''; ?>>متأخرة</option>
                                        <option value="renewed" <?php echo $filter_status === 'renewed' ? 'selected' : ''; ?>>مجددة</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="year" class="form-label">السنة</label>
                                    <select class="form-select" id="year" name="year">
                                        <option value="all" <?php echo $filter_year === 'all' ? 'selected' : ''; ?>>جميع السنوات</option>
                                        <?php foreach ($available_years as $year): ?>
                                            <option value="<?php echo $year['year']; ?>" <?php echo $filter_year == $year['year'] ? 'selected' : ''; ?>>
                                                <?php echo $year['year']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Borrow History -->
            <div id="historyResults">
            <?php if (empty($borrow_history)): ?>
                <div class="no-history">
                    <i class="fas fa-history"></i>
                    <h5>لا توجد إعارات</h5>
                    <p>لم تقم بأي إعارة بعد</p>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        البحث عن الكتب
                    </a>
                </div>
            <?php else: ?>
                <h4 class="mb-3">
                    <i class="fas fa-list me-2"></i>
                    سجل الإعارات (<?php echo count($borrow_history); ?> إعارة)
                </h4>
                
                <?php foreach ($borrow_history as $borrow): ?>
                    <?php
                        $status = $borrow['status_arabic'];
                        $badgeClass = 'bg-secondary';
                        if ($status === 'نشطة') $badgeClass = 'bg-info';
                        elseif ($status === 'مكتملة') $badgeClass = 'bg-success';
                        elseif ($status === 'متأخرة') $badgeClass = 'bg-danger';
                    ?>
                    <div class="card borrow-card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="book-title mb-1">
                                        <?php echo htmlspecialchars($borrow['book_title'] ?? 'بدون عنوان'); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-hashtag ms-1"></i>
                                        الرقم التسلسلي: <?php echo htmlspecialchars($borrow['serialnum_book']); ?>
                                    </div>
                                </div>
                                <span class="badge <?php echo $badgeClass; ?> px-3 py-2">
                                    <?php echo $status; ?>
                                </span>
                            </div>

                            <hr class="my-3">

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="small text-muted">تاريخ الإعارة</div>
                                    <div class="fw-semibold">
                                        <i class="fas fa-calendar-day ms-1 text-primary"></i>
                                        <?php echo date('Y/m/d', strtotime($borrow['boro_date'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted">تاريخ الإرجاع المتوقع</div>
                                    <div class="fw-semibold">
                                        <i class="fas fa-calendar-check ms-1 text-warning"></i>
                                        <?php echo date('Y/m/d', strtotime($borrow['boro_exp_ret_date'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted">رقم التصنيف</div>
                                    <div class="fw-semibold">
                                        <i class="fas fa-barcode ms-1 text-secondary"></i>
                                        <?php echo htmlspecialchars($borrow['classification_num'] ?? 'غير محدد'); ?>
                                    </div>
                                </div>
                                <?php if ($borrow['return_date']): ?>
                                    <div class="col-md-4">
                                        <div class="small text-muted">تاريخ الإرجاع الفعلي</div>
                                        <div class="fw-semibold">
                                            <i class="fas fa-rotate-left ms-1 text-success"></i>
                                            <?php echo date('Y/m/d', strtotime($borrow['return_date'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-end align-items-center mt-3">
                                <?php if ($borrow['status_arabic'] === 'نشطة'): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php 
                                            $due_date = new DateTime($borrow['boro_exp_ret_date']);
                                            $today = new DateTime();
                                            $diff = $today->diff($due_date);
                                            if ($diff->invert) {
                                                echo 'متأخر ' . $diff->days . ' يوم';
                                            } else {
                                                echo 'متبقي ' . $diff->days . ' يوم';
                                            }
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div> <!-- /#historyResults -->
        </div>
<?php if (!$isAjax): ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <!-- Page specific JS -->
    <script src="../assets/js/member-history.js"></script>
</body>
</html>
<?php else: ?>
</body>
</html>
<?php endif; ?>