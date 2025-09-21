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
  <title>إدارة طلبات الانضمام</title>
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

    .btn-success-premium {
      background: var(--gradient-success);
      color: white;
    }

    .btn-success-premium:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4);
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
    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.8rem;
      letter-spacing: 0.025em;
      text-transform: uppercase;
    }

    .status-pending {
      background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
      color: white;
    }

    .status-approved {
      background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
      color: white;
    }

    .status-rejected {
      background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
      color: white;
    }

    /* ===== Enhanced Note Field ===== */
    .note-container {
      background: rgba(248, 250, 252, 0.8);
      border: 1px solid #e2e8f0;
      border-radius: var(--border-radius-lg);
      padding: 0.75rem;
      transition: var(--transition);
      position: relative;
    }

    .note-container:hover {
      background: rgba(255, 255, 255, 0.95);
      border-color: var(--primary);
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }

    .note-field {
      border: none;
      background: transparent;
      resize: none;
      font-size: 0.875rem;
      line-height: 1.4;
      width: 100%;
      padding: 0;
      transition: var(--transition);
    }

    .note-field:focus {
      outline: none;
      background: rgba(255, 255, 255, 0.9);
      border-radius: 6px;
      padding: 0.5rem;
      box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
    }

    .note-field::placeholder {
      color: #94a3b8;
      font-style: italic;
    }

    .note-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 0.5rem;
      gap: 0.25rem;
    }

    .note-save-btn {
      background: var(--gradient-primary);
      border: none;
      border-radius: 6px;
      color: white;
      padding: 0.25rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 500;
      transition: var(--transition);
      opacity: 0.8;
    }

    .note-save-btn:hover {
      opacity: 1;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .note-save-btn:disabled {
      opacity: 0.5;
      transform: none;
    }

    /* ===== Enhanced Status Badges ===== */
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

    .status-pending-modern {
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
    }

    .status-approved-modern {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .status-rejected-modern {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .status-badge-modern:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
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

      .note-container {
        padding: 0.5rem;
      }

      .note-field {
        font-size: 0.8rem;
      }

      .note-save-btn {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
      }

      .status-badge-modern {
        font-size: 0.7rem;
        padding: 0.375rem 0.75rem;
        min-width: 80px;
      }
    }

    @media (max-width: 576px) {
      .table-premium thead th {
        padding: 0.75rem 0.5rem;
        font-size: 0.8rem;
      }

      .table-premium tbody td {
        padding: 0.75rem 0.5rem;
      }

      .note-container {
        min-width: 200px;
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
            <i class="fas fa-user-check"></i>
          </div>
          <div>
            <h1 class="h4 mb-1 fw-bold">إدارة طلبات الانضمام</h1>
            <p class="mb-0 opacity-90 fs-6">مراجعة وإدارة طلبات المشتركين للانضمام للأنشطة بتصميم احترافي</p>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="d-flex flex-wrap gap-2 justify-content-lg-end justify-content-center">
          <div class="stats-pill">
            <i class="fas fa-clock me-2"></i>مراجعة سريعة
          </div>
          <div class="stats-pill">
            <i class="fas fa-check-circle me-2"></i>إدارة ذكية
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
        <div class="search-results" id="searchResults"></div>
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

      <!-- Load Button -->
      <div class="d-flex justify-content-center">
        <button class="btn btn-premium btn-primary-premium" id="loadRequestsBtn" disabled>
          <i class="fas fa-download me-2"></i>
          تحميل طلبات الانضمام
        </button>
      </div>

      <!-- Alert Container -->
      <div id="alertContainer" class="mt-3" style="display:none"></div>
    </div>
  </div>

  <!-- Requests Table Section -->
  <div class="card-premium slide-up" id="requestsSection" style="display: none;">
    <div class="card-header text-white">
      <h2 class="h5 d-flex align-items-center gap-2 m-0">
        <i class="fas fa-list"></i>
        طلبات الانضمام
        <span class="badge bg-white text-primary ms-2" id="requestsCount">0</span>
      </h2>
    </div>
    <div class="card-body">
      
      <!-- Requests Table -->
      <div class="table-responsive">
        <table class="table table-premium align-middle">
          <thead>
            <tr>
              <th class="text-center" style="width: 60px;">#</th>
              <th><i class="fas fa-user me-2"></i>المشترك</th>
              <th><i class="fas fa-comment me-2"></i>سبب الانضمام</th>
              <th><i class="fas fa-sticky-note me-2"></i>ملاحظة الموظف</th>
              <th><i class="fas fa-clock me-2"></i>تاريخ الطلب</th>
              <th class="text-center"><i class="fas fa-flag me-2"></i>الحالة</th>
              <th class="text-center" style="min-width: 200px;"><i class="fas fa-cogs me-2"></i>الإجراءات</th>
            </tr>
          </thead>
          <tbody id="requestsTableBody">
            <!-- Dynamic content will be loaded here -->
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div id="emptyState" class="text-center py-5" style="display: none;">
        <div class="mb-4">
          <i class="fas fa-inbox text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
        </div>
        <h5 class="text-muted mb-3">لا توجد طلبات انضمام</h5>
        <p class="text-muted mb-4">لم يتم العثور على أي طلبات انضمام لهذا النشاط</p>
      </div>

    </div>
  </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Initialize Variables =====
const activitySearch = document.getElementById('activitySearch');
const searchResults = document.getElementById('searchResults');
const selectedActivity = document.getElementById('selectedActivity');
const selectedActivityName = document.getElementById('selectedActivityName');
const clearSelection = document.getElementById('clearSelection');
const loadRequestsBtn = document.getElementById('loadRequestsBtn');
const alertContainer = document.getElementById('alertContainer');
const requestsSection = document.getElementById('requestsSection');
const requestsTableBody = document.getElementById('requestsTableBody');
const requestsCount = document.getElementById('requestsCount');
const emptyState = document.getElementById('emptyState');

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
    case 'approved':
      return '<span class="status-badge-modern status-approved-modern"><i class="fas fa-check-circle"></i>مقبول</span>';
    case 'rejected':
      return '<span class="status-badge-modern status-rejected-modern"><i class="fas fa-times-circle"></i>مرفوض</span>';
    default:
      return '<span class="status-badge-modern status-pending-modern"><i class="fas fa-hourglass-half"></i>قيد المراجعة</span>';
  }
}

// ===== Smart Search Functions =====
async function searchActivities(query) {
  if (!query.trim()) {
    searchResults.style.display = 'none';
    return;
  }
  
  try {
    const response = await fetch('../../api/activities/list_admin_simple.php', {
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
    
    displaySearchResults(filtered);
  } catch (error) {
    console.error('Search error:', error);
    showAlert('danger', 'خطأ في البحث');
  }
}

function displaySearchResults(results) {
  if (results.length === 0) {
    searchResults.innerHTML = `
      <div class="search-result-item text-center text-muted">
        <i class="fas fa-search me-2"></i>
        لا توجد أنشطة مطابقة للبحث
      </div>
    `;
  } else {
    searchResults.innerHTML = results.map(activity => `
      <div class="search-result-item" data-id="${activity.id}" data-title="${escapeHtml(activity.title)}">
        <div class="search-result-title">${escapeHtml(activity.title)}</div>
        <div class="search-result-meta">
          <i class="fas fa-calendar me-1"></i>
          ${formatDateTime(activity.start_datetime)} - ${formatDateTime(activity.end_datetime)}
        </div>
      </div>
    `).join('');
    
    // Add click events to search results
    searchResults.querySelectorAll('.search-result-item[data-id]').forEach(item => {
      item.addEventListener('click', () => selectActivity(item));
    });
  }
  
  searchResults.style.display = 'block';
}

function selectActivity(item) {
  selectedActivityId = item.dataset.id;
  const activityTitle = item.dataset.title;
  
  selectedActivityName.textContent = activityTitle;
  selectedActivity.classList.remove('d-none');
  loadRequestsBtn.disabled = false;
  
  activitySearch.value = activityTitle;
  searchResults.style.display = 'none';
}

// ===== Load Requests Function =====
async function loadRequests() {
  if (!selectedActivityId) {
    showAlert('warning', 'يرجى اختيار نشاط أولاً');
    return;
  }
  
  // Show loading state
  const originalText = loadRequestsBtn.innerHTML;
  loadRequestsBtn.disabled = true;
  loadRequestsBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جار التحميل...';
  
  try {
    const response = await fetch(`../../api/activities/requests_list.php?activity_id=${encodeURIComponent(selectedActivityId)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const json = await response.json();
    
    if (!json.success) {
      showAlert('danger', json.message || 'فشل تحميل الطلبات');
      return;
    }
    
    const requests = json.data || [];
    renderRequestsTable(requests);
    requestsSection.style.display = 'block';
    
  } catch (error) {
    console.error('Load requests error:', error);
    showAlert('danger', 'خطأ في تحميل الطلبات');
  } finally {
    // Reset button state
    loadRequestsBtn.disabled = false;
    loadRequestsBtn.innerHTML = originalText;
  }
}

// ===== Render Requests Table =====
function renderRequestsTable(requests) {
  requestsCount.textContent = requests.length;
  
  if (requests.length === 0) {
    requestsTableBody.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }
  
  emptyState.style.display = 'none';
  requestsTableBody.innerHTML = requests.map((request, index) => `
    <tr>
      <td class="text-center fw-bold">${index + 1}</td>
      <td>
        <div class="d-flex align-items-center">
          <i class="fas fa-user-circle text-primary me-2 fs-5"></i>
          <div>
            <div class="fw-semibold">${escapeHtml(request.member_name || 'غير محدد')}</div>
            <small class="text-muted">ID: ${request.member_id}</small>
          </div>
        </div>
      </td>
      <td>
        <div class="text-wrap" style="max-width: 200px;">
          ${escapeHtml(request.reason || 'لم يتم تحديد سبب')}
        </div>
      </td>
      <td style="min-width: 280px;">
        <div class="note-container">
          <textarea class="note-field" 
                    id="note_${request.id}" 
                    rows="3" 
                    placeholder="اكتب ملاحظة إدارية...">${escapeHtml(request.admin_note || '')}</textarea>
        </div>
      </td>
      <td>
        <small class="text-muted">
          ${formatDateTime(request.created_at)}
        </small>
      </td>
      <td class="text-center">
        ${getStatusBadge(request.status)}
      </td>
      <td class="text-center">
        <div class="d-flex flex-wrap gap-1 justify-content-center">
          <button class="btn btn-premium btn-success-premium" 
                  onclick="updateRequestStatus(${request.id}, 'approved')"
                  ${request.status === 'approved' ? 'disabled' : ''}>
            <i class="fas fa-check me-1"></i>قبول
          </button>
          <button class="btn btn-premium btn-danger-premium" 
                  onclick="updateRequestStatus(${request.id}, 'rejected')"
                  ${request.status === 'rejected' ? 'disabled' : ''}>
            <i class="fas fa-times me-1"></i>رفض
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

// ===== Update Request Status =====
async function updateRequestStatus(requestId, decision) {
  // Get the note from the textarea field
  const noteField = document.getElementById(`note_${requestId}`);
  const note = noteField ? noteField.value.trim() : '';
  
  try {
    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('decision', decision);
    formData.append('admin_note', note);
    
    const response = await fetch('../../api/activities/request_decide.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    const json = await response.json();
    
    if (json.success) {
      const statusText = decision === 'approved' ? 'قُبل' : 'رُفض';
      showAlert('success', `تم ${statusText} الطلب بنجاح`);
      loadRequests(); // Reload the table
    } else {
      showAlert('danger', json.message || 'فشل في تحديث حالة الطلب');
    }
  } catch (error) {
    console.error('Update status error:', error);
    showAlert('danger', 'خطأ في الاتصال بالخادم');
  }
}

// ===== Save Note =====
async function saveNote(requestId) {
  const noteField = document.getElementById(`note_${requestId}`);
  if (!noteField) return;
  
  const note = noteField.value.trim();
  
  // Find the save button within the note container
  const noteContainer = noteField.closest('.note-container');
  const saveBtn = noteContainer.querySelector('.note-save-btn');
  const originalContent = saveBtn.innerHTML;
  
  // Show loading state
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري الحفظ...';
  
  try {
    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('decision', 'keep_current'); // Keep current status
    formData.append('admin_note', note);
    
    const response = await fetch('../../api/activities/request_decide.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    const json = await response.json();
    
    if (json.success) {
      showAlert('success', 'تم حفظ الملاحظة بنجاح');
      
      // Add visual feedback to container
      noteContainer.style.borderColor = '#10b981';
      noteContainer.style.background = 'rgba(16, 185, 129, 0.1)';
      
      // Reset visual feedback after 2 seconds
      setTimeout(() => {
        noteContainer.style.borderColor = '';
        noteContainer.style.background = '';
      }, 2000);
      
      // Success feedback on button
      saveBtn.innerHTML = '<i class="fas fa-check me-1"></i>تم الحفظ';
      saveBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
      
      setTimeout(() => {
        saveBtn.innerHTML = originalContent;
        saveBtn.style.background = '';
      }, 2000);
      
    } else {
      showAlert('danger', json.message || 'فشل في حفظ الملاحظة');
    }
  } catch (error) {
    console.error('Save note error:', error);
    showAlert('danger', 'خطأ في الاتصال بالخادم');
  } finally {
    // Reset button state after delay
    setTimeout(() => {
      saveBtn.disabled = false;
      if (saveBtn.innerHTML.includes('جاري الحفظ')) {
        saveBtn.innerHTML = originalContent;
      }
    }, 1000);
  }
}

// ===== Event Listeners =====
document.addEventListener('DOMContentLoaded', function() {
  // Search input with debounce
  activitySearch.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      searchActivities(this.value);
    }, 300);
  });
  
  // Hide search results when clicking outside
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container')) {
      searchResults.style.display = 'none';
    }
  });
  
  // Clear selection
  clearSelection.addEventListener('click', function() {
    selectedActivityId = null;
    selectedActivity.classList.add('d-none');
    loadRequestsBtn.disabled = true;
    activitySearch.value = '';
    requestsSection.style.display = 'none';
  });
  
  // Load requests button
  loadRequestsBtn.addEventListener('click', loadRequests);
});

</script>
</body>
</html>
