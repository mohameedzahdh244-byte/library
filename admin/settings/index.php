<?php
require_once '../../config/init.php';

// التحقق من صلاحيات المدير
checkAdminPermission();

$user_no = $_SESSION['user_no'];

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_library_info'])) {
        $library_name = trim($_POST['library_name']);
        $library_address = trim($_POST['library_address']);
        $library_phone = trim($_POST['library_phone']);
        $library_email = trim($_POST['library_email']);
        $library_website = trim($_POST['library_website']);
        $opening_hours = trim($_POST['opening_hours']);
        $library_description = trim($_POST['library_description']);
        
        if (!empty($library_name)) {
            // استخدم مخزن الإعدادات بنظام المفتاح/القيمة
            $ok = true;
            if (!$settings->set('library_name', $library_name, 'اسم المكتبة')) $ok = false;
            if (!$settings->set('library_address', $library_address, 'عنوان المكتبة')) $ok = false;
            if (!$settings->set('library_phone', $library_phone, 'هاتف المكتبة')) $ok = false;
            if (!$settings->set('library_email', $library_email, 'بريد المكتبة')) $ok = false;
            if (!$settings->set('library_website', $library_website, 'موقع المكتبة')) $ok = false;
            if (!$settings->set('working_hours', $opening_hours, 'ساعات العمل')) $ok = false;
            if (!$settings->set('library_description', $library_description, 'وصف المكتبة')) $ok = false;

            if ($ok) {
                $success_message = 'تم حفظ معلومات المكتبة بنجاح!';
            } else {
                $error_message = 'حدث خطأ أثناء حفظ المعلومات.';
            }
        } else {
            $error_message = 'اسم المكتبة مطلوب.';
        }
    }
    
    if (isset($_POST['save_borrowing_settings'])) {
        $max_books_per_member = intval($_POST['max_books_per_member']);
        $borrowing_period_days = intval($_POST['borrowing_period_days']);
        $renewal_period_days = intval($_POST['renewal_period_days']);
        $max_renewals = intval($_POST['max_renewals']);
        $fine_per_day = floatval($_POST['fine_per_day']);
        $reservation_expiry_hours = intval($_POST['reservation_expiry_hours']);
        
        if ($max_books_per_member > 0 && $borrowing_period_days > 0) {
            // احفظ الإعدادات في system_settings كمفاتيح
            $ok = true;
            if (!$settings->set('max_books_per_member', $max_books_per_member, 'الحد الأقصى للكتب لكل مشترك', 'number')) $ok = false;
            // ملاحظة: يستخدم النظام المفتاح loan_period_days
            if (!$settings->set('loan_period_days', $borrowing_period_days, 'مدة الإعارة بالأيام', 'number')) $ok = false;
            if (!$settings->set('renewal_period_days', $renewal_period_days, 'مدة التجديد بالأيام', 'number')) $ok = false;
            if (!$settings->set('max_renewals', $max_renewals, 'الحد الأقصى للتجديدات', 'number')) $ok = false;
            if (!$settings->set('fine_per_day', $fine_per_day, 'الغرامة لكل يوم', 'number')) $ok = false;
            if (!$settings->set('reservation_expiry_hours', $reservation_expiry_hours, 'صلاحية الحجز بالساعات', 'number')) $ok = false;

            if ($ok) {
                $success_message = 'تم حفظ إعدادات الإعارة بنجاح!';
            } else {
                $error_message = 'حدث خطأ أثناء حفظ الإعدادات.';
            }
        } else {
            $error_message = 'يرجى إدخال قيم صحيحة للإعدادات.';
        }
    }
    
    if (isset($_POST['save_system_settings'])) {
        $enable_notifications = isset($_POST['enable_notifications']) ? 1 : 0;
        $enable_audit_log = isset($_POST['enable_audit_log']) ? 1 : 0;
        $auto_backup = isset($_POST['auto_backup']) ? 1 : 0;
        $backup_frequency = $_POST['backup_frequency'];
        $session_timeout = intval($_POST['session_timeout']);
        $max_login_attempts = intval($_POST['max_login_attempts']);
        
        // خزّن الإعدادات كمفاتيح ضمن system_settings
        $ok = true;
        if (!$settings->set('enable_notifications', (bool)$enable_notifications, 'تفعيل الإشعارات', 'boolean')) $ok = false;
        if (!$settings->set('enable_audit_log', (bool)$enable_audit_log, 'تفعيل سجل العمليات', 'boolean')) $ok = false;
        if (!$settings->set('auto_backup', (bool)$auto_backup, 'النسخ الاحتياطي التلقائي', 'boolean')) $ok = false;
        if (!$settings->set('backup_frequency', $backup_frequency, 'تكرار النسخ الاحتياطي', 'text')) $ok = false;
        if (!$settings->set('session_timeout', $session_timeout, 'مهلة الجلسة بالدقائق', 'number')) $ok = false;
        if (!$settings->set('max_login_attempts', $max_login_attempts, 'حد محاولات تسجيل الدخول', 'number')) $ok = false;

        if ($ok) {
            $success_message = 'تم حفظ إعدادات النظام بنجاح!';
        } else {
            $error_message = 'حدث خطأ أثناء حفظ إعدادات النظام.';
        }
    }
}

