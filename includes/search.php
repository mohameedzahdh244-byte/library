<?php
require_once __DIR__ . '/../config/init.php';

// تأكد من وجود جدول ربط المؤلفين قبل أي استعلام يعتمد عليه
try {
  $conn->query(
    "CREATE TABLE IF NOT EXISTS book_authors (
      id INT AUTO_INCREMENT PRIMARY KEY,
      serialnum_book VARCHAR(191) NOT NULL,
      ANO VARCHAR(191) NOT NULL,
      UNIQUE KEY uniq_book_author (serialnum_book, ANO)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
} catch (Exception $e) {
  // تجاهل؛ الاستعلامات اللاحقة تستخدم LEFT JOIN ولن تفشل المنظومة إن لم يتوفر الجدول
}

$page_title = 'بحث عن كتاب';
// تعريف معرف صفحة التحليلات ليتم التقاط الزيارة والزمن النشط
$analytics_page = 'search';

// معالجة البحث (عام بدون تسجيل دخول)
$search_results = [];
$search_query = '';
$search_type = 'title';
$isAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1');

// إعداد الترقيم: 15 كتاب لكل صفحة
$limit = 15;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $limit;
$totalResults = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['search_query'])) {
    $search_query = trim($_POST['search_query'] ?? $_GET['search_query'] ?? '');
    $search_type = $_POST['search_type'] ?? $_GET['search_type'] ?? 'title';

    if ($search_query !== '') {
        // استعلام العدد الإجمالي
        $countSql = "SELECT COUNT(*) as total
                FROM book b
                LEFT JOIN (
                  SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname SEPARATOR ', ') AS authors
                  FROM book_authors ba
                  JOIN authors au ON au.ANO = ba.ANO
                  GROUP BY ba.serialnum_book
                ) bauth ON bauth.serialnum_book = b.serialnum_book
                WHERE 1=1";

        switch ($search_type) {
            case 'title':
                $countSql .= " AND b.book_title LIKE ?";
                break;
            case 'author':
                $countSql .= " AND bauth.authors LIKE ?";
                break;
            default:
                $countSql .= " AND b.book_title LIKE ?";
        }

        $param = "%{$search_query}%";
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param('s', $param);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalResults = $countResult->fetch_assoc()['total'];
        // استعلام النتائج مع الترقيم
        $sql = "SELECT 
                    b.*,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM borrow_transaction bt2 
                            LEFT JOIN return_book rb2 ON bt2.borrow_detail_id = rb2.borrow_detail_id
                            WHERE bt2.serialnum_book = b.serialnum_book 
                              AND rb2.return_date IS NULL
                        ) THEN 'معار'
                        ELSE 'متوفر'
                    END AS availability_status,
                    (
                        SELECT bt3.boro_exp_ret_date
                        FROM borrow_transaction bt3 
                        LEFT JOIN return_book rb3 ON bt3.borrow_detail_id = rb3.borrow_detail_id
                        WHERE bt3.serialnum_book = b.serialnum_book 
                          AND rb3.return_date IS NULL
                        ORDER BY bt3.boro_exp_ret_date DESC
                        LIMIT 1
                    ) AS boro_exp_ret_date,
                    bauth.authors AS author
                FROM book b
                /* مؤلفون متعددون عبر جدول الربط */
                LEFT JOIN (
                  SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname SEPARATOR ', ') AS authors
                  FROM book_authors ba
                  JOIN authors au ON au.ANO = ba.ANO
                  GROUP BY ba.serialnum_book
                ) bauth ON bauth.serialnum_book = b.serialnum_book
                WHERE 1=1";

        switch ($search_type) {
            case 'title':
                $sql .= " AND b.book_title LIKE ?";
                break;
            case 'author':
                $sql .= " AND bauth.authors LIKE ?";
                break;
            default:
                $sql .= " AND b.book_title LIKE ?";
        }

        $sql .= " ORDER BY b.book_title ASC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sii', $param, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $search_results = $res->fetch_all(MYSQLI_ASSOC);
    }
}

// حساب الترقيم
$totalPages = $totalResults > 0 ? ceil($totalResults / $limit) : 0;
$startResult = $totalResults > 0 ? ($offset + 1) : 0;
$endResult = min($offset + $limit, $totalResults);

