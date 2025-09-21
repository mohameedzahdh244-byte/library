<?php
/**
 * إدارة سجل العمليات (Audit Trail)
 * نقطة مركزية لتسجيل جميع العمليات في النظام.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class AuditLogger {
    private $conn;
    private static $hasMemNoColumn = null; // cache

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // التحقق مرة واحدة من وجود عمود mem_no
    private function hasMemNoColumn(): bool
    {
        if (self::$hasMemNoColumn !== null) return self::$hasMemNoColumn;
        $exists = false;
        if ($res = $this->conn->query("SHOW COLUMNS FROM audit_log LIKE 'mem_no'")) {
            $exists = $res->num_rows > 0;
            $res->free();
        }
        self::$hasMemNoColumn = $exists;
        return $exists;
    }

    // التحقق من وجود user_no فعلاً في جدول user
    private function userExists($userNo): bool
    {
        if (empty($userNo)) return false;
        $stmt = $this->conn->prepare("SELECT 1 FROM user WHERE user_no = ? LIMIT 1");
        $stmt->bind_param('s', $userNo);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }

    // تحديد الممثل (actor) حسب الدور والعملية: موظف/مدير => user_no،
    // مشترك => mem_no فقط في حالة حجز الكتب (table_name = 'book_reservation')
    private function resolveActor(string $action_type = null, string $table_name = null): array
    {
        $userType = $_SESSION['user_type'] ?? null; // admin, staff, member, ...
        $sessionUserNo = $_SESSION['user_no'] ?? null;

        $actorUserNo = null;
        $actorMemNo = null;

        if (in_array($userType, ['admin', 'staff'], true)) {
            // موظف/مدير: استخدم user_no إن كان صالحاً
            if ($this->userExists($sessionUserNo)) {
                $actorUserNo = $sessionUserNo;
            }
        } else {
            // عضو/مستخدم غير موظف: لا نملأ user_no لتجنب كسر FK
            $actorUserNo = null;
            // نخزّن mem_no فقط إذا كانت العملية تخص حجز الكتب
            $isReservation = ($table_name === 'book_reservation');
            if ($isReservation && $this->hasMemNoColumn()) {
                $actorMemNo = $sessionUserNo; // في هذا النظام mem_no يُحفظ في الجلسة
            }
        }

        return [$actorUserNo, $actorMemNo];
    }

    /**
     * تسجيل عملية عامة
     * old_values/new_values يمكن أن يكونا مصفوفات وسيتم ترميزها JSON
     */
    public function log($user_no, $action_type, $table_name = null, $record_id = null, $old_values = null, $new_values = null)
    {
        // تجاهل $user_no الممرّر، واعتمد على الجلسة + نوع الجدول لتحديد الممثل
        [$actorUserNo, $actorMemNo] = $this->resolveActor($action_type, $table_name);

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $session_id = session_id();

        $old_json = $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
        $new_json = $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;

        if ($this->hasMemNoColumn()) {
            $sql = "INSERT INTO audit_log (user_no, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, session_id, mem_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                'ssssssssss',
                $actorUserNo,
                $action_type,
                $table_name,
                $record_id,
                $old_json,
                $new_json,
                $ip_address,
                $user_agent,
                $session_id,
                $actorMemNo
            );
        } else {
            $sql = "INSERT INTO audit_log (user_no, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                'sssssssss',
                $actorUserNo,
                $action_type,
                $table_name,
                $record_id,
                $old_json,
                $new_json,
                $ip_address,
                $user_agent,
                $session_id
            );
        }

        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // مختصرات شائعة
    public function logLogin($user_no = null, $success = true) {
        $action = $success ? 'login_success' : 'login_failed';
        return $this->log(null, $action);
    }

    public function logLogout($user_no = null) {
        return $this->log(null, 'logout');
    }

    public function logCreate($user_no, $table_name, $record_id, $new_values) {
        return $this->log(null, 'create', $table_name, $record_id, null, $new_values);
    }

    public function logUpdate($user_no, $table_name, $record_id, $old_values, $new_values) {
        return $this->log(null, 'update', $table_name, $record_id, $old_values, $new_values);
    }

    public function logDelete($user_no, $table_name, $record_id, $old_values) {
        return $this->log(null, 'delete', $table_name, $record_id, $old_values, null);
    }

    // عمليات المكتبة
    public function logBorrow($user_no, $transaction_id, $book_id = null, $member_no = null) {
        $data = [
            'transaction_id' => $transaction_id,
            'book_id' => $book_id,
            'member_no' => $member_no,
            'borrow_date' => date('Y-m-d H:i:s')
        ];
        return $this->log(null, 'borrow_book', 'borrow_transaction', $transaction_id, null, $data);
    }

    public function logReturn($user_no, $transaction_id, $book_id = null, $member_no = null, $fine_amount = 0) {
        $data = [
            'transaction_id' => $transaction_id,
            'book_id' => $book_id,
            'member_no' => $member_no,
            'return_date' => date('Y-m-d H:i:s'),
            'fine_amount' => $fine_amount
        ];
        return $this->log(null, 'return_book', 'return_transaction', $transaction_id, null, $data);
    }

    public function logRenewal($user_no, $transaction_id, $book_id = null, $member_no = null, $old_due_date = null, $new_due_date = null) {
        $data = [
            'transaction_id' => $transaction_id,
            'book_id' => $book_id,
            'member_no' => $member_no,
            'old_due_date' => $old_due_date,
            'new_due_date' => $new_due_date,
            'renewal_date' => date('Y-m-d H:i:s')
        ];
        return $this->log(null, 'renew_book', 'borrow_transaction', $transaction_id, null, $data);
    }
}

// إنشاء كائن السجلات العام
global $auditLogger, $conn;
if (!isset($auditLogger) && isset($conn) && $conn instanceof mysqli) {
    $auditLogger = new AuditLogger($conn);
}

?>
