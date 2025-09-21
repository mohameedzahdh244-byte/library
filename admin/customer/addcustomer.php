<?php include_once __DIR__ . '/../../config/paths.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اضافة مستخدم</title>

    <link rel="stylesheet" href="<?php echo asset('css/bootstrap.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/add_customer.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo asset('vendor/fontawesome/css/all.min.css'); ?>">
</head>
<body>
<?php $isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1'; ?>
<?php if (!$isEmbed): ?>
  <div class="modal fade" id="addCustomerModal" data-bs-backdrop="false" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content rounded-4 shadow-lg">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-person-plus"></i> إضافة مستخدم جديد</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body p-4">
<?php else: ?>
  <div class="p-3">
    <h5 class="fw-bold mb-3 text-end text-secondary"><i class="bi bi-person-plus"></i> إضافة مستخدم جديد</h5>
<?php endif; ?>
          <form id="addCustomerForm" method="POST">
    <h5 class="fw-bold mb-3 text-end text-secondary">
      <i class="bi bi-person-badge"></i> بيانات المشترك
    </h5>
    <?php
    // جلب أعلى رقم مشترك حالي من قاعدة البيانات
    include_once __DIR__ . '/../../config/DB.php';
    $mem_no_auto = 1;
    $result = $conn->query("SELECT MAX(mem_no) AS max_no FROM customer");
    if ($result && $row = $result->fetch_assoc()) {
        $mem_no_auto = intval($row['max_no']) + 1;
    }
    ?>
    <!-- معاينة الصورة الشخصية (دائرية) مع الكاميرا -->
    <div class="d-flex flex-column align-items-center mb-4">
      <!-- حاوي الصورة/الفيديو مع تأثيرات جميلة -->
      <div class="position-relative mb-3">
        <div class="avatar-container">
          <img id="photoPreview" src="<?php echo PUBLIC_URL; ?>/placeholder.svg" alt="صورة المشترك" class="avatar-image">
          <video id="cameraStream" autoplay playsinline class="avatar-image d-none"></video>
          <canvas id="captureCanvas" class="d-none" width="120" height="120"></canvas>
          <!-- أيقونة الكاميرا -->
          <div class="avatar-camera-overlay" id="cameraOverlay" data-bs-toggle="tooltip" data-bs-placement="bottom" title="التقاط صورة">
            <i class="bi bi-camera-fill"></i>
          </div>
        </div>
      </div>
      
      <!-- مجموعة التحكم بالكاميرا -->
      <div class="camera-controls">
        <!-- اختيار الكاميرا مع زر التقاط وأيقونة -->
        <div class="input-group mb-3">
          <select id="cameraSelect" class="form-select border-start-0 border-end-0" aria-label="اختيار الكاميرا">
            <option value="">اختيار الكاميرا...</option>
          </select>
          <button type="button" id="captureBtn" class="btn btn-success input-group-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="التقاط الصورة">
          التقاط
            <i class="bi bi-camera-fill me-1"></i>
          </button>
        </div>
        
        <!-- زر إعادة التقاط فقط أسفل الحقل -->
        <div class="camera-actions d-flex gap-2 justify-content-center">
          <button type="button" id="retakeBtn" class="btn btn-outline-secondary d-none" data-bs-toggle="tooltip" data-bs-placement="bottom" title="إعادة التقاط الصورة">
            <i class="bi bi-arrow-counterclockwise me-1"></i>إعادة التقاط
          </button>
        </div>
      </div>
      
      <input type="hidden" id="personal_photo" name="personal_photo" value="">
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="mem_no" class="form-label">رقم المشترك</label>
            <input type="number" class="form-control" id="mem_no" name="mem_no" value="<?php echo $mem_no_auto; ?>" >
        </div>
        <div class="col-md-6 mb-3">
            <label for="mem_name" class="form-label">الاسم الكامل</label>
            <input type="text" class="form-control" id="mem_name" name="mem_name" >
        </div>
        <div class="col-md-6 mb-3">
            <label for="mem_id" class="form-label">رقم الهوية / الإقامة</label>
            <input type="text" class="form-control" id="mem_id" name="mem_id" >
        </div>
        <div class="col-md-6 mb-3">
            <label for="mem_gender" class="form-label">الجنس</label>
            <select class="form-select" id="mem_gender" name="mem_gender" >
                <option value="">اختر الجنس...</option>
                <option value="ذكر">ذكر</option>
                <option value="أنثى">أنثى</option>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">أرقام الجوال</label>
            <div id="phonesRepeater" class="d-flex flex-column gap-2">
              <div class="phone-row position-relative">
                <i class="bi bi-telephone-fill field-icon start"></i>
                <input type="text" class="form-control phone-input" name="phones[]" placeholder="أدخل رقم الجوال" />
                <button type="button" class="icon-btn add-phone text-success" aria-label="add"><i class="bi bi-plus-lg"></i></button>
              </div>
            </div>
            <input type="hidden" id="mem_phone_hidden" name="mem_phone" value="" />
            <small class="text-muted">يمكنك إضافة أكثر من رقم. سيتم استخدام أول رقم كرقم أساسي للتوافق.</small>
        </div>
        <div class="col-md-6 mb-3">
            <label for="mem_birth" class="form-label">تاريخ الميلاد</label>
            <input type="date" class="form-control" id="mem_birth" name="mem_birth">
        </div>
        <div class="col-md-6 mb-3">
            <label for="mem_residence" class="form-label">مكان السكن</label>
            <input type="text" class="form-control" id="mem_residence" name="mem_residence" >
        </div>
        <div class="col-md-6 mb-3">
            <label for="mem_work" class="form-label">مكان العمل</label>
            <input type="text" class="form-control" id="mem_work" name="mem_work">
        </div>
        <div class="col-md-6 mb-3">
            <label for="mem_type" class="form-label">المهنة</label>
            <select class="form-select" id="mem_type" name="mem_type" >
                <option value="">اختر النوع...</option>
                <option value="طفل">طفل</option>
                <option value="موظف">موظف</option>
                <option value="طالب مدرسة">طالب مدرسة</option>
                <option value="طالب جامعي">طالب جامعي</option>
                <option value="محامي">محامي</option>
                <option value="مهندس">مهندس</option>
                <option value="طبيب">طبيب</option>
                <option value="ربة بيت">ربة بيت</option>
                <option value="بلا">بلا</option>
                <option value="عامل">عامل</option>
                <option value="محاضر">محاضر</option>
            </select>
        </div>
        <!-- تم استبدال رفع الملف بالتقاط الكاميرا أعلاه -->
        <div class="col-md-6 mb-3">
            <label for="mem_password" class="form-label">كلمة المرور</label>
            <div class="modern-input-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" class="modern-form-control" id="mem_password" name="mem_password" autocomplete="new-password" placeholder="ادخال او توليد كلمة المرور">
                <i class="fas fa-eye toggle-password-icon" id="togglePwdIcon"></i>
                <button class="btn btn-outline-secondary generate-btn" type="button" id="regenPwdBtn" aria-label="توليد كلمة مرور جديدة">
                    <i class="fas fa-shuffle me-1"></i> توليد
                </button>
            </div>
            <small class="text-muted">لن يتم التوليد تلقائياً؛ اضغط "توليد" لإنشاء كلمة قوية، ويمكنك تعديلها يدوياً.</small>
        </div>
    </div>
    <h5 class="fw-bold mb-3 mt-4 text-end text-secondary">
      <i class="bi bi-card-checklist"></i> بيانات الاشتراك
    </h5>
    <div class="row">
        <?php
        $today = date('Y-m-d');
        $nextYear = date('Y-m-d', strtotime('+1 year'));
        ?>
        <div class="col-md-4 mb-3">
            <label for="start_date" class="form-label">تاريخ بداية الاشتراك</label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $today; ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label for="end_date" class="form-label">تاريخ نهاية الاشتراك</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $nextYear; ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label for="amount" class="form-label">المبلغ</label>
            <input type="number" class="form-control" id="amount" name="amount" step="any">
        </div>
        <div class="col-md-4 mb-3">
            <label for="currency" class="form-label">العملة</label>
            <select class="form-select" id="currency" name="currency">
                <option value="">اختر العملة...</option>
                <option value="دولار">دولار الامريكي</option>
                <option value="دينار"> دينارأردني</option>
                <option value="الشيكل">الشيكل</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label for="atatus" class="form-label">حالة الاشتراك</label>
            <select class="form-select" id="atatus" name="atatus">
                <option value="ساري" data-color="green">ساري</option>
                <option value="منتهي" data-color="red">منتهي</option>
                <option value="موقوف" data-color="gray">موقوف</option>
            </select>
            <small class="text-muted">يتم تحديد الحالة تلقائياً حسب التواريخ ويمكنك تعديلها يدوياً.</small>
        </div>
    </div>
    
    
    <div id="customerMessageArea" class="mt-3 text-center"></div>
    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="submit" class="btn btn-success px-4 fw-bold">
        تسجيل الاشتراك
        <i class="bi bi-save2"></i> 
      </button>
      <button type="button" id="closeEmbedCustomer" class="btn btn-outline-secondary px-4 fw-bold" aria-label="إغلاق">
         إغلاق
         <i class="bi bi-x-circle"></i>
      </button>



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




<script src="<?php echo asset('js/jquery-3.7.1.min.js'); ?>"></script>
<script src="<?php echo asset('js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo asset('js/addcustomer.js'); ?>"></script>

</body>
</html>
