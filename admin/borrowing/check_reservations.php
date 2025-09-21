<?php
require_once __DIR__ . '/../../config/init.php';
include_once __DIR__ . '/../../config/DB.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'errors' => ['طريقة الطلب غير مدعومة']]);
        exit;
    }

    $mem_no = $_POST['mem_no'] ?? '';
    $serials = $_POST['serialnum_book'] ?? [];
    if (is_string($serials)) {
        // دعم تمرير واحد كسلسلة
        $serials = [$serials];
    }

    if (empty($mem_no) || empty($serials)) {
        echo json_encode(['success' => false, 'errors' => ['بيانات غير مكتملة']]);
        exit;
    }

    $conflicts = [];
    foreach ($serials as $serial) {
        $serial = trim($serial);
        if ($serial === '') continue;

        // اجلب عنوان الكتاب
        $titleStmt = $conn->prepare("SELECT book_title FROM book WHERE serialnum_book = ? LIMIT 1");
        $titleStmt->bind_param('s', $serial);
        $titleStmt->execute();
        $titleRow = $titleStmt->get_result()->fetch_assoc();
        $book_title = $titleRow['book_title'] ?? $serial;

        // تحقّق من وجود حجز متاح لشخص آخر
        $resStmt = $conn->prepare("SELECT br.reservation_id, br.mem_no, c.mem_name
                                   FROM book_reservation br
                                   JOIN customer c ON c.mem_no = br.mem_no
                                   WHERE br.serialnum_book = ? AND br.status = 'available'
                                   ORDER BY br.reservation_date ASC
                                   LIMIT 1");
        $resStmt->bind_param('s', $serial);
        $resStmt->execute();
        $activeRes = $resStmt->get_result()->fetch_assoc();

        if ($activeRes && (string)$activeRes['mem_no'] !== (string)$mem_no) {
            $conflicts[] = [
                'serial' => $serial,
                'book_title' => $book_title,
                'mem_name' => $activeRes['mem_name']
            ];
        }
    }

    echo json_encode(['success' => true, 'conflicts' => $conflicts]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['خطأ بالخادم', $e->getMessage()]]);
}
