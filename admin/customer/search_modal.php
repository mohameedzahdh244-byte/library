<?php
require_once '../../config/init.php';

// Allow iframe embedding within same origin
header('X-Frame-Options: SAMEORIGIN');

// Permissions: restrict to staff/admin
checkStaffPermission();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>بحث عن مشترك</title>
  <link href="/assets/css/bootstrap.css" rel="stylesheet" />
  <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet" />
  <link href="/assets/css/search_customer.css" rel="stylesheet" />
</head>
<body>
  <div class="search-wrapper">
    <div class="glass-card">
      <div class="search-bar">
        <div class="search-container fade-in">
          <div class="search-wrap">
            <i class="fas fa-search search-icon"></i>
            <input id="q" type="text" class="form-control search-input" placeholder="ابحث برقم المشترك أو الاسم أو الهاتف" autocomplete="off" aria-label="ابحث عن مشترك">
            <button type="button" id="clearSearch" class="search-clear" aria-label="مسح البحث" title="مسح">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
      </div>
      <!-- Loading -->
      <div id="loading" class="loading">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 mb-0 text-muted">جاري البحث...</p>
      </div>
      <!-- Results Header -->
      <div id="resultsHeader" class="results-header d-flex justify-content-between align-items-center" style="display:none;">
        <h6 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2 text-primary"></i>نتائج البحث</h6>
        <div class="d-flex align-items-center gap-2">
          <button id="toggleLegend" type="button" class="btn btn-link btn-sm text-muted text-decoration-none" onclick="$('#iconsLegend').slideToggle(150)">
            <i class="fas fa-circle-info me-1"></i>معاني الرموز
          </button>
          <span id="resultsCount" class="text-muted small"></span>
        </div>
      </div>
      <!-- Icons Legend -->
      <div id="iconsLegend" class="icons-legend" style="display:none;">
        <span class="legend-item"><i class="fas fa-id-card text-primary"></i> رقم المشترك</span>
        <span class="legend-item"><i class="fas fa-user text-primary"></i> الاسم</span>
        <span class="legend-item"><i class="fas fa-phone text-success"></i> الهاتف</span>
        <span class="legend-item"><span class="badge badge-soft status-active">ساري</span></span>
        <span class="legend-item"><span class="badge badge-soft status-expired">منتهي</span></span>
      </div>
      <div class="results" id="results"></div>
      <!-- Inline Modal for editing -->
      <div id="editModal" class="edit-modal-backdrop" aria-hidden="true">
        <div class="edit-modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
          <div class="edit-modal-header">
            <div id="editModalTitle" class="edit-modal-title"><i class="fa fa-user-pen me-1 text-primary"></i>تعديل بيانات المشترك</div>
            <button type="button" class="edit-modal-close" id="closeEditModal" aria-label="إغلاق"><i class="fa fa-times"></i></button>
          </div>
          <div class="edit-modal-body">
            <iframe id="editFrame" src="about:blank" style="border:0; width:100%; height:100%" title="نموذج تعديل المشترك"></iframe>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="/assets/js/jquery-3.7.1.min.js"></script>
  <script src="/assets/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/search_customer.js"></script>
</body>
</html>
