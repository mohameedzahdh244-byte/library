<?php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=utf-8');

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'method_not_allowed']);
  exit;
}

// Read JSON body (Beacon may send as application/json)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = []; }

$page      = isset($data['page']) ? substr((string)$data['page'], 0, 64) : '';
$event     = isset($data['event']) ? substr((string)$data['event'], 0, 32) : 'pageview';
$active_ms = isset($data['active_ms']) ? (int)$data['active_ms'] : 0;
$url       = isset($data['url']) ? substr((string)$data['url'], 0, 512) : '';
$ref       = isset($data['ref']) ? substr((string)$data['ref'], 0, 512) : '';
session_start();
$sid       = isset($data['sid']) ? substr((string)$data['sid'], 0, 128) : (isset($_SESSION['user_no']) ? 'user_'.$_SESSION['user_no'] : '');
$ua        = isset($data['ua']) ? substr((string)$data['ua'], 0, 512) : ($_SERVER['HTTP_USER_AGENT'] ?? '');
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';
$ts        = isset($data['ts']) ? (int)$data['ts'] : (int)(microtime(true)*1000);

if ($page === '') {
  echo json_encode(['ok'=>false,'msg'=>'missing_page']);
  exit;
}

// Create table if not exists
$ddl = "CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ts_ms BIGINT NOT NULL,
  page VARCHAR(64) NOT NULL,
  event VARCHAR(32) NOT NULL,
  active_ms INT NOT NULL DEFAULT 0,
  url VARCHAR(512) NULL,
  ref VARCHAR(512) NULL,
  sid VARCHAR(128) NULL,
  ua VARCHAR(512) NULL,
  ip VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_page_ts (page, ts_ms),
  INDEX idx_event (event),
  INDEX idx_sid (sid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($ddl);

$stmt = $conn->prepare("INSERT INTO analytics_events (ts_ms, page, event, active_ms, url, ref, sid, ua, ip) VALUES (?,?,?,?,?,?,?,?,?)");
if ($stmt) {
  $stmt->bind_param('ississsss', $ts, $page, $event, $active_ms, $url, $ref, $sid, $ua, $ip);
  $stmt->execute();
  $stmt->close();
}

echo json_encode(['ok'=>true]);
