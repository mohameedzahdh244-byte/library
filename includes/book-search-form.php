<?php
// قابل لإعادة الاستخدام: نموذج بحث عن كتاب للاستخدام في الواجهة العامة
// يتوقع متغيرين اختياريين: $search_query, $search_type
$search_query = isset($search_query) ? $search_query : '';
$search_type  = isset($search_type) ? $search_type : 'title';
?>
<div class="card shadow-sm">
  <div class="card-header bg-light">
    <h5 class="mb-0"><i class="fas fa-search me-2"></i>البحث عن كتاب</h5>
  </div>
  <div class="card-body">
    <form method="POST" action="./../includes/search.php" id="publicSearchForm">
      <div class="row g-3 align-items-end">
        <div class="col-md-6">
          <label for="search_query" class="form-label fw-bold">كلمة البحث</label>
          <input type="text" class="form-control" id="search_query" name="search_query" placeholder="أدخل كلمة البحث" value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
        </div>
        <div class="col-md-4">
          <label for="search_type" class="form-label fw-bold">نوع البحث</label>
          <select class="form-select" id="search_type" name="search_type">
            <option value="title" <?php echo $search_type === 'title' ? 'selected' : ''; ?>>عنوان الكتاب</option>
            <option value="author" <?php echo $search_type === 'author' ? 'selected' : ''; ?>>المؤلف</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">&nbsp;</label>
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-search me-2"></i>
            بحث
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
