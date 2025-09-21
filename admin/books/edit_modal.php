<?php
require_once '../../config/init.php';

// السماح بعرض الصفحة في iframe
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// التحقق من صلاحيات الموظف/المدير
checkStaffPermission();

$serial = isset($_GET['serial']) ? trim($_GET['serial']) : '';
$serial = $serial === '' ? null : $serial;

// معالجة التحديث عبر AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=UTF-8');
  $action = $_POST['action'] ?? '';
  $serial_in = $_POST['serialnum_book'] ?? '';
  if ($action !== 'update' || $serial_in === '') {
    echo json_encode(['ok'=>false,'msg'=>'إجراء غير صحيح']);
    exit;
  }

  // تحقق من عدد الصفحات: يُسمح بالفراغ، وإن أُرسلت قيمة يجب أن تكون رقمية
  if (array_key_exists('num_pages', $_POST)) {
    $np = $_POST['num_pages'];
    if ($np !== '' && !is_numeric($np)) {
      echo json_encode(['ok'=>false,'msg'=>'عدد الصفحات يجب أن يكون رقماً فقط']);
      exit;
    }
  }

  // معالجة رفع صورة الغلاف الجديدة
  $cover_image_path = null;
  if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
      $upload_dir = '../../public/uploads/books/';
      $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      $max_size = 5 * 1024 * 1024; // 5MB
      
      $file_tmp = $_FILES['cover_image']['tmp_name'];
      $file_size = $_FILES['cover_image']['size'];
      $file_type = $_FILES['cover_image']['type'];
      $file_name = $_FILES['cover_image']['name'];
      
      if (!in_array($file_type, $allowed_types)) {
          echo json_encode(['ok'=>false,'msg'=>'نوع الملف غير مدعوم. يُسمح فقط بـ JPG, PNG, GIF, WebP']);
          exit;
      }
      if ($file_size > $max_size) {
          echo json_encode(['ok'=>false,'msg'=>'حجم الملف كبير جداً. الحد الأقصى 5MB']);
          exit;
      }
      
      $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
      $unique_name = $serial_in . '_' . time() . '.' . $file_extension;
      $target_path = $upload_dir . $unique_name;
      
      if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0755, true);
      }
      
      if (move_uploaded_file($file_tmp, $target_path)) {
          $cover_image_path = 'public/uploads/books/' . $unique_name;
          
          // حذف الصورة القديمة إن وجدت
          $old_img_stmt = $conn->prepare("SELECT cover_image FROM book WHERE serialnum_book = ?");
          $old_img_stmt->bind_param("s", $serial_in);
          $old_img_stmt->execute();
          $old_img_result = $old_img_stmt->get_result();
          if ($old_img_row = $old_img_result->fetch_assoc()) {
              $old_path = $old_img_row['cover_image'];
              if ($old_path && file_exists('../../' . $old_path)) {
                  unlink('../../' . $old_path);
              }
          }
          $old_img_stmt->close();
      } else {
          echo json_encode(['ok'=>false,'msg'=>'فشل في رفع الصورة']);
          exit;
      }
  }
  // دعم حفظ الصورة من base64 إذا لم يُرفع ملف
  if ($cover_image_path === null && isset($_POST['cover_image_data'])) {
    $dataUrl = trim((string)$_POST['cover_image_data']);
    if ($dataUrl !== '' && preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/', $dataUrl, $m)) {
      $ext = strtolower($m[1]); if ($ext === 'jpeg') { $ext = 'jpg'; }
      $bin = base64_decode($m[2]);
      if ($bin !== false) {
        $upload_dir_fs = realpath(__DIR__ . '/../../public/uploads/books');
        if ($upload_dir_fs === false) { $upload_dir_fs = __DIR__ . '/../../public/uploads/books'; }
        if (!is_dir($upload_dir_fs)) { @mkdir($upload_dir_fs, 0755, true); }
        $unique_name = $serial_in . '_' . time() . '.' . $ext;
        $full = rtrim($upload_dir_fs, '/\\') . '/' . $unique_name;
        if (@file_put_contents($full, $bin) !== false) {
          $cover_image_path = 'public/uploads/books/' . $unique_name;
          // حذف القديمة
          $old_img_stmt = $conn->prepare("SELECT cover_image FROM book WHERE serialnum_book = ?");
          if ($old_img_stmt) {
            $old_img_stmt->bind_param('s', $serial_in);
            $old_img_stmt->execute();
            $old_img_result = $old_img_stmt->get_result();
            if ($old_img_row = $old_img_result->fetch_assoc()) {
              $old_path = $old_img_row['cover_image'];
              if ($old_path && file_exists('../../' . $old_path)) { @unlink('../../' . $old_path); }
            }
            $old_img_stmt->close();
          }
        } else {
          echo json_encode(['ok'=>false,'msg'=>'تعذّر حفظ صورة الغلاف الملتقطة']);
          exit;
        }
      }
    }
  }
  // الحقول المسموح بتحديثها بشكل سريع (بدون ANO، لأنه يُدار عبر جدول book_authors)
  $fields = [
    'book_title' => 's',
    'classification_num' => 's',
    'pub_no' => 'i',
    'sup_no' => 'i',
    'book_language' => 's',
    'year' => 's',
    'edition' => 's',
    'dimension' => 's',
    'notes' => 's',
    'ISBN' => 's',
    'deposit_num' => 's',
    'num_pages' => 'i',
    'stage' => 's',
    'department' => 's',
    'book_type' => 's',
    'book_status' => 's',
    'summary' => 's',
  ];

  $set = [];$types='';$vals=[];
  foreach ($fields as $col=>$typ) {
    if (array_key_exists($col, $_POST)) {
      $val = $_POST[$col];
      // تحويل القيم الفارغة إلى NULL لبعض الحقول النصية الطويلة
      if ($val === '') {
        $set[] = "$col = NULL";
      } else {
        $set[] = "$col = ?";
        $types .= $typ;
        $vals[] = $val;
      }
    }
  }

  // إضافة صورة الغلاف إن تم رفعها
  if ($cover_image_path !== null) {
    $set[] = "cover_image = ?";
    $types .= 's';
    $vals[] = $cover_image_path;
  }

  if (empty($set)) {
    echo json_encode(['ok'=>false,'msg'=>'لم يتم إرسال أي حقول للتحديث']);
    exit;
  }

  $types .= 's';
  $vals[] = $serial_in;

  $sql = 'UPDATE book SET '.implode(', ', $set).' WHERE serialnum_book = ?';
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['ok'=>false,'msg'=>'فشل تحضير الاستعلام']);
    exit;
  }
  $stmt->bind_param($types, ...$vals);
  $ok = $stmt->execute();
  $stmt->close();

  // مزامنة المؤلفين المتعددين مثل منطق الإضافة
  $ANO = isset($_POST['ANO']) ? $_POST['ANO'] : '';
  $authors_multi = isset($_POST['authors']) && is_array($_POST['authors'])
      ? array_values(array_unique(array_filter($_POST['authors'], function($v){ return $v !== '' && $v !== null; })))
      : [];
  if ($ANO === '' && count($authors_multi) > 0) { $ANO = $authors_multi[0]; }
  // دمج بدون تكرار
  $allAuthors = $authors_multi;
  if ($ANO !== '' && !in_array($ANO, $allAuthors, true)) { $allAuthors[] = $ANO; }

  try {
    // أنشئ جدول الربط إن لزم
    $conn->query("CREATE TABLE IF NOT EXISTS book_authors (
      id INT AUTO_INCREMENT PRIMARY KEY,
      serialnum_book VARCHAR(191) NOT NULL,
      ANO VARCHAR(191) NOT NULL,
      UNIQUE KEY uniq_book_author (serialnum_book, ANO)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // احذف الروابط القديمة ثم أدخل الحالية
    $del = $conn->prepare('DELETE FROM book_authors WHERE serialnum_book = ?');
    if ($del) { $del->bind_param('s', $serial_in); $del->execute(); $del->close(); }
    if (count($allAuthors) > 0) {
      $ins = $conn->prepare('INSERT IGNORE INTO book_authors (serialnum_book, ANO) VALUES (?, ?)');
      if ($ins) {
        foreach ($allAuthors as $aid) { $ins->bind_param('ss', $serial_in, $aid); $ins->execute(); }
        $ins->close();
      }
    }
  } catch (Throwable $e) { /* تجاهل أخطاء المؤلفين حتى لا تعطل الحفظ */ }

  if ($ok && isset($auditLogger)) {
    $auditLogger->logUpdate($_SESSION['user_no'] ?? null, 'book', (string)$serial_in, null, $_POST);
  }

  echo json_encode(['ok'=>$ok]);
  exit;
}

