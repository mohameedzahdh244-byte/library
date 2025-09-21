<?php
// بحث تلقائي عن المشتركين (برقم أو اسم)

require_once __DIR__ . '/../../config/init.php';
include_once __DIR__ . '/../../config/DB.php';
$q = trim($_POST['q'] ?? '');
if(strlen($q) < 2) exit;
$today = date('Y-m-d');
// عرض جميع المشتركين (ساري ومنتهي) مع آخر تاريخ اشتراك
$sql = "
    SELECT 
        c.mem_no, 
        c.mem_name, 
        GROUP_CONCAT(DISTINCT mp.mem_phone SEPARATOR ', ') AS mem_phone,
        ms.latest_end_date AS end_date
    FROM customer c
    LEFT JOIN mem_phone mp ON mp.mem_no = c.mem_no
    LEFT JOIN (
        SELECT mem_no, MAX(end_date) AS latest_end_date
        FROM member_subscription
        GROUP BY mem_no
    ) ms ON ms.mem_no = c.mem_no
    WHERE (c.mem_no LIKE CONCAT('%', ?, '%') OR c.mem_name LIKE CONCAT('%', ?, '%'))
    GROUP BY c.mem_no, c.mem_name, ms.latest_end_date
    ORDER BY (ms.latest_end_date IS NULL) ASC, ms.latest_end_date DESC
    LIMIT 10
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $q, $q);
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows == 0) {
    echo '<div class="alert alert-warning py-1">لا يوجد مشترك مطابق</div>';
    exit;
}
echo '<ul class="list-group">';
while($row = $res->fetch_assoc()) {
    $end = $row['end_date'] ?? '';
    $status_badge = '';
    if ($end) {
        $status_badge = ($end >= $today)
            ? '<span class="badge bg-success">'.htmlspecialchars($end).'</span>'
            : '<span class="badge bg-danger">'.htmlspecialchars($end).'</span>';
    } else {
        $status_badge = '<span class="badge bg-secondary">لا اشتراك</span>';
    }

    echo '<li class="list-group-item choose-member" style="cursor:pointer" data-no="'.htmlspecialchars($row['mem_no'] ?? '').'" data-name="'.htmlspecialchars($row['mem_name'] ?? '').'" data-phone="'.htmlspecialchars($row['mem_phone'] ?? '').'" data-subscription-end="'.htmlspecialchars($end).'">';
    echo '<b>'.htmlspecialchars($row['mem_name'] ?? '').'</b> <span class="text-muted">('.htmlspecialchars($row['mem_no'] ?? '').')</span>';
    echo ' <span class="badge bg-info">'.htmlspecialchars($row['mem_phone'] ?? '').'</span>';
    echo ' '.$status_badge;
    echo '</li>';
}
echo '</ul>';
