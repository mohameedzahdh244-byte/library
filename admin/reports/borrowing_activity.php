<?php
require_once '../../config/init.php';

// التحقق من صلاحيات الموظف
checkStaffPermission();

$user_no = $_SESSION['user_no'];
$user_type = $_SESSION['user_type'];

// معالجة تصفية التقارير - نشاط الإعارة فقط
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department = $_GET['department'] ?? 'all';
// ترقيم الصفحات (افتراضي 20 لكل صفحة)
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// الحصول على الأقسام المتاحة
$departments_stmt = $conn->prepare("SELECT DISTINCT department FROM book WHERE department IS NOT NULL ORDER BY department");
$departments_stmt->execute();
$available_departments = $departments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// تقرير نشاط الإعارة
$report_title = 'تقرير نشاط الإعارة';
// تحديد نمط التجميع: السماح للمستخدم بالاختيار، وإلا اختيار تلقائي حسب المدى
$yf = (int)date('Y', strtotime($date_from));
$yt = (int)date('Y', strtotime($date_to));
$group = $_GET['group'] ?? (($yf !== $yt) ? 'year' : 'month'); // year | month
$group_by_year = ($group === 'year');
$period_label_name = $group_by_year ? 'السنة' : 'الشهر';
$report_description = $group_by_year ? 'إحصائيات الإعارة مجمّعة سنويًا' : 'إحصائيات الإعارة لهذه السنة مجمّعة شهريًا';

if ($group_by_year) {
    $sql = "SELECT CAST(YEAR(bt.boro_date) AS CHAR) AS period, COUNT(*) AS borrow_count,
                   SUM(CASE WHEN rb.return_date IS NOT NULL THEN 1 ELSE 0 END) AS returned_count,
                   SUM(CASE WHEN rb.return_date IS NULL THEN 1 ELSE 0 END) AS active_count
            FROM borrow_transaction bt
            LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id";
    if ($department !== 'all') {
        $sql .= " LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book";
    }
    $sql .= " WHERE bt.boro_date BETWEEN ? AND ?";
    if ($department !== 'all') {
        $sql .= " AND b.department = ?";
    }
    // إجمالي عدد الفترات
    $countSql = "SELECT COUNT(*) AS cnt FROM (" . $sql . " GROUP BY CAST(YEAR(bt.boro_date) AS CHAR)) t";
    $countStmt = $conn->prepare($countSql);
    if ($department !== 'all') { $countStmt->bind_param('sss', $date_from, $date_to, $department); } else { $countStmt->bind_param('ss', $date_from, $date_to); }
    $countStmt->execute();
    $total_records = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    $sql .= " GROUP BY CAST(YEAR(bt.boro_date) AS CHAR)
            ORDER BY CAST(YEAR(bt.boro_date) AS CHAR) ASC
            LIMIT ? OFFSET ?";
} else {
    $sql = "SELECT DATE_FORMAT(bt.boro_date, '%Y-%m') AS period, COUNT(*) AS borrow_count,
                   SUM(CASE WHEN rb.return_date IS NOT NULL THEN 1 ELSE 0 END) AS returned_count,
                   SUM(CASE WHEN rb.return_date IS NULL THEN 1 ELSE 0 END) AS active_count
            FROM borrow_transaction bt
            LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id";
    if ($department !== 'all') {
        $sql .= " LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book";
    }
    $sql .= " WHERE bt.boro_date BETWEEN ? AND ?";
    if ($department !== 'all') {
        $sql .= " AND b.department = ?";
    }
    // إجمالي عدد الفترات
    $countSql = "SELECT COUNT(*) AS cnt FROM (" . $sql . " GROUP BY DATE_FORMAT(bt.boro_date, '%Y-%m')) t";
    $countStmt = $conn->prepare($countSql);
    if ($department !== 'all') { $countStmt->bind_param('sss', $date_from, $date_to, $department); } else { $countStmt->bind_param('ss', $date_from, $date_to); }
    $countStmt->execute();
    $total_records = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    $sql .= " GROUP BY DATE_FORMAT(bt.boro_date, '%Y-%m')
            ORDER BY DATE_FORMAT(bt.boro_date, '%Y-%m') ASC
            LIMIT ? OFFSET ?";
}

