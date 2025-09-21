<?php
require_once '../../config/init.php';

// التحقق من صلاحية الموظف
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'صلاحيات غير كافية']));
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']));
}

$query = trim($_POST['query'] ?? '');

if (empty($query) || strlen($query) < 2) {
    die(json_encode(['success' => false, 'message' => 'يجب أن يكون البحث أكثر من حرفين']));
}

try {
    // البحث مع تحديد أعلام الحالة ثم تحويلها لحالة مركبة
    $stmt = $conn->prepare("
        SELECT 
            b.serialnum_book,
            b.book_title,
            b.classification_num,
            bauth.authors AS author,
            -- أعلام الحالة
            CASE WHEN EXISTS (
                SELECT 1
                FROM borrow_transaction bt
                LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                WHERE bt.serialnum_book = b.serialnum_book
                  AND rb.return_date IS NULL
                  AND bt.boro_exp_ret_date < NOW()
            ) THEN 1 ELSE 0 END AS is_overdue,
            CASE WHEN EXISTS (
                SELECT 1
                FROM borrow_transaction bt
                LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                WHERE bt.serialnum_book = b.serialnum_book
                  AND rb.return_date IS NULL
            ) THEN 1 ELSE 0 END AS is_borrowed,
            CASE WHEN EXISTS (
                SELECT 1
                FROM book_reservation r
                WHERE r.serialnum_book = b.serialnum_book
                  AND r.status IN ('pending','available')
            ) THEN 1 ELSE 0 END AS has_reservation,
            (
                SELECT bt3.boro_exp_ret_date
                FROM borrow_transaction bt3
                LEFT JOIN return_book rb3 ON bt3.borrow_detail_id = rb3.borrow_detail_id
                WHERE bt3.serialnum_book = b.serialnum_book
                  AND rb3.return_date IS NULL
                ORDER BY bt3.boro_exp_ret_date DESC
                LIMIT 1
            ) AS boro_exp_ret_date
        FROM book b
        LEFT JOIN (
            SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
            FROM book_authors ba
            JOIN authors au ON au.ANO = ba.ANO
            GROUP BY ba.serialnum_book
        ) bauth ON bauth.serialnum_book = b.serialnum_book
        WHERE 
            b.serialnum_book LIKE CONCAT('%', ?, '%') OR
            b.book_title LIKE CONCAT('%', ?, '%') OR
            b.classification_num LIKE CONCAT('%', ?, '%') OR
            bauth.authors LIKE CONCAT('%', ?, '%')
        ORDER BY 
            CASE 
                WHEN b.serialnum_book = ? THEN 1
                WHEN b.book_title LIKE CONCAT(?, '%') THEN 2
                WHEN b.book_title LIKE CONCAT('%', ?, '%') THEN 3
                ELSE 4
            END,
            b.book_title
        LIMIT 20
    ");
    
    // ربط المعاملات (4 للبحث بما فيها المؤلف + 3 للترتيب)
    $stmt->bind_param('sssssss', $query, $query, $query, $query, $query, $query, $query);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $books = [];
    
    while ($row = $result->fetch_assoc()) {
        $status = 'متوفر';
        $is_overdue = !empty($row['is_overdue']);
        $is_borrowed = !empty($row['is_borrowed']);
        $has_res = !empty($row['has_reservation']);

        if ($is_overdue) {
            $status = 'متأخر';
        } elseif ($is_borrowed) {
            // عند الإعارة لا نظهر محجوز-معار، بل 'معار' فقط
            $status = 'معار';
        } elseif ($has_res) {
            // فقط هذه الحالة المركبة مطلوبة
            $status = 'محجوز - متوفر';
        } else {
            $status = 'متوفر';
        }

        $books[] = [
            'serialnum_book' => $row['serialnum_book'],
            'book_title' => $row['book_title'],
            'classification_num' => $row['classification_num'],
            'author' => $row['author'] ?? '',
            'availability_status' => $status,
            'boro_exp_ret_date' => $row['boro_exp_ret_date'] ?? null,
        ];
    }
    
    echo json_encode([
        'success' => true,
        'books' => $books,
        'count' => count($books)
    ]);
    
} catch (Exception $e) {
    error_log("Error in search_api_simple.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
    ]);
}
?>
