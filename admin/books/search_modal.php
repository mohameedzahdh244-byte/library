        <?php
require_once '../../config/init.php';

// منع الكاش لهذه الصفحة لضمان تحميل أحدث نسخة دائمًا
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// السماح بعرض الصفحة في iframe
header('X-Frame-Options: SAMEORIGIN');

// التحقق من صلاحية الموظف
if (!isset($_SESSION['user_no']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    die('صلاحيات غير كافية.');
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البحث عن الكتب</title>

    <!-- Bootstrap CSS RTL -->
    <link href="/assets/css/bootstrap.css" rel="stylesheet">
    <!-- Font Awesome (Local) -->
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/book_search.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-0">
        <!-- Search Form -->
        <div class="search-container fade-in">
            <form id="staffSearchForm" method="POST" action="#" class="m-0">
                <div class="search-wrap">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="form-control search-input" id="searchInput" placeholder="ابحث برقم الكتاب، العنوان، المؤلف، أو التصنيف" autocomplete="off" aria-label="ابحث عن كتاب">
                    <button type="button" id="clearSearch" class="search-clear" aria-label="مسح البحث" title="مسح">
                        <i class="fas fa-times"></i>
                    </button>
                    <button type="submit" id="doSearch" class="btn btn-primary ms-2">
                    بحث
                    <i class="fas fa-search me-1"></i>
                </button>
                </div>
            </form>
        </div>
        
        <!-- Results Header -->
        <div id="resultsHeader" class="results-header d-flex justify-content-between align-items-center" style="display: none;">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-list-ul me-2 text-primary"></i>نتائج البحث
            </h6>
            <div class="d-flex align-items-center gap-2">
                <button id="toggleLegend" type="button" class="btn btn-link btn-sm text-muted text-decoration-none" onclick="$('#iconsLegend').slideToggle(150)">
                    <i class="fas fa-circle-info me-1"></i>معاني الرموز
                </button>
                <span id="resultsCount" class="results-count"></span>
            </div>
        </div>
        <!-- Icons Legend (collapsed by default) -->
        <div id="iconsLegend" class="icons-legend" style="display:none;">
            <span class="legend-item"><i class="fas fa-barcode text-primary"></i> الرقم التسلسلي</span>
            <span class="legend-item"><i class="fas fa-user text-primary"></i> المؤلف</span>
            <span class="legend-item"><i class="fas fa-bookmark text-success"></i> التصنيف</span>
            <span class="legend-item"><i class="fas fa-clock text-warning"></i> تاريخ متوقع للإرجاع</span>
            <span class="legend-item"><span class="status-badge status-available">متوفر</span></span>
            <span class="legend-item"><span class="status-badge status-borrowed">معار</span></span>
            <span class="legend-item"><span class="status-badge status-overdue">متأخر</span></span>
            <span class="legend-item"><span class="status-badge status-reserved">محجوز</span></span>
        </div>
        
        <!-- Loading -->
        <div id="loadingContainer" class="loading" style="display: none;">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 mb-0 text-muted">جاري البحث...</p>
        </div>
        
        <!-- Results Container as Grid -->
        <div id="resultsContainer" class="row g-3" style="max-height: 420px; overflow-y: auto;">
            <!-- Results will be populated here -->
        </div>
        
        <!-- No Results -->
        <div id="noResults" class="no-results" style="display: none;">
            <i class="fas fa-search fa-2x mb-3 text-primary"></i>
            <h6>لا توجد نتائج</h6>
            <p class="mb-0">لم يتم العثور على أي كتب تطابق البحث</p>
        </div>

        <!-- تضمين مودال تفاصيل الكتاب -->
        <?php include '../../includes/book-details-modal.php'; ?>
    </div>

    <!-- Bootstrap JS (Local) -->
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/jquery-3.7.1.min.js"></script>
    <script src="/assets/js/book_search.js?v=<?= time() ?>"></script>
    <script>
        // تعيين نوع المستخدم للمودال
        window.currentUserType = 'staff';
    </script>
</body>
</html>
