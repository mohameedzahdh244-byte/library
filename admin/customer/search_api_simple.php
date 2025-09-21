<?php
require_once '../../config/init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Only staff/admin
    checkStaffPermission();

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = 30;

    if ($q === '') {
        echo json_encode(['success' => true, 'members' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Build LIKE pattern
    $like = "%" . $q . "%";

    // Search by mem_no exact OR mem_name LIKE OR phone LIKE (from mem_phone table)
    // Determine status from latest subscription end date (member_subscription.end_date)
    $sql = "SELECT 
                c.mem_no,
                c.mem_name,
                (
                  SELECT mp.mem_phone 
                  FROM mem_phone mp 
                  WHERE mp.mem_no = c.mem_no 
                  ORDER BY mp.id_phone ASC 
                  LIMIT 1
                ) AS mem_phone,
                CASE WHEN COALESCE(MAX(ms.end_date), DATE('1900-01-01')) >= CURDATE() THEN 'ساري' ELSE 'منتهي' END AS mem_status
            FROM customer c
            LEFT JOIN member_subscription ms ON c.mem_no = ms.mem_no
            WHERE c.mem_no = ?
               OR c.mem_name LIKE ?
               OR EXISTS (
                    SELECT 1 FROM mem_phone mp2
                    WHERE mp2.mem_no = c.mem_no AND mp2.mem_phone LIKE ?
               )
            GROUP BY c.mem_no, c.mem_name
            ORDER BY c.mem_name ASC
            LIMIT ?";

    if (!$stmt = $conn->prepare($sql)) {
        throw new Exception('Failed to prepare statement');
    }

    // mem_no sometimes numeric; bind as string for compatibility
    $stmt->bind_param('sssi', $q, $like, $like, $limit);
    $stmt->execute();
    $res = $stmt->get_result();

    $members = [];
    while ($row = $res->fetch_assoc()) {
        $members[] = [
            'mem_no'    => $row['mem_no'] ?? '',
            'mem_name'  => $row['mem_name'] ?? '',
            'mem_phone' => $row['mem_phone'] ?? '',
            'mem_status'=> $row['mem_status'] ?? '',
        ];
    }

    echo json_encode(['success' => true, 'members' => $members], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error'], JSON_UNESCAPED_UNICODE);
}