// جلب بيانات الكتاب
$book = null;
if ($serial !== null) {
  $stmt = $conn->prepare('SELECT * FROM book WHERE serialnum_book = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $res = $stmt->get_result();
    $book = $res->fetch_assoc();
    $stmt->close();
  }
}

// جلب أسماء الناشر والمورد لعرضها في حقول البحث
$publisherName = '';
$supplierName = '';
if (!empty($book['pub_no'])) {
  $qp = $conn->prepare('SELECT pub_name FROM publisher WHERE pub_no = ? LIMIT 1');
  if ($qp) { $qp->bind_param('s', $book['pub_no']); $qp->execute(); $qp->bind_result($publisherName); $qp->fetch(); $qp->close(); }
}
if (!empty($book['sup_no'])) {
  $qs = $conn->prepare('SELECT sup_name FROM supplier WHERE sup_no = ? LIMIT 1');
  if ($qs) { $qs->bind_param('s', $book['sup_no']); $qs->execute(); $qs->bind_result($supplierName); $qs->fetch(); $qs->close(); }
}

if (!$book) {
  $book = [
    'serialnum_book' => $serial,
    'book_title' => '',
    'classification_num' => '',
    'pub_no' => '',
    'ANO' => '',
    'sup_no' => '',
    'book_language' => '',
    'year' => '',
    'edition' => '',
    'dimension' => '',
    'notes' => '',
    'ISBN' => '',
    'deposit_num' => '',
    'num_pages' => '',
    'stage' => '',
    'department' => '',
    'book_type' => '',
    'book_status' => '',
  ];
}

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تعديل بيانات الكتاب</title>
  <link rel="stylesheet" href="/assets/css/bootstrap.css">
  <link rel="stylesheet" href="/assets/css/addbook.css">
  <link rel="stylesheet" href="/assets/css/publisher.css">
  <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
