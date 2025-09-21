<?php
// تضمين نظام المسارات

require_once '../config/init.php';

// التحقق من صلاحيات المشترك
checkMemberPermission();

$mem_no = $_SESSION['user_no'];
// تم إزالة نظام AJAX المزدوج - الآن نمط واحد فقط

// الحصول على معلومات المشترك
$stmt = $conn->prepare("SELECT * FROM customer WHERE mem_no = ?");
$stmt->bind_param("s", $mem_no);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();

// معالجة البحث
$search_results = [];
$search_query = '';
$search_type = '';
// إعداد الترقيم: 15 كتاب لكل صفحة
$limit = 15;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $limit;
$totalResults = 0;

// دعم البحث عبر POST و GET للترقيم
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['search_query'])) {
    $search_query = trim($_POST['search_query'] ?? ($_GET['search_query'] ?? ''));
    $search_type = $_POST['search_type'] ?? ($_GET['search_type'] ?? 'title');
    
    if (!empty($search_query)) {
        // استعلام العد الإجمالي
        $countSql = "SELECT COUNT(*) AS cnt FROM book b
        LEFT JOIN (
            SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
            FROM book_authors ba
            JOIN authors au ON au.ANO = ba.ANO
            GROUP BY ba.serialnum_book
        ) bauth ON bauth.serialnum_book = b.serialnum_book
        WHERE 1=1";
        
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
                CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM book_reservation br 
                        WHERE br.serialnum_book = b.serialnum_book 
                          AND br.status IN ('pending','available')
                          AND (br.expiry_date IS NULL OR br.expiry_date > NOW())
                    ) THEN 1 ELSE 0
                END AS has_reservation,
                bauth.authors AS author
        FROM book b
        LEFT JOIN (
            SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
            FROM book_authors ba
            JOIN authors au ON au.ANO = ba.ANO
            GROUP BY ba.serialnum_book
        ) bauth ON bauth.serialnum_book = b.serialnum_book
        WHERE 1=1";

        // إضافة شرط البحث لكلا الاستعلامين
        $searchCondition = "";
        switch ($search_type) {
            case 'title':
                $searchCondition = " AND b.book_title LIKE ?";
                $search_param = "%$search_query%";
                break;
            case 'author':
                $searchCondition = " AND bauth.authors LIKE ?";
                $search_param = "%$search_query%";
                break;
            case 'serial':
                $searchCondition = " AND b.serialnum_book LIKE ?";
                $search_param = "%$search_query%";
                break;
            case 'classification':
                $searchCondition = " AND b.classification_num LIKE ?";
                $search_param = "%$search_query%";
                break;
        }
        
        // تطبيق الشرط على كلا الاستعلامين
        $countSql .= $searchCondition;
        $sql .= $searchCondition;
        
        // تنفيذ استعلام العد
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            $countStmt->bind_param("s", $search_param);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalResults = ($countResult && ($row = $countResult->fetch_assoc())) ? intval($row['cnt']) : 0;
            $countStmt->close();
        }
        
        // تنفيذ استعلام النتائج مع LIMIT/OFFSET
        $sql .= " ORDER BY b.book_title ASC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sii", $search_param, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $search_results = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// معالجة الحجز
