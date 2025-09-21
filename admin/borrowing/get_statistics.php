<?php
require_once '../../config/init.php';

// التحقق من صلاحية الموظف
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    die(json_encode(['total_borrows' => 0, 'overdue_borrows' => 0, 'today_returns' => 0]));
}

try {
    $today = date('Y-m-d');
    
    // إجمالي الإعارات الحالية (غير المُرجعة)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_borrows
        FROM borrow_transaction bt
        LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
        WHERE rb.return_date IS NULL
    ");
    $stmt->execute();
    $total_borrows = $stmt->get_result()->fetch_assoc()['total_borrows'];
    
    // الإعارات المتأخرة
    $stmt = $conn->prepare("
        SELECT COUNT(*) as overdue_borrows
        FROM borrow_transaction bt
        LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
        WHERE rb.return_date IS NULL AND bt.boro_exp_ret_date < ?
    ");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $overdue_borrows = $stmt->get_result()->fetch_assoc()['overdue_borrows'];
    
    // الإرجاع اليوم
    $stmt = $conn->prepare("
        SELECT COUNT(*) as today_returns
        FROM return_book
        WHERE DATE(return_date) = ?
    ");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $today_returns = $stmt->get_result()->fetch_assoc()['today_returns'];
    
    $statistics = [
        'total_borrows' => $total_borrows,
        'overdue_borrows' => $overdue_borrows,
        'today_returns' => $today_returns
    ];
    
    echo json_encode($statistics);
    
} catch (Exception $e) {
    error_log("Error in get_statistics.php: " . $e->getMessage());
    echo json_encode(['total_borrows' => 0, 'overdue_borrows' => 0, 'today_returns' => 0]);
}
?>
