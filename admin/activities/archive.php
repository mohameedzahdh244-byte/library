<?php
require_once '../../config/init.php';
checkStaffPermission();

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>أرشفة الأنشطة</title>
  <link href="../../assets/css/bootstrap.css" rel="stylesheet">
  <link href="../../assets/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="../../assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
  <link rel="icon" type="image/x-icon" href="../../public/logo.ico">
  
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

    /* ===== Enhanced Search Input ===== */
    .search-container {
      position: relative;
      margin-bottom: 1.5rem;
    }

    .search-input {
      height: 58px;
      padding: 1rem 3rem 1rem 1.25rem;
      border: 2px solid #e2e8f0;
      border-radius: var(--border-radius-md);
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(10px);
      transition: var(--transition);
      font-size: 1rem;
      width: 100%;
    }

    .search-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      background: rgba(255, 255, 255, 0.95);
      transform: translateY(-2px);
      outline: none;
    }

    .search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #718096;
      font-size: 1.1rem;
    }

    .search-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: var(--border-radius-md);
      box-shadow: var(--shadow-lg);
      max-height: 300px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
    }

    .search-result-item {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid #f1f5f9;
      cursor: pointer;
      transition: var(--transition);
    }

    .search-result-item:hover {
      background: #f8fafc;
      transform: translateX(5px);
    }

    .search-result-item:last-child {
      border-bottom: none;
    }

    .search-result-title {
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 0.25rem;
    }

    .search-result-meta {
      font-size: 0.875rem;
      color: #718096;
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

    .btn-warning-premium {
      background: var(--gradient-warning);
      color: white;
    }

    .btn-warning-premium:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(237, 137, 54, 0.4);
      color: white;
    }

    .btn-danger-premium {
      background: var(--gradient-danger);
      color: white;
    }

    .btn-danger-premium:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(245, 101, 101, 0.4);
      color: white;
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
      min-height: auto !important;
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
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
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

    /* ===== Enhanced Table ===== */
    .table-premium {
      border-radius: var(--border-radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-md);
      background: white;
    }

    .table-premium thead th {
      background: var(--gradient-primary);
      color: white;
      border: none;
      padding: 1.25rem 1rem;
      font-weight: 600;
      letter-spacing: 0.025em;
    }

    .table-premium tbody td {
      padding: 1rem;
      border-color: #f1f5f9;
      vertical-align: middle;
    }

    .table-premium tbody tr:hover {
      background: #f8fafc;
      transform: scale(1.01);
      transition: var(--transition);
    }

    /* ===== Status Badges ===== */
    .status-badge-modern {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.8rem;
      letter-spacing: 0.025em;
      text-transform: uppercase;
      border: 2px solid transparent;
      transition: var(--transition);
      min-width: 100px;
      justify-content: center;
    }

    .status-active-modern {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .status-archived-modern {
      background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
    }

    .status-pending-modern {
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
    }

    .status-badge-modern:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    /* ===== Toggle Switch ===== */
    .toggle-switch {
      position: relative;
      display: inline-block;
      width: 60px;
      height: 34px;
    }

    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .toggle-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: var(--gradient-primary);
      transition: var(--transition);
      border-radius: 34px;
    }

    .toggle-slider:before {
      position: absolute;
      content: "";
      height: 26px;
      width: 26px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: var(--transition);
      border-radius: 50%;
    }

    input:checked + .toggle-slider {
      background: var(--gradient-warning);
    }

    input:checked + .toggle-slider:before {
      transform: translateX(26px);
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
        margin: 0 auto 0.5rem;
      }

      .table-responsive {
        font-size: 0.875rem;
      }

      .btn-premium {
        padding: 0.875rem 2rem;
        font-size: 0.95rem;
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
            <i class="fas fa-archive"></i>
          </div>
          <div>
            <h1 class="h4 mb-1 fw-bold">أرشفة الأنشطة</h1>
            <p class="mb-0 opacity-90 fs-6">إدارة وأرشفة الأنشطة المنتهية والقديمة بتصميم احترافي</p>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="d-flex flex-wrap gap-2 justify-content-lg-end justify-content-center">
          <div class="stats-pill">
            <i class="fas fa-box me-2"></i>أرشفة ذكية
          </div>
          <div class="stats-pill">
            <i class="fas fa-search me-2"></i>بحث متقدم
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search and Filter Section -->
  <div class="card-premium slide-up mb-4">
    <div class="card-header text-white">
      <h2 class="h5 d-flex align-items-center gap-2 m-0">
        <i class="fas fa-search"></i>
        البحث والفلترة
      </h2>
    </div>
    <div class="card-body">
      
      <!-- Smart Search -->
      <div class="search-container">
        <input type="text" 
               class="search-input" 
               id="activitySearch" 
               placeholder="ابحث عن النشاط بالاسم..."
               autocomplete="off">
        <i class="fas fa-search search-icon"></i>
      </div>

      <!-- Search Options -->
      <div class="d-flex justify-content-center gap-2 mb-3">
        <button class="btn btn-premium btn-primary-premium" id="loadActivitiesBtn">
          <i class="fas fa-list me-2"></i>عرض جميع الأنشطة
        </button>
        <button class="btn btn-premium btn-warning-premium" id="searchAllBtn">
          <i class="fas fa-search me-2"></i>البحث في الكل
        </button>
      </div>

      <!-- Search Results Section -->
      <div id="searchResultsSection" class="mb-4" style="display: none;">
        <div class="card-premium">
          <div class="card-header text-white">
            <h6 class="m-0">
              <i class="fas fa-search me-2"></i>
              نتائج البحث
              <span class="badge bg-white text-primary ms-2" id="searchResultsCount">0</span>
            </h6>
          </div>
          <div class="card-body">
            <div id="searchResultsTable"></div>
          </div>
        </div>
      </div>

      <!-- Selected Activity Display -->
      <div id="selectedActivity" class="d-none">
        <div class="alert border-0 p-3" style="background: linear-gradient(135deg, rgba(72, 187, 120, 0.1) 0%, rgba(56, 161, 105, 0.1) 100%); border-radius: var(--border-radius-lg);">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <i class="fas fa-calendar-check text-success fs-4 me-3"></i>
              <div>
                <h6 class="fw-bold text-success mb-1">النشاط المختار</h6>
                <p class="mb-0 text-muted" id="selectedActivityName">-</p>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" id="clearSelection">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Alert Container -->
      <div id="alertContainer" class="mt-3" style="display:none"></div>
    </div>
  </div>

  <!-- Activities Table Section -->
  <div class="card-premium slide-up" id="activitiesSection" style="display: none;">
    <div class="card-header text-white">
      <h2 class="h5 d-flex align-items-center gap-2 m-0">
        <i class="fas fa-list"></i>
        <span id="sectionTitle">الأنشطة النشطة</span>
        <span class="badge bg-white text-primary ms-2" id="activitiesCount">0</span>
      </h2>
    </div>
    <div class="card-body">
      
      <!-- Activities Table -->
      <div class="table-responsive">
        <table class="table table-premium align-middle">
          <thead>
            <tr>
              <th class="text-center" style="width: 60px;">#</th>
              <th><i class="fas fa-heading me-2"></i>العنوان</th>
              <th><i class="fas fa-calendar me-2"></i>تاريخ البداية</th>
              <th><i class="fas fa-calendar me-2"></i>تاريخ النهاية</th>
              <th><i class="fas fa-map-marker-alt me-2"></i>المكان</th>
              <th class="text-center"><i class="fas fa-flag me-2"></i>الحالة</th>
              <th class="text-center" style="min-width: 200px;"><i class="fas fa-cogs me-2"></i>الإجراءات</th>
            </tr>
          </thead>
          <tbody id="activitiesTableBody">
            <!-- Dynamic content will be loaded here -->
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div id="emptyState" class="text-center py-5" style="display: none;">
        <div class="mb-4">
          <i class="fas fa-inbox text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
        </div>
        <h5 class="text-muted mb-3">لا توجد أنشطة</h5>
        <p class="text-muted mb-4" id="emptyStateMessage">لم يتم العثور على أي أنشطة</p>
      </div>

    </div>
  </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Initialize Variables =====
const activitySearch = document.getElementById('activitySearch');
const selectedActivity = document.getElementById('selectedActivity');
const selectedActivityName = document.getElementById('selectedActivityName');
const clearSelection = document.getElementById('clearSelection');
const loadActivitiesBtn = document.getElementById('loadActivitiesBtn');
const searchAllBtn = document.getElementById('searchAllBtn');
const alertContainer = document.getElementById('alertContainer');
const activitiesSection = document.getElementById('activitiesSection');
const activitiesTableBody = document.getElementById('activitiesTableBody');
const activitiesCount = document.getElementById('activitiesCount');
const sectionTitle = document.getElementById('sectionTitle');
const emptyState = document.getElementById('emptyState');
const emptyStateMessage = document.getElementById('emptyStateMessage');
const searchResultsSection = document.getElementById('searchResultsSection');
const searchResultsTable = document.getElementById('searchResultsTable');
const searchResultsCount = document.getElementById('searchResultsCount');

let selectedActivityId = null;
let searchTimeout = null;
let activities = [];

// ===== Enhanced Alert Function =====
function showAlert(type, message) {
  alertContainer.className = `alert alert-${type} border-0 rounded-3`;
  alertContainer.innerHTML = `
    <div class="d-flex align-items-center">
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
      <div>${message}</div>
    </div>
  `;
  alertContainer.style.display = 'block';
  
  // Auto-hide success messages
  if (type === 'success') {
    setTimeout(() => {
      alertContainer.style.display = 'none';
    }, 5000);
  }
}

// ===== Utility Functions =====
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDateTime(dateString) {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    
    return `${year}/${month}/${day} - ${hours}:${minutes}`;
  } catch (e) {
    return dateString;
  }
}

