<?php
require_once '../../config/init.php';

// التحقق من صلاحيات الموظف
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header('Location: ../../auth/loginform.php');
    exit;
}

// معاملات التصفية
$status = $_GET['status'] ?? '';
$member = $_GET['member'] ?? '';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// بناء الاستعلام
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($status) && in_array($status, ['new', 'reviewed', 'purchased', 'rejected'])) {
    $where_conditions[] = "bs.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

if (!empty($member)) {
    $where_conditions[] = "bs.mem_no = ?";
    $params[] = $member;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(bs.title LIKE ? OR bs.author LIKE ? OR c.mem_name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(bs.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(bs.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// استعلام البيانات
$sql = "
    SELECT 
        bs.id,
        bs.mem_no,
        bs.title,
        bs.author,
        bs.notes,
        bs.status,
        bs.staff_notes,
        bs.created_at,
        c.mem_name,
        (
            SELECT mp.mem_phone 
            FROM mem_phone mp 
            WHERE mp.mem_no = bs.mem_no 
            ORDER BY mp.id_phone ASC 
            LIMIT 1
        ) AS phone
    FROM book_suggestions bs
    LEFT JOIN customer c ON bs.mem_no = c.mem_no
    $where_clause
    ORDER BY bs.created_at DESC
    LIMIT 1000
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$suggestions = $result->fetch_all(MYSQLI_ASSOC);

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();

function getStatusText($status) {
    $statusMap = [
        'new' => 'جديد',
        'reviewed' => 'تمت المراجعة',
        'purchased' => 'تم الشراء',
        'rejected' => 'مرفوض'
    ];
    return $statusMap[$status] ?? $status;
}

// تسجيل عملية الطباعة كـ print_report ضمن جدول التقارير، لمواءمة باقي التقارير
if (isset($auditLogger)) {
    try {
        $auditLogger->log(null, 'print_report', 'report', null, null, [
            'report_name'  => 'book_suggestions',
            'record_count' => count($suggestions),
            'filters' => [
                'status'    => $status,
                'member'    => $member,
                'search'    => $search,
                'date_from' => $date_from,
                'date_to'   => $date_to
            ]
        ]);
    } catch (Throwable $e) {
        // تجاهل أي خطأ في التسجيل حتى لا يؤثر على الطباعة
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة اقتراحات الكتب - <?php echo $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="../../assets/css/bootstrap.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="../../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .page-break { page-break-before: always; }
        }
        
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: white;
        }
        
        .header-section {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .suggestions-table {
            font-size: 13px;
        }
        
        .suggestions-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border: 1px solid #dee2e6;
            padding: 8px;
        }
        
        .suggestions-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            vertical-align: top;
        }
        
        .status-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .status-new { background-color: #e3f2fd; color: #1976d2; }
        .status-reviewed { background-color: #fff3e0; color: #f57c00; }
        .status-purchased { background-color: #e8f5e8; color: #2e7d32; }
        .status-rejected { background-color: #ffebee; color: #d32f2f; }
        
        .notes-cell {
            max-width: 200px;
            word-wrap: break-word;
        }
        
        .footer-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="no-print mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h4>معاينة الطباعة</h4>
            <div>
                <button class="btn btn-primary me-2" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>
                    طباعة
                </button>
                <button class="btn btn-secondary" onclick="window.close()">
                    إغلاق
                </button>
            </div>
        </div>
        <hr>
    </div>

    <!-- Header -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2"><?php echo htmlspecialchars($libraryInfo['name']); ?></h2>
                <h4 class="text-primary mb-3">تقرير اقتراحات الكتب</h4>
                
                <!-- Filters Info -->
                <?php if (!empty($status) || !empty($search) || !empty($date_from) || !empty($date_to)): ?>
                    <div class="mb-3">
                        <h6>معايير التصفية:</h6>
                        <ul class="list-unstyled mb-0">
                            <?php if (!empty($status)): ?>
                                <li><strong>الحالة:</strong> <?php echo getStatusText($status); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($search)): ?>
                                <li><strong>البحث:</strong> <?php echo htmlspecialchars($search); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($date_from)): ?>
                                <li><strong>من تاريخ:</strong> <?php echo htmlspecialchars($date_from); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($date_to)): ?>
                                <li><strong>إلى تاريخ:</strong> <?php echo htmlspecialchars($date_to); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <p class="mb-1"><strong>تاريخ التقرير:</strong> <?php echo date('Y/m/d H:i'); ?></p>
                <p class="mb-1"><strong>عدد الاقتراحات:</strong> <?php echo count($suggestions); ?></p>
                <p class="mb-0"><strong>المستخدم:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'غير محدد'); ?></p>
            </div>
        </div>
    </div>

    <!-- Suggestions Table -->
    <?php if (empty($suggestions)): ?>
        <div class="text-center py-5">
            <h5>لا توجد اقتراحات للطباعة</h5>
            <p class="text-muted">لم يتم العثور على اقتراحات تطابق معايير البحث المحددة</p>
        </div>
    <?php else: ?>
        <table class="table table-bordered suggestions-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 20%;">عنوان الكتاب</th>
                    <th style="width: 15%;">المؤلف</th>
                    <th style="width: 15%;">العضو</th>
                    <th style="width: 10%;">الحالة</th>
                    <th style="width: 20%;">ملاحظات العضو</th>
                    <th style="width: 10%;">تاريخ الاقتراح</th>
                    <th style="width: 5%;">الهاتف</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suggestions as $index => $suggestion): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><strong><?php echo htmlspecialchars($suggestion['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($suggestion['author']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($suggestion['mem_name'] ?? 'غير محدد'); ?>
                            <br><small class="text-muted">(<?php echo htmlspecialchars($suggestion['mem_no']); ?>)</small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $suggestion['status']; ?>">
                                <?php echo getStatusText($suggestion['status']); ?>
                            </span>
                        </td>
                        <td class="notes-cell">
                            <?php if (!empty($suggestion['notes'])): ?>
                                <?php echo htmlspecialchars($suggestion['notes']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                            
                            <?php if (!empty($suggestion['staff_notes'])): ?>
                                <hr style="margin: 5px 0;">
                                <small><strong>ملاحظات الموظف:</strong><br>
                                <?php echo htmlspecialchars($suggestion['staff_notes']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y/m/d', strtotime($suggestion['created_at'])); ?></td>
                        <td>
                            <?php if (!empty($suggestion['phone'])): ?>
                                <?php echo htmlspecialchars($suggestion['phone']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer-section">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-1"><strong><?php echo htmlspecialchars($libraryInfo['name']); ?></strong></p>
                <?php if (!empty($libraryInfo['address'])): ?>
                    <p class="mb-1"><?php echo htmlspecialchars($libraryInfo['address']); ?></p>
                <?php endif; ?>
                <?php if (!empty($libraryInfo['phone'])): ?>
                    <p class="mb-0">هاتف: <?php echo htmlspecialchars($libraryInfo['phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-0">تم إنشاء هذا التقرير بواسطة نظام إدارة المكتبة</p>
                <p class="mb-0">تاريخ الطباعة: <?php echo date('Y/m/d H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.addEventListener('load', function() {
        //     setTimeout(() => window.print(), 500);
        // });
    </script>
</body>
</html>