if ($department !== 'all') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssii', $date_from, $date_to, $department, $per_page, $offset);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssii', $date_from, $date_to, $per_page, $offset);
}
$stmt->execute();
$report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// حساب الإحصائيات
$stats = [];
$stats['total_borrows'] = array_sum(array_column($report_data, 'borrow_count'));
$stats['total_returns'] = array_sum(array_column($report_data, 'returned_count'));
$stats['total_active'] = array_sum(array_column($report_data, 'active_count'));

// تحضير البيانات للمخطط
$chart_labels = array_column($report_data, 'period');
$chart_borrows = array_column($report_data, 'borrow_count');
$chart_returns = array_column($report_data, 'returned_count');
$chart_active = array_column($report_data, 'active_count');

// الحصول على إعدادات المكتبة لطباعة الشعار والاسم
$libraryInfo = $settings->getLibraryInfo();
$embed = isset($_GET['embed']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_title; ?></title>
    <link href="/assets/css/bootstrap.css" rel="stylesheet">
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <script src="/assets/js/chart.umd.min.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #f8f9fa;
        }
        
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 0;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2rem;
        }
        
        .filter-header {
            background: var(--light-bg);
            padding: 1rem 1.5rem;
            border-radius: 15px 15px 0 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .filter-body {
            padding: 1.5rem;
        }
        
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2rem;
        }
        
        .report-header {
            background: linear-gradient(135deg, var(--success-color), #2ecc71);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
        }
        
        .report-body {
            padding: 1.5rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border: none;
            margin-bottom: 1rem;
            text-align: center;
            /* Override global .stats-card color from assets/css/style.css */
            color: #0f172a;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #0f172a; /* high contrast */
        }
        
        .stats-label {
            color: #334155; /* darker and clearer than muted gray */
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .table-custom {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table-custom thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table-custom tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .btn-export {
            background: var(--secondary-color);
            border: none;
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-export:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .overdue-badge {
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        /* طباعة احترافية خفيفة مع Bootstrap */
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            /* محتوى الطباعة الأساسي */
            #printArea { font-size: 12px; color:#000; font-family: Tahoma, Arial, "Segoe UI", sans-serif; }
            #printArea .table { width: 100%; border-collapse: collapse; }
            #printArea thead { display: table-header-group; }
            #printArea tr { page-break-inside: avoid; break-inside: avoid; }
            #printArea th, #printArea td { border: 1px solid #000; padding: 6px 8px; }
            #printArea thead th { background: #d9d9d9; color:#000; font-weight:700; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            #printArea .text-muted { color:#000 !important; }
            #printArea hr { border-top: 2px solid #000 !important; opacity: 1 !important; margin: 6px 0; }
            /* منع ظهور عناصر الواجهة في الطباعة إن لم تكن موسومة مسبقًا */
            .page-header, .filter-card, .row.mb-4 { display: none !important; }
        }

        

        /* Utility */
        .print-only { display: none; }

    
    </style>
</head>
<body class="<?php echo $embed ? 'embedded' : ''; ?>">
    <div class="<?php echo $embed ? 'container-fluid px-0' : 'container'; ?>">
        <div class="reports-container">
            <!-- Page Header -->
            <div class="page-header position-relative d-print-none">
                <h2 class="mb-2">
                    <i class="fas fa-chart-line me-2"></i>
                    <?php echo $report_title; ?>
                </h2>
                <p class="mb-0"><?php echo $report_description; ?></p>
            </div>

            <!-- Filter Card -->
            <div class="filter-card d-print-none">
                <div class="filter-header">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>
                        تصفية التقرير
                    </h5>
                </div>
                
                <div class="filter-body">
                    <form method="GET" id="filterForm">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label for="date_from" class="form-label fw-bold">من تاريخ</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" 
                                           value="<?php echo $date_from; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label for="date_to" class="form-label fw-bold">إلى تاريخ</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" 
                                           value="<?php echo $date_to; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="department" class="form-label fw-bold">القسم</label>
                                    <select class="form-select" id="department" name="department">
                                        <option value="all">جميع الأقسام</option>
                                        <?php foreach ($available_departments as $dept): ?>
                                            <option value="<?php echo $dept['department']; ?>" <?php echo $department === $dept['department'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- اختيار التجميع سنوي/شهري -->
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label for="group" class="form-label fw-bold">التجميع</label>
                                    <select class="form-select" id="group" name="group">
                                        <option value="year" <?php echo $group_by_year ? 'selected' : ''; ?>>سنوي</option>
                                        <option value="month" <?php echo !$group_by_year ? 'selected' : ''; ?>>شهري</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            تطبيق
                                            <i class="fas fa-check me-2"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="exportReport()">
                                            تصدير
                                            <i class="fas fa-download me-2"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" onclick="printReport()">
                                            طباعة
                                            <i class="fas fa-print me-2"></i>   
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Statistics -->
            <div class="row mb-4 d-print-none">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_borrows']; ?></div>
                        <div class="stats-label">إجمالي الإعارات</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon bg-success">
                            <i class="fas fa-undo"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_returns']; ?></div>
                        <div class="stats-label">إجمالي الإرجاعات</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-icon bg-warning text-dark">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_active']; ?></div>
                        <div class="stats-label">الإعارات النشطة</div>
                    </div>
                </div>
            </div>

            <!-- Printable Area: Header + Charts + Table -->
            <div id="printArea">
                <!-- Print Header (Bootstrap only) -->
                <div class="d-none d-print-block mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <!-- شعار المكتبة -->
                            <img src="/public/logo.png" alt="شعار المكتبة" style="width:52px;height:52px;object-fit:contain;">
                            <div>
                                <div class="fw-bold" style="font-size:18px;"><?php echo htmlspecialchars($libraryInfo['name'] ?? 'اسم المكتبة'); ?></div>
                                <div class="text-dark" style="font-size:14px;">التقرير: <?php echo htmlspecialchars($report_title); ?></div>
                            </div>
                        </div>
                        <div class="text-start" style="font-size:12px;">
                            <div>النطاق: <?php echo ($date_from || $date_to) ? (htmlspecialchars($date_from) . ' - ' . htmlspecialchars($date_to)) : 'غير محدد'; ?></div>
                            <div>تاريخ الطباعة: <?php echo date('Y/m/d H:i'); ?></div>
                        </div>
                    </div>
                    <hr class="my-2">
                </div>

            <!-- Chart -->
            <div class="report-card d-print-block">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-chart-line me-2 text-primary"></i>
                        الإعارات عبر الزمن
                    </h5>
                    <div class="chart-container">
                        <canvas id="borrowingChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="report-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo $period_label_name; ?></th>
                                    <th>عدد الإعارات</th>
                                    <th>عدد الإرجاعات</th>
                                    <th>الإعارات النشطة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report_data)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle me-2"></i>
                                            لا توجد بيانات للفترة المحددة
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['period']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $row['borrow_count']; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $row['returned_count']; ?></span></td>
                                            <td><span class="badge bg-warning text-dark"><?php echo $row['active_count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (($total_records ?? 0) > $per_page): ?>
                <?php
                    $total_pages = (int)ceil($total_records / $per_page);
                    $page = max(1, min($page, $total_pages));
                    $qs = $_GET;
                    unset($qs['page']);
                    $base = http_build_query($qs);
                    $link = function($p) use ($base) { return '?' . ($base ? $base . '&' : '') . 'page=' . $p; };
                ?>
                <nav class="d-print-none" aria-label="ترقيم الصفحات">
                    <ul class="pagination justify-content-center mt-3">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $page > 1 ? $link($page - 1) : '#'; ?>" aria-label="السابق">&laquo;</a>
                        </li>
                        <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            if ($start > 1) {
                                echo '<li class="page-item"><a class="page-link" href="' . $link(1) . '">1</a></li>';
                                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            }
                            for ($p = $start; $p <= $end; $p++) {
                                $active = $p === $page ? ' active' : '';
                                echo '<li class="page-item' . $active . '"><a class="page-link" href="' . $link($p) . '">' . $p . '</a></li>';
                            }
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="' . $link($total_pages) . '">' . $total_pages . '</a></li>';
                            }
                        ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $page < $total_pages ? $link($page + 1) : '#'; ?>" aria-label="التالي">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Print total summary -->
            <div class="print-only" style="margin-top:6mm; font-size:12px;">
                <strong>إجمالي السجلات:</strong> <?php echo count($report_data); ?>
            </div>
            </div> <!-- /#printArea -->
        </div>
    </div>

    <!-- Bootstrap JS (Local) -->
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Chart (Line with 3 series: Borrows, Returns, Active)
        <?php if (!empty($report_data)): ?>
        const ctx = document.getElementById('borrowingChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [
                    { label: 'الإعارات', data: <?php echo json_encode($chart_borrows); ?>,
                      borderColor: '#1E88E5', backgroundColor: 'rgba(30,136,229,.12)', pointBackgroundColor: '#1E88E5', tension: 0.3, fill: true, borderWidth: 2 },
                    { label: 'الإرجاعات', data: <?php echo json_encode($chart_returns); ?>,
                      borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,.12)', pointBackgroundColor: '#10B981', tension: 0.3, fill: true, borderWidth: 2 },
                    { label: 'النشطة', data: <?php echo json_encode($chart_active); ?>,
                      borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,.12)', pointBackgroundColor: '#F59E0B', tension: 0.3, fill: true, borderWidth: 2 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: { rtl: true, textDirection: 'rtl' },
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 14 } }
                }
            }
        });
        <?php endif; ?>

        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('type', 'borrowing_activity');
            const exportUrl = 'export.php?' + params.toString() + '&format=excel';
            window.open(exportUrl, '_blank');
        }

        function printReport() {
            // تقدير عدد الصفوف لتصغير المسافات عند الجداول القصيرة
            try {
                const rows = document.querySelectorAll('.table-custom tbody tr').length;
                const bodyEl = document.body;
                if (rows > 0 && rows <= 20) {
                    bodyEl.classList.add('portrait');
                } else {
                    bodyEl.classList.remove('portrait');
                }
                // تنظيف بعد الطباعة
                const cleanup = () => bodyEl.classList.remove('portrait');
                if (window.matchMedia) {
                    const mq = window.matchMedia('print');
                    const handler = (e) => { if (!e.matches) { cleanup(); mq.removeEventListener('change', handler); } };
                    mq.addEventListener('change', handler);
                }
                window.onafterprint = cleanup;
            } catch (e) { /* تجاهل */ }

            // تسجيل عملية الطباعة (غير حاجز)
            try {
                const params = new URLSearchParams(window.location.search);
                params.set('type', 'borrowing_activity');
                fetch('print_log.php?' + params.toString(), { method: 'GET', keepalive: true }).catch(() => {});
            } catch (e) { /* تجاهل */ }

            window.print();
        }
        
        // Submit filters when pressing Enter anywhere inside the filter form
        (function(){
            const form = document.getElementById('filterForm');
            if (!form) return;
            form.addEventListener('keydown', function(e){
                if (e.key === 'Enter') {
                    e.preventDefault();
                    try { form.requestSubmit ? form.requestSubmit() : form.submit(); } catch (_) { form.submit(); }
                }
            });
        })();
    </script>
</body>
</html>