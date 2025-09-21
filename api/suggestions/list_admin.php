<?php
/**
 * API لعرض جميع اقتراحات الكتب للموظفين
 * Admin Book Suggestions List API
 */

require_once '../../config/init.php';

header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

try {
    // التحقق من وجود جدول book_suggestions
    $check = $conn->query("SHOW TABLES LIKE 'book_suggestions'");
    if ($check->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'جدول الاقتراحات غير موجود. يرجى تنفيذ ملف db_create_suggestions.sql أولاً.'
        ]);
        exit;
    }

    // معاملات التصفية
    $status = $_GET['status'] ?? '';
    $member = $_GET['member'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 50), 100); // حد أقصى 100
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    
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
    
    // استعلام العد الإجمالي
    $count_sql = "
        SELECT COUNT(*) as total
        FROM book_suggestions bs
        LEFT JOIN customer c ON bs.mem_no = c.mem_no
        $where_clause
    ";
    
    if (!empty($params)) {
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_result = $count_stmt->get_result();
        $total_count = $total_result->fetch_assoc()['total'];
    } else {
        $total_result = $conn->query($count_sql);
        $total_count = $total_result->fetch_assoc()['total'];
    }
    
    // استعلام البيانات الرئيسي
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
            ) AS phone
        FROM book_suggestions bs
        LEFT JOIN customer c ON bs.mem_no = c.mem_no
        $where_clause
        ORDER BY bs.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    // إضافة معاملات LIMIT و OFFSET
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'id' => (int)$row['id'],
            'mem_no' => $row['mem_no'],
            'mem_name' => $row['mem_name'],
            'phone' => $row['phone'],
            'title' => $row['title'],
            'author' => $row['author'],
            'notes' => $row['notes'],
            'status' => $row['status'],
            'staff_notes' => $row['staff_notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    // إحصائيات سريعة
    $stats_sql = "
        SELECT 
            status,
            COUNT(*) as count
        FROM book_suggestions
        GROUP BY status
    ";
    $stats_result = $conn->query($stats_sql);
    $stats = [];
    while ($row = $stats_result->fetch_assoc()) {
        $stats[$row['status']] = (int)$row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'pagination' => [
            'total' => (int)$total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_count
        ],
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Admin suggestions list error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء تحميل الاقتراحات: ' . $e->getMessage()
    ]);
}
?>
