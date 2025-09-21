<?php
// تم حذف نظام المسارات الذكي - استخدام مسارات نسبية مباشرة
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - مكتبة بلدية الخليل</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../public/logo.ico">
    
    <!-- منع الكاش في صفحة تسجيل الدخول -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <?php
 include '../includes/header.php'; ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="../public/logo2.png" alt="شعار المكتبة" class="mb-3" style="height: 64px; width: auto;">
                        <h3 class="fw-bold">تسجيل الدخول</h3>
                        <p class="text-muted">مكتبة بلدية الخليل</p>
                    </div>
                    
                    <form method="POST" action="login.php" id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">اسم المستخدم أو رقم العضوية</label>
                            <div class="modern-input-group">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="modern-form-control" id="username" name="username" placeholder="اسم المستخدم"
                                        autofocus value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                       
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">كلمة المرور</label>
                            <div class="modern-input-group">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="modern-form-control" id="password" name="password" placeholder="كلمة المرور" >
                                <i class="fas fa-eye toggle-password-icon" id="toggleIcon"></i>
                            </div>
                        </div>
                        <div id="alert-login-placeholder" class="mb-2"></div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                        تسجيل الدخول
                            <i class="fas fa-sign-in-alt me-2"></i>  
                        </button>
                        

                        <div class="text-center">
                            <a href="../index.php" class="text-muted text-decoration-none d-inline-flex align-items-center" dir="ltr">
                                <i class="fas fa-arrow-left me-1"></i>
                                العودة للصفحة الرئيسية
                            </a>
                        </div>
                     
                       
                        

                
                
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

 <script>
    <?php
    $notifyMsg = '';
    $notifyType = 'error';
    if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
        $notifyMsg = 'يجب تسجيل الدخول أولاً أو ليس لديك صلاحية الوصول.';
    } elseif (!empty($error)) {
        $notifyMsg = $error;
    }
    if ($notifyMsg) {
        echo "window.addEventListener('DOMContentLoaded',function(){showNotification('".addslashes($notifyMsg)."','$notifyType');});";
    }
    ?>
    // منع الرجوع عبر زر المتصفح من صفحة تسجيل الدخول (بعد تسجيل الخروج)
    (function(){
      if (window.history && history.pushState) {
        history.replaceState(null, document.title, location.href);
        history.pushState(null, document.title, location.href);
        window.addEventListener('popstate', function () {
          history.pushState(null, document.title, location.href);
        });
      }
    })();
    </script>
<script type="text/javascript" src="../assets/js/login.js"></script>


<?php include '../includes/footer.php'; ?>
</body>
</html>