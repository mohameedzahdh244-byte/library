<?php
require_once __DIR__ . '/../config/init.php'; // بدء الجلسة
include '../config/DB.php'; 

header('Content-Type: application/json');

// === منع القوة العمياء حسب IP + backoff (5 محاولات/10 دقائق => حظر 5 دقائق) ===
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$storePath = __DIR__ . '/../config/login_attempts.json';
$maxAttempts = 5;
$windowSecs = 10 * 60; // 10 دقائق
$blockSecs = 5 * 60;  // 5 دقائق

function readAttempts($path) {
    if (!file_exists($path)) return [];
    $fp = fopen($path, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $json = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}
function writeAttempts($path, $data) {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fp = fopen($path, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}
function cleanupWindow($records, $now, $windowSecs) {
    foreach ($records as $ip => $rec) {
        // إزالة محاولات قديمة خارج النافذة
        $attempts = array_values(array_filter($rec['attempts'] ?? [], function($t) use ($now, $windowSecs){
            return ($now - $t) <= $windowSecs;
        }));
        $records[$ip]['attempts'] = $attempts;
        // إزالة حظر منتهي
        if (!empty($records[$ip]['block_until']) && $now >= $records[$ip]['block_until']) {
            unset($records[$ip]['block_until']);
        }
        // تنظيف الإدخالات الفارغة
        if (empty($records[$ip]['attempts']) && empty($records[$ip]['block_until'])) {
            unset($records[$ip]);
        }
    }
    return $records;
}

$now = time();
$records = readAttempts($storePath);
$records = cleanupWindow($records, $now, $windowSecs);

// حالة الحظر الحالية
$ipRec = $records[$clientIp] ?? ['attempts' => []];
if (!empty($ipRec['block_until']) && $now < $ipRec['block_until']) {
    $retryAfter = $ipRec['block_until'] - $now;
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'تم حظرك مؤقتًا بسبب محاولات متكررة. حاول بعد ' . $retryAfter . ' ثانية.',
        'type' => 'warning'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
// حد محاولات بسيط عبر الجلسة (5 محاولات خلال 10 دقائق)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
}
$attempts = &$_SESSION['login_attempts'];
// إعادة ضبط النافذة الزمنية بعد 10 دقائق
if (time() - ($attempts['first'] ?? 0) > 600) { // 600 ثانية
    $attempts = ['count' => 0, 'first' => time()];
}
if ($attempts['count'] >= 5) {
    echo json_encode([
        'success' => false,
        'message' => 'عدد محاولات تسجيل الدخول كبير. حاول مجددًا بعد قليل.',
        'type' => 'warning'
    ]);
    exit();
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['username']) && isset($_POST['password'])
) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === '' || $password === '') {
        // تسجيل محاولة فاشلة (IP)
        $records[$clientIp]['attempts'][] = $now;
        $records = cleanupWindow($records, $now, $windowSecs);
        if (count($records[$clientIp]['attempts']) >= $maxAttempts) {
            $records[$clientIp]['block_until'] = $now + $blockSecs;
        }
        writeAttempts($storePath, $records);

        echo json_encode([
            'success' => false,
            'message' => 'يرجى إدخال اسم المستخدم وكلمة المرور',
            'type' => 'warning'
        ]);
        exit();
    }

    // تحقق من جدول المستخدمين
    $sql = "SELECT * FROM user WHERE user_no = ? OR user_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // تحقق من كلمة المرور (لاحقًا استبدلها بـ password_verify)
        if ($password === $row["user_password"]) {
            // نجاح: تنظيف محاولات IP
            unset($records[$clientIp]);
            writeAttempts($storePath, $records);

            // تجديد معرف الجلسة قبل تعيين الجلسة
            session_regenerate_id(true);
            $_SESSION['user_name'] = $row['user_name'];
            $_SESSION['user_no'] = $row['user_no'];
            $_SESSION['user_type'] = $row['user_type'] ?? 'staff';
            $_SESSION['last_activity'] = time();
            $_SESSION['session_start_time'] = time();

            // إعادة ضبط العدّاد بعد نجاح الدخول
            $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];

            // تحديد التوجيه حسب نوع المستخدم
            if ($row['user_type'] === 'admin') {
                $redirectUrl = '../admin/dashboard.php';
            } elseif ($row['user_type'] === 'staff') {
                $redirectUrl = '../admin/dashboard.php';
            } else {
                $redirectUrl = '../admin/dashboard.php';
            }

            // سجل عملية تسجيل دخول ناجحة (موظف/مدير)
            if (isset($auditLogger)) { $auditLogger->logLogin($row['user_no'] ?? null, true); }

            echo json_encode([
                'success' => true,
                'type' => 'success',
                'redirect' => $redirectUrl
            ]);
            exit();
        }
    } else {
        // تحقق من جدول المشتركين
        if (is_numeric($username)) {
            $sql = "SELECT * FROM customer WHERE mem_no = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
        } else {
            $sql = "SELECT * FROM customer WHERE mem_name = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if ($password === $row["mem_password"]) {
                // نجاح: تنظيف محاولات IP
                unset($records[$clientIp]);
                writeAttempts($storePath, $records);

                // تجديد معرف الجلسة
                session_regenerate_id(true);
                $_SESSION['user_name'] = $row['mem_name'];
                $_SESSION['user_no'] = $row['mem_no'];
                $_SESSION['user_type'] = 'member';
                $_SESSION['last_activity'] = time();
                $_SESSION['session_start_time'] = time();

                // إعادة ضبط العدّاد بعد نجاح الدخول
                $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];

                $redirectUrl = '../member/dashboard.php';

                // سجل عملية تسجيل دخول ناجحة (مشترك)
                if (isset($auditLogger)) { $auditLogger->logLogin($row['mem_no'] ?? null, true); }
                echo json_encode([
                    'success' => true,
                    'type' => 'success',
                    'redirect' => $redirectUrl
                ]);
                exit();
            }
        }
    }

    // فشل: زيادة العدّاد (جلسة) وتسجيل محاولة IP وقد ينتج عنه حظر
    $_SESSION['login_attempts']['count'] = ($_SESSION['login_attempts']['count'] ?? 0) + 1;
    $records[$clientIp]['attempts'][] = $now;
    $records = cleanupWindow($records, $now, $windowSecs);
    if (count($records[$clientIp]['attempts']) >= $maxAttempts) {
        $records[$clientIp]['block_until'] = $now + $blockSecs;
    }
    writeAttempts($storePath, $records);

    // إذا لم ينجح أي تحقق
    echo json_encode([
        'success' => false,
        'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
        'type' => 'error'
    ]);
    exit();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'طلب غير صالح. يرجى المحاولة لاحقاً.',
        'type' => 'error'
    ]);
    exit();
}

$conn->close();
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.',
        'type' => 'error'
    ]);
    exit();
}
