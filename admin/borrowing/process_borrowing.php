<?php
require_once '../../config/init.php';

// Endpoint خلفي موحّد للإعارة والإرجاع
// تم نقل المنطق من borrowing_dashboard.php وحذف واجهة العرض نهائياً

// التحقق من صلاحية الموظف
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    die('صلاحيات غير كافية.');
}

$user_no = $_SESSION['user_no'];

// معالجة طلب POST للإرجاع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mem_no']) && !empty($_POST['return_books'])) {
    $staff_user_no = $_SESSION['user_no'];
    $mem_no = $_POST['mem_no'] ?? '';
    $return_date = isset($_POST['return_date']) && $_POST['return_date'] !== '' ? $_POST['return_date'] : date('Y-m-d');
    $serials = $_POST['return_books'] ?? []; // مصفوفة الكتب المُراد إرجاعها
    // إزالة التكرارات وتنظيف الإدخال لضمان عدم تكرار نفس الكتاب في نفس العملية
    $serials = array_values(array_unique(array_filter(array_map('trim', $serials), function($s){ return $s !== ''; })));
    $fine_amount = floatval($_POST['fine_amount'] ?? 0);

    $errors = [];

    // التحقق من وجود العضو
    $stmt = $conn->prepare("SELECT c.mem_no, c.mem_name FROM customer c WHERE c.mem_no = ?");
    $stmt->bind_param('s', $mem_no);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    if (!$member) {
        $errors[] = 'العضو غير موجود.';
    }

    // التحقق من حالة كل كتاب
    $returnable_books = [];
    $seenBorrowIds = [];
    foreach ($serials as $serial) {
        $stmt = $conn->prepare("
            SELECT 
                bt.borrow_detail_id,
                bt.boro_no,
                bt.serialnum_book,
                bt.boro_exp_ret_date,
                b.book_title
            FROM borrow_transaction bt
            JOIN book b ON bt.serialnum_book = b.serialnum_book
            JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
            LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
            WHERE bt.serialnum_book = ? AND ct.mem_no = ? AND rb.return_date IS NULL
        ");
        $stmt->bind_param('ss', $serial, $mem_no);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();

        if (!$book) {
            $errors[] = "الكتاب بالرقم $serial غير معار لهذا المشترك أو تم إرجاعه مسبقاً.";
        } else {
            // منع إدراج نفس السجل مرتين في نفس العملية
            if (in_array($book['borrow_detail_id'], $seenBorrowIds, true)) {
                continue;
            }
            $returnable_books[] = $book; // قابل للإرجاع
            $seenBorrowIds[] = $book['borrow_detail_id'];
        }
    }

    // إذا فيه أخطاء أو كتب غير قابلة للإرجاع
    if (!empty($errors) || count($returnable_books) !== count($serials)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
    } else {
        $conn->begin_transaction();
        try {
            // return_transaction
            $stmt = $conn->prepare("INSERT INTO return_transaction (mem_no, user_no, transaction_ret_date) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception("خطأ في تحضير جملة SQL: " . $conn->error);
            $stmt->bind_param("iss", $mem_no, $staff_user_no, $return_date);
            $stmt->execute();
            $return_no = $conn->insert_id;
            if (!$return_no) throw new Exception("فشل الحصول على رقم معاملة الإرجاع");

            // return_book
            $stmt2 = $conn->prepare("INSERT INTO return_book (serialnum_book, return_no, boro_no, return_date, borrow_detail_id, total_fine) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt2) throw new Exception("خطأ في تحضير جملة SQL return_book: " . $conn->error);
            foreach ($returnable_books as $book) {
                $serial = $book['serialnum_book'];
                $book_fine = $fine_amount / count($returnable_books);
                $stmt2->bind_param("siisid", $serial, $return_no, $book['boro_no'], $return_date, $book['borrow_detail_id'], $book_fine);
                $stmt2->execute();

                // ترقية أول حجز pending إلى available عند إرجاع الكتاب
                try {
                    $hours = 48;
                    if (isset($settings)) {
                        $conf = $settings->getBorrowingSettings();
                        if (!empty($conf['reservation_expiry_hours'])) { $hours = (int)$conf['reservation_expiry_hours']; }
                    } else {
                        $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reservation_expiry_hours' LIMIT 1");
                        if ($row = $res->fetch_assoc()) { $hours = (int)$row['setting_value']; }
                    }
                    $sel = $conn->prepare("SELECT reservation_id FROM book_reservation WHERE serialnum_book = ? AND status = 'pending' ORDER BY reservation_date ASC LIMIT 1");
                    $sel->bind_param('s', $serial);
                    $sel->execute();
                    $resv = $sel->get_result()->fetch_assoc();
                    if ($resv && !empty($resv['reservation_id'])) {
                        $rid = (int)$resv['reservation_id'];
                        $upd = $conn->prepare("UPDATE book_reservation SET status = 'available', expiry_date = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE reservation_id = ?");
                        $upd->bind_param('ii', $hours, $rid);
                        $upd->execute();
                    }
                } catch (Exception $e) { /* تجاهل أخطاء الترقية */ }
            }

            if (isset($auditLogger)) { $auditLogger->log(null, 'إرجاع كتاب', 'return_transaction', $return_no, null, null); }
            $conn->commit();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => "تم تنفيذ الإرجاع بنجاح للعضو {$member['mem_name']} وعدد الكتب: ".count($returnable_books)]);
                exit;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'حدث خطأ أثناء تنفيذ العملية: '.$e->getMessage();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
        }
    }
}

