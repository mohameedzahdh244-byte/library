<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../auth/check_auth.php';

// السماح فقط للموظفين/المديرين
checkStaffPermission();

$report_type = $_GET['type'] ?? 'overdue';
$date_from   = $_GET['date_from'] ?? date('Y-m-01');
$date_to     = $_GET['date_to'] ?? date('Y-m-d');
$department  = $_GET['department'] ?? 'all';

try {
    if (isset($auditLogger)) {
        $logData = [
            'action'      => 'print_report',
            'report_type' => $report_type,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'department'  => $department,
            'printed_at'  => date('Y-m-d H:i:s')
        ];
        $auditLogger->log(null, 'print_report', 'report', null, null, $logData);
    }
    http_response_code(204); // بدون محتوى
} catch (Throwable $e) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok']);
}
