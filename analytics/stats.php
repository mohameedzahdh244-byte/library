<?php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

$page  = isset($_GET['page']) ? trim($_GET['page']) : '';
$range = isset($_GET['range']) ? trim($_GET['range']) : 'day'; // day|month|year

$validPages = ['about','member','search'];
if ($page === '' || !in_array($page, $validPages, true)) {
  echo json_encode(['ok'=>false,'msg'=>'invalid_page']);
  exit;
}

$now = time();
$start = $now;
$fmt = '';
$bucketSelect = '';

switch ($range) {
  case 'day':
    // آخر 24 ساعة
    $start = $now - 24*3600;
    $fmt = '%Y-%m-%d %H:00';
    $bucketSelect = "DATE_FORMAT(FROM_UNIXTIME(ts_ms/1000), '%Y-%m-%d %H:00')";
    break;
  case 'month':
    // آخر 30 يوماً
    $start = $now - 30*24*3600;
    $fmt = '%Y-%m-%d';
    $bucketSelect = "DATE_FORMAT(FROM_UNIXTIME(ts_ms/1000), '%Y-%m-%d')";
    break;
  case 'year':
  default:
    // آخر 12 شهراً
    $start = strtotime(date('Y-m-01 00:00:00', $now) . ' -11 months');
    $fmt = '%Y-%m';
    $bucketSelect = "DATE_FORMAT(FROM_UNIXTIME(ts_ms/1000), '%Y-%m')";
    $range = 'year';
    break;
}

$startMs = $start * 1000;
$endMs = (int)(microtime(true)*1000);

// تجميع حسب السلة الزمنية
// تحضير السلاسل والإجماليات
$series = [];
$totalVisits = 0;
$totalVisitorsSet = [];
$totalActiveMs = 0;
$memberLogins = null; // only for page=member

if ($page === 'member') {
  // 1) السلسلة الزمنية من audit_log (login_success)
  $sqlL = "SELECT DATE_FORMAT(created_at, '$fmt') AS bucket, COUNT(*) AS logins
           FROM audit_log
           WHERE action_type='login_success' AND user_no IS NULL
             AND created_at BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)
           GROUP BY bucket
           ORDER BY bucket ASC";
  $stmL = $conn->prepare($sqlL);
  $stmL->bind_param('ii', $start, $now);
  $stmL->execute();
  $resL = $stmL->get_result();
  while ($row = $resL->fetch_assoc()) {
    $logins = (int)($row['logins'] ?? 0);
    $series[] = [
      'bucket' => $row['bucket'],
      'visits' => $logins, // نستخدم مفتاح visits ليقرأه الواجهات الحالية كمخطط
      'visitors' => 0,
      'active_minutes' => 0 // سنعبئ الإجمالي لاحقاً من جدول التحليلات
    ];
    $totalVisits += $logins;
  }
  $stmL->close();

  // 2) إجمالي الوقت النشط من analytics_events (يبقى كما هو)
  $sqlA = "SELECT SUM(active_ms) AS active_ms
           FROM analytics_events
           WHERE page=? AND ts_ms BETWEEN ? AND ?";
  $stmA = $conn->prepare($sqlA);
  $stmA->bind_param('sii', $page, $startMs, $endMs);
  $stmA->execute();
  $resA = $stmA->get_result()->fetch_assoc();
  $totalActiveMs = (int)($resA['active_ms'] ?? 0);
  $stmA->close();

  // 3) توزيع الوقت النشط على السلال الزمنية لعرض مخطط الدقائق عبر الزمن
  $sqlAB = "SELECT $bucketSelect AS bucket, SUM(active_ms) AS active_ms
            FROM analytics_events
            WHERE page=? AND ts_ms BETWEEN ? AND ?
            GROUP BY bucket
            ORDER BY bucket ASC";
  $stmAB = $conn->prepare($sqlAB);
  $stmAB->bind_param('sii', $page, $startMs, $endMs);
  $stmAB->execute();
  $resAB = $stmAB->get_result();
  // ابنِ فهرس للسلسلة الحالية حسب السلة
  $idx = [];
  foreach ($series as $i => $row) { $idx[$row['bucket']] = $i; }
  while ($r = $resAB->fetch_assoc()) {
    $bucket = $r['bucket'];
    $mins = round(((int)($r['active_ms'] ?? 0))/60000, 2);
    if (isset($idx[$bucket])) {
      $series[$idx[$bucket]]['active_minutes'] = $mins;
    } else {
      // في حال لا يوجد دخول في هذه السلة، أضف نقطة بدقائق فقط
      $series[] = [
        'bucket' => $bucket,
        'visits' => 0,
        'visitors' => 0,
        'active_minutes' => $mins
      ];
    }
  }
  $stmAB->close();
} else {
  // السلوك الافتراضي: من جدول analytics_events
  $sql = "SELECT $bucketSelect AS bucket,
                 COUNT(CASE WHEN event='pageview' THEN 1 END) AS visits,
                 COUNT(DISTINCT sid) AS visitors,
                 SUM(active_ms) AS active_ms
          FROM analytics_events
          WHERE page = ? AND ts_ms BETWEEN ? AND ?
          GROUP BY bucket
          ORDER BY bucket ASC";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('sii', $page, $startMs, $endMs);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $series[] = [
      'bucket' => $row['bucket'],
      'visits' => (int)($row['visits'] ?? 0),
      'visitors' => (int)($row['visitors'] ?? 0),
      'active_minutes' => round(((int)($row['active_ms'] ?? 0))/60000, 2)
    ];
    $totalVisits += (int)($row['visits'] ?? 0);
    $totalActiveMs += (int)($row['active_ms'] ?? 0);
  }
  $stmt->close();
}

// إجماليات: زوار فريدون (للصفحات غير member فقط)
$uniq_visitors = 0;
if ($page !== 'member') {
  $sql2 = "SELECT COUNT(DISTINCT sid) AS uniq_visitors FROM analytics_events WHERE page=? AND ts_ms BETWEEN ? AND ?";
  $stm2 = $conn->prepare($sql2);
  $stm2->bind_param('sii', $page, $startMs, $endMs);
  $stm2->execute();
  $stm2->bind_result($uniq_visitors);
  $stm2->fetch();
  $stm2->close();
}

// عدد تسجيلات دخول المشتركين ضمن الفترة (تُحسب فقط عندما تكون الصفحة member)
if ($page === 'member') {
  // نتوقع وجود عمود created_at في audit_log كـ TIMESTAMP
  try {
    $sql3 = "SELECT COUNT(*) AS c FROM audit_log WHERE action_type='login_success' AND user_no IS NULL AND created_at BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)";
    $stm3 = $conn->prepare($sql3);
    $stm3->bind_param('ii', $start, $now);
    $stm3->execute();
    $memberLogins = 0;
    $stm3->bind_result($memberLogins);
    $stm3->fetch();
    $stm3->close();
  } catch (Throwable $e) {
    $memberLogins = 0;
  }
}

$response = [
  'ok' => true,
  'page' => $page,
  'range' => $range,
  'start_ms' => $startMs,
  'end_ms' => $endMs,
  'totals' => [
    'visits' => $totalVisits,
    'visitors' => ($page === 'member') ? null : (int)$uniq_visitors,
    'member_logins' => ($page === 'member') ? (int)($memberLogins ?? 0) : null,
    'active_minutes' => round($totalActiveMs/60000, 2)
  ],
  'series' => $series
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