// الحصول على الإعدادات الحالية
$libraryInfo = $settings->getLibraryInfo();
$borrowingSettings = $settings->getBorrowingSettings();
$systemSettings = [
    'enable_notifications' => (int)$settings->get('enable_notifications', 1),
    'enable_audit_log' => (int)$settings->get('enable_audit_log', 1),
    'auto_backup' => (int)$settings->get('auto_backup', 0),
    'backup_frequency' => (string)$settings->get('backup_frequency', 'daily'),
    'session_timeout' => (int)$settings->get('session_timeout', 30),
    'max_login_attempts' => (int)$settings->get('max_login_attempts', 5),
];

// الحصول على إحصائيات النظام
$embed = isset($_GET['embed']);
$stats = [
    'total_users' => 0,
    'total_books' => 0,
    'total_members' => 0,
    'total_borrows' => 0,
    'total_fines' => 0,
    'system_uptime' => 'غير متوفر'
];

// حساب الإحصائيات
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user");
$stmt->execute();
$stats['total_users'] = ($stmt->get_result()->fetch_assoc()['count']) ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM book");
$stmt->execute();
$stats['total_books'] = ($stmt->get_result()->fetch_assoc()['count']) ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM customer");
$stmt->execute();
$stats['total_members'] = ($stmt->get_result()->fetch_assoc()['count']) ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrow_transaction");
$stmt->execute();
$stats['total_borrows'] = ($stmt->get_result()->fetch_assoc()['count']) ?? 0;

