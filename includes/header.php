<?php
// إزالة نظام المسارات الذكي - استخدام مسارات نسبية مباشرة
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'مكتبة بلدية الخليل'; ?></title>
    
    <link rel="icon" type="image/x-icon" href="../public/logo.ico">

    <!-- Bootstrap CSS -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <!-- Font Awesome (Local) للأيقونات -->
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    
    <!-- Google Fonts Cairo (Local) -->
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <!-- Header CSS -->
    <link rel="stylesheet" href="../assets/css/header.css">
</head>
<body class="<?php echo isset($body_class) ? $body_class : ''; ?>" data-analytics-page="<?php echo isset($analytics_page) ? htmlspecialchars($analytics_page) : ''; ?>">
    <!-- شريط التنقل العلوي المحسن -->
    <nav id="mainNavbar" class="navbar navbar-expand-lg navbar-dark bg-gradient shadow-sm<?php echo (isset($body_class) && $body_class === 'home-page') ? ' sticky-top' : ''; ?>" 
         style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1565c0 100%) !important; backdrop-filter: blur(10px);">
        <div class="container-fluid px-3 px-lg-4">
            <!-- شعار المكتبة -->
            <a class="navbar-brand d-flex align-items-center fw-bold py-2" href="../index.php" 
               style="transition: all 0.3s ease; text-decoration: none;">
                <div class="d-flex align-items-center">
                    <img src="../public/logo.png" alt="شعار المكتبة" 
                         class="me-5" 
                         style="height: 32px; width: auto; margin-left: 20px;">
                    <div class="d-flex flex-column">
                        <span class="fs-5 text-white mb-0">مكتبة بلدية الخليل</span>
                        <small class="text-white-50 d-none d-md-block" style="font-size: 0.75rem;">مكتبة رقمية متطورة</small>
                    </div>
                </div>
            </a>
        
            <!-- زر القائمة للموبايل -->
            <button class="navbar-toggler border-0 p-2 rounded-3 shadow-sm" type="button" 
                    data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    style="background: rgba(255,255,255,0.1); backdrop-filter: blur(5px);">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- قائمة التنقل -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- روابط التنقل الرئيسية -->
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 py-2 rounded-pill fw-semibold position-relative" 
                           href="../index.php" style="transition: all 0.3s ease;">
                            <i class="fas fa-home me-1"></i>
                            الرئيسية
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 py-2 rounded-pill fw-semibold position-relative" 
                           href="../includes/search.php" style="transition: all 0.3s ease;">
                            <i class="fas fa-search me-1"></i>
                            بحث عن كتب
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 py-2 rounded-pill fw-semibold position-relative" 
                           href="../includes/activities_public.php" style="transition: all 0.3s ease;">
                            <i class="fas fa-calendar-alt me-1"></i>
                            الأنشطة الثقافية
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 py-2 rounded-pill fw-semibold position-relative" 
                           href="../index.php#about" style="transition: all 0.3s ease;">
                            <i class="fas fa-info-circle me-1"></i>
                            حول المكتبة
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-3 py-2 rounded-pill fw-semibold position-relative" 
                           href="../index.php#contact" style="transition: all 0.3s ease;">
                            <i class="fas fa-envelope me-1"></i>
                            اتصل بنا
                        </a>
                    </li>
                </ul>
                
                <!-- قسم المستخدم والإعدادات -->
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                        <li class="nav-item dropdown mx-1">
                            <a class="nav-link dropdown-toggle d-flex align-items-center px-3 py-2 rounded-pill fw-semibold" 
                               href="#" role="button" data-bs-toggle="dropdown"
                               style="background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); transition: all 0.3s ease;">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-2"
                                         style="width: 32px; height: 32px;">
                                        <i class="fas fa-user text-primary"></i>
                                    </div>
                                    <span class="d-none d-lg-inline"><?php echo $_SESSION['user_name']; ?></span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2"
                                style="background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); min-width: 200px;">
                                <li class="px-3 py-2 border-bottom">
                                    <small class="text-muted">مرحباً</small>
                                    <div class="fw-bold text-dark"><?php echo $_SESSION['user_name']; ?></div>
                                </li>
                                <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'staff'): ?>
                                    <li>
                                        <a class="dropdown-item py-2 rounded-2 mx-2 my-1" href="../admin/dashboard.php">
                                            <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                                            لوحة التحكم
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li>
                                        <a class="dropdown-item py-2 rounded-2 mx-2 my-1" href="../member/dashboard.php">
                                            <i class="fas fa-user-circle me-2 text-success"></i>
                                            حسابي
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider mx-2"></li>
                                <li>
                                    <a class="dropdown-item py-2 rounded-2 mx-2 my-1 text-danger" href="../auth/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>
                                        تسجيل الخروج
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item mx-1">
                            <a class="nav-link d-flex align-items-center px-4 py-2 rounded-pill fw-semibold" 
                               href="../auth/loginform.php"
                               style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px); transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                تسجيل الدخول
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- مفتاح الوضع الليلي المحسن -->
                    <li class="nav-item mx-1">
                        <button class="btn btn-outline-light rounded-circle p-2 shadow-sm position-relative" 
                                id="themeToggle" 
                                style="width: 40px; height: 40px; background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2); transition: all 0.3s ease;">
                            <i class="fas fa-moon" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <script src="../assets/js/analytics.js"></script>
    <?php if (isset($body_class) && $body_class === 'home-page'): ?>
    <script>
      (function(){
        const nav = document.getElementById('mainNavbar');
        if(!nav) return;
        function onScroll(){
          if (window.scrollY > 10) nav.classList.add('shadow-sm');
          else nav.classList.remove('shadow-sm');
        }
        window.addEventListener('scroll', onScroll, {passive:true});
        onScroll();
      })();
    </script>
    <?php endif; ?>