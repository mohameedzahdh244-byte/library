<?php
require_once 'config/init.php';
$page_title = "مكتبة بلدية الخليل - الصفحة الرئيسية";
$body_class = 'home-page';
$analytics_page = 'home';
include 'includes/header.php';

// جلب الإحصائيات الحقيقية من قاعدة البيانات
$stats = [
    'books' => 60000,
    'members' => 25000,
    'years' => 55
];

if (isset($conn)) {
    try {
        // عدد الكتب الحقيقي من جدول book
        $result = $conn->query("SELECT COUNT(*) as count FROM book");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['books'] = (int)$row['count'];
        }
        
        // عدد الأعضاء الحقيقي من جدول customer
        $result = $conn->query("SELECT COUNT(*) as count FROM customer");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['members'] = (int)$row['count'];
        }
        
        // حساب سنوات الخدمة (من 1970 إلى السنة الحالية)
        $stats['years'] = date('Y') - 1970;
        
    } catch (Exception $e) {
        // في حالة خطأ، استخدام القيم الافتراضية
        error_log("خطأ في جلب الإحصائيات: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
</head>
<body>
    <!-- القسم الرئيسي المحدث -->
<section class="hero-section position-relative overflow-hidden">
    <div class="hero-bg-overlay"></div>
    <div class="container py-5">
        <div class="row align-items-center min-vh-75">
            <!-- المحتوى الرئيسي -->
            <div class="col-lg-6 hero-content text-center">
                <!-- الشعار الرئيسي -->
                <div class="hero-logo-container mb-4 text-center">
                    <img src="public/logo.png" alt="شعار مكتبة بلدية الخليل" class="hero-logo img-fluid animate-fade-in w-25">
                </div>
                
                <!-- العنوان الرئيسي -->
                <h1 class="hero-title display-3 fw-bold text-white mb-3 animate-slide-up">
                    مكتبة بلدية الخليل 
                </h1>
                
                <!-- العنوان الفرعي -->
                <h2 class="hero-subtitle h3 text-white mb-4 fw-normal animate-slide-up">
                    <span class="text-primary-light">بوابتك إلى عالم المعرفة</span>
                </h2>
                
                <!-- الوصف -->
                <p class="hero-description lead text-white mb-5 lh-lg animate-fade-in-delay">
                    مكتبة عريقة تأسست عام 1970، تضم أكثر من 60 ألف كتاب ومرجع علمي في بيئة أكاديمية حديثة 
                    تلبي احتياجات الباحثين والطلاب وعشاق القراءة من جميع أنحاء فلسطين
                </p>
                
                <!-- أزرار العمل -->
                <div class="hero-actions d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-lg-start animate-slide-up-delay">
                    <a href="auth/loginform.php" class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow-lg">
                        
                        ابدأ التصفح الآن
                        <i class="fas fa-sign-in-alt me-2"></i>
                    </a>
                    <a href="#about" class="btn btn-outline-light btn-lg px-5 py-3 rounded-pill">
                        
                        تعرف على المكتبة
                        <i class="fas fa-info-circle me-2"></i>
                    </a>
                </div>
            </div>
            
            <!-- الإحصائيات التفاعلية -->
            <div class="col-lg-6 mt-5 mt-lg-0">
                <div class="hero-stats-container">
                    <div class="row g-4">
                        <div class="col-6">
                            <div class="hero-stat-card text-center p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-20">
                                <div class="stat-icon mb-3">
                                    <i class="fas fa-book text-white fs-1" style="color: #ffffff !important; text-shadow: 0 0 10px rgba(59, 130, 246, 0.8);"></i>
                                </div>
                                <div class="stat-number display-4 fw-bold text-white mb-2 counter" data-target="<?php echo $stats['books']; ?>">0</div>
                                <div class="stat-label text-light fs-5">كتاب ومرجع</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-stat-card text-center p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-20">
                                <div class="stat-icon mb-3">
                                    <i class="fas fa-users text-white fs-1" style="color: #ffffff !important; text-shadow: 0 0 10px rgba(34, 197, 94, 0.8);"></i>
                                </div>
                                <div class="stat-number display-4 fw-bold text-white mb-2 counter" data-target="<?php echo $stats['members']; ?>">0</div>
                                <div class="stat-label text-light fs-5">مشترك </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-stat-card text-center p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-20">
                                <div class="stat-icon mb-3">
                                    <i class="fas fa-clock text-warning fs-1"></i>
                                </div>
                                <div class="stat-number display-4 fw-bold text-white mb-2">24/7</div>
                                <div class="stat-label text-light fs-5">بحث متاح</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-stat-card text-center p-4 rounded-4 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-20">
                                <div class="stat-icon mb-3">
                                    <i class="fas fa-history text-info fs-1"></i>
                                </div>
                                <div class="stat-number display-4 fw-bold text-white mb-2 counter" data-target="<?php echo $stats['years']; ?>">0</div>
                                <div class="stat-label text-light fs-5">عام من الخدمة</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- قسم المعلومات الأساسية -->
<section id="about" class="py-5 bg-light">
    <div class="container">
        <!-- عنوان القسم الرئيسي -->
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="display-4 fw-bold text-dark mb-3">
                    <i class="fas fa-university text-primary me-3"></i>
                    تعرف على مكتبة بلدية الخليل
                </h2>
                <div class="bg-primary mx-auto mb-4" style="height: 3px; width: 100px; border-radius: 2px;"></div>
                <p class="lead text-muted fs-4 mb-0">مؤسسة ثقافية عريقة تخدم المجتمع منذ أكثر من نصف قرن</p>
            </div>
        </div>

        <!-- الشبكة التفاعلية للمعلومات -->
        <div class="row g-4">
            <!-- نبذة عن المكتبة -->
            <div class="col-lg-12 mb-4">
                <div class="info-card h-100 p-5 rounded-4 shadow-lg bg-white border-0 position-relative overflow-hidden">
                    <div class="card-bg-pattern"></div>
                    <div class="position-relative z-index-2">
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-wrapper me-4">
                                <i class="fas fa-university text-primary fs-1"></i>
                            </div>
                            <div>
                                <h3 class="h2 fw-bold text-dark mb-2">حول مكتبة بلدية الخليل</h3>
                                <div class="bg-primary" style="height: 2px; width: 60px; border-radius: 1px;"></div>
                            </div>
                        </div>
                        <p class="fs-5 text-dark lh-lg mb-0 fw-medium">
                            مكتبة بلدية الخليل العامة مؤسسة ثقافية تعليمية تربوية اجتماعية هادفة، تعمل على تنظيم وإتاحة الكتاب والمعرفة لجميع شرائح المجتمع بمختلف مستوياتهم العمرية والعلمية والثقافية، وإعداد وتنشئة جيل مثقف واع قادر على تحمل مسؤولياته في المستقبل.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- الشبكة ثلاثية الأعمدة -->
        <div class="row g-4 mt-2">
            <!-- الرسالة -->
            <div class="col-lg-4">
                <div class="info-card h-100 p-4 rounded-4 shadow bg-white border-0 text-center hover-lift">
                    <div class="icon-circle mx-auto mb-4 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; border-radius: 50%;">
                        <i class="fas fa-bullseye text-primary fs-2"></i>
                    </div>
                    <h4 class="h3 fw-bold text-dark mb-3">رسالتنا</h4>
                    <div class="bg-primary mx-auto mb-3" style="height: 2px; width: 40px; border-radius: 1px;"></div>
                    <p class="text-dark lh-lg mb-0">
                        توسيع قدرة التعلم لدى الطلبة والباحثين والمواطنين، وتعزيز قدراتهم التعليمية والبحثية من خلال تسهيل الوصول إلى مجموعة واسعة من المعارف البشرية والمعلومات والأفكار في بيئة مؤاتية وداعمة.
                    </p>
                </div>
            </div>

            <!-- التأسيس -->
            <div class="col-lg-4">
                <div class="info-card h-100 p-4 rounded-4 shadow bg-white border-0 text-center hover-lift">
                    <div class="icon-circle mx-auto mb-4 bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; border-radius: 50%;">
                        <i class="fas fa-calendar-alt text-success fs-2"></i>
                    </div>
                    <h4 class="h3 fw-bold text-dark mb-3">تأسيس المكتبة</h4>
                    <div class="bg-success mx-auto mb-3" style="height: 2px; width: 40px; border-radius: 1px;"></div>
                    <p class="text-dark lh-lg mb-0">
                        أنشئت المكتبة عام ألف وتسعمائة وسبعون، تحديداً في الثلاثين من تموز [30/7/1970م].
                    </p>
                </div>
            </div>

            <!-- الرؤية -->
            <div class="col-lg-4">
                <div class="info-card h-100 p-4 rounded-4 shadow bg-white border-0 text-center hover-lift">
                    <div class="icon-circle mx-auto mb-4 bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; border-radius: 50%;">
                        <i class="fas fa-eye text-info fs-2"></i>
                    </div>
                    <h4 class="h3 fw-bold text-dark mb-3">رؤية المكتبة</h4>
                    <div class="bg-info mx-auto mb-3" style="height: 2px; width: 40px; border-radius: 1px;"></div>
                    <p class="text-dark lh-lg mb-0">
                        نطمح أن تكون المكتبة أفضل مزود لخدمات المعلومات في فلسطين، والوصول لأوسع شريحة ممكنة من أبناء المجتمع، وأن نلهم الباحثين والقراء روح البحث ومتعة القراءة.
                    </p>
                </div>
            </div>
        </div>

        <!-- الأهداف الإستراتيجية -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="info-card p-5 rounded-4 shadow-lg bg-white border-0">
                    <div class="text-center mb-5">
                        <h3 class="display-5 fw-bold mb-3 text-dark">
                            <i class="fas fa-target me-3 text-primary"></i>
                            الأهداف الإستراتيجية
                        </h3>
                        <div class="bg-primary mx-auto mb-3" style="height: 3px; width: 100px; border-radius: 2px;"></div>
                        <p class="lead mb-0 text-muted">خطتنا لخدمة المجتمع وتطوير المعرفة</p>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="goal-item text-center p-4 rounded-3 bg-white border-0 shadow-lg hover-lift position-relative overflow-hidden">
                                <div class="goal-bg-pattern bg-warning bg-opacity-5"></div>
                                <div class="icon-circle mx-auto mb-4 position-relative" style="width: 100px; height: 100px;">
                                    <div class="icon-bg bg-warning rounded-circle d-flex align-items-center justify-content-center shadow-lg position-relative" style="width: 100%; height: 100%;">
                                        <i class="fas fa-users fs-1 text-white"></i>
                                    </div>
                                </div>
                                <h5 class="fw-bold mb-3 text-dark">
                                    <i class="fas fa-handshake text-warning me-2"></i>
                                    التفاعل المجتمعي
                                </h5>
                                <div class="bg-warning mx-auto mb-3" style="height: 3px; width: 50px; border-radius: 2px;"></div>
                                <p class="mb-0 text-muted lh-lg">مكتبة بلدية الخليل أكثر تفاعلاً مع المجتمع المحلي ومنبر لقضايا المجتمع</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="goal-item text-center p-4 rounded-3 bg-white border-0 shadow-lg hover-lift position-relative overflow-hidden">
                                <div class="goal-bg-pattern bg-success bg-opacity-5"></div>
                                <div class="icon-circle mx-auto mb-4 position-relative" style="width: 100px; height: 100px;">
                                    <div class="icon-bg bg-success rounded-circle d-flex align-items-center justify-content-center shadow-lg position-relative" style="width: 100%; height: 100%;">
                                        <i class="fas fa-book-open fs-1 text-white"></i>
                                    </div>
                                </div>
                                <h5 class="fw-bold mb-3 text-dark">
                                    <i class="fas fa-book-reader text-success me-2"></i>
                                    ترسيخ القراءة
                                </h5>
                                <div class="bg-success mx-auto mb-3" style="height: 3px; width: 50px; border-radius: 2px;"></div>
                                <p class="mb-0 text-muted lh-lg">اجتذاب رواد جدد للمكتبة وترسيخ عادة القراءة في المجتمع</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="goal-item text-center p-4 rounded-3 bg-white border-0 shadow-lg hover-lift position-relative overflow-hidden">
                                <div class="goal-bg-pattern bg-info bg-opacity-5"></div>
                                <div class="icon-circle mx-auto mb-4 position-relative" style="width: 100px; height: 100px;">
                                    <div class="icon-bg bg-info rounded-circle d-flex align-items-center justify-content-center shadow-lg position-relative" style="width: 100%; height: 100%;">
                                        <i class="fas fa-laptop fs-1 text-white"></i>
                                    </div>
                                </div>
                                <h5 class="fw-bold mb-3 text-dark">
                                    <i class="fas fa-laptop text-info me-2"></i>
                                    التطوير الرقمي
                                </h5>
                                <div class="bg-info mx-auto mb-3" style="height: 3px; width: 50px; border-radius: 2px;"></div>
                                <p class="mb-0 text-muted lh-lg">تطوير القدرات الإلكترونية للمكتبة لخدمة الباحثين والقرّاء عن بعد</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- قسم معلومات التواصل -->
<section id="contact" class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center mb-4">
                <div class="mb-4">
                    <h2 class="display-4 fw-bold text-primary mb-3">
                        <i class="fas fa-phone-alt me-3"></i>
                        معلومات التواصل
                    </h2>
                    <div class="bg-primary mx-auto" style="height: 3px; width: 80px; border-radius: 2px;"></div>
                </div>
                <p class="fs-5 fw-medium text-dark">تواصل معنا للاستفسار عن خدماتنا أو للحصول على المساعدة</p>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-3 col-sm-6">
                <div class="card text-center h-100 shadow-lg border-0 bg-gradient">
                    <div class="card-body p-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 60px; height: 60px;">
                            <i class="fas fa-map-marker-alt fa-lg"></i>
                        </div>
                        <h5 class="fw-bold text-primary mb-3">العنوان</h5>
                        <p class="fs-6 fw-medium text-dark mb-0">الخليل، فلسطين<br>بئر الحمص</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-center h-100 shadow-lg border-0 bg-gradient">
                    <div class="card-body p-4">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 60px; height: 60px;">
                            <i class="fas fa-phone fa-lg"></i>
                        </div>
                        <h5 class="fw-bold text-success mb-3">الهاتف</h5>
                        <p class="fs-6 fw-medium text-dark mb-0" dir="ltr">02 222 0639</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-center h-100 shadow-lg border-0 bg-gradient">
                    <div class="card-body p-4">
                        <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 60px; height: 60px;">
                            <i class="fas fa-envelope fa-lg"></i>
                        </div>
                        <h5 class="fw-bold text-info mb-3">البريد الإلكتروني</h5>
                        <p class="mb-0">
                            <a class="fs-6 fw-medium text-dark text-decoration-none" href="https://mail.google.com/mail/?view=cm&fs=1&to=hebronlib@gmail.com" target="_blank">hebronlib@gmail.com</a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-center h-100 shadow-lg border-0 bg-gradient">
                    <div class="card-body p-4">
                        <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 60px; height: 60px;">
                            <i class="fas fa-globe fa-lg"></i>
                        </div>
                        <h5 class="fw-bold text-warning mb-3">الموقع الإلكتروني</h5>
                        <p class="mb-0">
                            <a class="fs-6 fw-medium text-dark text-decoration-none" target="_blank" href="http://www.hebron-city.ps/">hebron-city.ps</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- تضمين مودال تفاصيل الكتاب للعرض في الصفحة التعريفية -->
<?php include 'includes/book-details-modal.php'; ?>

<?php include 'includes/footer.php'; ?>

<script>
  // تحديد نوع المستخدم للصفحة التعريفية
  window.currentUserType = 'guest';

  // فتح مودال تفاصيل الكتاب عند النقر على أي عنصر يحمل data-book-serial
  document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e){
      const el = e.target.closest('[data-book-serial]');
      if (!el) return;
      // تجاهل النقر على العناصر التفاعلية الداخلية
      if (e.target.closest('a, button, form, input, select, textarea, label')) return;
      const serial = el.getAttribute('data-book-serial');
      if (serial && typeof showBookDetails === 'function') {
        e.preventDefault();
        showBookDetails(serial);
      }
    });
  });
</script>
    <!-- تضمين ملف JavaScript للتأثيرات -->
    <script src="assets/js/homepage-animations.js"></script>
</body>
</html>



