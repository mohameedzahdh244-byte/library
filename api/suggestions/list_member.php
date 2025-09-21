<?php
/**
 * API لعرض اقتراحات العضو
 * Member Book Suggestions List API
 */

require_once '../../config/init.php';

header('Content-Type: application/json; charset=utf-8');

// التأكد من أن الطلب GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_no']) || $_SESSION['user_type'] !== 'member') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

$mem_no = $_SESSION['user_no'];

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

    // استعلام لجلب اقتراحات العضو (آخر 20 اقتراح)
    $stmt = $conn->prepare("
        SELECT id, title, author, notes, status, staff_notes, created_at, updated_at
        FROM book_suggestions 
        WHERE mem_no = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->bind_param("s", $mem_no);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'notes' => $row['notes'],
            'status' => $row['status'],
            'staff_notes' => $row['staff_notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'count' => count($suggestions)
    ]);
    
} catch (Exception $e) {
    error_log("Member suggestions list error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء تحميل الاقتراحات'
    ]);
}
?>
