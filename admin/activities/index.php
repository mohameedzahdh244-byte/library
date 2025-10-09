<?php
require_once '../../config/init.php';
checkStaffPermission();

// جلب التصنيفات من activity_categories (المفعّلة فقط)
$types = [];
$res = $conn->query("SELECT id, name_ar AS name FROM activity_categories WHERE is_active = 1 ORDER BY name_ar ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $types[] = $row;
    }
}
$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>إدارة الأنشطة</title>
  <link href="<?= CSS_URL ?>/bootstrap.css" rel="stylesheet">
  <link href="<?= CSS_URL ?>/style.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/bootstrap-icons/font/bootstrap-icons.css">
  <link rel="icon" type="image/x-icon" href="<?= PUBLIC_URL ?>/logo.ico">
  
  <!-- Flatpickr CSS for enhanced date/time picker (Local) -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/flatpickr/css/flatpickr.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/flatpickr/themes/material_blue.css">
  
  <style>
    
    /* ===== CSS Variables ===== */
    :root {
      --primary: #667eea;
      --primary-dark: #5a67d8;
      --secondary: #764ba2;
      --success: #48bb78;
      --warning: #ed8936;
      --danger: #f56565;
      --info: #4299e1;
      --light: #f7fafc;
      --dark: #2d3748;
      
      --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --gradient-success: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
      --gradient-warning: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
      --gradient-danger: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
      --gradient-info: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
      
      --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
      --shadow-lg: 0 8px 25px rgba(0,0,0,0.15);
      --shadow-xl: 0 12px 40px rgba(0,0,0,0.2);
      
      --border-radius-sm: 8px;
      --border-radius-md: 12px;
      --border-radius-lg: 16px;
      --border-radius-xl: 20px;
      
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* ===== Global Styles ===== */
    body {
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
    }

    /* ===== Modern Card System ===== */
    .card-premium {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: var(--border-radius-xl);
      box-shadow: var(--shadow-lg);
      transition: var(--transition);
      overflow: hidden;
    }

    .card-premium:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
    }

    .card-premium .card-header {
      background: var(--gradient-primary);
      border: none;
      padding: 1.5rem 2rem;
      position: relative;
      overflow: hidden;
    }

    .card-premium .card-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
      pointer-events: none;
    }

    .card-premium .card-body {
      padding: 2rem;
    }

    /* ===== Enhanced Form Controls ===== */
    .form-floating-modern {
      position: relative;
      margin-bottom: 1.5rem;
    }

    .form-floating-modern .form-control,
    .form-floating-modern .form-select {
      height: 58px;
      padding: 1rem 1.25rem 0.5rem;
      border: 2px solid #e2e8f0;
      border-radius: var(--border-radius-md);
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(10px);
      transition: var(--transition);
      font-size: 1rem;
      line-height: 1.5;
    }

    .form-floating-modern .form-control:focus,
    .form-floating-modern .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      background: rgba(255, 255, 255, 0.95);
      transform: translateY(-2px);
    }

    .form-floating-modern label {
      position: absolute;
      top: 0;
      right: 1.25rem;
      height: 100%;
      padding: 1rem 0;
      pointer-events: none;
      border: none;
      transform-origin: 0 0;
      transition: var(--transition);
      color: #718096;
      font-weight: 500;
    }

    .form-floating-modern .form-control:focus ~ label,
    .form-floating-modern .form-control:not(:placeholder-shown) ~ label,
    .form-floating-modern .form-select:focus ~ label,
    .form-floating-modern .form-select:not([value=""]) ~ label {
      opacity: 0.85;
      transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
      color: var(--primary);
    }

    /* ===== Enhanced Buttons ===== */
    .btn-premium {
      padding: 0.875rem 2rem;
      border-radius: var(--border-radius-md);
      font-weight: 600;
      font-size: 0.95rem;
      letter-spacing: 0.025em;
      transition: var(--transition);
      border: none;
      position: relative;
      overflow: hidden;
    }

    .btn-premium::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.5s;
    }

    .btn-premium:hover::before {
      left: 100%;
    }

    .btn-primary-premium {
      background: var(--gradient-primary);
      color: white;
    }

    .btn-primary-premium:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
      color: white;
    }

    .btn-success-premium {
      background: var(--gradient-success);
      color: white;
    }

    .btn-success-premium:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4);
      color: white;
    }

    .btn-outline-premium {
      background: rgba(255, 255, 255, 0.8);
      border: 2px solid #e2e8f0;
      color: var(--dark);
      backdrop-filter: blur(10px);
    }

    .btn-outline-premium:hover {
      background: rgba(255, 255, 255, 0.95);
      border-color: var(--primary);
      color: var(--primary);
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
    }

    /* ===== Header Section ===== */
    .hero-section {
      background: var(--gradient-primary);
      border-radius: var(--border-radius-xl);
      padding: 1rem 2rem !important;
      margin-bottom: 2rem;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .hero-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml;charset=utf-8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="25" cy="75" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="75" cy="25" r="1" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
      opacity: 0.3;
    }

    .hero-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin-bottom: 1rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .stats-pill {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 50px;
      padding: 0.5rem 1rem;
      color: white;
      font-size: 0.8rem;
      font-weight: 500;
    }

    /* ===== Preview Card ===== */
    .preview-container {
      background: linear-gradient(145deg, #ffffff, #f8fafc);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      position: relative;
      border: 1px solid #e2e8f0;
      box-shadow: var(--shadow-md);
    }

    .preview-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--gradient-primary);
      border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .badge-premium {
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.8rem;
      letter-spacing: 0.025em;
      text-transform: uppercase;
    }

    /* ===== Date/Time Picker Enhancements ===== */
    .flatpickr-input {
      background: rgba(255, 255, 255, 0.8) !important;
      backdrop-filter: blur(10px) !important;
      border: 2px solid #e2e8f0 !important;
      border-radius: var(--border-radius-md) !important;
      transition: var(--transition) !important;
    }

    .flatpickr-input:focus {
      border-color: var(--primary) !important;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
      transform: translateY(-2px) !important;
    }

    /* ===== Responsive Design ===== */
    @media (max-width: 768px) {
      .hero-section {
        padding: 1rem 1.5rem;
        text-align: center;
      }
      
      .card-premium .card-body {
        padding: 1.5rem;
      }
      
      .btn-premium {
        width: 100%;
        margin-bottom: 0.5rem;
      }
      
      .hero-icon {
        margin: 0 auto 1rem;
      }
    }

    /* ===== Animation Classes ===== */
    .fade-in {
      animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .slide-up {
      animation: slideUp 0.6s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>
<div class="container <?php echo $isEmbed ? 'p-3' : 'py-4'; ?>" dir="rtl">
  <!-- Hero Section -->
  <div class="hero-section fade-in" style="padding: 1rem 2rem !important; min-height: auto !important;">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <div class="d-flex align-items-center mb-1">
          <div class="hero-icon me-4">
            <i class="fas fa-bullhorn"></i>
          </div>
          <div>
            <h1 class="h4 mb-1 fw-bold">إدارة الأنشطة</h1>
            <p class="mb-0 opacity-90 fs-6">إنشاء ونشر الأنشطة الثقافية والاجتماعية بتصميم احترافي عالمي</p>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="d-flex flex-wrap gap-2 justify-content-lg-end justify-content-center">
          <div class="stats-pill">
            <i class="fas fa-rocket me-2"></i>نشر فوري
          </div>
          <div class="stats-pill">
            <i class="fas fa-eye me-2"></i>معاينة مباشرة
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card card-premium slide-up">
        <div class="card-header text-white">
          <h2 class="h5 d-flex align-items-center gap-2 m-0">
            <i class="fas fa-plus-circle"></i>
            إنشاء نشاط جديد
          </h2>
        </div>
        <div class="card-body">
          <form id="activityForm">
            
            <!-- Category Selection -->
            <div class="form-floating-modern">
              <select class="form-select" name="type_id" id="type_id" required>
                <option value="">اختر التصنيف</option>
                <?php foreach ($types as $t): ?>
                  <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
              <label><i class="fas fa-tags text-primary me-2"></i>تصنيف النشاط</label>
              <div class="mt-2">
                <button type="button" class="btn btn-premium btn-primary-premium" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                  <i class="fas fa-plus me-1"></i>إضافة تصنيف جديد
                </button>
              </div>
            </div>

            <!-- Activity Title -->
            <div class="form-floating-modern">
              <input type="text" class="form-control" name="title" id="title"required>
              <label><i class="fas fa-heading text-primary me-2"></i>عنوان النشاط</label>
            </div>

            <!-- Location -->
            <div class="form-floating-modern">
              <input type="text" class="form-control" name="location" id="location" required>
              <label><i class="fas fa-map-marker-alt text-success me-2"></i>مكان النشاط</label>
            </div>

            <!-- Date and Time Row -->
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <div class="form-floating-modern">
                  <input type="text" class="form-control flatpickr-datetime" name="start_datetime" id="start_datetime"  required>
                  <label><i class="fas fa-calendar-plus text-info me-2"></i>تاريخ ووقت البداية</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating-modern">
                  <input type="text" class="form-control flatpickr-datetime" name="end_datetime" id="end_datetime" required>
                  <label><i class="fas fa-calendar-check text-warning me-2"></i>تاريخ ووقت النهاية</label>
                </div>
              </div>
            </div>

            <!-- Supervisors -->
            <div class="form-floating-modern">
              <input type="text" class="form-control" name="supervisors" id="supervisors">
              <label><i class="fas fa-users text-info me-2"></i>أسماء المشرفين (اختياري)</label>
              <div class="form-text mt-2">
                <i class="fas fa-info-circle text-muted me-1"></i>
                افصل بين الأسماء بفواصل (مثال: أحمد، علي، فاطمة)
              </div>
            </div>

            <!-- Payment Row -->
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <div class="form-floating-modern">
                  <select class="form-select" name="is_paid" id="is_paid">
                    <option value="0">🆓 مجاني</option>
                    <option value="1">💰 مدفوع</option>
                  </select>
                  <label><i class="fas fa-money-bill-wave text-warning me-2"></i>نوع الرسوم</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating-modern">
                  <input type="number" step="0.01" min="0" class="form-control" name="fee_amount" id="fee_amount" placeholder="0.00">
                  <label><i class="fas fa-coins text-warning me-2"></i>قيمة الرسوم</label>
                </div>
              </div>
            </div>

            <!-- Description -->
            <div class="form-floating-modern">
              <textarea class="form-control" name="description" id="description" style="height: 120px;" ></textarea>
              <label><i class="fas fa-file-text text-secondary me-2"></i>تفاصيل النشاط</label>
              <div class="form-text mt-2">
                <i class="fas fa-lightbulb text-muted me-1"></i>
                اكتب وصفاً شاملاً للنشاط، الأهداف، والمتطلبات
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-3 justify-content-center pt-3">
              <button type="submit" class="btn btn-premium btn-outline-premium">
                <i class="fas fa-save me-2"></i>
                حفظ كمسودة
              </button>
              <button type="button" id="publishBtn" class="btn btn-premium btn-success-premium">
                <i class="fas fa-rocket me-2"></i>
                نشر النشاط
              </button>
            </div>

          </form>
          
          <!-- Alert Container -->
          <div id="formAlert" class="mt-4" style="display:none"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card card-premium slide-up">
        <div class="card-header text-white">
          <h2 class="h5 d-flex align-items-center gap-2 m-0">
            <i class="fas fa-eye"></i>
            معاينة مباشرة
          </h2>
        </div>
        <div class="card-body">
          <div class="preview-container">
            
            <!-- Preview Badges -->
            <div class="d-flex flex-wrap gap-2 mb-4">
              <span class="badge badge-premium bg-primary" id="prevType">تصنيف النشاط</span>
              <span class="badge badge-premium bg-success d-none" id="prevFree">
                <i class="fas fa-gift me-1"></i>مجاني
              </span>
              <span class="badge badge-premium bg-warning text-dark d-none" id="prevPaid">
                <i class="fas fa-money-bill me-1"></i>مدفوع
              </span>
            </div>

            <!-- Preview Title -->
            <h3 class="h4 fw-bold mb-4 text-dark" id="prevTitle" style="color: var(--primary) !important;">
              عنوان النشاط سيظهر هنا
            </h3>

            <!-- Preview Details -->
            <div class="row g-3 mb-4">
              <div class="col-12">
                <div class="d-flex align-items-center p-3 rounded-3" style="background: rgba(102, 126, 234, 0.1);">
                  <div class="me-3">
                    <i class="fas fa-map-marker-alt text-danger fs-5"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">المكان</small>
                    <span class="fw-semibold" id="prevLocation">سيتم تحديد المكان</span>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="d-flex align-items-center p-3 rounded-3" style="background: rgba(72, 187, 120, 0.1);">
                  <div class="me-3">
                    <i class="fas fa-calendar-plus text-success fs-5"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">البداية</small>
                    <span class="fw-semibold" id="prevStart">غير محدد</span>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="d-flex align-items-center p-3 rounded-3" style="background: rgba(237, 137, 54, 0.1);">
                  <div class="me-3">
                    <i class="fas fa-calendar-check text-warning fs-5"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">النهاية</small>
                    <span class="fw-semibold" id="prevEnd">غير محدد</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Supervisors Section -->
            <div class="mb-4 d-none" id="prevSupervisorsWrap">
              <div class="d-flex align-items-center p-3 rounded-3" style="background: rgba(66, 153, 225, 0.1);">
                <div class="me-3">
                  <i class="fas fa-users text-info fs-5"></i>
                </div>
                <div>
                  <small class="text-muted d-block">المشرفون</small>
                  <span class="fw-semibold" id="prevSupervisors"></span>
                </div>
              </div>
            </div>

            <!-- Description Section -->
            <div class="border-top pt-4">
              <h6 class="text-muted mb-3">
                <i class="fas fa-file-text me-2"></i>تفاصيل النشاط
              </h6>
              <p class="mb-0 text-secondary lh-lg" id="prevDesc" style="min-height: 60px;">
                تفاصيل النشاط ستظهر هنا عند كتابتها في النموذج...
              </p>
            </div>

          </div>
        </div>
      </div>
      
      <!-- Info Alert -->
      <div class="alert border-0 mt-4 p-4" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border-radius: var(--border-radius-lg);">
        <div class="d-flex align-items-start">
          <div class="me-3">
            <i class="fas fa-info-circle text-primary fs-4"></i>
          </div>
          <div>
            <h6 class="fw-bold text-primary mb-2">معاينة تفاعلية</h6>
            <p class="mb-0 text-muted small lh-lg">
              تتحدث هذه المعاينة تلقائياً مع كل تغيير في النموذج لتعطيك فكرة واضحة عن شكل النشاط النهائي.
              سيظهر النشاط للزوار بنفس هذا التصميم في الصفحة الرئيسية.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>إضافة تصنيف نشاط</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="categoryForm">
          <div class="mb-3">
            <label class="form-label">الاسم بالعربية</label>
            <input type="text" class="form-control" name="name_ar" required>
          </div>
          <div class="mb-3">
            <label class="form-label">الوصف (اختياري)</label>
            <textarea class="form-control" name="description" rows="2"></textarea>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
            <label class="form-check-label" for="is_active">مفعل</label>
          </div>
        </form>
        <div id="catAlert" class="alert mt-2 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
        <button type="button" id="saveCategoryBtn" class="btn btn-primary">حفظ التصنيف</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= JS_URL ?>/bootstrap.bundle.min.js"></script>
<!-- Flatpickr JS for enhanced date/time picker (Local) -->
<script src="<?= ASSETS_URL ?>/vendor/flatpickr/js/flatpickr.min.js"></script>
<script src="<?= ASSETS_URL ?>/vendor/flatpickr/l10n/ar.js"></script>
<script>
// ===== Initialize Variables =====
const form = document.getElementById('activityForm');
const publishBtn = document.getElementById('publishBtn');
const alertBox = document.getElementById('formAlert');
const categoryForm = document.getElementById('categoryForm');
const saveCategoryBtn = document.getElementById('saveCategoryBtn');
const catAlert = document.getElementById('catAlert');
const typeSelect = document.getElementById('type_id');

// Live preview elements
const prevType = document.getElementById('prevType');
const prevFree = document.getElementById('prevFree');
const prevPaid = document.getElementById('prevPaid');
const prevTitle = document.getElementById('prevTitle');
const prevLocation = document.getElementById('prevLocation');
const prevStart = document.getElementById('prevStart');
const prevEnd = document.getElementById('prevEnd');
const prevSupervisorsWrap = document.getElementById('prevSupervisorsWrap');
const prevSupervisors = document.getElementById('prevSupervisors');
const prevDesc = document.getElementById('prevDesc');

// ===== Initialize Enhanced Date/Time Pickers =====
document.addEventListener('DOMContentLoaded', function() {
  // Configure Flatpickr for Arabic locale
  flatpickr.localize(flatpickr.l10ns.ar);
  
  // Start DateTime Picker
  const startPicker = flatpickr("#start_datetime", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    time_24hr: true,
    locale: "ar",
    minDate: "today",
    defaultHour: 9,
    defaultMinute: 0,
    theme: "material_blue",
    onChange: function(selectedDates, dateStr, instance) {
      // Update end date minimum when start date changes
      if (selectedDates[0]) {
        endPicker.set('minDate', selectedDates[0]);
        // Auto-set end date to 2 hours after start if not set
        if (!endPicker.selectedDates[0]) {
          const endDate = new Date(selectedDates[0]);
          endDate.setHours(endDate.getHours() + 2);
          endPicker.setDate(endDate);
        }
      }
      updatePreview();
    }
  });
  
  // End DateTime Picker
  const endPicker = flatpickr("#end_datetime", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    time_24hr: true,
    locale: "ar",
    minDate: "today",
    defaultHour: 11,
    defaultMinute: 0,
    theme: "material_blue",
    onChange: function(selectedDates, dateStr, instance) {
      updatePreview();
    }
  });
  
  // Initialize preview
  updatePreview();
});

