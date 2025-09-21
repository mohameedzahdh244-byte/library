<?php
require_once '../../config/init.php';

// التحقق من صلاحيات الموظف
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header('Location: ../../auth/loginform.php');
    exit;
}

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة اقتراحات الكتب - <?php echo $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="../../assets/css/bootstrap.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="../../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="../../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <!-- Dashboard CSS -->
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <!-- Enhanced Forms CSS -->
    <link href="../../assets/css/enhanced-forms.css" rel="stylesheet">
    
    <!-- Analytics -->
    <script src="../../assets/js/analytics.js"></script>
    
    <style>
        /* Enhanced Admin Suggestions Styles */
        .hero-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 70%, rgba(255,255,255,0.1) 0%, transparent 50%);
        }
        
        .suggestion-card {
            border: none;
            border-radius: 16px;
            margin-bottom: 20px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .suggestion-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border: 2px solid #007bff;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .status-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .status-badge:hover::before {
            left: 100%;
        }
        
        .status-new { 
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
            border: 2px solid #2196f3;
        }
        .status-reviewed { 
            background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%);
            color: #f57c00;
            border: 2px solid #ff9800;
        }
        .status-purchased { 
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            color: #2e7d32;
            border: 2px solid #4caf50;
        }
        .status-rejected { 
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #d32f2f;
            border: 2px solid #f44336;
        }
        
        .filters-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: none;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,123,255,0.1) 0%, rgba(108,117,125,0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stats-card:hover::before {
            opacity: 1;
        }
        
        .stats-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .action-buttons {
            gap: 1rem;
        }
        
        .btn-enhanced {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-enhanced::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-enhanced:hover::before {
            left: 100%;
        }
        
        .btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body data-analytics-page="admin">
    <div class="container-fluid">
        <!-- Hero Header -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="hero-header text-white p-5 fade-in-up">
                    <div class="position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="mb-3 mb-md-0">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-white bg-opacity-20 rounded-circle p-3 me-3">
                                        <i class="fas fa-lightbulb text-white fs-2"></i>
                                    </div>
                                    <div>
                                        <h1 class="display-6 fw-bold mb-2">إدارة اقتراحات الكتب</h1>
                                        <p class="lead mb-0 text-white-75">مراجعة وإدارة اقتراحات الأعضاء للكتب الجديدة بكفاءة عالية</p>
                                    </div>
                                </div>
                                <div class="d-flex gap-4 text-white-50">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-users me-2"></i>
                                        <span>إدارة شاملة</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-chart-line me-2"></i>
                                        <span>إحصائيات مفصلة</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-filter me-2"></i>
                                        <span>فلترة متقدمة</span>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons d-flex">
                                <button class="btn btn-enhanced btn-success me-2" onclick="exportSuggestions()">
                                    <i class="fas fa-file-excel me-2"></i>
                                    تصدير Excel
                                </button>
                                <button class="btn btn-enhanced btn-light" onclick="printSuggestions()">
                                    <i class="fas fa-print me-2"></i>
                                    طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Statistics -->
        <div class="row mb-5" id="statsContainer">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stats-card shadow-lg fade-in-up" style="animation-delay: 0.1s">
                    <div class="mb-3">
                        <i class="fas fa-star text-primary fs-2"></i>
                    </div>
                    <div class="stats-number text-primary" id="newCount">-</div>
                    <div class="stats-label">اقتراحات جديدة</div>
                    <div class="mt-2">
                        <small class="text-muted">في انتظار المراجعة</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stats-card shadow-lg fade-in-up" style="animation-delay: 0.2s">
                    <div class="mb-3">
                        <i class="fas fa-eye text-warning fs-2"></i>
                    </div>
                    <div class="stats-number text-warning" id="reviewedCount">-</div>
                    <div class="stats-label">تمت المراجعة</div>
                    <div class="mt-2">
                        <small class="text-muted">قيد الدراسة</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stats-card shadow-lg fade-in-up" style="animation-delay: 0.3s">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success fs-2"></i>
                    </div>
                    <div class="stats-number text-success" id="purchasedCount">-</div>
                    <div class="stats-label">تم الشراء</div>
                    <div class="mt-2">
                        <small class="text-muted">متوفر بالمكتبة</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stats-card shadow-lg fade-in-up" style="animation-delay: 0.4s">
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger fs-2"></i>
                    </div>
                    <div class="stats-number text-danger" id="rejectedCount">-</div>
                    <div class="stats-label">مرفوض</div>
                    <div class="mt-2">
                        <small class="text-muted">غير مناسب</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filters -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card filters-card shadow-lg fade-in-up">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-filter text-primary me-2"></i>
                            فلترة وبحث متقدم
                        </h5>
                    </div>
                    <div class="card-body pt-3">
                        <form id="filtersForm" class="row g-4">
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <select class="form-select form-select-enhanced" name="status" id="statusFilter">
                                        <option value="">جميع الحالات</option>
                                        <option value="new">جديد</option>
                                        <option value="reviewed">تمت المراجعة</option>
                                        <option value="purchased">تم الشراء</option>
                                        <option value="rejected">مرفوض</option>
                                    </select>
                                    <label for="statusFilter">
                                        <i class="fas fa-filter text-primary me-2"></i>
                                        الحالة
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" name="member" id="memberFilter" placeholder="ابحث باسم العضو">
                                    <label for="memberFilter">
                                        <i class="fas fa-user text-primary me-2"></i>
                                        رقم العضو
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" name="title" id="titleFilter" placeholder="ابحث بعنوان الكتاب">
                                    <label for="titleFilter">
                                        <i class="fas fa-book text-primary me-2"></i>
                                        عنوان الكتاب
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" name="author" id="authorFilter" placeholder="ابحث باسم المؤلف">
                                    <label for="authorFilter">
                                        <i class="fas fa-user-edit text-primary me-2"></i>
                                        المؤلف
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">من تاريخ</label>
                                <input type="date" class="form-control" name="date_from" id="dateFrom">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">إلى تاريخ</label>
                                <input type="date" class="form-control" name="date_to" id="dateTo">
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-3 justify-content-end pt-3 border-top">
                                    <button type="submit" class="btn btn-enhanced btn-primary px-4">
                                        بحث 
                                        <i class="fas fa-search me-1"></i>
                                       
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suggestions List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div id="loadingSpinner" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جاري التحميل...</span>
                            </div>
                            <p class="mt-2 text-muted">جاري تحميل الاقتراحات...</p>
                        </div>
                        
                        <div id="suggestionsContainer" style="display: none;">
                            <div id="suggestionsList"></div>
                            
                            <!-- Pagination -->
                            <div id="paginationContainer" class="d-flex justify-content-between align-items-center mt-4">
                                <div class="text-muted" id="paginationInfo"></div>
                                <div>
                                    <button class="btn btn-outline-primary me-2" id="prevBtn" onclick="changePage(-1)">
                                        <i class="fas fa-chevron-right me-1"></i>
                                        السابق
                                    </button>
                                    <button class="btn btn-outline-primary" id="nextBtn" onclick="changePage(1)">
                                        التالي
                                        <i class="fas fa-chevron-left ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="noResults" style="display: none;" class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>لا توجد اقتراحات</h5>
                            <p class="text-muted">لم يتم العثور على اقتراحات تطابق معايير البحث</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden print iframe (for in-page printing) -->
    <iframe id="printFrame" style="position:absolute; width:0; height:0; border:0; visibility:hidden;"></iframe>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تحديث حالة الاقتراح</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="statusForm">
                        <input type="hidden" id="suggestionId" name="id">
                        <div class="mb-3">
                            <label class="form-label">الحالة الجديدة</label>
                            <select class="form-select" name="status" id="newStatus" required>
                                <option value="new">جديد</option>
                                <option value="reviewed">تمت المراجعة</option>
                                <option value="purchased">تم الشراء</option>
                                <option value="rejected">مرفوض</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ملاحظات الموظف</label>
                            <textarea class="form-control" name="staff_notes" id="staffNotes" rows="3" 
                                      placeholder="أي ملاحظات أو تفاصيل إضافية (اختياري)" maxlength="1000"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="updateStatus()">
                        حفظ التغييرات
                        <i class="fas fa-save me-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
        <div id="alertContainer"></div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentPage = 0;
        let currentFilters = {};
        let statusModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
            loadSuggestions();
            
            // Form submission
            document.getElementById('filtersForm').addEventListener('submit', function(e) {
                e.preventDefault();
                currentPage = 0;
                loadSuggestions();
            });
            
            // Real-time search
            let searchTimeout;
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        currentPage = 0;
                        loadSuggestions();
                    }, 500);
                });
            }
        });
        
        async function loadSuggestions() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            const suggestionsContainer = document.getElementById('suggestionsContainer');
            const noResults = document.getElementById('noResults');
            
            loadingSpinner.style.display = 'block';
            suggestionsContainer.style.display = 'none';
            noResults.style.display = 'none';
            
            // Get filter values
            const formData = new FormData(document.getElementById('filtersForm'));
            const params = new URLSearchParams();
            
            for (let [key, value] of formData.entries()) {
                if (value.trim()) {
                    params.append(key, value);
                }
            }
            
            params.append('limit', '20');
            params.append('offset', currentPage * 20);
            
            try {
                const response = await fetch(`../../api/suggestions/list_admin.php?${params}`);
                const result = await response.json();
                
                if (result.success) {
                    displaySuggestions(result.suggestions);
                    updatePagination(result.pagination);
                    updateStats(result.stats);
                    
                    if (result.suggestions.length === 0) {
                        noResults.style.display = 'block';
                    } else {
                        suggestionsContainer.style.display = 'block';
                    }
                } else {
                    const msg = result.message || 'حدث خطأ أثناء تحميل الاقتراحات';
                    showAlert('danger', msg);
                }
            } catch (error) {
                console.error('Error loading suggestions:', error);
                showAlert('danger', 'خطأ في الاتصال');
            } finally {
                loadingSpinner.style.display = 'none';
            }
        }
        
        function displaySuggestions(suggestions) {
            const container = document.getElementById('suggestionsList');
            
            const html = suggestions.map(suggestion => {
                const statusText = getStatusText(suggestion.status);
                const statusClass = `status-${suggestion.status}`;
                const createdDate = new Date(suggestion.created_at).toLocaleDateString('ar-EG');
                
                return `
                    <div class="suggestion-card p-3">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="mb-2 fw-bold">${escapeHtml(suggestion.title)}</h6>
                                <p class="mb-1 text-muted">
                                    <i class="fas fa-user-edit me-1"></i>
                                    المؤلف: ${escapeHtml(suggestion.author)}
                                </p>
                                <p class="mb-1 text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    العضو: ${escapeHtml(suggestion.mem_name || 'غير محدد')} (${suggestion.mem_no})
                                </p>
                                ${suggestion.phone ? `
                                    <p class="mb-1 text-muted">
                                        <i class="fas fa-phone me-1"></i>
                                        ${escapeHtml(suggestion.phone)}
                                    </p>
                                ` : ''}
                                ${suggestion.notes ? `
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <small><strong>ملاحظات العضو:</strong> ${escapeHtml(suggestion.notes)}</small>
                                    </div>
                                ` : ''}
                                ${suggestion.staff_notes ? `
                                    <div class="mt-2 p-2 bg-info bg-opacity-10 rounded">
                                        <small><strong>ملاحظات الموظف:</strong> ${escapeHtml(suggestion.staff_notes)}</small>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="mb-2">
                                    <span class="status-badge ${statusClass}">${statusText}</span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        ${createdDate}
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="openStatusModal(${suggestion.id}, '${suggestion.status}', '${escapeHtml(suggestion.staff_notes || '')}')">
                                    تحديث الحالة
                                    <i class="fas fa-edit me-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = html;
        }
        
        function updateStats(stats) {
            document.getElementById('newCount').textContent = stats.new || 0;
            document.getElementById('reviewedCount').textContent = stats.reviewed || 0;
            document.getElementById('purchasedCount').textContent = stats.purchased || 0;
            document.getElementById('rejectedCount').textContent = stats.rejected || 0;
        }
        
        function updatePagination(pagination) {
            const info = document.getElementById('paginationInfo');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            const start = pagination.offset + 1;
            const end = Math.min(pagination.offset + pagination.limit, pagination.total);
            
            info.textContent = `عرض ${start}-${end} من ${pagination.total}`;
            
            prevBtn.disabled = pagination.offset === 0;
            nextBtn.disabled = !pagination.has_more;
        }
        
        function changePage(direction) {
            currentPage += direction;
            if (currentPage < 0) currentPage = 0;
            loadSuggestions();
        }
        
        function openStatusModal(id, currentStatus, staffNotes) {
            document.getElementById('suggestionId').value = id;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('staffNotes').value = staffNotes;
            statusModal.show();
        }
        
        async function updateStatus() {
            const form = document.getElementById('statusForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('../../api/suggestions/update_status.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'تم تحديث حالة الاقتراح بنجاح');
                    statusModal.hide();
                    loadSuggestions();
                } else {
                    showAlert('danger', result.message || 'حدث خطأ أثناء التحديث');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showAlert('danger', 'خطأ في الاتصال');
            }
        }
        
        function getStatusText(status) {
            const statusMap = {
                'new': 'جديد',
                'reviewed': 'تمت المراجعة',
                'purchased': 'تم الشراء',
                'rejected': 'مرفوض'
            };
            return statusMap[status] || status;
        }
        
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();
            
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('beforeend', alertHtml);
            
            setTimeout(() => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function printSuggestions() {
            const params = new URLSearchParams();
            const formData = new FormData(document.getElementById('filtersForm'));
            
            for (let [key, value] of formData.entries()) {
                if (value.trim()) {
                    params.append(key, value);
                }
            }
            
            const iframe = document.getElementById('printFrame');
            const url = `print.php?${params}`;
            // عند تحميل المحتوى داخل الإطار نقوم بأمر الطباعة
            iframe.onload = function() {
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch (e) {
                    console.error('Print failed in iframe', e);
                } finally {
                    // يمكن إبقاء iframe مخفياً؛ لا حاجة لإزالته
                }
            };
            iframe.src = url;
        }
        
        function exportSuggestions() {
            const params = new URLSearchParams();
            const formData = new FormData(document.getElementById('filtersForm'));
            
            for (let [key, value] of formData.entries()) {
                if (value.trim()) {
                    params.append(key, value);
                }
            }
            
            window.location.href = `export.php?${params}`;
        }
    </script>
</body>
</html>
