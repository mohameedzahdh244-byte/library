<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../auth/check_auth.php';

// السماح فقط للمديرين
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$user_no = $_GET['user_no'] ?? '';
$embed = isset($_GET['embed']) && $_GET['embed'] == '1';
if ($user_no === '') { header('Location: index.php'); exit; }

$errors = [];

// جلب بيانات المستخدم
$user = null;
try {
    $stmt = $conn->prepare("SELECT user_no, user_name, user_address, user_password, user_tel, user_type FROM user WHERE user_no = ? LIMIT 1");
    $stmt->bind_param('s', $user_no);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) { $user = $res->fetch_assoc(); }
} catch (Throwable $e) { $errors[] = 'خطأ في الجلب: ' . $e->getMessage(); }

if (!$user) { header('Location: index.php'); exit; }

// لا نستخدم صورًا للمستخدمين في هذه الواجهة

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = trim($_POST['user_name'] ?? '');
    $user_address = trim($_POST['user_address'] ?? '');
    $user_password = trim($_POST['user_password'] ?? '');
    $user_tel = trim($_POST['user_tel'] ?? '');
    $user_type = trim($_POST['user_type'] ?? 'staff');

    if ($user_name === '') { $errors[] = 'اسم المستخدم مطلوب'; }
    if (!in_array($user_type, ['admin','staff'])) { $user_type = 'staff'; }

    if (!$errors) {
        try {
            if ($user_password === '') {
                $stmt = $conn->prepare("UPDATE user SET user_name = ?, user_address = ?, user_tel = ?, user_type = ? WHERE user_no = ?");
                $stmt->bind_param('sssss', $user_name, $user_address, $user_tel, $user_type, $user_no);
            } else {
                $stmt = $conn->prepare("UPDATE user SET user_name = ?, user_address = ?, user_password = ?, user_tel = ?, user_type = ? WHERE user_no = ?");
                $stmt->bind_param('ssssss', $user_name, $user_address, $user_password, $user_tel, $user_type, $user_no);
            }
            $stmt->execute();
            // لا يوجد حفظ صور للمستخدمين هنا
            $suffix = $embed ? '&embed=1' : '';
            // تسجيل العملية في سجل التدقيق
            try {
                if (isset($auditLogger)) {
                    $oldVals = [
                        'user_no' => $user['user_no'],
                        'user_name' => $user['user_name'],
                        'user_address' => $user['user_address'] ?? '',
                        'user_password' => $user['user_password'],
                        'user_tel' => $user['user_tel'] ?? '',
                        'user_type' => $user['user_type']
                    ];
                    $newVals = [
                        'user_no' => $user['user_no'],
                        'user_name' => $user_name,
                        'user_address' => $user_address,
                        'user_password' => ($user_password === '' ? $user['user_password'] : $user_password),
                        'user_tel' => $user_tel,
                        'user_type' => $user_type
                    ];
                    $auditLogger->logUpdate(null, 'user', (string)$user['user_no'], $oldVals, $newVals);
                }
            } catch (Throwable $e2) { /* تجاهل */ }
            header('Location: index.php?msg=' . urlencode('تم تحديث بيانات الموظف') . $suffix);
            exit;
        } catch (Throwable $e) {
            $errors[] = 'خطأ في التعديل: ' . $e->getMessage();
        }
    }
}

if (!$embed) { include __DIR__ . '/../../includes/header.php'; }
// تحميل موارد أساسية عند العرض داخل iframe
if ($embed) {
    echo '<link href="/assets/css/bootstrap.css" rel="stylesheet">';
    echo '<link href="/assets/css/style.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">';
}
?>
<div class="container mt-4" dir="rtl">
  <h3 class="mb-3">تعديل بيانات الموظف</h3>

  <?php foreach ($errors as $er): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($er); ?></div>
  <?php endforeach; ?>

  <form method="post" class="card p-3" style="max-width:640px;">
    <div class="mb-3">
      <label class="form-label">رقم الموظف</label>
      <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['user_no']); ?>" disabled>
    </div>
    <div class="mb-3">
      <label class="form-label">اسم المستخدم</label>
      <input type="text" name="user_name" class="form-control" required value="<?php echo htmlspecialchars($user['user_name']); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">العنوان</label>
      <input type="text" name="user_address" class="form-control" value="<?php echo htmlspecialchars($user['user_address'] ?? ''); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">كلمة المرور الجديدة (اتركها فارغة للإبقاء على الحالية)</label>
      <div class="modern-input-group">
        <i class="fas fa-lock input-icon"></i>
        <input type="password" id="user_password" name="user_password" class="modern-form-control" placeholder="بدون تغيير" value="<?php echo htmlspecialchars($user['user_password']); ?>">
        <i class="fas fa-eye toggle-password-icon" id="togglePassword"></i>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">هاتف</label>
      <input type="text" name="user_tel" class="form-control" value="<?php echo htmlspecialchars($user['user_tel'] ?? ''); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">النوع</label>
      <select name="user_type" class="form-select">
        <option value="staff" <?php echo ($user['user_type']==='staff')?'selected':''; ?>>موظف</option>
        <option value="admin" <?php echo ($user['user_type']==='admin')?'selected':''; ?>>مدير</option>
      </select>
    </div>
    
    <div class="d-flex gap-2 justify-content-end">
      <a href="index.php<?php echo $embed ? '?embed=1' : '';?>" class="btn btn-secondary">رجوع</a>
      <button type="submit" class="btn btn-primary">حفظ</button>
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
