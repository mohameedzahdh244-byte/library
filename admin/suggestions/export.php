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
        bs.updated_at,
        c.mem_name,
        (
            SELECT mp.mem_phone 
            FROM mem_phone mp 
            WHERE mp.mem_no = bs.mem_no 
            ORDER BY mp.id_phone ASC 
            LIMIT 1
        ) AS phone,
        '' AS email
    FROM book_suggestions bs
    LEFT JOIN customer c ON bs.mem_no = c.mem_no
    $where_clause
    ORDER BY bs.created_at DESC
    LIMIT 5000
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$suggestions = $result->fetch_all(MYSQLI_ASSOC);

function getStatusText($status) {
    $statusMap = [
        'new' => 'جديد',
        'reviewed' => 'تمت المراجعة',
        'purchased' => 'تم الشراء',
        'rejected' => 'مرفوض'
    ];
    return $statusMap[$status] ?? $status;
}

// إعداد headers للتصدير
$filename = 'book_suggestions_' . date('Y-m-d_H-i-s') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// إضافة BOM لUTF-8 لضمان عرض عربي صحيح
echo chr(0xEF).chr(0xBB).chr(0xBF);

// إخراج جدول HTML متوافق مع Excel
echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">';
echo '<style>table{border-collapse:collapse;font-family:\'Cairo\',Arial,Helvetica,sans-serif} th,td{border:1px solid #999;padding:6px;} th{background:#f2f2f2;font-weight:700} .num{text-align:left;direction:ltr}</style>';
echo '</head><body>';
echo '<table>'; 
echo '<thead><tr>';
echo '<th>الرقم</th>';
echo '<th>رقم العضو</th>';
echo '<th>اسم العضو</th>';
echo '<th>الهاتف</th>';
echo '<th>عنوان الكتاب</th>';
echo '<th>المؤلف</th>';
echo '<th>ملاحظات العضو</th>';
echo '<th>الحالة</th>';
echo '<th>ملاحظات الموظف</th>';
echo '<th>تاريخ الاقتراح</th>';
echo '<th>تاريخ التحديث</th>';
echo '</tr></thead><tbody>';

foreach ($suggestions as $index => $s) {
    $row = [
        $index + 1,
        $s['mem_no'],
        $s['mem_name'] ?? 'غير محدد',
        $s['phone'] ?? '',
        $s['title'],
        $s['author'],
        $s['notes'] ?? '',
        getStatusText($s['status']),
        $s['staff_notes'] ?? '',
        date('Y/m/d H:i', strtotime($s['created_at'])),
        !empty($s['updated_at']) ? date('Y/m/d H:i', strtotime($s['updated_at'])) : ''
    ];
    echo '<tr>';
    foreach ($row as $cell) {
        $val = htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8');
        echo '<td>' . $val . '</td>';
    }
    echo '</tr>';
}

echo '</tbody></table>';
echo '</body></html>';

// تسجيل العملية في سجل التدقيق
if (isset($auditLogger)) {
    $auditLogger->log(null, 'export_report', 'report', null, null, [
        'report_name'  => 'book_suggestions',
        'export_type'  => 'xls_html',
        'record_count' => count($suggestions),
        'filters' => [
            'status'    => $status,
            'search'    => $search,
            'date_from' => $date_from,
            'date_to'   => $date_to
        ]
    ]);
}

exit;
?>
