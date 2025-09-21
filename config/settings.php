<?php
/**
 * ملف إدارة إعدادات النظام
 * System Settings Management
 */





class SystemSettings {

    private $conn;
    private $cache = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadSettings();
    }
    
    /**
     * تحميل جميع الإعدادات من قاعدة البيانات
     */
    private function loadSettings() {
        $stmt = $this->conn->prepare("SELECT setting_key, setting_value, setting_type FROM system_settings");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $value = $this->parseSettingValue($row['setting_value'], $row['setting_type']);
            $this->cache[$row['setting_key']] = $value;
        }
    }
    
    /**
     * تحليل قيمة الإعداد حسب نوعه
     */
    private function parseSettingValue($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true) ?: [];
            default:
                return $value;
        }
    }
    
    /**
     * الحصول على قيمة إعداد
     */
    public function get($key, $default = null) {
        return $this->cache[$key] ?? $default;
    }
    
    /**
     * تعيين قيمة إعداد
     */
    public function set($key, $value, $description = '', $type = 'text', $is_public = false) {
        $value_str = $this->serializeSettingValue($value, $type);
        
        $stmt = $this->conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_description, setting_type, is_public) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            setting_description = VALUES(setting_description),
            setting_type = VALUES(setting_type),
            is_public = VALUES(is_public),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->bind_param("ssssi", $key, $value_str, $description, $type, $is_public);
        $result = $stmt->execute();
        
        if ($result) {
            $this->cache[$key] = $value;
        }
        
        return $result;
    }
    
    /**
     * تسلسل قيمة الإعداد
     */
    private function serializeSettingValue($value, $type) {
        switch ($type) {
            case 'json':
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            case 'boolean':
                return $value ? '1' : '0';
            default:
                return (string)$value;
        }
    }
    
    /**
     * الحصول على جميع الإعدادات العامة
     */
    public function getPublicSettings() {
        $stmt = $this->conn->prepare("
            SELECT setting_key, setting_value, setting_description, setting_type 
            FROM system_settings 
            WHERE is_public = 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = [
                'value' => $this->parseSettingValue($row['setting_value'], $row['setting_type']),
                'description' => $row['setting_description'],
                'type' => $row['setting_type']
            ];
        }
        
        return $settings;
    }
    
    /**
     * الحصول على إعدادات المكتبة
     */
    public function getLibraryInfo() {
        return [
            'name' => $this->get('library_name', 'مكتبة بلدية الخليل'),
            'address' => $this->get('library_address', 'الخليل، فلسطين'),
            'phone' => $this->get('library_phone', '+970-2-222-8000'),
            'email' => $this->get('library_email', 'library@hebron-city.ps'),
            'working_hours' => $this->get('working_hours', [])
        ];
    }
    
    /**
     * الحصول على إعدادات الإعارة
     */
    public function getBorrowingSettings() {
        return [
            'max_books_per_member' => $this->get('max_books_per_member', 5),
            'loan_period_days' => $this->get('loan_period_days', 14),
            'max_renewals' => $this->get('max_renewals', 2),
            'fine_per_day' => $this->get('fine_per_day', 1.00),
            'reservation_expiry_hours' => $this->get('reservation_expiry_hours', 48)
        ];
    }
}

// إنشاء كائن الإعدادات العام
global $settings;
if (!isset($settings)) {
    $settings = new SystemSettings($conn);
}
?> 