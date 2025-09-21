<?php
require_once '../../config/init.php';

// السماح بعرض الصفحة في iframe
header('X-Frame-Options: SAMEORIGIN');

// التحقق من الصلاحيات إن لزم
checkStaffPermission();

// اتصال قاعدة البيانات
require_once __DIR__ . '/../../config/DB.php';

// معالجة طلبات التحديث/التجديد عبر AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=UTF-8');
  $action = isset($_POST['action']) ? $_POST['action'] : '';
  $mem_no = isset($_POST['mem_no']) ? intval($_POST['mem_no']) : 0;
  if ($mem_no <= 0) { echo json_encode(['ok'=>false,'msg'=>'mem_no مفقود']); exit; }

  if ($action === 'update') {
    // مزامنة mem_phone ليكون أول رقم من phones[] إن أُرسلت
    if (isset($_POST['phones']) && is_array($_POST['phones'])) {
      $phones_in = array_values(array_unique(array_filter(array_map(function($p){ return trim((string)$p); }, $_POST['phones']), function($p){ return $p!==''; })));
      if (!empty($phones_in)) {
        $_POST['mem_phone'] = $phones_in[0];
      }
    }
    // 1) تحديث بيانات المشترك في customer (بدون mem_phone لأنه غير موجود في الجدول)
    $custFields = [
      'mem_name'      => 's',
      'mem_id'        => 's',
      'mem_gender'    => 's',
      'mem_birth'     => 's',
      'mem_residence' => 's',
      'mem_work'      => 's',
      'mem_type'      => 's'
    ];
    $cSet = [];$cTypes='';$cVals=[];
    foreach ($custFields as $col=>$typ) {
      if (array_key_exists($col, $_POST)) {
        // خاص: اسمح بترْك تاريخ الميلاد فارغًا => يتم حفظ NULL بدل سلسلة فارغة لتوافق نوع DATE
        if ($col === 'mem_birth' && (($_POST[$col] ?? '') === '' || $_POST[$col] === null)) {
          $cSet[] = 'mem_birth = NULL';
          continue;
        }
        $cSet[] = "$col = ?";
        $cTypes .= $typ;
        $cVals[] = isset($_POST[$col]) ? $_POST[$col] : '';
      }
    }
    // كلمة السر اختيارية
    if (isset($_POST['mem_password']) && $_POST['mem_password'] !== '') { $cSet[] = "mem_password = ?"; $cTypes .= 's'; $cVals[] = $_POST['mem_password']; }
    if ($cSet) {
      $cTypes .= 'i';
      $cVals[] = $mem_no;
      $sql = "UPDATE customer SET ".implode(', ', $cSet)." WHERE mem_no = ?";
      $stmt = $conn->prepare($sql);
      if(!$stmt){ echo json_encode(['ok'=>false,'msg'=>'DB prepare failed (customer)']); exit; }
      // عوّض nulls بشكل صحيح
      // ملاحظة: bind_param لا يدعم null مباشرة مع السبلات، لكن سنمرر القيم كما هي
      $stmt->bind_param($cTypes, ...$cVals);
      $stmt->execute();
    }

    // 1.ج) تحديث الصورة الشخصية إن تم رفعها/التقاطها
    try {
      $savedPathWeb = '';
      // أولوية: ملف مرفوع
      if (isset($_FILES['personal_photo_file']) && !empty($_FILES['personal_photo_file']['tmp_name']) && is_uploaded_file($_FILES['personal_photo_file']['tmp_name'])) {
        $mime = mime_content_type($_FILES['personal_photo_file']['tmp_name']);
        $ext  = 'jpg';
        if ($mime === 'image/png') $ext = 'png';
        elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') $ext = 'jpg';
        $base = realpath(__DIR__ . '/../../public');
        if ($base === false) { $base = __DIR__ . '/../../public'; }
        $dir  = $base . '/uploads/members';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $fileName = 'mem_' . $mem_no . '_' . time() . '.' . $ext;
        $fullPath = $dir . '/' . $fileName;
        if (@move_uploaded_file($_FILES['personal_photo_file']['tmp_name'], $fullPath)) {
          $savedPathWeb = '/public/uploads/members/' . $fileName;
        }
      }
      // إن لم يكن هناك ملف، جرّب base64 من الحقل المخفي
      if ($savedPathWeb === '') {
        $dataUrl = isset($_POST['personal_photo']) ? trim((string)$_POST['personal_photo']) : '';
        if ($dataUrl !== '' && preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $dataUrl, $m)) {
          $ext = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
          $data = base64_decode($m[2]);
          if ($data !== false) {
            $base = realpath(__DIR__ . '/../../public');
            if ($base === false) { $base = __DIR__ . '/../../public'; }
            $dir  = $base . '/uploads/members';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $fileName = 'mem_' . $mem_no . '_' . time() . '.' . $ext;
            $fullPath = $dir . '/' . $fileName;
            if (@file_put_contents($fullPath, $data) !== false) {
              $savedPathWeb = '/public/uploads/members/' . $fileName;
            }
          }
        }
      }
      // حدّث المسار في قاعدة البيانات إن تم الحفظ
      if ($savedPathWeb !== '') {
        $stmt = $conn->prepare('UPDATE customer SET personal_photo = ? WHERE mem_no = ?');
        if ($stmt) { $stmt->bind_param('si', $savedPathWeb, $mem_no); $stmt->execute(); $stmt->close(); }
      }
    } catch (Throwable $eImg) { /* تجاهل أخطاء الصورة */ }

    // 1.ب) تحديث جدول mem_phone بأرقام متعددة إن وُجدت
    if (isset($_POST['phones']) && is_array($_POST['phones'])) {
      $phones = array_values(array_unique(array_filter(array_map(function($p){ return trim((string)$p); }, $_POST['phones']), function($p){ return $p!==''; })));
      // احذف القديم ثم أدرج الجديد بسرعة
      $del = $conn->prepare('DELETE FROM mem_phone WHERE mem_no = ?');
      $del->bind_param('i', $mem_no);
      $del->execute();
      $del->close();
      if (!empty($phones)) {
        $ins = $conn->prepare('INSERT IGNORE INTO mem_phone (mem_no, mem_phone) VALUES (?, ?)');
        foreach ($phones as $ph) { $ins->bind_param('is', $mem_no, $ph); $ins->execute(); }
        $ins->close();
      }
    }

    // 2) تحديث بيانات الاشتراك في أحدث سجل من member_subscription
    $sub_start = $_POST['start_date'] ?? null;
    $sub_end   = $_POST['end_date'] ?? null;
    $sub_amount= $_POST['amount'] ?? null;
    $sub_curr  = $_POST['currency'] ?? null;
    $sub_status= $_POST['atatus'] ?? null; // الاسم كما هو

    // العثور على أحدث اشتراك (لا يوجد عمود id في member_subscription)
    $latest = $conn->prepare("SELECT start_date, end_date, amount, currency, atatus FROM member_subscription WHERE mem_no = ? ORDER BY end_date DESC LIMIT 1");
    $latest->bind_param('i', $mem_no);
    $latest->execute();
    $latest->store_result();
    $latest->bind_result($s_start,$s_end,$s_amount,$s_curr,$s_status);
    $has = $latest->num_rows > 0 && $latest->fetch();
    $latest->free_result();
    $latest->close();

    if ($has) {
      // حدّث أحدث اشتراك باستخدام end_date السابق لتحديد السجل (لا يوجد id)
      $set = ['start_date = ?','end_date = ?','currency = ?','atatus = ?'];
      $types = 'ssss';
      $vals = [$sub_start, $sub_end, $sub_curr, $sub_status];
      if ($sub_amount === '' || $sub_amount === null) {
        $set[] = 'amount = NULL';
      } else {
        $set[] = 'amount = ?';
        $types .= 'd';
        $vals[] = $sub_amount;
      }
      // شرط التحديد: mem_no و end_date السابق
      $types .= 'is';
      $vals[] = $mem_no;
      $vals[] = $s_end;
      $sql = 'UPDATE member_subscription SET '.implode(', ', $set).' WHERE mem_no = ? AND end_date = ?';
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$vals);
      $ok = $stmt->execute();
      $stmt->close();
      // تسجيل العملية كتجديد اشتراك
      if (isset($auditLogger)) {
        $auditLogger->log(
          null,
          'تجديد الاشتراك',
          'member_subscription',
          (string)$mem_no,
          ['prev_end' => $s_end],
          [
            'new_start' => $sub_start,
            'new_end'   => $sub_end,
            'amount'    => $sub_amount,
            'currency'  => $sub_curr,
            'mem_no'    => (string)$mem_no
          ]
        );
      }
    } else {
      // لا يوجد اشتراك سابق: أنشئ واحداً لهذه القيم
      $user_no = isset($_SESSION['user_no']) ? intval($_SESSION['user_no']) : null;
      if ($sub_amount === '' || $sub_amount === null) {
        $sql = "INSERT INTO member_subscription (user_no, mem_no, start_date, end_date, amount, currency, atatus) VALUES (?,?,?,?,NULL,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iissss', $user_no, $mem_no, $sub_start, $sub_end, $sub_curr, $sub_status);
      } else {
        $sql = "INSERT INTO member_subscription (user_no, mem_no, start_date, end_date, amount, currency, atatus) VALUES (?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iissdss', $user_no, $mem_no, $sub_start, $sub_end, $sub_amount, $sub_curr, $sub_status);
      }
      $ok = $stmt->execute();
      $stmt->close();
    }

    echo json_encode(['ok'=> isset($ok)? (bool)$ok : true ]);
    exit;
  } elseif ($action === 'renew') {
    // تجديد: إنشاء سجل جديد في member_subscription بناءً على أحدث اشتراك
    $today = date('Y-m-d');
    $res = $conn->prepare('SELECT end_date, amount, currency FROM member_subscription WHERE mem_no = ? ORDER BY end_date DESC LIMIT 1');
    $res->bind_param('i', $mem_no);
    $res->execute();
    $res->bind_result($end_date_curr, $amount_curr, $currency_curr);
    $res->fetch();
    $res->close();
    $base = ($end_date_curr && $end_date_curr > $today) ? $end_date_curr : $today;
    $new_start = $base;
    $new_end = date('Y-m-d', strtotime($base.' +1 year'));
    $user_no = isset($_SESSION['user_no']) ? intval($_SESSION['user_no']) : null;
    $amount_in = isset($_POST['amount']) && $_POST['amount'] !== '' ? $_POST['amount'] : $amount_curr;
    $currency_in = isset($_POST['currency']) && $_POST['currency'] !== '' ? $_POST['currency'] : $currency_curr;
    if ($amount_in === '' || $amount_in === null) {
      $stmt = $conn->prepare("INSERT INTO member_subscription (user_no, mem_no, start_date, end_date, amount, currency, atatus) VALUES (?,?,?,?,NULL,?, 'ساري')");
      $stmt->bind_param('iisss', $user_no, $mem_no, $new_start, $new_end, $currency_in);
    } else {
      $stmt = $conn->prepare("INSERT INTO member_subscription (user_no, mem_no, start_date, end_date, amount, currency, atatus) VALUES (?,?,?,?,?,?, 'ساري')");
      $stmt->bind_param('iissds', $user_no, $mem_no, $new_start, $new_end, $amount_in, $currency_in);
    }
    $ok = $stmt->execute();
    // تسجيل العملية في سجل التدقيق لظهورها في لوحة التحكم
    if ($ok && isset($auditLogger)) {
      // تسجيل باسم عربي مباشر: تجديد الاشتراك
      $auditLogger->log(
        null,
        'تجديد الاشتراك',
        'member_subscription',
        (string)$mem_no,
        ['prev_end' => $end_date_curr],
        [
          'new_start' => $new_start,
          'new_end'   => $new_end,
          'amount'    => $amount_in,
          'currency'  => $currency_in,
          'mem_no'    => (string)$mem_no
        ]
      );
    }
    echo json_encode(['ok'=>$ok, 'new_end'=>$new_end]);
    exit;
  }
  echo json_encode(['ok'=>false,'msg'=>'إجراء غير معروف']);
  exit;
}