// معالجة طلب POST للإعارة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mem_no']) && empty($_POST['return_books'])) {
    $staff_user_no = $_SESSION['user_no'];
    $mem_no = $_POST['mem_no'] ?? '';
    $borrow_date = isset($_POST['borrow_date']) && $_POST['borrow_date'] !== '' ? $_POST['borrow_date'] : date('Y-m-d');
    $expected_return_date = isset($_POST['expected_return_date']) && $_POST['expected_return_date'] !== '' ? $_POST['expected_return_date'] : date('Y-m-d', strtotime('+14 days'));
    $serials = $_POST['serialnum_book'] ?? [];

    $errors = [];

    // تحقق العضو واشتراكه
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT c.mem_no, c.mem_name FROM customer c JOIN member_subscription ms ON c.mem_no = ms.mem_no WHERE c.mem_no = ? AND ms.end_date > ? ORDER BY ms.end_date DESC LIMIT 1");
    $stmt->bind_param('ss', $mem_no, $today);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    if (!$member) { $errors[] = 'العضو غير موجود أو الاشتراك غير ساري.'; }

    // حد الكتب
    $max_books = 5;
    $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_books_per_member'");
    if ($row = $res->fetch_assoc()) { $max_books = (int)$row['setting_value']; }

    // عدد الكتب المعارة حالياً
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM borrow_transaction bt
        LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
        LEFT JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
        WHERE ct.mem_no = ? AND rb.return_date IS NULL
    ");
    $stmt->bind_param('s', $mem_no);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    if ($cnt + count($serials) > $max_books) { $errors[] = "لا يمكن إعارة أكثر من $max_books كتب لهذا المشترك."; }

    // تحقق حالة الكتب والحجوزات
    $available_books = [];
    $reservations_to_complete = [];
    foreach ($serials as $serial) {
        $stmt = $conn->prepare("
            SELECT 
                b.serialnum_book,
                b.book_title,
                bt.boro_exp_ret_date,
                rb.return_date
            FROM book b
            LEFT JOIN borrow_transaction bt ON b.serialnum_book = bt.serialnum_book
            LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
            WHERE b.serialnum_book = ?
            ORDER BY bt.boro_date DESC
            LIMIT 1
        ");
        $stmt->bind_param('s', $serial);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        if (!$book) {
            $errors[] = "الكتاب بالرقم $serial غير موجود.";
        } else {
            $isBorrowed = !empty($book['boro_exp_ret_date']) && empty($book['return_date']);
            $isOverdue = $isBorrowed && ($book['boro_exp_ret_date'] < date('Y-m-d'));
            if ($isBorrowed) {
                $errors[] = $isOverdue ? "الكتاب '{$book['book_title']}' معار ومتأخر عن موعد الإرجاع." : "الكتاب '{$book['book_title']}' معار حالياً.";
            } else {
                // حجز متاح
                $resStmt = $conn->prepare("SELECT br.reservation_id, br.mem_no, c.mem_name FROM book_reservation br JOIN customer c ON c.mem_no = br.mem_no WHERE br.serialnum_book = ? AND br.status = 'available' ORDER BY br.reservation_date ASC LIMIT 1");
                $resStmt->bind_param('s', $serial);
                $resStmt->execute();
                $activeRes = $resStmt->get_result()->fetch_assoc();
                if ($activeRes && !empty($activeRes['reservation_id'])) {
                    if ((string)$activeRes['mem_no'] !== (string)$mem_no) {
                        $errors[] = "الكتاب '{$book['book_title']}' محجوز حالياً للمشترك: {$activeRes['mem_name']}.";
                    } else {
                        $available_books[] = $book; $reservations_to_complete[$serial] = (int)$activeRes['reservation_id'];
                    }
                } else {
                    $available_books[] = $book; // متوفر
                }
            }
        }
    }

    if (!empty($errors) || count($available_books) !== count($serials)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
    } else {
        $conn->begin_transaction();
        try {
            // customer_transaction
            $stmt = $conn->prepare("INSERT INTO customer_transaction (mem_no, user_no, transaction_date) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception("خطأ في تحضير جملة SQL: " . $conn->error);
            $stmt->bind_param("iis", $mem_no, $staff_user_no, $borrow_date);
            $stmt->execute();
            $boro_no = $conn->insert_id;
            if (!$boro_no) throw new Exception("فشل الحصول على رقم المعاملة");

            // borrow_transaction
            $stmt2 = $conn->prepare("INSERT INTO borrow_transaction (boro_no, serialnum_book, boro_date, boro_exp_ret_date) VALUES (?, ?, ?, ?)");
            if (!$stmt2) throw new Exception("خطأ في تحضير جملة SQL borrow_transaction: " . $conn->error);
            foreach ($available_books as $book) {
                $serial = $book['serialnum_book'];
                $stmt2->bind_param("isss", $boro_no, $serial, $borrow_date, $expected_return_date);
                $stmt2->execute();
            }

            // إكمال الحجز إن وُجد
            if (!empty($reservations_to_complete)) {
                foreach ($available_books as $book) {
                    $serial = $book['serialnum_book'];
                    if (isset($reservations_to_complete[$serial])) {
                        $rid = $reservations_to_complete[$serial];
                        $upd = $conn->prepare("UPDATE book_reservation SET status = 'borrowed' WHERE reservation_id = ?");
                        $upd->bind_param('i', $rid);
                        $upd->execute();
                        if (isset($auditLogger)) { $auditLogger->logUpdate($staff_user_no, 'book_reservation', $rid, ['status' => 'available'], ['status' => 'borrowed']); }
                    }
                }
            }

            if (isset($auditLogger)) { $auditLogger->log(null, 'إعارة كتاب', 'customer_transaction', $boro_no, null, null); }
            $conn->commit();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => "تم تنفيذ الإعارة بنجاح للعضو {$member['mem_name']} وعدد الكتب: ".count($available_books)]);
                exit;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'حدث خطأ أثناء تنفيذ العملية: '.$e->getMessage();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
        }
    }
}

// الوصول بدون POST => 405
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Borrow/Return endpoint. Use POST.']);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(405);
        echo 'Borrow/Return endpoint. Use POST.';
    }
}