// ===== Enhanced Date Formatting =====
function formatDateTimeArabic(val) {
  if (!val) return 'غير محدد';
  try {
    const d = new Date(val);
    if (isNaN(d.getTime())) return val;
    
    // تنسيق التاريخ الميلادي العادي
    const year = d.getFullYear();
    const month = d.getMonth() + 1;
    const day = d.getDate();
    const hours = d.getHours().toString().padStart(2, '0');
    const minutes = d.getMinutes().toString().padStart(2, '0');
    
    // أسماء الأشهر بالعربية
    const monthNames = [
      'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
      'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
    ];
    
    return `${day} ${monthNames[month - 1]} ${year} - ${hours}:${minutes}`;
  } catch(e) { 
    return val; 
  }
}

// ===== Live Preview Update Function =====
function updatePreview() {
  // Category/Type
  const opt = typeSelect.options[typeSelect.selectedIndex];
  prevType.textContent = opt && opt.value ? opt.textContent : 'تصنيف النشاط';
  
  // Title
  const titleVal = form.querySelector('[name="title"]').value.trim();
  prevTitle.textContent = titleVal || 'عنوان النشاط سيظهر هنا';
  
  // Location
  const locVal = form.querySelector('[name="location"]').value.trim();
  prevLocation.textContent = locVal || 'سيتم تحديد المكان';
  
  // DateTime
  const startVal = form.querySelector('[name="start_datetime"]').value;
  const endVal = form.querySelector('[name="end_datetime"]').value;
  prevStart.textContent = formatDateTimeArabic(startVal);
  prevEnd.textContent = formatDateTimeArabic(endVal);
  
  // Supervisors
  const supVal = form.querySelector('[name="supervisors"]').value.trim();
  if (supVal) {
    prevSupervisorsWrap.classList.remove('d-none');
    prevSupervisors.textContent = supVal;
  } else {
    prevSupervisorsWrap.classList.add('d-none');
    prevSupervisors.textContent = '';
  }
  
  // Payment Status
  const isPaid = form.querySelector('[name="is_paid"]').value === '1';
  if (isPaid) {
    prevFree.classList.add('d-none');
    prevPaid.classList.remove('d-none');
  } else {
    prevPaid.classList.add('d-none');
    prevFree.classList.remove('d-none');
  }
  
  // Description
  const descVal = form.querySelector('[name="description"]').value.trim();
  prevDesc.textContent = descVal || 'تفاصيل النشاط ستظهر هنا عند كتابتها في النموذج...';
}

