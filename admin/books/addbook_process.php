<?php
require_once __DIR__ . '/../../config/init.php';
include_once __DIR__ . '/../../config/DB.php';


$message = "";
$message_type = ""; // "success" أو "warning" أو "danger"
$user_no = $_SESSION['user_no'];  


if ($_SERVER["REQUEST_METHOD"] === "POST") {
 
    $serialnum_book    = $_POST['serialnum_book'];
    $pub_no            = $_POST['pub_no'];
    $ANO               = isset($_POST['ANO']) ? $_POST['ANO'] : '';
    // مؤلفون متعددون من الواجهة الجديدة
    $authors_multi     = isset($_POST['authors']) && is_array($_POST['authors']) ? array_values(array_unique(array_filter($_POST['authors'], function($v){ return $v !== '' && $v !== null; }))) : [];
    $sup_no            = isset($_POST['sup_no']) ? $_POST['sup_no'] : '';

    $classification_num = $_POST['classification_num'];
    $book_title        = $_POST['book_title'];
    $book_language = $_POST['book_language'] ?? '';
    $year              = $_POST['year']??'';
    $dimension         = $_POST['dimension'];
    $stage             = $_POST['stage'];
    $ISBN              = $_POST['ISBN'];
    $deposit_num       = $_POST['deposit_num'];
    $notes             = $_POST['notes'];
    $edition           = $_POST['edition'];
    $book_type         = $_POST['book_type'];
    $book_status       = $_POST['book_status'];
    $department        = $_POST['department'];
    $num_pages         = isset($_POST['num_pages']) && $_POST['num_pages'] !== '' ? $_POST['num_pages'] : null;
    $summary           = isset($_POST['summary']) ? trim($_POST['summary']) : null;
    
    
   // الحفاظ على التوافق: إن لم يُرسل ANO لكن أُرسلت قائمة مؤلفين، اجعل أول عنصر هو ANO
   if ($ANO === '' && count($authors_multi) > 0) {
       $ANO = $authors_multi[0];
   }

   // تحقّق: إذا أُرسلت قيمة غير رقمية لعدد الصفحات، أعرض رسالة خطأ (يُسمح بتركها فارغة)
   if ($num_pages !== null && !is_numeric($num_pages)) {
       $message = "⚠️ عدد الصفحات يجب أن يكون رقماً فقط.";
       $message_type = "warning";
   }

   // معالجة رفع صورة الغلاف
   $cover_image_path = null;
   if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
       $upload_dir = '../../public/uploads/books/';
       $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
       $max_size = 5 * 1024 * 1024; // 5MB
       
       $file_tmp = $_FILES['cover_image']['tmp_name'];
       $file_size = $_FILES['cover_image']['size'];
       $file_type = $_FILES['cover_image']['type'];
       $file_name = $_FILES['cover_image']['name'];
       
       // التحقق من نوع الملف
       if (!in_array($file_type, $allowed_types)) {
           $message = "⚠️ نوع الملف غير مدعوم. يُسمح فقط بـ JPG, PNG, GIF, WebP";
           $message_type = "warning";
       }
       // التحقق من حجم الملف
       elseif ($file_size > $max_size) {
           $message = "⚠️ حجم الملف كبير جداً. الحد الأقصى 5MB";
           $message_type = "warning";
       }
       else {
           // إنشاء اسم ملف فريد
           $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
           $unique_name = $serialnum_book . '_' . time() . '.' . $file_extension;
           $target_path = $upload_dir . $unique_name;
           
           // إنشاء المجلد إن لم يكن موجوداً
           if (!is_dir($upload_dir)) {
               mkdir($upload_dir, 0755, true);
           }
           
           // رفع الملف
           if (move_uploaded_file($file_tmp, $target_path)) {
               $cover_image_path = 'public/uploads/books/' . $unique_name;
           } else {
               $message = "⚠️ فشل في رفع الصورة. حاول مرة أخرى.";
               $message_type = "warning";
           }
       }
   }
   // إن لم تُرفع كملف، ادعم الاستلام عبر base64 من الحقل المخفي cover_image_data
   if ($cover_image_path === null && isset($_POST['cover_image_data'])) {
       $dataUrl = trim((string)$_POST['cover_image_data']);
       if ($dataUrl !== '' && preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/', $dataUrl, $m)) {
           $ext = strtolower($m[1]);
           if ($ext === 'jpeg') { $ext = 'jpg'; }
           $bin = base64_decode($m[2]);
           if ($bin !== false) {
               $upload_dir_fs = realpath(__DIR__ . '/../../public/uploads/books');
               if ($upload_dir_fs === false) { $upload_dir_fs = __DIR__ . '/../../public/uploads/books'; }
               if (!is_dir($upload_dir_fs)) { @mkdir($upload_dir_fs, 0755, true); }
               $unique_name = $serialnum_book . '_' . time() . '.' . $ext;
               $full = rtrim($upload_dir_fs, '/\\') . '/' . $unique_name;
               if (@file_put_contents($full, $bin) !== false) {
                   $cover_image_path = 'public/uploads/books/' . $unique_name;
               } else {
                   $message = "⚠️ تعذّر حفظ صورة الغلاف الملتقطة.";
                   $message_type = "warning";
               }
           }
       }
   }

   $sql = "INSERT INTO book (
    user_no, serialnum_book, pub_no, sup_no, classification_num, book_title, 
    book_language, year, dimension, stage, ISBN, deposit_num,
    notes, edition, book_type, book_status, department, num_pages, cover_image, summary
) VALUES (
    '$user_no', '$serialnum_book', '$pub_no', NULLIF('$sup_no',''), '$classification_num', '$book_title',
    '$book_language', '$year', '$dimension', '$stage', '$ISBN', '$deposit_num',
    '$notes', '$edition', '$book_type', '$book_status', '$department', " . ($num_pages !== null ? "'$num_pages'" : "NULL") . ", " . ($cover_image_path ? "'$cover_image_path'" : "NULL") . ", " . ($summary ? "'$summary'" : "NULL") . "
)";

   
if ($message_type !== "") {
    // وُجد خطأ مسبق (مثل num_pages غير رقمية)
} elseif ($serialnum_book === '' || $book_title === '' || $classification_num === '') {
    $message = "⚠️ الرجاء تعبئة جميع الحقول المطلوبة أولاً.";
    $message_type = "warning";
} elseif ($pub_no === '') {
    // تحقق من اختيار الناشر
    $message = "⚠️ الرجاء اختيار اسم الناشر.";
    $message_type = "warning"; 
} elseif ($ANO === '' && count($authors_multi) === 0) {
    // قبول إما مؤلف واحد قديم أو قائمة متعددة جديدة
    $message = "⚠️ الرجاء اختيار اسم المؤلف (واحد على الأقل).";
    $message_type = "warning";
} else {
  

    try {
        $conn->query($sql);
        // إنشاء جدول ربط عند الحاجة وربط جميع المؤلفين
        // ملاحظة: نبقي ANO كأول مؤلف للتوافق، لكن نُسجل كل المؤلفين في book_authors
        $createLink = "CREATE TABLE IF NOT EXISTS book_authors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            serialnum_book VARCHAR(191) NOT NULL,
            ANO VARCHAR(191) NOT NULL,
            UNIQUE KEY uniq_book_author (serialnum_book, ANO)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($createLink);

        // تحضير قائمة المؤلفين: دمج ANO الأساسي مع القائمة المتعددة
        $allAuthors = $authors_multi;
        if ($ANO !== '') {
            if (!in_array($ANO, $allAuthors, true)) { $allAuthors[] = $ANO; }
        }
        if (count($allAuthors) > 0) {
            $stmtBA = $conn->prepare("INSERT IGNORE INTO book_authors (serialnum_book, ANO) VALUES (?, ?)");
            if (!$stmtBA) {
                // في حال خطأ التحضير، نتجاهل الربط ولا نفشل حفظ الكتاب
            } else {
                foreach ($allAuthors as $aid) {
                    $stmtBA->bind_param('ss', $serialnum_book, $aid);
                    $stmtBA->execute();
                }
                $stmtBA->close();
            }
        }

        $message = "✔️ تم إضافة الكتاب بنجاح.";
        $message_type = "success";
        // Audit log (مركزي)
        if (isset($auditLogger)) {
            $auditLogger->logCreate(null, 'book', $serialnum_book, [
                'serialnum_book' => $serialnum_book,
                'book_title' => $book_title,
            ]);
        }
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $message = "⚠️ رقم المتسلسل موجود مسبقًا، الرجاء استخدام رقم مختلف.";
            $message_type = "warning";
        } elseif (strpos($e->getMessage(), 'a foreign key constraint fails') !== false) {
            $message = "❌ اسم الناشر أو المؤلف غير موجود، أو رقم تصنيف خاطئ، الرجاء التحقق." ;
            $message_type = "danger";
        } else {
            $message = "❌ حدث خطأ غير متوقع: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

} 


header('Content-Type: application/json');
echo json_encode([
    'message' => $message,
    'type' => $message_type
]);
exit;
?>