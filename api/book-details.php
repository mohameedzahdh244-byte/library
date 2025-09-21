<?php
require_once '../config/init.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$serial = isset($_GET['serial']) ? trim($_GET['serial']) : '';

if (empty($serial)) {
    echo json_encode(['success' => false, 'message' => 'رقم الكتاب مطلوب']);
    exit;
}

try {
    // جلب بيانات الكتاب الأساسية
    $stmt = $conn->prepare("
        SELECT b.*, p.pub_name as publisher_name
        FROM book b
        LEFT JOIN publisher p ON b.pub_no = p.pub_no
        WHERE b.serialnum_book = ?
    ");
    
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();
    
    if (!$book) {
        echo json_encode(['success' => false, 'message' => 'الكتاب غير موجود']);
        exit;
    }
    
    // جلب أسماء المؤلفين
    $authors_stmt = $conn->prepare("
        SELECT a.Aname 
        FROM book_authors ba 
        JOIN authors a ON ba.ANO = a.ANO 
        WHERE ba.serialnum_book = ?
        ORDER BY a.Aname
    ");
    
    $authors_stmt->bind_param("s", $serial);
    $authors_stmt->execute();
    $authors_result = $authors_stmt->get_result();
    
    $authors = [];
    while ($author = $authors_result->fetch_assoc()) {
        $authors[] = $author['Aname'];
    }
    $authors_stmt->close();
    
    // تحضير مسار صورة الغلاف مع بديل placeholder إذا لم تكن موجودة فعلياً
    $coverImage = $book['cover_image'];
    $coverImagePath = $coverImage ? (dirname(__DIR__) . DIRECTORY_SEPARATOR . $coverImage) : '';
    if (empty($coverImage) || !file_exists($coverImagePath)) {
        // استخدام placeholder عام ضمن مجلد public
        $coverImage = 'public/placeholder.svg';
    }

    // تحضير البيانات للإرسال
    $response = [
        'success' => true,
        'book' => [
            'serialnum_book' => $book['serialnum_book'],
            'book_title' => $book['book_title'],
            'authors' => !empty($authors) ? implode(', ', $authors) : 'غير محدد',
            'publisher' => $book['publisher_name'] ?: 'غير محدد',
            'year' => $book['year'],
            'classification_num' => $book['classification_num'],
            'ISBN' => $book['ISBN'],
            'num_pages' => $book['num_pages'],
            'book_language' => $book['book_language'],
            'book_status' => $book['book_status'],
            'cover_image' => $coverImage,
            'summary' => $book['summary'],
            'edition' => $book['edition'],
            'dimension' => $book['dimension'],
            'notes' => $book['notes'],
            'department' => $book['department'],
            'book_type' => $book['book_type']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ في الخادم: ' . $e->getMessage()
    ]);
}
?>