if (isset($_POST['reserve_book'])) {
    $book_serial = $_POST['book_serial'];
    $reservation_hours = 48; // ساعات صلاحية الحجز

    // التحقق من عدم وجود حجز سابق لنفس العضو ونفس الكتاب
    $check_stmt = $conn->prepare("
        SELECT 1 FROM book_reservation 
        WHERE mem_no = ? AND serialnum_book = ? AND status IN ('pending', 'available')
    ");
    $check_stmt->bind_param("ss", $mem_no, $book_serial);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows == 0) {
        // التحقق من الحد الأقصى للحجوزات النشطة للعضو (4)
        $mCntStmt = $conn->prepare("SELECT COUNT(*) AS c FROM book_reservation WHERE mem_no = ? AND status IN ('pending','available') AND (expiry_date IS NULL OR expiry_date > NOW())");
        if ($mCntStmt) {
            $mCntStmt->bind_param("s", $mem_no);
            $mCntStmt->execute();
            $mCntRes = $mCntStmt->get_result();
            $memberActiveCnt = ($mCntRes && ($rowm = $mCntRes->fetch_assoc())) ? intval($rowm['c']) : 0;
            $mCntStmt->close();
        } else {
            $memberActiveCnt = 0; // في حال فشل التحضير نسمح بالمتابعة بدل كسر العملية
        }

        if ($memberActiveCnt >= 4) {
            // تجاوز الحد الأقصى للحجوزات
            $error_message = 'لقد وصلت إلى الحد الأقصى للحجوزات (4 كتب). يرجى إلغاء أحد الحجوزات أو استلام كتاب قبل إضافة حجز جديد.';
        } else {
        // 1) تنظيف الحجوزات المنتهية صلاحيتها وترقية التالي إن لزم
        $conn->query("UPDATE book_reservation SET status='expired' WHERE status IN ('pending','available') AND expiry_date IS NOT NULL AND expiry_date <= NOW()");

        // إن لم يعد هناك حجز متاح (available) وهناك حجز منتظر، قم بترقية الأقدم
        $promoteCheck = $conn->prepare("SELECT 1 FROM book_reservation WHERE serialnum_book = ? AND status = 'available' AND (expiry_date IS NULL OR expiry_date > NOW()) LIMIT 1");
        $promoteCheck->bind_param("s", $book_serial);
        $promoteCheck->execute();
        $hasAvailable = $promoteCheck->get_result()->num_rows > 0;

        if (!$hasAvailable) {
            // رُقِّ الأقدم في الانتظار إلى 'available'
            $promoteStmt = $conn->prepare("SELECT reservation_id FROM book_reservation WHERE serialnum_book = ? AND status='pending' ORDER BY reservation_date ASC, reservation_id ASC LIMIT 1");
            if ($promoteStmt) {
                $promoteStmt->bind_param("s", $book_serial);
                $promoteStmt->execute();
                $promoteRes = $promoteStmt->get_result();
                if ($rowp = $promoteRes->fetch_assoc()) {
                    $pid = (int)$rowp['reservation_id'];
                    $upd = $conn->prepare("UPDATE book_reservation SET status='available', expiry_date = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE reservation_id = ?");
                    $upd->bind_param('ii', $reservation_hours, $pid);
                    $upd->execute();
                }
            }
        }

        // 2) تقرير حالة الحجز الجديد
        $borrowed_q = $conn->prepare("SELECT 1 FROM borrow_transaction bt LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id WHERE bt.serialnum_book = ? AND rb.return_date IS NULL LIMIT 1");
        $borrowed_q->bind_param("s", $book_serial);
        $borrowed_q->execute();
        $isBorrowed = $borrowed_q->get_result()->num_rows > 0;

        $active_res_cnt_q = $conn->prepare("SELECT COUNT(*) AS c FROM book_reservation br WHERE br.serialnum_book = ? AND br.status IN ('pending','available') AND (br.expiry_date IS NULL OR br.expiry_date > NOW())");
        $active_res_cnt_q->bind_param("s", $book_serial);
        $active_res_cnt_q->execute();
        $active_cnt = ($active_res_cnt_q->get_result()->fetch_assoc()['c'] ?? 0);

        // متوفر فورًا فقط إذا لم يكن مُعارًا ولا توجد حجوزات نشطة سابقة
        $status = (!$isBorrowed && (int)$active_cnt === 0) ? 'available' : 'pending';

        // 3) إنشاء الحجز
        $insert_stmt = $conn->prepare("INSERT INTO book_reservation (mem_no, serialnum_book, expiry_date, status) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?)");
        $insert_stmt->bind_param("ssis", $mem_no, $book_serial, $reservation_hours, $status);

        if ($insert_stmt->execute()) {
            // تسجيل العملية
            $auditLogger->logCreate($mem_no, 'book_reservation', $book_serial, [
                'mem_no' => $mem_no,
                'serialnum_book' => $book_serial,
                'status' => $status,
                'expiry_hours' => $reservation_hours
            ]);

            if ($status === 'available') {
                $success_message = 'تم حجز الكتاب وهو متوفر الآن. لديك مدة يومين للاستلام.';
            } else {
                $success_message = 'تمت إضافتك إلى قائمة الانتظار. سيتم إشعارك عند توفر الكتاب أو انتهاء حجز سابق.';
            }
        } else {
            $error_message = 'حدث خطأ أثناء الحجز. يرجى المحاولة مرة أخرى.';
        }
        }
    } else {
        $error_message = 'لديك حجز سابق لهذا الكتاب.';
    }

    // استجابة AJAX بدون إعادة تحميل الصفحة
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => isset($success_message),
            'message' => $success_message ?? $error_message,
            'status' => $status ?? null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// الحصول على إعدادات المكتبة
$libraryInfo = $settings->getLibraryInfo();
$borrowingSettings = $settings->getBorrowingSettings();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البحث عن الكتب - <?php echo $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/member-search.css">
    <link rel="stylesheet" href="../assets/css/member-common.css">
    
    <!-- Font Awesome -->
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="../assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <!-- Member Dashboard CSS -->
    <link href="../assets/css/member-dashboard.css" rel="stylesheet">
    <!-- Analytics -->
    <script src="../assets/js/analytics.js"></script>
</head>
<body data-analytics-page="member">
    <div class="container-fluid px-4">
        <div class="search-container">
            <!-- Toasts Container (top-right) -->
            <div id="toastContainer" style="position: fixed; top: 5rem; right: 1rem; z-index: 1080;"></div>
            <!-- Search Form -->
            <div class="search-card">
                <div class="search-header">
                    <h3 class="mb-2">
                        <i class="fas fa-search me-2"></i>
                        البحث عن الكتب
                    </h3>
                    <p class="mb-0">ابحث عن الكتب المتوفرة في مكتبتنا</p>
                </div>
                
                <div class="search-form">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="searchForm">
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
                            <option value="serial" <?php echo $search_type === 'serial' ? 'selected' : ''; ?>>الرقم التسلسلي</option>
                            <option value="classification" <?php echo $search_type === 'classification' ? 'selected' : ''; ?>>رقم التصنيف</option>
                          </select>
                        </div>
                        <div class="col-md-2">
                          <div class="d-flex align-items-end h-100">
                            <button type="submit" class="btn btn-primary w-100">
                              <i class="fas fa-search me-2"></i>
                              بحث
                            </button>
                          </div>
                        </div>
                      </div>
                    </form>
                </div>
            </div>

            <!-- Search Results -->
            <div id="liveResults">
            <?php if (!empty($search_results)): ?>
                <div class="search-stats">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>عرض:</strong> <?php echo count($search_results); ?> من <?php echo $totalResults; ?> كتاب
                        </div>
                        <div class="col-md-6 text-md-end">
                            <strong>كلمة البحث:</strong> "<?php echo htmlspecialchars($search_query); ?>"
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <?php foreach ($search_results as $book): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="book-card" data-book-serial="<?php echo htmlspecialchars($book['serialnum_book']); ?>" role="button">
                                <!-- صورة الكتاب -->
                                <?php if (!empty($book['cover_image'])): ?>
                                    <div class="book-cover">
                                        <img src="../<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                             alt="غلاف <?php echo htmlspecialchars($book['book_title']); ?>" 
                                             class="book-cover-img">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="book-info">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h5 class="book-title"><?php echo htmlspecialchars($book['book_title']); ?></h5>
                                            <div class="book-author">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($book['author'] ?? 'غير محدد'); ?>
                                            </div>
                                        </div>
                                        <?php 
                                            $isBorrowed = ($book['availability_status'] === 'معار');
                                            $hasRes = !empty($book['has_reservation']);
                                            // عند الإعارة نعرض "معار" فقط حتى لو محجوز
                                            if ($isBorrowed) {
                                                $statusLabel = 'معار';
                                                $badgeClass = 'borrowed';
                                            } elseif ($hasRes) {
                                                $statusLabel = 'متوفر محجوز';
                                                $badgeClass = 'reserved';
                                            } else {
                                                $statusLabel = 'متوفر';
                                                $badgeClass = 'available';
                                            }
                                        ?>
                                        <span class="availability-badge <?php echo $badgeClass; ?>">
                                            <?php echo $statusLabel; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="book-details mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <small><strong>الرقم التسلسلي:</strong><br><?php echo htmlspecialchars($book['serialnum_book']); ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small><strong>رقم التصنيف:</strong><br><?php echo htmlspecialchars($book['classification_num'] ?? 'غير محدد'); ?></small>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-6">
                                                <small><strong>اللغة:</strong> <?php echo htmlspecialchars($book['book_language'] ?? 'العربية'); ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small><strong>السنة:</strong> <?php echo htmlspecialchars($book['year'] ?? 'غير محدد'); ?></small>
                                            </div>
                                        </div>
                                        <?php if ($book['department']): ?>
                                            <div class="mt-2">
                                                <small><strong>القسم:</strong> <?php echo htmlspecialchars($book['department']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($book['availability_status'] === 'معار'): ?>
                                        <div class="alert alert-warning py-2 mb-3">
                                            <small>
                                                <i class="fas fa-clock me-1"></i>
                                                متوقع الإرجاع: <?php echo date('Y/m/d', strtotime($book['boro_exp_ret_date'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-copy me-1"></i>
                                            النسخ المتوفرة: <?php echo ($book['availability_status'] === 'متوفر') ? 1 : 0; ?>
                                        </small>
                                        <div>
                                            <form method="POST" style="display: inline;" data-is-borrowed="<?php echo $isBorrowed ? '1' : '0'; ?>" data-has-res="<?php echo $hasRes ? '1' : '0'; ?>">
                                                <input type="hidden" name="reserve_book" value="1">
                                                <input type="hidden" name="book_serial" value="<?php echo $book['serialnum_book']; ?>">
                                                <button type="submit" class="btn btn-reserve text-white btn-sm">
                                                    <i class="fas fa-calendar-plus me-1"></i>
                                                    حجز
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <!-- صندوق الرسالة الخاص بالحجز لكل بطاقة -->
                                    <div class="reserve-msg mt-2"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
                    // ترقيم الصفحات
                    $totalPages = ($totalResults > 0) ? (int)ceil($totalResults / $limit) : 1;
                    if ($totalPages > 1):
                        $qs = [
                            'search_query' => $search_query,
                            'search_type' => $search_type
                        ];
                        $makeUrl = function($page) use ($qs) {
                            $qs['page'] = $page;
                            return '?' . http_build_query($qs);
                        };
                        $start = $offset + 1;
                        $end = min($offset + $limit, $totalResults);
                ?>
                <div class="d-flex flex-column flex-md-row align-items-center justify-content-between mt-4 gap-2">
                    <div class="small text-muted order-2 order-md-1">
                        <i class="fas fa-info-circle me-1"></i>
                        عرض <?php echo $start; ?>–<?php echo $end; ?> من <?php echo $totalResults; ?> نتيجة
                    </div>
                    <nav aria-label="ترقيم صفحات البحث" class="order-1 order-md-2 w-100 w-md-auto">
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <li class="page-item <?php echo ($currentPage <= 1 ? 'disabled' : ''); ?>">
                                <a class="page-link d-inline-flex align-items-center" href="<?php echo $currentPage>1 ? $makeUrl($currentPage-1) : '#'; ?>">
                                    <i class="fas fa-chevron-right ms-1"></i>
                                    <span>السابق</span>
                                </a>
                            </li>
                            <?php
                            // إظهار نطاق من الأرقام حول الصفحة الحالية
                            $from = max(1, $currentPage - 2);
                            $to = min($totalPages, $currentPage + 2);
                            
                            // إضافة الصفحة الأولى إذا لم تكن ضمن النطاق
                            if ($from > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $makeUrl(1); ?>">1</a>
                                </li>
                                <?php if ($from > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif;
                            
                            for ($p=$from; $p<=$to; $p++): ?>
                                <li class="page-item <?php echo ($p==$currentPage?'active':''); ?>">
                                    <a class="page-link" href="<?php echo $makeUrl($p); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor;
                            
                            // إضافة الصفحة الأخيرة إذا لم تكن ضمن النطاق
                            if ($to < $totalPages): ?>
                                <?php if ($to < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $makeUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item <?php echo ($currentPage >= $totalPages ? 'disabled' : ''); ?>">
                                <a class="page-link d-inline-flex align-items-center" href="<?php echo $currentPage<$totalPages ? $makeUrl($currentPage+1) : '#'; ?>">
                                    <span>التالي</span>
                                    <i class="fas fa-chevron-left me-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php elseif (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['search_query'])) && !empty($search_query)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h5>لا توجد نتائج</h5>
                    <p>لم نتمكن من العثور على كتب تطابق بحثك</p>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        بحث جديد
                    </a>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <!-- تضمين مودال تفاصيل الكتاب -->
        <?php include '../includes/book-details-modal.php'; ?>
    </div>
    <!-- Bootstrap JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <!-- Page specific JS -->
    <script src="../assets/js/member-search.js"></script>
    <script>
        // تعيين نوع المستخدم للمودال
        window.currentUserType = 'member';
    </script>
</body>
</html>