// ===== Event Listeners for Live Preview =====
['input', 'change', 'keyup'].forEach(evt => {
  form.addEventListener(evt, function(e) {
    updatePreview();
  });
});

// ===== Enhanced Alert Functions =====
function showAlert(type, message) {
  alertBox.className = `alert alert-${type} border-0 rounded-3`;
  alertBox.innerHTML = `
    <div class="d-flex align-items-center">
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
      <div>${message}</div>
    </div>
  `;
  alertBox.style.display = 'block';
  
  // Auto-hide success messages
  if (type === 'success') {
    setTimeout(() => {
      alertBox.style.display = 'none';
    }, 5000);
  }
}

function showCatAlert(type, message) {
  catAlert.className = `alert alert-${type} border-0`;
  catAlert.innerHTML = `
    <div class="d-flex align-items-center">
      <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
      <div>${message}</div>
    </div>
  `;
  catAlert.classList.remove('d-none');
}


// ===== Enhanced Form Submission =====
function submitForm(publish = false) {
  const fd = new FormData(form);
  fd.append('publish', publish ? '1' : '0');

  // Enhanced validation
  const title = form.querySelector('[name="title"]').value.trim();
  const location = form.querySelector('[name="location"]').value.trim();
  const start = form.querySelector('[name="start_datetime"]').value;
  const end = form.querySelector('[name="end_datetime"]').value;
  const typeId = form.querySelector('[name="type_id"]').value;

  // Validation checks
  if (!title) {
    showAlert('danger', 'يرجى إدخال عنوان النشاط');
    form.querySelector('[name="title"]').focus();
    return;
  }
  
  if (!location) {
    showAlert('danger', 'يرجى إدخال مكان النشاط');
    form.querySelector('[name="location"]').focus();
    return;
  }
  
  if (!typeId) {
    showAlert('danger', 'يرجى اختيار تصنيف النشاط');
    form.querySelector('[name="type_id"]').focus();
    return;
  }
  
  if (!start) {
    showAlert('danger', 'يرجى تحديد تاريخ ووقت بداية النشاط');
    return;
  }
  
  if (!end) {
    showAlert('danger', 'يرجى تحديد تاريخ ووقت نهاية النشاط');
    return;
  }
  
  if (start && end && (new Date(end) <= new Date(start))) {
    showAlert('danger', 'تاريخ ووقت النهاية يجب أن يكون بعد تاريخ ووقت البداية');
    return;
  }

  // Show loading state
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalSubmitText = submitBtn.innerHTML;
  const originalPublishText = publishBtn.innerHTML;
  
  if (publish) {
    publishBtn.disabled = true;
    publishBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري النشر...';
  } else {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...';
  }
  fetch('../../api/activities/create.php', {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(async r => {
    const ct = r.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await r.text();
      console.error('Non-JSON response from server:', text);
      throw new Error('non-json');
    }
    return r.json();
  }).then(json => {
    if (json.success) {
      const message = publish ? 
        'تم نشر النشاط بنجاح! سيظهر للزوار في الصفحة الرئيسية.' : 
        'تم حفظ النشاط كمسودة بنجاح.';
      showAlert('success', message);
      form.reset();
      updatePreview();
      
      // Reset flatpickr instances
      document.querySelectorAll('.flatpickr-datetime').forEach(input => {
        if (input._flatpickr) {
          input._flatpickr.clear();
        }
      });
    } else {
      showAlert('danger', json.message || 'حدث خطأ أثناء الحفظ');
    }
  }).catch(err => {
    console.error('Submission error:', err);
    showAlert('danger', 'خطأ في الاتصال بالخادم. يرجى التحقق من الاتصال والمحاولة مرة أخرى.');
  }).finally(() => {
    // Reset button states
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalSubmitText;
    publishBtn.disabled = false;
    publishBtn.innerHTML = originalPublishText;
  });
}

// ===== Form Event Listeners =====
form.addEventListener('submit', function(e) {
  e.preventDefault();
  submitForm(false);
});

publishBtn.addEventListener('click', function() {
  submitForm(true);
});

// ===== Category Management =====
saveCategoryBtn.addEventListener('click', function() {
  const fd = new FormData(categoryForm);
  fd.append('is_active', document.getElementById('is_active').checked ? '1' : '0');
  
  // Show loading state
  const originalText = saveCategoryBtn.innerHTML;
  saveCategoryBtn.disabled = true;
  saveCategoryBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...';
  
  fetch('../../api/activities/categories_create.php', {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(r => r.json()).then(json => {
    if (json.success) {
      showCatAlert('success', json.message || 'تم إضافة التصنيف بنجاح');
      
      // Add new option to select and select it
      if (json.data && json.data.id && json.data.display_name) {
        const opt = document.createElement('option');
        opt.value = json.data.id;
        opt.textContent = json.data.display_name;
        typeSelect.appendChild(opt);
        typeSelect.value = json.data.id;
        updatePreview(); // Update preview with new category
      }
      
      // Close modal after a moment
      setTimeout(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'));
        if (modal) modal.hide();
        catAlert.classList.add('d-none');
        categoryForm.reset();
      }, 1000);
    } else {
      showCatAlert('danger', json.message || 'فشل في إضافة التصنيف');
    }
  }).catch(err => {
    console.error('Category creation error:', err);
    showCatAlert('danger', 'خطأ في الاتصال بالخادم');
  }).finally(() => {
    // Reset button state
    saveCategoryBtn.disabled = false;
    saveCategoryBtn.innerHTML = originalText;
  });
});

</script>
</body>
</html>
