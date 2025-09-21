<?php
require_once '../../config/init.php';
checkStaffPermission();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department = $_GET['department'] ?? 'all';
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$departments_stmt = $conn->prepare("SELECT DISTINCT department FROM book WHERE department IS NOT NULL ORDER BY department");
$departments_stmt->execute();
$available_departments = $departments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$report_title = 'تقرير الكتب المتأخرة';
$report_description = 'قائمة الكتب التي تأخر إرجاعها عن الموعد المحدد';

// عدّ إجمالي السجلات
$countSql = "SELECT COUNT(DISTINCT bt.borrow_detail_id) AS cnt
             FROM borrow_transaction bt
             LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book
             LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
             WHERE rb.borrow_detail_id IS NULL
               AND bt.boro_exp_ret_date < CURDATE()
               AND bt.boro_exp_ret_date BETWEEN ? AND ?";
if ($department !== 'all') { $countSql .= " AND b.department = ?"; }

$countStmt = $conn->prepare($countSql);
if ($department !== 'all') { $countStmt->bind_param('sss', $date_from, $date_to, $department); }
else { $countStmt->bind_param('ss', $date_from, $date_to); }
$countStmt->execute();
$total_overdue_records = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

// جلب صفحة النتائج
$sql = "SELECT 
            bt.borrow_detail_id,
            bt.serialnum_book,
            bt.boro_no,
            bt.boro_date,
            bt.boro_exp_ret_date,
            b.book_title,
            b.department,
            c.mem_name,
            c.mem_no,
            DATEDIFF(CURDATE(), bt.boro_exp_ret_date) as days_overdue
        FROM borrow_transaction bt
        LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book
        INNER JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
        LEFT JOIN customer c ON ct.mem_no = c.mem_no
        LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
        WHERE rb.borrow_detail_id IS NULL
          AND bt.boro_exp_ret_date < CURDATE()
          AND bt.boro_exp_ret_date BETWEEN ? AND ?";
if ($department !== 'all') { $sql .= " AND b.department = ?"; }
$sql .= " ORDER BY bt.boro_exp_ret_date ASC LIMIT ? OFFSET ?";

if ($department !== 'all') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssii', $date_from, $date_to, $department, $per_page, $offset);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssii', $date_from, $date_to, $per_page, $offset);
}
$stmt->execute();
$report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// دمج المؤلفين وأرقام الهواتف
if (!empty($report_data)) {
    $serials = array_values(array_unique(array_filter(array_column($report_data, 'serialnum_book'))));
    $memNos = array_values(array_unique(array_filter(array_column($report_data, 'mem_no'))));

    $authorsBySerial = [];
    if (!empty($serials)) {
        $serialsEsc = array_map(function($s) use ($conn) { return "'" . $conn->real_escape_string($s) . "'"; }, $serials);
        $sqlAuthors = "SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
                       FROM book_authors ba
                       JOIN authors au ON au.ANO = ba.ANO
                       WHERE ba.serialnum_book IN (" . implode(',', $serialsEsc) . ")
                       GROUP BY ba.serialnum_book";
        $resA = $conn->query($sqlAuthors);
        if ($resA) {
            while ($rowA = $resA->fetch_assoc()) {
                $authorsBySerial[$rowA['serialnum_book']] = $rowA['authors'] ?? '';
            }
        }
    }

    $phonesByMem = [];
    if (!empty($memNos)) {
        $memNosInt = array_map('intval', $memNos);
        $sqlPhones = "SELECT mem_no, GROUP_CONCAT(DISTINCT mem_phone ORDER BY mem_phone SEPARATOR ', ') AS mem_phone
                      FROM mem_phone
                      WHERE mem_no IN (" . implode(',', $memNosInt) . ")
                      GROUP BY mem_no";
        $resP = $conn->query($sqlPhones);
        if ($resP) {
            while ($rowP = $resP->fetch_assoc()) {
                $phonesByMem[$rowP['mem_no']] = $rowP['mem_phone'] ?? '';
            }
        }
    }

    foreach ($report_data as &$r) {
        $r['author'] = $authorsBySerial[$r['serialnum_book']] ?? '';
        $r['mem_phone'] = $phonesByMem[$r['mem_no']] ?? '';
    }
    unset($r);
}

