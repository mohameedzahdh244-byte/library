<?php
require_once '../../config/init.php';

// التحقق من صلاحيات الموظف
checkStaffPermission();

// معالجة تصفية التقارير
$report_type = $_GET['type'] ?? 'overdue';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department = $_GET['department'] ?? 'all';
$format = $_GET['format'] ?? 'excel';

// تسجيل عملية التصدير في سجل العمليات
try {
    if (isset($auditLogger)) {
        $logData = [
            'action'      => 'export_report',
            'report_type' => $report_type,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'department'  => $department,
            'format'      => $format,
            'exported_at' => date('Y-m-d H:i:s')
        ];
        // table_name = 'report' لتوحيد العرض في لوحة التحكم
        $auditLogger->log(null, 'export_report', 'report', null, null, $logData);
    }
} catch (Throwable $e) { /* تجاهل الأخطاء حتى لا تُعطل التنزيل */ }

// حالات خاصة: سجل النظام ونسخة احتياطية لقاعدة البيانات
if ($report_type === 'system_logs') {
    // تصدير JSON لسجل النظام من جدول audit_log
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Ymd_His') . '.json"');

    $rows = [];
    if ($res = $conn->query("SELECT * FROM audit_log")) {
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $res->free();
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($report_type === 'system_backup') {
    // تصدير نسخة احتياطية بصيغة SQL (بيانات فقط INSERTs)
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="db_backup_' . date('Ymd_His') . '.sql"');

    // عنوان وتعليقات
    echo "-- Library System Data Backup\n";
    echo "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // جلب أسماء الجداول
    $tables = [];
    if ($res = $conn->query("SHOW TABLES")) {
        while ($row = $res->fetch_array(MYSQLI_NUM)) { $tables[] = $row[0]; }
        $res->free();
    }

    foreach ($tables as $table) {
        // إفراغ جدول قبل الإدراج (اختياري)
        echo "-- Table: `{$table}`\n";
        echo "-- ----------------------------------------\n";
        echo "/* Data for table `{$table}` */\n";

        $result = $conn->query("SELECT * FROM `{$table}`");
        if ($result && $result->num_rows > 0) {
            $fields_info = $result->fetch_fields();
            $columns = array_map(function($f){ return '`' . $f->name . '`'; }, $fields_info);
            $col_list = implode(', ', $columns);

            $valuesChunk = [];
            while ($row = $result->fetch_assoc()) {
                $vals = [];
                foreach ($fields_info as $f) {
                    $name = $f->name;
                    if (is_null($row[$name])) {
                        $vals[] = 'NULL';
                    } else {
                        $escaped = $conn->real_escape_string($row[$name]);
                        $vals[] = "'" . str_replace(["\r","\n"],["\\r","\\n"], $escaped) . "'";
                    }
                }
                $valuesChunk[] = '(' . implode(', ', $vals) . ')';

                // تفريغ على دفعات لتفادي أحجام ضخمة جداً
                if (count($valuesChunk) >= 200) {
                    echo "INSERT INTO `{$table}` ({$col_list}) VALUES\n" . implode(",\n", $valuesChunk) . ";\n\n";
                    $valuesChunk = [];
                }
            }
            if (!empty($valuesChunk)) {
                echo "INSERT INTO `{$table}` ({$col_list}) VALUES\n" . implode(",\n", $valuesChunk) . ";\n\n";
            }
        }
        if ($result) { $result->free(); }
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

// الحصول على البيانات حسب نوع التقرير
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'overdue':
        $report_title = 'تقرير الكتب المتأخرة';
        
        $sql = "SELECT bt.*, b.book_title, bauth.authors AS author, b.department, c.mem_name, COALESCE(mp.mem_phone,'') AS mem_phone, c.mem_no,
                       DATEDIFF(CURDATE(), bt.boro_exp_ret_date) as days_overdue
                FROM borrow_transaction bt
                LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book
                LEFT JOIN (
                    SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
                    FROM book_authors ba
                    JOIN authors au ON au.ANO = ba.ANO
                    GROUP BY ba.serialnum_book
                ) bauth ON bauth.serialnum_book = b.serialnum_book
                INNER JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
                LEFT JOIN customer c ON ct.mem_no = c.mem_no
                LEFT JOIN (
                    SELECT mem_no, GROUP_CONCAT(DISTINCT mem_phone ORDER BY mem_phone SEPARATOR ', ') AS mem_phone
                    FROM mem_phone
                    GROUP BY mem_no
                ) mp ON mp.mem_no = c.mem_no
                LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                WHERE rb.return_date IS NULL AND bt.boro_exp_ret_date < CURDATE()";
        
        if ($department !== 'all') {
            $sql .= " AND b.department = ?";
        }
        
        $sql .= " ORDER BY bt.boro_exp_ret_date ASC";
        
        $stmt = $conn->prepare($sql);
        if ($department !== 'all') {
            $stmt->bind_param('s', $department);
        }
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'borrowing_activity':
        $report_title = 'تقرير نشاط الإعارة';
        
        $sql = "SELECT DATE(bt.boro_date) as borrow_date, COUNT(*) as borrow_count,
                       SUM(CASE WHEN rb.return_date IS NOT NULL THEN 1 ELSE 0 END) as returned_count,
                       SUM(CASE WHEN rb.return_date IS NULL THEN 1 ELSE 0 END) as active_count
                FROM borrow_transaction bt
                LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                WHERE bt.boro_date BETWEEN ? AND ?
                GROUP BY DATE(bt.boro_date)
                ORDER BY borrow_date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $date_from, $date_to);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'popular_books':
        $report_title = 'تقرير الكتب الأكثر إعارة';
        
        $sql = "SELECT b.book_title, bauth.authors AS author, b.department, COUNT(*) as borrow_count,
                       SUM(CASE WHEN rb.return_date IS NOT NULL THEN 1 ELSE 0 END) as returned_count
                FROM borrow_transaction bt
                LEFT JOIN book b ON bt.serialnum_book = b.serialnum_book
                LEFT JOIN (
                    SELECT ba.serialnum_book, GROUP_CONCAT(au.Aname ORDER BY au.Aname SEPARATOR ', ') AS authors
                    FROM book_authors ba
                    JOIN authors au ON au.ANO = ba.ANO
                    GROUP BY ba.serialnum_book
                ) bauth ON bauth.serialnum_book = b.serialnum_book
                LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                WHERE bt.boro_date BETWEEN ? AND ?";
        
        if ($department !== 'all') {
            $sql .= " AND b.department = ?";
        }
        
        $sql .= " GROUP BY b.serialnum_book
                  ORDER BY borrow_count DESC
                  LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        if ($department !== 'all') {
            $stmt->bind_param('sss', $date_from, $date_to, $department);
        } else {
            $stmt->bind_param('ss', $date_from, $date_to);
        }
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'member_activity':
        $report_title = 'تقرير نشاط المشتركين';
        
        $sql = "SELECT c.mem_name, c.mem_no, COALESCE(mp.mem_phone,'') AS mem_phone, COUNT(*) as total_borrows,
                       SUM(CASE WHEN rb.return_date IS NOT NULL THEN 1 ELSE 0 END) as returned_borrows,
                       SUM(CASE WHEN rb.return_date IS NULL THEN 1 ELSE 0 END) as active_borrows,
                       SUM(CASE WHEN rb.return_date IS NULL AND bt.boro_exp_ret_date < CURDATE() THEN 1 ELSE 0 END) as overdue_borrows
                FROM borrow_transaction bt
                INNER JOIN customer_transaction ct ON bt.boro_no = ct.boro_no
                LEFT JOIN customer c ON ct.mem_no = c.mem_no
                LEFT JOIN (
                    SELECT mem_no, GROUP_CONCAT(DISTINCT mem_phone ORDER BY mem_phone SEPARATOR ', ') AS mem_phone
                    FROM mem_phone
                    GROUP BY mem_no
                ) mp ON mp.mem_no = c.mem_no
                LEFT JOIN return_book rb ON bt.borrow_detail_id = rb.borrow_detail_id
                WHERE bt.boro_date BETWEEN ? AND ?
                GROUP BY c.mem_no
                ORDER BY total_borrows DESC
                LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $date_from, $date_to);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
}

// إنشاء ملف Excel
if ($format === 'excel') {
    // تعيين headers للتحميل
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $report_title . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // بداية ملف HTML (Excel يتعرف على HTML)
    echo '<!DOCTYPE html>';
    echo '<html dir="rtl">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '.header { background-color: #4CAF50; color: white; text-align: center; padding: 20px; }';
    echo '.stats { background-color: #f9f9f9; padding: 10px; margin: 10px 0; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // عنوان التقرير
    echo '<div class="header">';
    echo '<h1>' . $report_title . '</h1>';
    echo '<p>تاريخ التصدير: ' . date('Y/m/d H:i') . '</p>';
    echo '<p>الفترة: من ' . date('Y/m/d', strtotime($date_from)) . ' إلى ' . date('Y/m/d', strtotime($date_to)) . '</p>';
    if ($department !== 'all') {
        echo '<p>القسم: ' . htmlspecialchars($department) . '</p>';
    }
    echo '</div>';
    
    // إحصائيات سريعة
    echo '<div class="stats">';
    echo '<h3>إحصائيات سريعة</h3>';
    echo '<p>إجمالي النتائج: ' . count($report_data) . '</p>';
    
    if ($report_type === 'overdue') {
        $total_fines = array_sum(array_column($report_data, 'days_overdue')) * 1.00;
        echo '<p>إجمالي الغرامات المحتملة: ' . number_format($total_fines, 2) . ' شيكل</p>';
    } elseif ($report_type === 'borrowing_activity') {
        $total_borrows = array_sum(array_column($report_data, 'borrow_count'));
        $total_returns = array_sum(array_column($report_data, 'returned_count'));
        echo '<p>إجمالي الإعارات: ' . $total_borrows . '</p>';
        echo '<p>إجمالي الإرجاعات: ' . $total_returns . '</p>';
    }
    echo '</div>';
    
    // جدول البيانات
    if (!empty($report_data)) {
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        
        // رؤوس الأعمدة حسب نوع التقرير
        switch ($report_type) {
            case 'overdue':
                echo '<th>اسم المشترك</th>';
                echo '<th>رقم الهاتف</th>';
                echo '<th>عنوان الكتاب</th>';
                echo '<th>المؤلف</th>';
                echo '<th>القسم</th>';
                echo '<th>تاريخ الإعارة</th>';
                echo '<th>تاريخ الاستحقاق</th>';
                echo '<th>أيام التأخير</th>';
                echo '<th>الغرامة المحتملة</th>';
                break;
                
            case 'borrowing_activity':
                echo '<th>التاريخ</th>';
                echo '<th>عدد الإعارات</th>';
                echo '<th>عدد الإرجاعات</th>';
                echo '<th>الإعارات النشطة</th>';
                break;
                
            case 'popular_books':
                echo '<th>عنوان الكتاب</th>';
                echo '<th>المؤلف</th>';
                echo '<th>القسم</th>';
                echo '<th>عدد الإعارات</th>';
                echo '<th>عدد الإرجاعات</th>';
                echo '<th>نسبة الإرجاع</th>';
                break;
                
            case 'member_activity':
                echo '<th>اسم المشترك</th>';
                echo '<th>رقم العضوية</th>';
                echo '<th>رقم الهاتف</th>';
                echo '<th>إجمالي الإعارات</th>';
                echo '<th>الإعارات المكتملة</th>';
                echo '<th>الإعارات النشطة</th>';
                echo '<th>الإعارات المتأخرة</th>';
                echo '<th>نسبة الالتزام</th>';
                break;
                
        }
        
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        // بيانات الصفوف
        foreach ($report_data as $row) {
            echo '<tr>';
            
            switch ($report_type) {
                case 'overdue':
                    echo '<td>' . htmlspecialchars($row['mem_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['mem_phone']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['book_title']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['author'] ?? 'غير محدد') . '</td>';
                    echo '<td>' . htmlspecialchars($row['department'] ?? 'غير محدد') . '</td>';
                    echo '<td>' . date('Y/m/d', strtotime($row['boro_date'])) . '</td>';
                    echo '<td>' . date('Y/m/d', strtotime($row['boro_exp_ret_date'])) . '</td>';
                    echo '<td>' . $row['days_overdue'] . ' يوم</td>';
                    echo '<td>' . number_format($row['days_overdue'] * 1.00, 2) . ' شيكل</td>';
                    break;
                    
                case 'borrowing_activity':
                    echo '<td>' . date('Y/m/d', strtotime($row['borrow_date'])) . '</td>';
                    echo '<td>' . $row['borrow_count'] . '</td>';
                    echo '<td>' . $row['returned_count'] . '</td>';
                    echo '<td>' . $row['active_count'] . '</td>';
                    break;
                    
                case 'popular_books':
                    echo '<td>' . htmlspecialchars($row['book_title']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['author'] ?? 'غير محدد') . '</td>';
                    echo '<td>' . htmlspecialchars($row['department'] ?? 'غير محدد') . '</td>';
                    echo '<td>' . $row['borrow_count'] . '</td>';
                    echo '<td>' . $row['returned_count'] . '</td>';
                    $return_rate = $row['borrow_count'] > 0 ? round(($row['returned_count'] / $row['borrow_count']) * 100, 1) : 0;
                    echo '<td>' . $return_rate . '%</td>';
                    break;
                    
                case 'member_activity':
                    echo '<td>' . htmlspecialchars($row['mem_name']) . '</td>';
                    echo '<td>' . $row['mem_no'] . '</td>';
                    echo '<td>' . htmlspecialchars($row['mem_phone']) . '</td>';
                    echo '<td>' . $row['total_borrows'] . '</td>';
                    echo '<td>' . $row['returned_borrows'] . '</td>';
                    echo '<td>' . $row['active_borrows'] . '</td>';
                    echo '<td>' . $row['overdue_borrows'] . '</td>';
                    $compliance_rate = $row['total_borrows'] > 0 ? round(($row['returned_borrows'] / $row['total_borrows']) * 100, 1) : 0;
                    echo '<td>' . $compliance_rate . '%</td>';
                    break;
                    
            }
            
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p style="text-align: center; color: #666; padding: 20px;">لا توجد بيانات متاحة لهذا التقرير</p>';
    }
    
    echo '</body>';
    echo '</html>';
    
} else {
    // تصدير بصيغة CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $report_title . '_' . date('Y-m-d') . '.csv"');
    
    // إضافة BOM للدعم العربي
    echo "\xEF\xBB\xBF";
    
    // رؤوس الأعمدة
    $headers = [];
    switch ($report_type) {
        case 'overdue':
            $headers = ['اسم المشترك', 'رقم الهاتف', 'عنوان الكتاب', 'المؤلف', 'القسم', 'تاريخ الإعارة', 'تاريخ الاستحقاق', 'أيام التأخير', 'الغرامة المحتملة'];
            break;
        case 'borrowing_activity':
            $headers = ['التاريخ', 'عدد الإعارات', 'عدد الإرجاعات', 'الإعارات النشطة'];
            break;
        case 'popular_books':
            $headers = ['عنوان الكتاب', 'المؤلف', 'القسم', 'عدد الإعارات', 'عدد الإرجاعات', 'نسبة الإرجاع'];
            break;
        case 'member_activity':
            $headers = ['اسم المشترك', 'رقم العضوية', 'رقم الهاتف', 'إجمالي الإعارات', 'الإعارات المكتملة', 'الإعارات النشطة', 'الإعارات المتأخرة', 'نسبة الالتزام'];
            break;
    }
    
    // كتابة رؤوس الأعمدة
    echo implode(',', $headers) . "\n";
    
    // كتابة البيانات
    foreach ($report_data as $row) {
        $csv_row = [];
        
        switch ($report_type) {
            case 'overdue':
                $csv_row = [
                    $row['mem_name'],
                    $row['mem_phone'],
                    $row['book_title'],
                    $row['author'] ?? 'غير محدد',
                    $row['department'] ?? 'غير محدد',
                    date('Y/m/d', strtotime($row['boro_date'])),
                    date('Y/m/d', strtotime($row['boro_exp_ret_date'])),
                    $row['days_overdue'] . ' يوم',
                    number_format($row['days_overdue'] * 1.00, 2) . ' شيكل'
                ];
                break;
                
            case 'borrowing_activity':
                $csv_row = [
                    date('Y/m/d', strtotime($row['borrow_date'])),
                    $row['borrow_count'],
                    $row['returned_count'],
                    $row['active_count']
                ];
                break;
                
            case 'popular_books':
                $return_rate = $row['borrow_count'] > 0 ? round(($row['returned_count'] / $row['borrow_count']) * 100, 1) : 0;
                $csv_row = [
                    $row['book_title'],
                    $row['author'] ?? 'غير محدد',
                    $row['department'] ?? 'غير محدد',
                    $row['borrow_count'],
                    $row['returned_count'],
                    $return_rate . '%'
                ];
                break;
                
            case 'member_activity':
                $compliance_rate = $row['total_borrows'] > 0 ? round(($row['returned_borrows'] / $row['total_borrows']) * 100, 1) : 0;
                $csv_row = [
                    $row['mem_name'],
                    $row['mem_no'],
                    $row['mem_phone'],
                    $row['total_borrows'],
                    $row['returned_borrows'],
                    $row['active_borrows'],
                    $row['overdue_borrows'],
                    $compliance_rate . '%'
                ];
                break;
                
        }
        
        // تنظيف البيانات للـ CSV
        $csv_row = array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $csv_row);
        
        echo implode(',', $csv_row) . "\n";
    }
}

// "بدون تسجيل عمليات" حسب طلب العميل
?> 