</head>
<body>
  <?php if (!$isEmbed): ?>
  <div class="modal fade" id="editBookModal" data-bs-backdrop="false" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content rounded-4 shadow-lg">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">تعديل بيانات الكتاب</h5>
        </div>
        <div class="modal-body p-4">
  <?php else: ?>
  <div class="p-3">
  <?php endif; ?>

    <form id="editBookForm" method="POST" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="action" value="update">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">رقم المتسلسل</label>
          <input name="serialnum_book" type="text" class="form-control" value="<?= htmlspecialchars($book['serialnum_book'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">عنوان الكتاب</label>
          <input name="book_title" type="text" class="form-control" value="<?= htmlspecialchars($book['book_title'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">رمز التصنيف</label>
          <input name="classification_num" type="text" class="form-control" value="<?= htmlspecialchars($book['classification_num'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label d-flex justify-content-between align-items-center">
            <span>اسم الناشر</span>
            <button type="button" class="btn btn-sm btn-outline-primary openCreatePublisher">إضافة ناشر</button>
          </label>
          <div class="position-relative">
            <input type="text" class="form-control" id="publisher_search" placeholder="ابحث باسم الناشر" value="<?= htmlspecialchars($publisherName) ?>">
            <input type="hidden" name="pub_no" id="pub_no" value="<?= htmlspecialchars($book['pub_no'] ?? '') ?>">
            <div id="publisher_result" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>
            <div id="publisher_message" class="mt-1" style="display:none; max-width: fit-content;" role="status" aria-live="polite"></div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label d-flex justify-content-between align-items-center">
            <span>اسم المؤلف</span>
            <button type="button" class="btn btn-sm btn-outline-primary openCreateAuthor">إضافة مؤلف</button>
          </label>
          <div class="position-relative">
            <?php
              // جلب مؤلفي الكتاب الحاليين
              $currentAuthors = [];
              if (!empty($book['serialnum_book'])) {
                $q = $conn->prepare('SELECT ba.ANO, au.Aname FROM book_authors ba JOIN authors au ON au.ANO = ba.ANO WHERE ba.serialnum_book = ? ORDER BY au.Aname');
                if ($q) { $q->bind_param('s', $book['serialnum_book']); $q->execute(); $R = $q->get_result(); while ($row = $R->fetch_assoc()) { $currentAuthors[] = $row; } $q->close(); }
              }
              $anoInitial = isset($currentAuthors[0]['ANO']) ? $currentAuthors[0]['ANO'] : '';
            ?>
            <div id="author_tags" class="d-flex flex-wrap gap-2 mb-2">
              <?php foreach ($currentAuthors as $a): ?>
                <span class="badge bg-secondary d-inline-flex align-items-center p-2">
                  <span class="ms-1"><?= htmlspecialchars($a['Aname']) ?></span>
                  <button type="button" class="btn btn-sm btn-light ms-2 remove-author" aria-label="إزالة" data-id="<?= htmlspecialchars($a['ANO']) ?>">&times;</button>
                  <input type="hidden" name="authors[]" value="<?= htmlspecialchars($a['ANO']) ?>">
                </span>
              <?php endforeach; ?>
            </div>
            <input type="text" class="form-control" id="author_search" placeholder="ابحث باسم المؤلف">
            <input type="hidden" name="ANO" id="ANO" value="<?= htmlspecialchars($anoInitial) ?>">
            <div id="author_result" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>
            <div id="author_message" class="mt-1" style="display:none; max-width: fit-content;" role="status" aria-live="polite"></div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label d-flex justify-content-between align-items-center">
            <span>اسم المورد</span>
            <button type="button" class="btn btn-sm btn-outline-primary openCreateSupplier">إضافة مورد</button>
          </label>
          <div class="position-relative">
            <input type="text" class="form-control" id="supplier_search" placeholder="ابحث باسم المورد" value="<?= htmlspecialchars($supplierName) ?>">
            <input type="hidden" name="sup_no" id="sup_no" value="<?= htmlspecialchars($book['sup_no'] ?? '') ?>">
            <div id="supplier_result" class="list-group position-absolute w-100" style="z-index:1000; display:none;"></div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <label for="book_language" class="form-label"> لغة الكتاب</label>
          <?php $lang = trim((string)($book['book_language'] ?? '')); ?>
          <select id="book_language" name="book_language" class="form-select">
            <option value="" <?= $lang===''? 'selected':''; ?>>اختر اللغة</option>
            <?php
              $langs = ['عربي','انجليزي','عربي وانجليزي','فرنسي','الماني','عبري','تركي','اسباني','ايطالي','روسي'];
              foreach ($langs as $L) {
                $sel = ($lang === $L) ? 'selected' : '';
                echo "<option value=\"$L\" $sel>$L</option>";
              }
            ?>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label for="year" class="form-label">السنة</label>
          <input id="year" name="year" type="text" class="form-control" value="<?= htmlspecialchars($book['year'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">الطبعة</label>
          <input name="edition" type="text" class="form-control" value="<?= htmlspecialchars($book['edition'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">الأبعاد</label>
          <input name="dimension" type="text" class="form-control" value="<?= htmlspecialchars($book['dimension'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">ملاحظات</label>
          <input name="notes" type="text" class="form-control" value="<?= htmlspecialchars($book['notes'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">ISBN</label>
          <input name="ISBN" type="text" class="form-control" value="<?= htmlspecialchars($book['ISBN'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">رقم الإيداع</label>
          <input name="deposit_num" type="text" class="form-control" value="<?= htmlspecialchars($book['deposit_num'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">عدد الصفحات</label>
          <input name="num_pages" type="number" class="form-control" placeholder="أدخل عدد الصفحات" min="1" value="<?= htmlspecialchars($book['num_pages'] ?? '') ?>">
          <div class="invalid-feedback" id="num_pages_error"></div>
        </div>
        <!-- واجهة تصوير الغلاف الاحترافية -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-header bg-transparent border-0 text-center py-3">
                    <h6 class="text-white mb-0 fw-bold">
                      صورة غلاف الكتاب
                      <i class="bi bi-camera-fill me-2"></i>

                    </h6>
                </div>
                <div class="card-body text-center p-4">
                    <!-- منطقة المعاينة الرئيسية -->
                    <div class="position-relative d-inline-block mb-3">
                        <div class="cover-preview-container" style="width: 200px; height: 280px; margin: 0 auto;">
                            <img id="coverPreviewEdit" src="<?= !empty($book['cover_image']) ? ('/'.htmlspecialchars($book['cover_image'])) : '/public/placeholder.svg' ?>" alt="غلاف الكتاب" 
                                 class="img-fluid rounded-3 shadow-lg border border-white border-3" 
                                 style="width: 100%; height: 100%; object-fit: cover; background: #f8f9fa;">
                            <video id="coverCameraEdit" autoplay playsinline class="d-none rounded-3 shadow-lg border border-white border-3" 
                                   style="width: 100%; height: 100%; object-fit: cover;"></video>
                            <canvas id="coverCanvasEdit" width="400" height="560" class="d-none"></canvas>
                        </div>
                        <!-- مؤشر الحالة -->
                        <div class="position-absolute top-0 start-0 m-2">
                            <span id="cameraStatusEdit" class="badge bg-success bg-opacity-75 d-none">
                            جاهز للتصوير
                            <i class="bi bi-camera-video-fill me-1"></i>
                            </span>
                        </div>
                    </div>
                    
                    <!-- أزرار التحكم -->
                    <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                        <button type="button" id="openCoverCameraEdit" class="btn btn-light btn-lg px-4 py-2 rounded-pill shadow-sm">
                            <span class="fw-semibold">فتح الكاميرا</span>
                            <i class="bi bi-camera-video text-primary me-2"></i>
                        </button>
                        <button type="button" id="captureCoverPhotoEdit" class="btn btn-success btn-lg px-4 py-2 rounded-pill shadow-sm d-none">
                            <span class="fw-semibold">التقاط الصورة</span>
                            <i class="bi bi-camera-fill me-2"></i>
                        </button>
                        <button type="button" id="retakeCoverPhotoEdit" class="btn btn-outline-light btn-lg px-4 py-2 rounded-pill shadow-sm d-none">
                            <span class="fw-semibold">إعادة التقاط</span>
                            <i class="bi bi-arrow-counterclockwise me-2"></i>
                        </button>
                    </div>
                    
                    <!-- خيار رفع الملف -->
                    <div class="border-top border-white border-opacity-25 pt-3">
                        <label for="cover_image_edit" class="btn btn-outline-light btn-lg px-4 py-2 rounded-pill shadow-sm mb-2 d-inline-block">
                            <span class="fw-semibold">رفع صورة من الجهاز</span>
                            <i class="bi bi-upload me-2"></i>
                        </label>
                        <input id="cover_image_edit" name="cover_image" type="file" class="d-none" accept="image/*" capture="environment" />
                        <div class="text-white-50 small mt-2">
                            يمكنك رفع صورة أو التقاط صورة جديدة • JPG, PNG, GIF • حد أقصى 5MB
                        </div>
                    </div>
                </div>
            </div>
          </div>
          <input type="hidden" id="cover_image_data" name="cover_image_data" value="" />
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">ملخص/مقدمة الكتاب</label>
          <textarea name="summary" class="form-control" rows="4" placeholder="أدخل ملخصاً أو مقدمة عن الكتاب..."><?= htmlspecialchars($book['summary'] ?? '') ?></textarea>
        </div>
        <!-- صف 1: مرحـلة المعالجة + القسم -->
        <div class="col-12">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label for="stage" class="form-label">مرحلة المعالجة</label>
              <?php $stageVal = trim((string)($book['stage'] ?? '')); ?>
              <select id="stage" name="stage" class="form-select">
                <option value="" <?= $stageVal===''? 'selected':''; ?>>اختر المرحلة</option>
                <?php $stages = ['بصدد إعداد الطلب','طُبع أمر الطلب','أرسل الطلب','أرسل التذكير','أُلغي الطلب','تم تسلّم الكتاب','تمت فهرست الكتاب','تم تكشيف الكتاب','تم ترفيف الكتاب','حرر الكتاب للإستخدام'];
                  foreach ($stages as $S) { $sel = ($stageVal === $S) ? 'selected' : ''; echo "<option value=\"$S\" $sel>$S</option>"; }
                ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label for="department" class="form-label">القسم</label>
              <?php $dept = trim((string)($book['department'] ?? '')); ?>
              <select id="department" name="department" class="form-select">
                <option value="" <?= $dept===''? 'selected':''; ?>>اختر القسم</option>
                <?php $depts = ['المكتبة العامة','مكتبة الطفل','مكتبة العلوم الاسلامية'];
                  foreach ($depts as $D) { $sel = ($dept === $D) ? 'selected' : ''; echo "<option value=\"$D\" $sel>$D</option>"; }
                ?>
              </select>
            </div>
          </div>
        </div>
        <!-- صف 2: نوع الكتاب + حالة الكتاب -->
        <div class="col-12 mt-1">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label for="book_type" class="form-label">نوع الكتاب</label>
              <?php $typeVal = trim((string)($book['book_type'] ?? '')); ?>
              <select id="book_type" name="book_type" class="form-select">
                <option value="" <?= $typeVal===''? 'selected':''; ?>>اختر نوع الكتاب</option>
                <?php $types = ['عادي','مرجع','مجموعة رسائل','رسالة جامعية','مجموعة','مرجع اطفال','مجلة علمية محكمة','مجلة'];
                  foreach ($types as $T) { $sel = ($typeVal === $T) ? 'selected' : ''; echo "<option value=\"$T\" $sel>$T</option>"; }
                ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label for="book_status" class="form-label">حالة الكتاب</label>
              <?php $statusVal = trim((string)($book['book_status'] ?? '')); ?>
              <select id="book_status" name="book_status" class="form-select">
                <option value="" <?= $statusVal===''? 'selected':''; ?>>اختر حالة الكتاب</option>
                <?php $statuses = ['على الرف','في التجليد','مفقود','متلف','جديد','في المخزن'];
                  foreach ($statuses as $B) { $sel = ($statusVal === $B) ? 'selected' : ''; echo "<option value=\"$B\" $sel>$B</option>"; }
                ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div id="editBookMessage" class="mt-2"></div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="submit" class="btn btn-success px-4 fw-bold">حفظ التغييرات<i class="bi bi-save2 me-1"></i></button>
        <button type="button" id="closeEditBook" class="btn btn-outline-secondary px-4 fw-bold">إغلاق<i class="bi bi-x-circle me-1"></i></button>
      </div>
    </form>

  <?php if (!$isEmbed): ?>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  </div>
  <?php endif; ?>

  <script src="/assets/js/jquery-3.7.1.min.js"></script>
  <script src="/assets/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/publisher.js"></script>
  <script src="/assets/js/author.js"></script>
  <script src="/assets/js/supplier.js"></script>
  <script src="/assets/js/edit_book.js"></script>
  <script>
    (function(){
      var btn = document.getElementById('closeEditBook');
      if (!btn) return;
      btn.addEventListener('click', function(){
        try {
          var pwin = window.parent || null; var pdoc = pwin && pwin.document;
          if (pdoc) {
            var el = pdoc.getElementById('editBookHostModal');
            if (el) {
              if (pwin.bootstrap) { (pwin.bootstrap.Modal.getInstance(el) || new pwin.bootstrap.Modal(el)).hide(); }
              else { el.classList.remove('show'); el.setAttribute('aria-hidden','true'); el.setAttribute('inert',''); }
              return;
            }
          }
        } catch(e) {}
        if (document.referrer) { history.back(); } else { window.close(); }
      });
      // في حالة عدم التضمين، أعرض المودال تلقائياً كما في الإضافة
      var modalEl = document.getElementById('editBookModal');
      if (modalEl && typeof bootstrap !== 'undefined') {
        var inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl, { backdrop: false, keyboard: false });
        inst.show();
        modalEl.addEventListener('hidden.bs.modal', function(){ if (document.referrer) { history.back(); } else { window.close(); } });
      }
    })();
  </script>
  <?php include_once __DIR__ . '/../publishers/publisher_modal.php'; ?>
  <?php include_once __DIR__ . '/../authors/author_modal.php'; ?>
  <?php include_once __DIR__ . '/../suppliers/supplier_modal.php'; ?>
</body>
</html>
