<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/DB.php';
// تحضير صفحة كاملة مع الهيدر والفوتر
$page_title = 'الأنشطة الثقافية والاجتماعية';
$body_class = '';
$analytics_page = 'public-activities';
include __DIR__ . '/header.php';

// إعدادات الترقيم
$page = isset($_GET['apage']) ? max(1, (int)$_GET['apage']) : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

$type_id = isset($_GET['atype']) && $_GET['atype'] !== '' ? (int)$_GET['atype'] : null;
// لم يعد هناك نص حر للتصنيف

$where = "WHERE status = 'published' AND end_datetime > NOW()";
$params = [];
$types = '';

if (!is_null($type_id)) {
  $where .= " AND type_id = ?";
  $types .= 'i';
  $params[] = $type_id;
}
// لا يوجد فلتر للنص الحر بعد إزالة العمود

// العد
$sql_count = "SELECT COUNT(*) AS cnt FROM activities $where";
$stmt = $conn->prepare($sql_count);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$total = 0; if ($res && $row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
$stmt->close();

// جلب
$sql = "SELECT a.id,
        COALESCE(c.name_ar, '') AS type_name,
        a.title, a.location, a.start_datetime, a.end_datetime, a.supervisors, a.is_paid, a.fee_amount, a.description
        FROM activities a
        LEFT JOIN activity_categories c ON c.id = a.type_id
        $where
        ORDER BY a.start_datetime ASC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$types2 = $types . 'ii';
$params2 = $params; $params2[] = $per_page; $params2[] = $offset;
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && $r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();

$total_pages = max(1, (int)ceil($total / $per_page));
?>
<section class="py-5" id="activities">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="h4 m-0 d-flex align-items-center gap-2">
        <img src="./../public/logo2.png" width="24" height="24" alt="logo"/>
        الأنشطة الثقافية والاجتماعية
      </h2>
      <span class="text-muted">عرض <?= count($rows) ?> من <?= (int)$total ?> نشاط</span>
    </div>

    <div class="row g-3">
      <?php if (empty($rows)): ?>
        <div class="col-12">
          <div class="alert alert-info mb-0">لا توجد أنشطة منشورة حالياً.</div>
        </div>
      <?php else: ?>
        <?php foreach ($rows as $a): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
              <div class="card-body d-flex flex-column">
                <div class="small text-primary mb-1"><?php echo htmlspecialchars($a['type_name'] ?? 'نشاط', ENT_QUOTES, 'UTF-8'); ?></div>
                <h3 class="h6 fw-bold"><?php echo htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="text-muted small mb-2">
                  <div>المكان: <?php echo htmlspecialchars($a['location'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div>من: <?php echo date('Y-m-d H:i', strtotime($a['start_datetime'])); ?></div>
                  <div>إلى: <?php echo date('Y-m-d H:i', strtotime($a['end_datetime'])); ?></div>
                </div>
                <div class="small mb-2">
                  <?php if ((int)$a['is_paid'] === 1): ?>
                    <span class="badge bg-warning text-dark">مدفوع</span>
                    <span class="text-muted">رسوم: <?php echo number_format((float)$a['fee_amount'], 2); ?></span>
                  <?php else: ?>
                    <span class="badge bg-success">مجاني</span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($a['supervisors'])): ?>
                  <div class="small text-muted mb-2">المشرفون: <?php echo htmlspecialchars($a['supervisors'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if (!empty($a['description'])): ?>
                  <p class="small mb-0 text-secondary" style="white-space: pre-line;"><?php echo nl2br(htmlspecialchars(mb_strimwidth($a['description'], 0, 180, '...'), ENT_QUOTES, 'UTF-8')); ?></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4" aria-label="ترقيم الأنشطة">
      <ul class="pagination justify-content-center">
        <?php
          $base = strtok($_SERVER['REQUEST_URI'], '?');
          parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
          $q['apage'] = 1;
          $first = $base . '?' . http_build_query($q);
          $q['apage'] = max(1, $page-1);
          $prev = $base . '?' . http_build_query($q);
          $q['apage'] = min($total_pages, $page+1);
          $next = $base . '?' . http_build_query($q);
          $q['apage'] = $total_pages;
          $last = $base . '?' . http_build_query($q);
        ?>
        <li class="page-item <?php echo $page==1?'disabled':''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($first); ?>">الأولى</a></li>
        <li class="page-item <?php echo $page==1?'disabled':''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($prev); ?>">السابق</a></li>
        <li class="page-item disabled"><span class="page-link">صفحة <?php echo $page; ?> / <?php echo $total_pages; ?></span></li>
        <li class="page-item <?php echo $page==$total_pages?'disabled':''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($next); ?>">التالي</a></li>
        <li class="page-item <?php echo $page==$total_pages?'disabled':''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($last); ?>">الأخيرة</a></li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