// تفضيل عمود total_fine إن وجد، وإلا استخدام fine_amount، وإلا 0
$colCheck = $conn->prepare("SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'return_book'
      AND COLUMN_NAME IN ('total_fine','fine_amount')");
$colCheck->execute();
$res = $colCheck->get_result();
$fineColumn = null;
while ($row = $res->fetch_assoc()) {
    if ($row['COLUMN_NAME'] === 'total_fine') { $fineColumn = 'total_fine'; break; }
    if ($row['COLUMN_NAME'] === 'fine_amount') { $fineColumn = 'fine_amount'; }
}

if ($fineColumn) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(`$fineColumn`),0) AS total FROM return_book");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total_fines'] = $result['total'] ?? 0;
} else {
    $stats['total_fines'] = 0;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات النظام - <?php echo $libraryInfo['name']; ?></title>
    
    <!-- Bootstrap 5 RTL (Local) -->
    <link href="/assets/css/bootstrap.css" rel="stylesheet">
    <!-- Font Awesome (Local) -->
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts Cairo (Local) -->
    <link href="/assets/fonts/cairo/cairo.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #f8f9fa;
        }
        
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 0;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2rem;
        }
        
        .settings-header {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .settings-body {
            padding: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border: none;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-save {
            background: var(--success-color);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            background: #2ecc71;
            transform: translateY(-2px);
        }
        
        .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .system-info {
            background: rgba(52, 152, 219, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .backup-section {
            background: rgba(231, 76, 60, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }

        

    </style>
</head>
<body class="<?php echo $embed ? 'embedded' : ''; ?>">
    <div class="<?php echo $embed ? 'container-fluid px-0' : 'container'; ?>">
        <div class="settings-container">
            <!-- Page Header -->
            <div class="page-header position-relative">
                <h2 class="mb-2">
                    <i class="fas fa-cog me-2"></i>
                    إعدادات النظام
                </h2>
                <p class="mb-0">إدارة إعدادات المكتبة والنظام</p>
            </div>

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

            <!-- System Statistics -->
            <div class="row mb-4 justify-content-center">
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                        <div class="stats-label">المستخدمين</div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-success">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_books']; ?></div>
                        <div class="stats-label">الكتب</div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-info">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_members']; ?></div>
                        <div class="stats-label">المشتركين</div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-warning">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stats-number"><?php echo $stats['total_borrows']; ?></div>
                        <div class="stats-label">الإعارات</div>
                    </div>
                </div>
                
            </div>

            <!-- Library Information Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات المكتبة
                    </h5>
                </div>
                
                <div class="settings-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="library_name" class="form-label fw-bold">اسم المكتبة *</label>
                                    <input type="text" class="form-control" id="library_name" name="library_name" 
                                           value="<?php echo htmlspecialchars($libraryInfo['name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="library_phone" class="form-label fw-bold">رقم الهاتف</label>
                                    <input type="text" class="form-control" id="library_phone" name="library_phone" 
                                           value="<?php echo htmlspecialchars($libraryInfo['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="library_address" class="form-label fw-bold">عنوان المكتبة</label>
                            <textarea class="form-control" id="library_address" name="library_address" 
                                      rows="2"><?php echo htmlspecialchars($libraryInfo['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="library_email" class="form-label fw-bold">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" id="library_email" name="library_email" 
                                           value="<?php echo htmlspecialchars($libraryInfo['email'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="library_website" class="form-label fw-bold">الموقع الإلكتروني</label>
                                    <input type="url" class="form-control" id="library_website" name="library_website" 
                                           value="<?php echo htmlspecialchars($libraryInfo['website'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="opening_hours" class="form-label fw-bold">ساعات العمل</label>
                            <input type="text" class="form-control" id="opening_hours" name="opening_hours" 
                                   value="<?php echo htmlspecialchars($libraryInfo['working_hours'] ?? ''); ?>"
                                   placeholder="مثال: الأحد - الخميس: 8:00 ص - 4:00 م">
                        </div>
                        
                        <div class="mb-3">
                            <label for="library_description" class="form-label fw-bold">وصف المكتبة</label>
                            <textarea class="form-control" id="library_description" name="library_description" 
                                      rows="3"><?php echo htmlspecialchars($libraryInfo['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="save_library_info" class="btn btn-save text-white">
                            حفظ معلومات المكتبة
                            <i class="fas fa-save me-2"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Borrowing Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <h5 class="mb-0">
                        <i class="fas fa-hand-holding-usd me-2"></i>
                        إعدادات الإعارة
                    </h5>
                </div>
                
                <div class="settings-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_books_per_member" class="form-label fw-bold">الحد الأقصى للكتب لكل مشترك</label>
                                    <input type="number" class="form-control" id="max_books_per_member" name="max_books_per_member" 
                                           value="<?php echo $borrowingSettings['max_books_per_member'] ?? 3; ?>" min="1" max="10">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="borrowing_period_days" class="form-label fw-bold">مدة الإعارة بالأيام</label>
                                    <input type="number" class="form-control" id="borrowing_period_days" name="borrowing_period_days" 
                                           value="<?php echo $borrowingSettings['loan_period_days'] ?? 14; ?>" min="1" max="90">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="renewal_period_days" class="form-label fw-bold">مدة التجديد بالأيام</label>
                                    <input type="number" class="form-control" id="renewal_period_days" name="renewal_period_days" 
                                           value="<?php echo $settings->get('renewal_period_days', 7); ?>" min="1" max="30">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_renewals" class="form-label fw-bold">الحد الأقصى للتجديدات</label>
                                    <input type="number" class="form-control" id="max_renewals" name="max_renewals" 
                                           value="<?php echo $borrowingSettings['max_renewals'] ?? 2; ?>" min="0" max="5">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fine_per_day" class="form-label fw-bold">الغرامة لكل يوم (شيكل)</label>
                                    <input type="number" class="form-control" id="fine_per_day" name="fine_per_day" 
                                           value="<?php echo $borrowingSettings['fine_per_day'] ?? 1.00; ?>" min="0" step="0.01">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="reservation_expiry_hours" class="form-label fw-bold">صلاحية الحجز بالساعات</label>
                                    <input type="number" class="form-control" id="reservation_expiry_hours" name="reservation_expiry_hours" 
                                           value="<?php echo $borrowingSettings['reservation_expiry_hours'] ?? 48; ?>" min="1" max="168">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="save_borrowing_settings" class="btn btn-save text-white">
                            حفظ إعدادات الإعارة
                            <i class="fas fa-save me-2"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <h5 class="mb-0">
                        <i class="fas fa-server me-2"></i>
                        إعدادات النظام
                    </h5>
                </div>
                
                <div class="settings-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_notifications" name="enable_notifications"
                                               <?php echo ($systemSettings['enable_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="enable_notifications">
                                            تفعيل الإشعارات
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_audit_log" name="enable_audit_log"
                                               <?php echo ($systemSettings['enable_audit_log'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="enable_audit_log">
                                            تفعيل سجل العمليات
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="session_timeout" class="form-label fw-bold">مهلة الجلسة (دقائق)</label>
                                    <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                           value="<?php echo $systemSettings['session_timeout'] ?? 30; ?>" min="5" max="480">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_login_attempts" class="form-label fw-bold">الحد الأقصى لمحاولات تسجيل الدخول</label>
                                    <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                           value="<?php echo $systemSettings['max_login_attempts'] ?? 5; ?>" min="3" max="10">
                                </div>
                            </div>
                        </div>
                        
                        <div class="backup-section">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-database me-2"></i>
                                إعدادات النسخ الاحتياطي
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="auto_backup" name="auto_backup"
                                                   <?php echo ($systemSettings['auto_backup'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="auto_backup">
                                                النسخ الاحتياطي التلقائي
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="backup_frequency" class="form-label fw-bold">تكرار النسخ الاحتياطي</label>
                                        <select class="form-select" id="backup_frequency" name="backup_frequency">
                                            <option value="daily" <?php echo ($systemSettings['backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>يومياً</option>
                                            <option value="weekly" <?php echo ($systemSettings['backup_frequency'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>أسبوعياً</option>
                                            <option value="monthly" <?php echo ($systemSettings['backup_frequency'] ?? 'daily') === 'monthly' ? 'selected' : ''; ?>>شهرياً</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="save_system_settings" class="btn btn-save text-white">
                            حفظ إعدادات النظام
                            <i class="fas fa-save me-2"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Information -->
            <div class="settings-card">
                <div class="settings-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات النظام
                    </h5>
                </div>
                
                <div class="settings-body">
                    <div class="system-info">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>نظام التشغيل:</strong> <?php echo PHP_OS; ?></p>
                                <p><strong>خادم الويب:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'غير محدد'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>الذاكرة المستخدمة:</strong> <?php echo round(memory_get_usage(true) / 1024 / 1024, 2); ?> MB</p>
                                <p><strong>الحد الأقصى للذاكرة:</strong> <?php echo ini_get('memory_limit'); ?></p>
                                <p><strong>وقت التنفيذ:</strong> <?php echo round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?> ثانية</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="../reports/export.php?type=system_backup" class="btn btn-outline-primary">
                            نسخة احتياطية من قاعدة البيانات
                            <i class="fas fa-download me-2"></i>
                        </a>
                        <a href="../reports/export.php?type=system_logs" class="btn btn-outline-secondary">
                            سجل النظام
                            <i class="fas fa-file-alt me-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container (top-right) -->
    <div class="position-fixed top-0 start-0 p-3" style="z-index: 1080">
        <div id="liveToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastBody"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (Local) -->
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toast helper
        function showToast(message, type = 'success') {
            const toastEl = document.getElementById('liveToast');
            const toastBody = document.getElementById('toastBody');
            // Set color scheme
            toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-info', 'text-bg-warning');
            const map = { success: 'text-bg-success', error: 'text-bg-danger', info: 'text-bg-info', warning: 'text-bg-warning' };
            toastEl.classList.add(map[type] || 'text-bg-success');
            // Message
            toastBody.textContent = message;
            // Show for 3 seconds
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
        }

        // Trigger toast from PHP messages if present
        <?php if (isset($success_message)) { ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($success_message); ?>', 'success');
            });
        <?php } elseif (isset($error_message)) { ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($error_message); ?>', 'error');
            });
        <?php } ?>

        // تحديث تلقائي كل 5 دقائق
        setInterval(function() {
            location.reload();
        }, 300000);
        
        // تأكيد حفظ الإعدادات
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('هل أنت متأكد من حفظ هذه الإعدادات؟')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html> 