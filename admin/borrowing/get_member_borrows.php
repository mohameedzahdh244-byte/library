<?php
require_once '../../config/init.php';

// التحقق من صلاحية الموظف
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    die(json_encode([]));
}

$mem_no = $_POST['mem_no'] ?? '';

if (empty($mem_no)) {
    die(json_encode([]));
}

try {
    // جلب الإعارات الحالية للمشترك (غير المُرجعة)
    $stmt = $conn->prepare("
        SELECT 
            bt.borrow_detail_id,
            bt.serialnum_book,
            bt.boro_date,
            bt.boro_exp_ret_date,
            b.book_title
        FROM borrow_transaction bt
        JOIN book b ON bt.serialnum_book = b.serialnum_book
        JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
        LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
        WHERE ct.mem_no = ? AND rb.return_date IS NULL
        ORDER BY bt.boro_date DESC
    ");
    
    $stmt->bind_param('s', $mem_no);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $borrows = [];
    while ($row = $result->fetch_assoc()) {
        $borrows[] = $row;
    }
    
    // إرجاع البيانات بالتنسيق المتوقع من الجافاسكربت
    echo json_encode([
        'success' => true,
        'borrows' => $borrows
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_member_borrows.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'borrows' => [],
        'error' => $e->getMessage()
    ]);
}
?>