// قراءة رقم المشترك من الاستعلام
$mem_no     = isset($_GET['mem_no']) ? intval($_GET['mem_no']) : 0;

// قيم افتراضية
$mem_name = $mem_phone = $mem_status = '';
$mem_id = $mem_gender = $mem_birth = $mem_type = '';
$mem_residence = $mem_work = '';
$mem_password = '';
$start_date = $end_date = $currency = '';
$amount = '';
$phones_list = [];

// تواريخ افتراضية للاشتراك
$today = date('Y-m-d');
$nextYear = date('Y-m-d', strtotime('+1 year'));

// جلب البيانات من قاعدة البيانات لملء النموذج
if ($mem_no > 0) {
  // بيانات المشترك الشخصية من customer (بدون mem_phone)
  $stmt = $conn->prepare("SELECT mem_no, mem_name, mem_id, mem_gender, mem_birth, mem_residence, mem_work, mem_type, mem_password, personal_photo FROM customer WHERE mem_no = ? LIMIT 1");
  $stmt->bind_param('i', $mem_no);
  if ($stmt->execute()) {
    $stmt->bind_result($r_no, $r_name, $r_id, $r_gender, $r_birth, $r_residence, $r_work, $r_type, $r_password, $r_photo);
    if ($stmt->fetch()) {
      $mem_name   = $r_name ?: '';
      $mem_id     = $r_id ?: '';
      $mem_gender = $r_gender ?: '';
      $mem_birth  = $r_birth ?: '';
      $mem_residence = $r_residence ?: '';
      $mem_work      = $r_work ?: '';
      $mem_type   = $r_type ?: '';
      $mem_password = $r_password ?: '';
      $mem_photo = $r_photo ?: '';
    }
  }
  $stmt->close();

  // أحدث اشتراك من member_subscription
  $stmt2 = $conn->prepare("SELECT start_date, end_date, amount, currency, atatus FROM member_subscription WHERE mem_no = ? ORDER BY end_date DESC LIMIT 1");
  $stmt2->bind_param('i', $mem_no);
  if ($stmt2->execute()) {
    $stmt2->bind_result($r_start, $r_end, $r_amount, $r_currency, $r_status);
    if ($stmt2->fetch()) {
      $start_date = $r_start ?: $today;
      $end_date   = $r_end ?: $nextYear;
      $amount     = $r_amount ?: '';
      $currency   = $r_currency ?: '';
      $mem_status = $r_status ?: '';
    }
  }
  $stmt2->close();
}

