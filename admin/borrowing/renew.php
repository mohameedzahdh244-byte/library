<?php
require_once '../../config/init.php';

header('Content-Type: application/json; charset=utf-8');

// السماح فقط للموظفين والمديرين
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['صلاحيات غير كافية.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['الطريقة غير مدعومة. استخدم POST.']]);
    exit;
}

$errors = [];
$borrow_detail_id = isset($_POST['borrow_detail_id']) ? (int)$_POST['borrow_detail_id'] : 0;
$new_exp_date = $_POST['new_exp_date'] ?? '';

if ($borrow_detail_id <= 0) $errors[] = 'معرّف الإعارة غير صالح.';
if (empty($new_exp_date)) $errors[] = 'يرجى تحديد تاريخ جديد.';

// تحقق بدائي من صيغة التاريخ Y-m-d
if ($new_exp_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_exp_date)) {
    $errors[] = 'صيغة التاريخ غير صحيحة (YYYY-MM-DD).';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // جلب تفاصيل الإعارة والتأكد أنها غير مُرجعة
    $stmt = $conn->prepare("SELECT bt.borrow_detail_id, bt.serialnum_book, bt.boro_exp_ret_date, ct.mem_no
                             FROM borrow_transaction bt
                             JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
                             LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                             WHERE bt.borrow_detail_id = ? AND rb.return_date IS NULL");
    $stmt->bind_param('i', $borrow_detail_id);
    $stmt->execute();
    $borrow = $stmt->get_result()->fetch_assoc();

    if (!$borrow) {
        echo json_encode(['success' => false, 'errors' => ['لم يتم العثور على الإعارة أو تم إرجاعها مسبقًا.']]);
        exit;
    }

    $serial = $borrow['serialnum_book'];
    $mem_no = (string)$borrow['mem_no'];

    // منع التجديد إذا كانت هناك حجز فعّال لعضو آخر
    $resStmt = $conn->prepare("SELECT br.reservation_id, br.mem_no FROM book_reservation br
                               WHERE br.serialnum_book = ? AND br.status IN ('available','pending')
                               ORDER BY br.reservation_date ASC LIMIT 1");
    $resStmt->bind_param('s', $serial);
    $resStmt->execute();
    $activeRes = $resStmt->get_result()->fetch_assoc();

    if ($activeRes && !empty($activeRes['reservation_id'])) {
        if ((string)$activeRes['mem_no'] !== $mem_no) {
            echo json_encode(['success' => false, 'errors' => ['لا يمكن التجديد: يوجد حجز فعّال لعضو آخر على هذا الكتاب.']]);
            exit;
        }
    }

    // التحقق أن التاريخ الجديد ليس قبل اليوم أو قبل التاريخ الحالي للإرجاع المتوقع
    $today = date('Y-m-d');
    if ($new_exp_date < $today) {
        echo json_encode(['success' => false, 'errors' => ['التاريخ الجديد لا يمكن أن يكون في الماضي.']]);
        exit;
    }
    if (!empty($borrow['boro_exp_ret_date']) && $new_exp_date < $borrow['boro_exp_ret_date']) {
        echo json_encode(['success' => false, 'errors' => ['التاريخ الجديد يجب أن يكون بعد التاريخ المتوقع الحالي.']]);
        exit;
    }

    // تحديث تاريخ الإرجاع المتوقع
    $upd = $conn->prepare("UPDATE borrow_transaction SET boro_exp_ret_date = ? WHERE borrow_detail_id = ?");
    $upd->bind_param('si', $new_exp_date, $borrow_detail_id);
    $upd->execute();

    if (isset($auditLogger)) {
        // سجل تعديل الحقل
        $auditLogger->logUpdate($_SESSION['user_no'], 'borrow_transaction', $borrow_detail_id, ['boro_exp_ret_date' => $borrow['boro_exp_ret_date']], ['boro_exp_ret_date' => $new_exp_date]);
        // سجل عملية تجديد موحّدة لتظهر في "آخر العمليات" مع بيانات واضحة
        $auditLogger->logRenewal(
            $_SESSION['user_no'],          // سيتم تجاهله داخليًا واعتماد الممثل من الجلسة
            $borrow_detail_id,              // transaction_id (borrow_detail_id)
            $serial,                        // book_id (serialnum_book)
            $mem_no,                        // member_no
            $borrow['boro_exp_ret_date'] ?? null, // old_due_date
            $new_exp_date                   // new_due_date
        );
    }

    echo json_encode(['success' => true, 'message' => 'تم التجديد بنجاح', 'new_exp_date' => $new_exp_date]);
    exit;

} catch (Exception $e) {
    error_log('Renew error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['حدث خطأ داخلي أثناء التجديد.']]);
    exit;
}