function getStatusBadge(status) {
  switch (status) {
    case 'published':
      return '<span class="status-badge-modern status-active-modern"><i class="fas fa-eye"></i>منشور</span>';
    case 'draft':
      return '<span class="status-badge-modern status-pending-modern"><i class="fas fa-edit"></i>مسودة</span>';
    case 'archived':
      return '<span class="status-badge-modern status-archived-modern"><i class="fas fa-archive"></i>مؤرشف</span>';
    default:
      return '<span class="status-badge-modern status-pending-modern"><i class="fas fa-question"></i>غير محدد</span>';
  }
}

// ===== Smart Search Functions =====
async function searchActivities(query) {
  if (!query.trim()) {
    searchResultsSection.style.display = 'none';
    return;
  }
  
  try {
    // Get all activities (published, draft, archived)
    const response = await fetch('../../api/activities/list_admin_simple.php?all_status=1', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const json = await response.json();
    
    if (!json.success) {
      showAlert('danger', json.message || 'فشل جلب الأنشطة');
      return;
    }
    
    activities = json.data || [];
    const filtered = activities.filter(activity => 
      activity.title && activity.title.toLowerCase().includes(query.toLowerCase())
    );
    
    displaySearchResultsTable(filtered);
  } catch (error) {
    console.error('Search error:', error);
    showAlert('danger', 'خطأ في البحث');
  }
}

// ===== Display Search Results in Table =====
function displaySearchResultsTable(results) {
  searchResultsCount.textContent = results.length;
  
  if (results.length === 0) {
    searchResultsTable.innerHTML = `
      <div class="text-center py-4 text-muted">
        <i class="fas fa-search fs-1 mb-3 opacity-25"></i>
        <h6>لا توجد أنشطة مطابقة للبحث</h6>
        <p class="mb-0">جرب البحث بكلمات أخرى</p>
      </div>
    `;
  } else {
    searchResultsTable.innerHTML = `
      <div class="table-responsive">
        <table class="table table-premium align-middle">
          <thead>
            <tr>
              <th class="text-center" style="width: 60px;">#</th>
              <th><i class="fas fa-heading me-2"></i>العنوان</th>
              <th><i class="fas fa-calendar me-2"></i>تاريخ البداية</th>
              <th><i class="fas fa-calendar me-2"></i>تاريخ النهاية</th>
              <th><i class="fas fa-map-marker-alt me-2"></i>المكان</th>
              <th class="text-center"><i class="fas fa-flag me-2"></i>الحالة</th>
              <th class="text-center" style="min-width: 200px;"><i class="fas fa-cogs me-2"></i>الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            ${results.map((activity, index) => `
              <tr>
                <td class="text-center fw-bold">${index + 1}</td>
                <td>
                  <div class="fw-semibold">${escapeHtml(activity.title || 'غير محدد')}</div>
                  <small class="text-muted">ID: ${activity.id}</small>
                </td>
                <td>
                  <small class="text-muted">
                    ${formatDateTime(activity.start_datetime)}
                  </small>
                </td>
                <td>
                  <small class="text-muted">
                    ${formatDateTime(activity.end_datetime)}
                  </small>
                </td>
                <td>
                  <div class="text-wrap" style="max-width: 150px;">
                    ${escapeHtml(activity.location || 'غير محدد')}
                  </div>
                </td>
                <td class="text-center">
                  ${getStatusBadge(activity.status)}
                </td>
                <td class="text-center">
                  <div class="d-flex flex-wrap gap-1 justify-content-center">
                    ${activity.status === 'archived' ? 
                      `<button class="btn btn-premium btn-primary-premium" 
                              onclick="unarchiveActivity(${activity.id})">
                        <i class="fas fa-undo me-1"></i>إلغاء الأرشفة
                      </button>` :
                      `<button class="btn btn-premium btn-warning-premium" 
                              onclick="archiveActivity(${activity.id})">
                        <i class="fas fa-archive me-1"></i>أرشفة
                      </button>`
                    }
                    ${activity.status === 'draft' ? 
                      `<button class="btn btn-premium btn-primary-premium" 
                              onclick="publishActivity(${activity.id})">
                        <i class="fas fa-eye me-1"></i>نشر
                      </button>` : ''
                    }
                  </div>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  }
  
  // Hide main activities section and show search results
  activitiesSection.style.display = 'none';
  searchResultsSection.style.display = 'block';
}

// ===== Search All Activities Function =====
async function searchAllActivities() {
  const query = activitySearch.value.trim();
  if (!query) {
    showAlert('warning', 'يرجى إدخال كلمة للبحث');
    return;
  }
  
  // Show loading state
  const originalText = searchAllBtn.innerHTML;
  searchAllBtn.disabled = true;
  searchAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جار البحث...';
  
  try {
    await searchActivities(query);
  } catch (error) {
    console.error('Search all error:', error);
    showAlert('danger', 'خطأ في البحث');
  } finally {
    // Reset button state
    searchAllBtn.disabled = false;
    searchAllBtn.innerHTML = originalText;
  }
}

// ===== Load Activities Function =====
async function loadActivities() {
  // Show loading state
  const originalText = loadActivitiesBtn.innerHTML;
  loadActivitiesBtn.disabled = true;
  loadActivitiesBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جار التحميل...';
  
  try {
    let apiUrl = '../../api/activities/list_admin_simple.php';
    const params = new URLSearchParams();
    
    // If we have a selected activity, load only that activity
    if (selectedActivityId) {
      params.append('activity_id', selectedActivityId);
    } else {
      // Load all activities with all statuses
      params.append('all_status', '1');
    }
    
    if (params.toString()) {
      apiUrl += '?' + params.toString();
    }
    
    const response = await fetch(apiUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const json = await response.json();
    
    if (!json.success) {
      showAlert('danger', json.message || 'فشل تحميل الأنشطة');
      return;
    }
    
    const activities = json.data || [];
    renderActivitiesTable(activities);
    activitiesSection.style.display = 'block';
    
    // Update section title and hide search results
    sectionTitle.textContent = 'جميع الأنشطة';
    emptyStateMessage.textContent = 'لا توجد أنشطة';
    searchResultsSection.style.display = 'none';
    
  } catch (error) {
    console.error('Load activities error:', error);
    showAlert('danger', 'خطأ في تحميل الأنشطة');
  } finally {
    // Reset button state
    loadActivitiesBtn.disabled = false;
    loadActivitiesBtn.innerHTML = originalText;
  }
}

// ===== Render Activities Table =====
function renderActivitiesTable(activities) {
  activitiesCount.textContent = activities.length;
  
  if (activities.length === 0) {
    activitiesTableBody.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }
  
  emptyState.style.display = 'none';
  activitiesTableBody.innerHTML = activities.map((activity, index) => `
    <tr>
      <td class="text-center fw-bold">${index + 1}</td>
      <td>
        <div class="fw-semibold">${escapeHtml(activity.title || 'غير محدد')}</div>
        <small class="text-muted">ID: ${activity.id}</small>
      </td>
      <td>
        <small class="text-muted">
          ${formatDateTime(activity.start_datetime)}
        </small>
      </td>
      <td>
        <small class="text-muted">
          ${formatDateTime(activity.end_datetime)}
        </small>
      </td>
      <td>
        <div class="text-wrap" style="max-width: 150px;">
          ${escapeHtml(activity.location || 'غير محدد')}
        </div>
      </td>
      <td class="text-center">
        ${getStatusBadge(activity.status)}
      </td>
      <td class="text-center">
        <div class="d-flex flex-wrap gap-1 justify-content-center">
          ${activity.status === 'archived' ? 
            `<button class="btn btn-premium btn-primary-premium" 
                    onclick="unarchiveActivity(${activity.id})">
              <i class="fas fa-undo me-1"></i>إلغاء الأرشفة
            </button>` :
            `<button class="btn btn-premium btn-warning-premium" 
                    onclick="archiveActivity(${activity.id})">
              <i class="fas fa-archive me-1"></i>أرشفة
            </button>`
          }
          ${activity.status === 'draft' ? 
            `<button class="btn btn-premium btn-primary-premium" 
                    onclick="publishActivity(${activity.id})">
              <i class="fas fa-eye me-1"></i>نشر
            </button>` : ''
          }
        </div>
      </td>
    </tr>
  `).join('');
}

// ===== Archive/Unarchive Functions =====
async function archiveActivity(activityId) {
  if (!confirm('هل أنت متأكد من أرشفة هذا النشاط؟')) return;
  
  try {
    const formData = new FormData();
    formData.append('activity_id', activityId);
    
    const response = await fetch('../../api/activities/archive.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    const json = await response.json();
    
    if (json.success) {
      showAlert('success', 'تم أرشفة النشاط بنجاح');
      // Reload current view
      if (searchResultsSection.style.display !== 'none') {
        searchAllActivities();
      } else {
        loadActivities();
      }
    } else {
      showAlert('danger', json.message || 'فشل في أرشفة النشاط');
    }
  } catch (error) {
    console.error('Archive error:', error);
    showAlert('danger', 'خطأ في الاتصال بالخادم');
  }
}

async function unarchiveActivity(activityId) {
  if (!confirm('هل أنت متأكد من إلغاء أرشفة هذا النشاط؟')) return;
  
  try {
    const formData = new FormData();
    formData.append('activity_id', activityId);
    
    const response = await fetch('../../api/activities/unarchive.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    const json = await response.json();
    
    if (json.success) {
      showAlert('success', 'تم إلغاء أرشفة النشاط بنجاح');
      // Reload current view
      if (searchResultsSection.style.display !== 'none') {
        searchAllActivities();
      } else {
        loadActivities();
      }
    } else {
      showAlert('danger', json.message || 'فشل في إلغاء أرشفة النشاط');
    }
  } catch (error) {
    console.error('Unarchive error:', error);
    showAlert('danger', 'خطأ في الاتصال بالخادم');
  }
}

// ===== Publish Activity Function =====
async function publishActivity(activityId) {
  if (!confirm('هل أنت متأكد من نشر هذا النشاط؟')) return;
  
  try {
    const formData = new FormData();
    formData.append('activity_id', activityId);
    
    const response = await fetch('../../api/activities/publish.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    const json = await response.json();
    
    if (json.success) {
      showAlert('success', 'تم نشر النشاط بنجاح');
      // Reload current view
      if (searchResultsSection.style.display !== 'none') {
        searchAllActivities();
      } else {
        loadActivities();
      }
    } else {
      showAlert('danger', json.message || 'فشل في نشر النشاط');
    }
  } catch (error) {
    console.error('Publish error:', error);
    showAlert('danger', 'خطأ في الاتصال بالخادم');
  }
}


// ===== Event Listeners =====
document.addEventListener('DOMContentLoaded', function() {
  // Search input with Enter key
  activitySearch.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      searchAllActivities();
    }
  });
  
  // Clear search results when input is empty
  activitySearch.addEventListener('input', function() {
    if (!this.value.trim()) {
      searchResultsSection.style.display = 'none';
    }
  });
  
  // Clear selection
  clearSelection.addEventListener('click', function() {
    selectedActivityId = null;
    selectedActivity.classList.add('d-none');
    activitySearch.value = '';
    activitiesSection.style.display = 'none';
    searchResultsSection.style.display = 'none';
  });
  
  // Load activities button
  loadActivitiesBtn.addEventListener('click', loadActivities);
  
  // Search all button
  searchAllBtn.addEventListener('click', searchAllActivities);
});

</script>
</body>
</html>