$libraryInfo = $settings->getLibraryInfo();
$embed = isset($_GET['embed']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير التأخير - <?php echo $libraryInfo['name']; ?></title>
    
    <link href="/assets/css/bootstrap.css" rel="stylesheet">
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/fonts/cairo/cairo.css" rel="stylesheet">
    
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
            background: linear-gradient(135deg, var(--danger-color), #c0392b);
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
            background: linear-gradient(135deg, var(--danger-color), #c0392b);
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
            background-color: rgba(231, 76, 60, 0.05);
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
            <div class="page-header position-relative d-print-none">
                <h2 class="mb-2">
                    <i class="fas fa-clock-rotate-left me-2"></i>
                    تقارير التأخير
                </h2>
                <p class="mb-0">تقرير مفصل للكتب المتأخرة</p>
            </div>

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

            <div class="row mb-4 d-print-none">
                <div class="col-12 col-md-6 col-lg-6 mx-auto">
                    <div class="stats-card">
                        <div class="stats-icon bg-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stats-number"><?php echo $total_overdue_records; ?></div>
                        <div class="stats-label">كتب متأخرة</div>
                    </div>
                </div>
            </div>

            <div class="report-card">
                <div class="report-header d-print-none">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="me-auto">
                            <h4 class="mb-1"><?php echo $report_title; ?></h4>
                            <p class="mb-0"><?php echo $report_description; ?></p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark fs-6">
                                <?php echo (int)$total_overdue_records; ?> نتيجة
                            </span>
                            <span id="reportStatus" class="badge bg-info text-dark">
                                تقرير مُحضَّر
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="report-body">
                    <?php if (empty($report_data)): ?>
                        <div class="no-data">
                            <i class="fas fa-chart-line"></i>
                            <h5>لا توجد بيانات</h5>
                            <p>لا توجد بيانات متاحة لهذا التقرير في الفترة المحددة</p>
                        </div>
                    <?php else: ?>
                        <div id="printArea">
                            <div class="d-none d-print-block mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="/public/logo.png" alt="شعار المكتبة" style="width:52px;height:52px;object-fit:contain;">
                                        <div>
                                            <div class="fw-bold" style="font-size:18px;"><?php echo htmlspecialchars($libraryInfo['name'] ?? 'اسم المكتبة'); ?></div>
                                            <div class="text-dark" style="font-size:14px;">التقرير: <?php echo htmlspecialchars($report_title); ?></div>
                                        </div>
                                    </div>
                                    <div class="text-start" style="font-size:12px;">
                                        <div>النطاق: <?php echo htmlspecialchars($date_from) . ' - ' . htmlspecialchars($date_to); ?></div>
                                        <div>تاريخ الطباعة: <?php echo date('Y/m/d H:i'); ?></div>
                                    </div>
                                </div>
                                <hr class="my-2">
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-custom">
                                <thead>
                                    <tr>
                                        <th>المشترك</th>
                                        <th>الكتاب</th>
                                        <th>القسم</th>
                                        <th>تاريخ الإعارة</th>
                                        <th>تاريخ الاستحقاق</th>
                                        <th>أيام التأخير</th>
                                        <th class="d-print-none">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($row['mem_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo $row['mem_phone']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($row['book_title']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($row['author'] ?? 'غير محدد'); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['department'] ?? 'غير محدد'); ?></td>
                                            <td><?php echo !empty($row['boro_date']) ? date('Y/m/d', strtotime($row['boro_date'])) : ''; ?></td>
                                            <td><?php echo !empty($row['boro_exp_ret_date']) ? date('Y/m/d', strtotime($row['boro_exp_ret_date'])) : ''; ?></td>
                                            <td>
                                                <span class="overdue-badge">
                                                    <?php echo $row['days_overdue']; ?> يوم
                                                </span>
                                            </td>
                                            <td class="d-print-none">
                                                <a href="../borrowing/return.php?embed=1&mem_no=<?php echo urlencode($row['mem_no']); ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    إرجاع
                                                    <i class="fas fa-undo me-1"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>

                            <?php if ($total_overdue_records > $per_page): ?>
                                <?php
                                    $total_pages = (int)ceil($total_overdue_records / $per_page);
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

                            <div class="print-only" style="margin-top:6mm; font-size:12px;">
                                <strong>إجمالي السجلات:</strong> <?php echo count($report_data); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            const exportUrl = 'export.php?' + params.toString() + '&format=excel&type=overdue';
            window.open(exportUrl, '_blank');
            document.getElementById('reportStatus').className = 'badge bg-success';
            document.getElementById('reportStatus').textContent = 'تقرير مُصدَّر';
        }

        function printReport() {
            document.getElementById('reportStatus').className = 'badge bg-primary';
            document.getElementById('reportStatus').textContent = 'جاهز للطباعة';
            window.print();
        }
        
        document.getElementById('filterForm').addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                this.submit();
            }
        });
    </script>
</body>
</html>