// إن كان الطلب عبر AJAX نعيد فقط جزء النتائج HTML ثم ننهي
if ($isAjax) {
    ob_start();
    if (!empty($search_results)) { ?>
        <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
            <div>
                <strong>عدد النتائج:</strong> <?php echo $totalResults; ?>
                <span class="mx-2">|</span>
                <strong>عبارة البحث:</strong> "<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>"
            </div>
            <small class="text-muted">
                عرض <?php echo $startResult; ?>-<?php echo $endResult; ?> من <?php echo $totalResults; ?>
            </small>
        </div>
        <div class="row g-3">
            <?php foreach ($search_results as $book): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm" role="button" data-book-serial="<?php echo htmlspecialchars($book['serialnum_book']); ?>">
                        <!-- صورة الكتاب -->
                        <?php if (!empty($book['cover_image'])): ?>
                            <div class="book-cover">
                                <img src="./../<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                     alt="غلاف <?php echo htmlspecialchars($book['book_title']); ?>" 
                                     class="book-cover-img">
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($book['book_title']); ?></h5>
                                    <div class="text-muted small">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars(trim($book['author'] ?? ''), ENT_QUOTES, 'UTF-8') ?: 'غير محدد'; ?>
                                    </div>
                                </div>
                                <?php 
                                    $isBorrowed = ($book['availability_status'] === 'معار');
                                    $isOverdue = false;
                                    if ($isBorrowed && !empty($book['boro_exp_ret_date'])) {
                                        $isOverdue = (strtotime($book['boro_exp_ret_date']) < time());
                                    }
                                    
                                    if ($isOverdue) {
                                        $statusLabel = 'متأخر';
                                        $badgeClass = 'overdue';
                                    } elseif ($isBorrowed) {
                                        $statusLabel = 'معار';
                                        $badgeClass = 'borrowed';
                                    } else {
                                        $statusLabel = 'متوفر';
                                        $badgeClass = 'available';
                                    }
                                ?>
                                <span class="availability-badge <?php echo $badgeClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </div>
                            <div class="mt-2 small">
                                <div><strong>الرقم التسلسلي:</strong> <?php echo htmlspecialchars($book['serialnum_book']); ?></div>
                                <?php if (!empty($book['classification_num'])): ?>
                                  <div><strong>رقم التصنيف:</strong> <?php echo htmlspecialchars($book['classification_num']); ?></div>
                                <?php endif; ?>
                                
                            </div>
                            <div class="mt-auto pt-2">
                                <a href="./../auth/loginform.php" class="btn btn-outline-primary w-100">تسجيل الدخول للحجز</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <!-- Pagination -->
            <nav aria-label="ترقيم الصفحات" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo ($currentPage - 1); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    
                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=1">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif;
                    endif;
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                            <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor;
                    
                    if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo ($currentPage + 1); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $search_query !== '') { ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h5>لا توجد نتائج</h5>
            <p class="text-muted">لم نتمكن من العثور على كتب تطابق بحثك</p>
        </div>
    <?php }
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

include __DIR__ . '/header.php';
?>

<!-- CSS مخصص للبحث العام -->
<link rel="stylesheet" href="./../assets/css/public-search.css">

<div class="container py-5">
  <div class="mt-4">
    <?php include __DIR__ . '/book-search-form.php'; ?>
  </div>

  <div class="mt-4" id="liveResults">
    <?php if (!empty($search_results)) : ?>
      <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
        <div>
          <strong>عدد النتائج:</strong> <?php echo $totalResults; ?>
          <span class="mx-2">|</span>
          <strong>عبارة البحث:</strong> "<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>"
        </div>
        <small class="text-muted">
          عرض <?php echo $startResult; ?>-<?php echo $endResult; ?> من <?php echo $totalResults; ?>
        </small>
      </div>

      <div class="row g-3">
        <?php foreach ($search_results as $book): ?>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm" role="button" data-book-serial="<?php echo htmlspecialchars($book['serialnum_book']); ?>">
              <!-- صورة الكتاب -->
              <?php if (!empty($book['cover_image'])): ?>
                <div class="book-cover">
                  <img src="./../<?php echo htmlspecialchars($book['cover_image']); ?>" 
                       alt="غلاف <?php echo htmlspecialchars($book['book_title']); ?>" 
                       class="book-cover-img">
                </div>
              <?php endif; ?>
              
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="flex-grow-1">
                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($book['book_title']); ?></h5>
                    <div class="text-muted small">
                      <i class="fas fa-user me-1"></i>
                      <?php echo htmlspecialchars(trim($book['author'] ?? ''), ENT_QUOTES, 'UTF-8') ?: 'غير محدد'; ?>
                    </div>
                  </div>
                  <?php 
                      $isBorrowed = ($book['availability_status'] === 'معار');
                      $isOverdue = false;
                      if ($isBorrowed && !empty($book['boro_exp_ret_date'])) {
                          $isOverdue = (strtotime($book['boro_exp_ret_date']) < time());
                      }
                      
                      if ($isOverdue) {
                          $statusLabel = 'متأخر';
                          $badgeClass = 'overdue';
                      } elseif ($isBorrowed) {
                          $statusLabel = 'معار';
                          $badgeClass = 'borrowed';
                      } else {
                          $statusLabel = 'متوفر';
                          $badgeClass = 'available';
                      }
                  ?>
                  <span class="availability-badge <?php echo $badgeClass; ?>">
                      <?php echo $statusLabel; ?>
                  </span>
                </div>

                <div class="mt-2 small">
                  <div><strong>الرقم التسلسلي:</strong> <?php echo htmlspecialchars($book['serialnum_book']); ?></div>
                  <?php if (!empty($book['classification_num'])): ?>
                    <div><strong>رقم التصنيف:</strong> <?php echo htmlspecialchars($book['classification_num']); ?></div>
                  <?php endif; ?>
                  
                </div>

                <div class="mt-auto pt-2">
                  <a href="./../auth/loginform.php" class="btn btn-outline-primary w-100">
                    تسجيل الدخول للحجز
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <?php if ($totalPages > 1): ?>
        <!-- Pagination -->
        <nav aria-label="ترقيم الصفحات" class="mt-4">
          <ul class="pagination justify-content-center">
            <?php if ($currentPage > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo ($currentPage - 1); ?>">
                  <i class="fas fa-chevron-right"></i>
                </a>
              </li>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
            
            if ($startPage > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=1">1</a>
              </li>
              <?php if ($startPage > 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
              <?php endif;
            endif;
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
              <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
              </li>
            <?php endfor;
            
            if ($endPage < $totalPages): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
              <?php endif; ?>
              <li class="page-item">
                <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
              </li>
            <?php endif; ?>
            
            <?php if ($currentPage < $totalPages): ?>
              <li class="page-item">
                <a class="page-link" href="?search_query=<?php echo urlencode($search_query); ?>&search_type=<?php echo urlencode($search_type); ?>&page=<?php echo ($currentPage + 1); ?>">
                  <i class="fas fa-chevron-left"></i>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $search_query !== ''): ?>
      <div class="text-center py-5">
        <i class="fas fa-search fa-3x text-muted mb-3"></i>
        <h5>لا توجد نتائج</h5>
        <p class="text-muted">لم نتمكن من العثور على كتب تطابق بحثك</p>
        <a href="./../includes/search.php" class="btn btn-primary">بحث جديد</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- تضمين مودال تفاصيل الكتاب لاستخدامه في الصفحة العامة -->
<?php include __DIR__ . '/book-details-modal.php'; ?>

<script src="./../assets/js/public-search.js"></script>
<script>
  // تعريف نوع المستخدم كضيف لهذه الصفحة
  window.currentUserType = 'guest';
  // تفويض نقر عام لأي عنصر يملك data-book-serial لفتح مودال التفاصيل
  document.addEventListener('click', function(e){
    const el = e.target.closest('[data-book-serial]');
    if (!el) return;
    if (e.target.closest('a, button, form, input, select, textarea, label')) return;
    const serial = el.getAttribute('data-book-serial');
    if (serial && typeof showBookDetails === 'function') {
      e.preventDefault();
      showBookDetails(serial);
    }
  });
  // ملاحظة: بما أن التفويض على document، فهو يعمل أيضاً مع النتائج المحمّلة عبر AJAX
  // من /includes/search.php بواسطة public-search.js
</script>
<?php include __DIR__ . '/footer.php'; ?>
