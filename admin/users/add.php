<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../auth/check_auth.php';

// السماح فقط للمديرين
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$errors = [];

// احضار الرقم التالي تلقائياً (أكبر رقم + 1)
$next_user_no = 1;
try {
    $res = $conn->query("SELECT COALESCE(MAX(user_no), 0) + 1 AS next_no FROM user");
    if ($res && $row = $res->fetch_assoc()) { $next_user_no = (int)$row['next_no']; }
} catch (Throwable $e) { /* تجاهل: سنُكمل بالقيمة الافتراضية */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // سنُهمل القيمة المُرسلة ونستخدم الرقم التالي من القاعدة
    $user_no = (string)$next_user_no;
    $user_name = trim($_POST['user_name'] ?? '');
    $user_address = trim($_POST['user_address'] ?? '');
    $user_password = trim($_POST['user_password'] ?? '');
    $user_tel = trim($_POST['user_tel'] ?? '');
    $user_type = trim($_POST['user_type'] ?? 'staff');

    if ($user_name === '' || $user_password === '') {
        $errors[] = 'يرجى تعبئة جميع الحقول المطلوبة';
    }
    if (!in_array($user_type, ['admin','staff'])) {
        $user_type = 'staff';
    }

    if (!$errors) {
        try {
            // حاول إيجاد رقم متاح (في حال سبق وتمت إضافته بالتزامن)
            $user_no_int = (int)$user_no;
            for ($i = 0; $i < 5; $i++) {
                $chk = $conn->prepare("SELECT 1 FROM user WHERE user_no = ? LIMIT 1");
                $chk->bind_param('i', $user_no_int);
                $chk->execute();
                $exists = $chk->get_result()->num_rows > 0;
                if (!$exists) break;
                $user_no_int++;
            }

            $stmt = $conn->prepare("INSERT INTO user (user_no, user_name, user_address, user_password, user_tel, user_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssss', $user_no_int, $user_name, $user_address, $user_password, $user_tel, $user_type);
            $stmt->execute();
            // تسجيل العملية في سجل التدقيق
            try {
                if (isset($auditLogger)) {
                    $auditLogger->logCreate(null, 'user', (string)$user_no_int, [
                        'user_no' => $user_no_int,
                        'user_name' => $user_name,
                        'user_address' => $user_address,
                        'user_tel' => $user_tel,
                        'user_type' => $user_type
                    ]);
                }
            } catch (Throwable $e2) { /* تجاهل */ }
            $suffix = (isset($_GET['embed']) && $_GET['embed'] == '1') ? '&embed=1' : '';
            header('Location: index.php?msg=' . urlencode('تمت إضافة الموظف بنجاح') . $suffix);
            exit;
        } catch (Throwable $e) {
            $errors[] = 'خطأ في الإضافة: ' . $e->getMessage();
        }
    }
}

// عند الفتح ضمن iframe لا نعرض الهيدر والفوتر
$embed = isset($_GET['embed']) && $_GET['embed'] == '1';
if (!$embed) {
    include __DIR__ . '/../../includes/header.php';
}
// تحميل موارد أساسية عند العرض داخل iframe
if ($embed) {
    echo '<link href="/assets/css/bootstrap.css" rel="stylesheet">';
    echo '<link href="/assets/css/style.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">';
}
?>
<div class="container mt-4" dir="rtl">
  <h3 class="mb-3">إضافة موظف جديد</h3>

  <?php foreach ($errors as $er): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($er); ?></div>
  <?php endforeach; ?>

  <form method="post" class="card p-3" style="max-width:720px;">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">رقم الموظف (يتولد تلقائياً)</label>
        <input type="text" name="user_no" class="form-control" value="<?php echo htmlspecialchars($next_user_no); ?>" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label">اسم المستخدم</label>
        <input type="text" name="user_name" class="form-control" placeholder="مثال: أحمد محمد" required value="<?php echo htmlspecialchars($_POST['user_name'] ?? ''); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">الهاتف</label>
        <input type="text" name="user_tel" class="form-control" placeholder="مثال: 059xxxxxxxx" value="<?php echo htmlspecialchars($_POST['user_tel'] ?? ''); ?>">
      </div>
      <div class="col-md-12">
        <label class="form-label">العنوان</label>
        <input type="text" name="user_address" class="form-control" placeholder="المدينة - الحي - الشارع" value="<?php echo htmlspecialchars($_POST['user_address'] ?? ''); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">كلمة المرور</label>
        <div class="modern-input-group">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" id="user_password" name="user_password" class="modern-form-control" placeholder="أدخل كلمة المرور" >
          <i class="fas fa-eye toggle-password-icon" id="togglePassword"></i>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label">النوع</label>
        <select name="user_type" class="form-select">
          <option value="staff" <?php echo (($_POST['user_type'] ?? '')==='staff')?'selected':''; ?>>موظف</option>
          <option value="admin" <?php echo (($_POST['user_type'] ?? '')==='admin')?'selected':''; ?>>مدير</option>
        </select>
      </div>
      
      <div class="col-12 d-flex gap-2 justify-content-end mt-2">
        <a href="index.php<?php echo $embed ? '?embed=1' : '';?>" class="btn btn-secondary">رجوع</a>
        <button type="submit" class="btn btn-primary px-4">حفظ</button>
      </div>
    </div>
  </form>
</div>
<?php 
// تحميل السكربتات عند العرض داخل iframe
if ($embed) {
    echo '<script src="/assets/js/bootstrap.bundle.min.js"></script>';
}
if (!$embed) { include __DIR__ . '/../../includes/footer.php'; }
?>
<script>
  (function(){
    const pwd = document.getElementById('user_password');
    const icon = document.getElementById('togglePassword');
    if (pwd && icon) {
      icon.addEventListener('click', function(){
        const show = pwd.type === 'password';
        pwd.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
      });
    }
  })();
</script>
