<?php
/**
 * API لتحديث حالة اقتراح الكتاب
 * Update Book Suggestion Status API
 */

require_once '../../config/init.php';

// التأكد من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// التحقق من البيانات المرسلة
$suggestion_id = (int)($_POST['id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');
$staff_notes = trim($_POST['staff_notes'] ?? '');

// التحقق من صحة البيانات
if ($suggestion_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف الاقتراح غير صحيح']);
    exit;
}

if (!in_array($new_status, ['new', 'reviewed', 'purchased', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'حالة غير صحيحة']);
    exit;
}

if (!empty($staff_notes) && strlen($staff_notes) > 1000) {
    echo json_encode(['success' => false, 'message' => 'ملاحظات الموظف طويلة جداً']);
    exit;
}

try {
    // الحصول على البيانات الحالية للاقتراح
    $stmt = $conn->prepare("SELECT * FROM book_suggestions WHERE id = ?");
    $stmt->bind_param("i", $suggestion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_data = $result->fetch_assoc();
    
    if (!$old_data) {
        echo json_encode(['success' => false, 'message' => 'الاقتراح غير موجود']);
        exit;
    }
    
    // تحديث الاقتراح
    $stmt = $conn->prepare("
        UPDATE book_suggestions 
        SET status = ?, staff_notes = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $new_status, $staff_notes, $suggestion_id);
    
    if ($stmt->execute()) {
        // تسجيل العملية في سجل التدقيق
        if (isset($auditLogger)) {
            $auditLogger->logUpdate(null, 'book_suggestions', $suggestion_id, 
                [
                    'status' => $old_data['status'],
                    'staff_notes' => $old_data['staff_notes']
                ],
                [
                    'status' => $new_status,
                    'staff_notes' => $staff_notes,
                    'updated_by' => $_SESSION['user_no']
                ]
            );
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم تحديث حالة الاقتراح بنجاح'
        ]);
    } else {
        throw new Exception('فشل في تحديث الاقتراح');
    }
    
} catch (Exception $e) {
    error_log("Book suggestion update error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء تحديث الاقتراح'
    ]);
}
?>
