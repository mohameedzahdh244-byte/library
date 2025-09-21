<?php
// بحث تلقائي عن الكتب (برقم تسلسلي أو عنوان)

require_once __DIR__ . '/../../config/init.php';
include_once __DIR__ . '/../../config/DB.php';
$q = trim($_POST['q'] ?? '');
if(strlen($q) < 2) exit;
$stmt = $conn->prepare("SELECT serialnum_book, book_title FROM book WHERE (serialnum_book LIKE CONCAT('%', ?, '%') OR book_title LIKE CONCAT('%', ?, '%')) LIMIT 10");
$stmt->bind_param('ss', $q, $q);
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows == 0) {
    echo '<div class="alert alert-warning py-1 book-result">لا يوجد كتاب مطابق</div>';
    exit;
}
echo '<ul class="list-group book-result">';
$today = date('Y-m-d');
while($row = $res->fetch_assoc()) {
    $serial = $row['serialnum_book'];
    $status = 'success';
    $status_text = 'متوفر';
    $disabled = '';
    // جلب آخر عملية إعارة لهذا الكتاب
    $stmt2 = $conn->prepare("SELECT borrow_detail_id, boro_exp_ret_date FROM borrow_transaction WHERE serialnum_book = ? ORDER BY boro_date DESC, borrow_detail_id DESC LIMIT 1");
    if($stmt2 && $stmt2->bind_param('s', $serial) && $stmt2->execute()) {
        $borrow = $stmt2->get_result()->fetch_assoc();
        if($borrow && isset($borrow['borrow_detail_id'])) {
            // جلب آخر عملية إرجاع لهذا الكتاب
            $stmt3 = $conn->prepare("SELECT return_date FROM return_book WHERE borrow_detail_id = ? ORDER BY return_date DESC LIMIT 1");
            if($stmt3 && $stmt3->bind_param('i', $borrow['borrow_detail_id']) && $stmt3->execute()) {
                $ret = $stmt3->get_result()->fetch_assoc();
                if(!$ret || !$ret['return_date']) {
                    // لم يتم الإرجاع بعد
                    if($borrow['boro_exp_ret_date'] < $today) {
                        // متأخر => أحمر
                        $status = 'danger';
                        $status_text = 'متأخر';
                    } else {
                        // معار => أصفر
                        $status = 'warning';
                        $status_text = 'معار';
                    }
                    $disabled = 'disabled';
                }
            }
        }
    }

    $book_status = ($disabled === 'disabled') ? 'borrowed' : 'available';
    echo '<li class="list-group-item choose-book '.$disabled.'" style="cursor:pointer" data-serial="'.htmlspecialchars($row['serialnum_book']).'" data-title="'.htmlspecialchars($row['book_title']).'" data-status="'.$book_status.'">';
    echo '<b>'.htmlspecialchars($row['book_title']).'</b> <span class="text-muted">('.htmlspecialchars($row['serialnum_book']).')</span>';
    echo ' <span class="badge bg-'. $status .'">'. $status_text .'</span>';
    echo '</li>';
}
echo '</ul>';
