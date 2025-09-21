<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../auth/check_auth.php';

// السماح فقط للمديرين
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin'])) {
    header('Location: ../dashboard.php');
    exit;
}

// جلب جميع الموظفين
$users = [];
try {
    $q = $conn->query("SELECT user_no, user_name, user_type FROM user ORDER BY user_no ASC");
    while ($row = $q->fetch_assoc()) { $users[] = $row; }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// عند الفتح داخل iframe لا نُظهر الهيدر والفوتر
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
<?php if ($embed): ?>
<!-- Toast أعلى يمين الشاشة داخل iframe -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080;">
  <div id="embedToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
    <div class="d-flex">
      <div class="toast-body" id="embedToastBody">تمت العملية بنجاح</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
<?php endif; ?>
<div class="container mt-4" dir="rtl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>إدارة الموظفين</h3>
    <a href="add.php<?php echo $embed ? '?embed=1' : '';?>" class="btn btn-primary"> إضافة موظف <i class="fas fa-plus"></i></a>
  </div>

  <?php if (!$embed): ?>
    <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger">خطأ: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>الرقم</th>
          <th>اسم المستخدم</th>
          <th>النوع</th>
          <th>إجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$users): ?>
          <tr><td colspan="4" class="text-center text-muted">لا يوجد موظفون</td></tr>
        <?php else: foreach ($users as $u): ?>
          <tr>
            <td><?php echo htmlspecialchars($u['user_no']); ?></td>
            <td><?php echo htmlspecialchars($u['user_name']); ?></td>
            <td><span class="badge bg-<?php echo $u['user_type']==='admin'?'danger':'secondary'; ?>"><?php echo htmlspecialchars($u['user_type']); ?></span></td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary" href="edit.php?user_no=<?php echo urlencode($u['user_no']); ?><?php echo $embed ? '&embed=1' : '';?>">تعديل</a>
              <a class="btn btn-sm btn-outline-danger" href="delete.php?user_no=<?php echo urlencode($u['user_no']); ?><?php echo $embed ? '&embed=1' : '';?>" onclick="return confirm('هل أنت متأكد من حذف هذا الموظف؟');">حذف</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php 
// تحميل السكربتات عند العرض داخل iframe
if ($embed) {
    echo '<script src="/assets/js/bootstrap.bundle.min.js"></script>';
}
if (!$embed) { include __DIR__ . '/../../includes/footer.php'; }
if ($embed) {
    echo '<script>(function(){ try { var p=new URLSearchParams(window.location.search); var m=p.get("msg"); var e=p.get("error"); var el=document.getElementById("embedToast"); var body=document.getElementById("embedToastBody"); if(!el||!body) return; if(m||e){ var isErr=!!e; body.textContent=(isErr?e:m); el.classList.remove("bg-success","bg-danger"); el.classList.add(isErr?"bg-danger":"bg-success"); var t=(window.bootstrap&&bootstrap.Toast)?new bootstrap.Toast(el):null; if(t) t.show(); } } catch(_){} })();</script>';
}
?>