// جلب أرقام الهواتف من جدول mem_phone
if ($mem_no > 0) {
  $ph = $conn->prepare('SELECT mem_phone FROM mem_phone WHERE mem_no = ? ORDER BY id_phone ASC');
  if ($ph) {
    $ph->bind_param('i', $mem_no);
    if ($ph->execute()) {
      $res = $ph->get_result();
      while ($row = $res->fetch_assoc()) { $phones_list[] = $row['mem_phone']; }
    }
    $ph->close();
  }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تعديل بيانات المشترك</title>
  <link href="/assets/css/bootstrap.css" rel="stylesheet" />
  <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet" />
  <link href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="/assets/css/style.css" rel="stylesheet" />
  <link href="/assets/css/edit_customer.css" rel="stylesheet" />
  <link href="/assets/css/add_customer.css" rel="stylesheet" />
</head>
<body>
  <div class="container py-3">
    <div class="card card-glass">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fa fa-user-pen me-2 text-primary"></i>تعديل بيانات المشترك</h5>
        <span class="badge bg-light text-dark">رقم: <?php echo htmlspecialchars($mem_no); ?></span>
      </div>
      <div class="card-body">
        <form id="editForm">
          <input type="hidden" name="action" value="update" />
          <input type="hidden" id="personal_photo" name="personal_photo" value="" />
          <!-- بيانات المشترك -->
          <h6 class="mb-2 mt-1"><i class="fa fa-user me-1 text-primary"></i>بيانات المشترك</h6>
          <!-- صورة المشترك: معاينة + كاميرا -->
          <div class="d-flex flex-column align-items-center mb-3">
            <div class="position-relative mb-2">
              <div class="avatar-container">
                <img id="photoPreview" src="<?php echo htmlspecialchars(($mem_photo && $mem_photo !== '') ? $mem_photo : '/public/placeholder.svg'); ?>" alt="صورة المشترك" class="avatar-image">
                <video id="cameraStream" autoplay playsinline class="avatar-image d-none"></video>
                <canvas id="captureCanvas" class="d-none" width="120" height="120"></canvas>
                <div class="avatar-camera-overlay" id="cameraOverlay" data-bs-toggle="tooltip" data-bs-placement="bottom" title="التقاط صورة">
                  <i class="bi bi-camera-fill"></i>
                </div>
              </div>
            </div>
            <div class="camera-controls" style="max-width:420px;width:100%">
              <div class="input-group mb-2">
                <select id="cameraSelect" class="form-select border-start-0 border-end-0" aria-label="اختيار الكاميرا">
                  <option value="">اختيار الكاميرا...</option>
                </select>
                <button type="button" id="captureBtn" class="btn btn-success btn-sm input-group-text" data-bs-toggle="tooltip" data-bs-placement="bottom" title="التقاط الصورة">
                  التقاط
                  <i class="bi bi-camera-fill me-1"></i>
                </button>
                <button type="button" id="retakeBtn" class="btn btn-outline-secondary btn-sm input-group-text d-none" data-bs-toggle="tooltip" data-bs-placement="bottom" title="إعادة التقاط الصورة">
                  إعادة التقاط
                  <i class="bi bi-arrow-counterclockwise me-1"></i>
                </button>
                <label for="personal_photo_file" class="btn btn-outline-secondary btn-sm input-group-text" data-bs-toggle="tooltip" data-bs-placement="bottom" title="رفع صورة من الجهاز">
                  رفع
                  <i class="bi bi-upload me-1"></i>
                </label>
                <input type="file" class="d-none" id="personal_photo_file" name="personal_photo_file" accept="image/*">
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">رقم المشترك</label>
              <input type="number" class="form-control" name="mem_no" value="<?php echo htmlspecialchars($mem_no); ?>" readonly>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">الاسم الكامل</label>
              <input type="text" class="form-control" name="mem_name" value="<?php echo htmlspecialchars($mem_name); ?>" placeholder="الاسم الكامل">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">رقم الهوية / الإقامة</label>
              <input type="text" class="form-control" name="mem_id" value="<?php echo htmlspecialchars($mem_id); ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">الجنس</label>
              <select class="form-select" name="mem_gender">
                <option value="">اختر الجنس...</option>
                <option value="ذكر" <?php echo $mem_gender==='ذكر'? 'selected':''; ?>>ذكر</option>
                <option value="أنثى" <?php echo $mem_gender==='أنثى'? 'selected':''; ?>>أنثى</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">أرقام الجوال</label>
              <div id="phonesRepeaterEdit" class="d-flex flex-column gap-2">
                <?php
                  $phones_to_render = !empty($phones_list) ? $phones_list : [ $mem_phone ];
                  $rendered_any = false;
                  foreach ($phones_to_render as $idx => $p) {
                    $p = trim((string)$p);
                    if ($p === '' && $rendered_any) continue;
                    $rendered_any = true;
                ?>
                  <div class="phone-row position-relative">
                    <i class="bi bi-telephone-fill field-icon start"></i>
                    <input type="text" class="form-control phone-input" name="phones[]" value="<?php echo htmlspecialchars($p); ?>" placeholder="أدخل رقم الجوال" />
                    <?php if ($idx === 0) { ?>
                      <button type="button" class="icon-btn add-phone text-success" aria-label="add"><i class="bi bi-plus-lg"></i></button>
                    <?php } else { ?>
                      <button type="button" class="icon-btn remove-phone text-danger" aria-label="remove"><i class="bi bi-dash-lg"></i></button>
                    <?php } ?>
                  </div>
                <?php } ?>
                <?php if (!$rendered_any) { ?>
                  <div class="phone-row position-relative">
                    <i class="bi bi-telephone-fill field-icon start"></i>
                    <input type="text" class="form-control phone-input" name="phones[]" placeholder="أدخل رقم الجوال" />
                    <button type="button" class="icon-btn add-phone text-success" aria-label="add"><i class="bi bi-plus-lg"></i></button>
                  </div>
                <?php } ?>
              </div>
              <input type="hidden" id="mem_phone_hidden" name="mem_phone" value="<?php echo htmlspecialchars($mem_phone); ?>" />
              <small class="text-muted">يمكنك إضافة أكثر من رقم. سيتم استخدام أول رقم كرقم أساسي للتوافق.</small>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">تاريخ الميلاد</label>
              <input type="date" class="form-control" name="mem_birth" value="<?php echo htmlspecialchars($mem_birth); ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">مكان السكن</label>
              <input type="text" class="form-control" name="mem_residence" value="<?php echo htmlspecialchars($mem_residence); ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">مكان العمل</label>
              <input type="text" class="form-control" name="mem_work" value="<?php echo htmlspecialchars($mem_work); ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">المهنة</label>
              <select class="form-select" name="mem_type">
                <option value="">اختر النوع...</option>
                <?php
                  $types = ['طفل','موظف','طالب مدرسة','طالب جامعي','محامي','مهندس','طبيب','ربة بيت','بلا','عامل','محاضر'];
                  foreach($types as $t){
                    $sel = ($mem_type === $t) ? 'selected' : '';
                    echo "<option value=\"$t\" $sel>$t</option>";
                  }
                ?>
              </select>
            </div>
            
            <div class="col-12 col-md-6">
              <label class="form-label">كلمة المرور</label>
              <div class="modern-input-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" class="modern-form-control" id="mem_password" name="mem_password" value="<?php echo htmlspecialchars($mem_password); ?>" placeholder="كلمة المرور">
                <i class="fas fa-eye toggle-password-icon" id="toggleIcon"></i>
              </div>
            </div>
          </div>

          <!-- بيانات الاشتراك -->
          <h6 class="mb-2 mt-4"><i class="fa fa-id-card me-1 text-primary"></i>بيانات الاشتراك</h6>
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <label class="form-label">تاريخ بداية الاشتراك</label>
              <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date ?: $today); ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">تاريخ نهاية الاشتراك</label>
              <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date ?: $nextYear); ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">المبلغ</label>
              <input type="number" class="form-control" step="any" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">العملة</label>
              <select class="form-select" name="currency">
                <option value="">اختر العملة...</option>
                <?php
                  $currs = ['دولار','دينار','الشيكل'];
                  foreach($currs as $c){ $sel = ($currency === $c)?'selected':''; echo "<option value=\"$c\" $sel>$c</option>"; }
                ?>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">حالة الاشتراك</label>
              <!-- ملاحظة: لنتوافق مع addcustomer استخدمنا الاسم atatus -->
              <select class="form-select" name="atatus">
                <option value="ساري" <?php echo $mem_status==='ساري'? 'selected':''; ?>>ساري</option>
                <option value="منتهي" <?php echo $mem_status==='منتهي'? 'selected':''; ?>>منتهي</option>
                <option value="موقوف" <?php echo $mem_status==='موقوف'? 'selected':''; ?>>موقوف</option>
              </select>
              <small class="hint">يتم تحديد الحالة تلقائياً حسب التواريخ ويمكنك تعديلها يدوياً.</small>
            </div>
          </div>
          <div class="mt-3 d-flex align-items-center justify-content-between">
            <div class="hint"><i class="fa fa-circle-info me-1"></i>عدّل الحقول ثم اضغط "تحديث البيانات" وسيتم حفظ التغييرات فوراً.</div>
            <div class="actions">
              <button type="submit" class="btn btn-success px-4 fw-bold" id="btnUpdate">تحديث البيانات <i class="fa fa-save icon-gap"></i></button>
              <button type="button" class="btn btn-outline-secondary px-4 fw-bold" id="btnClose">إغلاق <i class="fa fa-times icon-gap"></i></button>
              </div>
          </div>

          <div id="updateMessageArea" class="mt-3"></div>

        <hr class="my-2"/>
      </div>
    </div>
  </div>

  <script src="/assets/js/jquery-3.7.1.min.js"></script>
  <script src="/assets/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/edit_customer.js"></script>
</body>
</html>
