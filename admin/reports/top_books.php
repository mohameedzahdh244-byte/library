<?php
require_once '../../config/init.php';

// التحقق من صلاحيات الموظف
checkStaffPermission();

$user_no = $_SESSION['user_no'];
$user_type = $_SESSION['user_type'];

// معالجة تصفية التقارير - الكتب الأكثر إعارة فقط
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department = $_GET['department'] ?? 'all';
// ترقيم الصفحات
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// الحصول على الأقسام المتاحة
$departments_stmt = $conn->prepare("SELECT DISTINCT department FROM book WHERE department IS NOT NULL ORDER BY department");
$departments_stmt->execute();
$available_departments = $departments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// تقرير الكتب الأكثر إعارة
$report_title = 'تقرير الكتب الأكثر إعارة';
$report_description = 'الكتب الأكثر طلباً وإعارة';

// إجمالي السجلات (عدد الكتب المطابقة)
$countSql = "SELECT COUNT(DISTINCT b.serialnum_book) AS cnt
            FROM borrow_transaction bt
            LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book
            WHERE bt.boro_date BETWEEN ? AND ?";
if ($department !== 'all') {
    $countSql .= " AND b.department = ?";
}
$countStmt = $conn->prepare($countSql);
if ($department !== 'all') {
    $countStmt->bind_param('sss', $date_from, $date_to, $department);
} else {
    $countStmt->bind_param('ss', $date_from, $date_to);
}
$countStmt->execute();
$total_records = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

// استعلام الصفحة الحالية
$sql = "SELECT b.book_title, bauth.authors AS author, b.department, COUNT(*) as borrow_count,
               SUM(CASE WHEN rb.return_date IS NOT NULL THEN 1 ELSE 0 END) as returned_count
        FROM borrow_transaction bt
        LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book
        LEFT JOIN (
            SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
            FROM book_authors ba
            JOIN authors au ON au.ANO = ba.ANO
            GROUP BY ba.serialnum_book
        ) bauth ON bauth.serialnum_book = b.serialnum_book
        LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
        WHERE bt.boro_date BETWEEN ? AND ?";

if ($department !== 'all') {
    $sql .= " AND b.department = ?";
}

$sql .= " GROUP BY b.serialnum_book
          ORDER BY borrow_count DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($department !== 'all') {
    $stmt->bind_param('sssii', $date_from, $date_to, $department, $per_page, $offset);
} else {
    $stmt->bind_param('ssii', $date_from, $date_to, $per_page, $offset);
}
$stmt->execute();
$report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
            color: #0f172a;
        }
        
        .stats-label {
            color: #334155;
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
            #printArea { font-size: 12px; color:#000; font-family: Tahoma, Arial, "Segoe UI", sans-serif; }
            #printArea .table { width: 100%; border-collapse: collapse; }
            #printArea thead { display: table-header-group; }
            #printArea tr { page-break-inside: avoid; break-inside: avoid; }
            #printArea th, #printArea td { border: 1px solid #000; padding: 6px 8px; }
            #printArea thead th { background: #d9d9d9; color:#000; font-weight:700; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            #printArea .text-muted { color:#000 !important; }
            #printArea hr { border-top: 2px solid #000 !important; opacity: 1 !important; margin: 6px 0; }
            .page-header, .filter-card, .row.mb-4 { display: none !important; }
        }

        .print-only { display: none; }
    </style>
</head>
<body class="<?php echo $embed ? 'embedded' : ''; ?>">
    <div class="<?php echo $embed ? 'container-fluid px-0' : 'container'; ?>">
        <div class="reports-container">
            <!-- Page Header -->
            <div class="page-header position-relative d-print-none">
                <h2 class="mb-2">
                    <i class="fas fa-ranking-star me-2"></i>
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
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="date_from" class="form-label fw-bold">من تاريخ</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" 
                                           value="<?php echo $date_from; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
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

                            <div class="col-md-3">
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
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        الكتب الأكثر إعارة
                    </h5>
                    <div class="chart-container">
                        <canvas id="popularBooksChart"></canvas>
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
                                    <th>الكتاب</th>
                                    <th>المؤلف</th>
                                    <th>القسم</th>
                                    <th>عدد الإعارات</th>
                                    <th>عدد الإرجاعات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report_data)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle me-2"></i>
                                            لا توجد بيانات للفترة المحددة
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($row['book_title']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($row['author'] ?? 'غير محدد'); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['author'] ?? 'غير محدد'); ?></td>
                                            <td><?php echo htmlspecialchars($row['department'] ?? 'غير محدد'); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $row['borrow_count']; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $row['returned_count']; ?></span></td>
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
        // Chart (Bar chart for popular books)
        <?php if (!empty($report_data)): ?>
        const ctx = document.getElementById('popularBooksChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($r){ return (string)($r['book_title'] ?? 'غير معروف'); }, $report_data), JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'عدد الإعارات',
                    data: <?php echo json_encode(array_map(function($r){ return (int)($r['borrow_count'] ?? 0); }, $report_data)); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.6)',
                    borderColor: '#3498db',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: { rtl: true, textDirection: 'rtl' },
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { 
                        ticks: { 
                            maxRotation: 45, 
                            autoSkip: true, 
                            maxTicksLimit: 10 
                        } 
                    }
                }
            }
        });
        <?php endif; ?>

        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('type', 'popular_books');
            const exportUrl = 'export.php?' + params.toString() + '&format=excel';
            window.open(exportUrl, '_blank');
        }

        function printReport() {
            // تسجيل عملية الطباعة (غير حاجز)
            try {
                const params = new URLSearchParams(window.location.search);
                params.set('type', 'popular_books');
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