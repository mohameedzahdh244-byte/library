<?php
/**
 * API لإنشاء اقتراح كتاب جديد
 * Create Book Suggestion API
 */

require_once '../../config/init.php';

header('Content-Type: application/json; charset=utf-8');

// التأكد من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// التحقق من البيانات المرسلة
$title = trim($_POST['title'] ?? '');
$author = trim($_POST['author'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// التحقق من صحة البيانات
$errors = [];

if (empty($title)) {
    $errors[] = 'عنوان الكتاب مطلوب';
} elseif (strlen($title) < 2) {
    $errors[] = 'عنوان الكتاب يجب أن يكون أكثر من حرفين';
} elseif (strlen($title) > 255) {
    $errors[] = 'عنوان الكتاب طويل جداً';
}

if (empty($author)) {
    $errors[] = 'اسم المؤلف مطلوب';
} elseif (strlen($author) < 2) {
    $errors[] = 'اسم المؤلف يجب أن يكون أكثر من حرفين';
} elseif (strlen($author) > 255) {
    $errors[] = 'اسم المؤلف طويل جداً';
}

if (!empty($notes) && strlen($notes) > 1000) {
    $errors[] = 'الملاحظات طويلة جداً';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
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

    // التحقق من حد المعدل (5 اقتراحات كحد أقصى في الساعة)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM book_suggestions 
        WHERE mem_no = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bind_param("s", $mem_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $rateCheck = $result->fetch_assoc();
    
    if ($rateCheck['count'] >= 5) {
        echo json_encode([
            'success' => false, 
            'message' => 'لقد تجاوزت الحد المسموح من الاقتراحات (5 اقتراحات في الساعة). يرجى المحاولة لاحقاً.'
        ]);
        exit;
    }
    
    // التحقق من التكرار (نفس العنوان والمؤلف خلال 24 ساعة)
    $stmt = $conn->prepare("
        SELECT id 
        FROM book_suggestions 
        WHERE mem_no = ? AND LOWER(title) = LOWER(?) AND LOWER(author) = LOWER(?) 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bind_param("sss", $mem_no, $title, $author);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'لقد اقترحت هذا الكتاب مؤخراً. يرجى الانتظار 24 ساعة قبل اقتراحه مرة أخرى.'
        ]);
        exit;
    }
    
    // إدراج الاقتراح الجديد
    $stmt = $conn->prepare("
        INSERT INTO book_suggestions (mem_no, title, author, notes, status, created_at) 
        VALUES (?, ?, ?, ?, 'new', NOW())
    ");
    $stmt->bind_param("ssss", $mem_no, $title, $author, $notes);
    
    if ($stmt->execute()) {
        $suggestion_id = $conn->insert_id;
        
        // تسجيل العملية في سجل التدقيق
        if (isset($auditLogger)) {
            $auditLogger->logCreate(null, 'book_suggestions', $suggestion_id, [
                'mem_no' => $mem_no,
                'title' => $title,
                'author' => $author,
                'notes' => $notes,
                'status' => 'new'
            ]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم إرسال اقتراحك بنجاح!',
            'id' => $suggestion_id
        ]);
    } else {
        throw new Exception('فشل في حفظ الاقتراح');
    }
    
} catch (Exception $e) {
    error_log("Book suggestion creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء حفظ الاقتراح. يرجى المحاولة مرة أخرى.'
    ]);
}
?>
