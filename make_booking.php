<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// 使用者未登入
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => '請先登入']);
    exit;
}

$user_id = $_SESSION['user_id'];
$slot_id = $_POST['slot_id'] ?? null;

// 基本驗證
if (!$slot_id || !is_numeric($slot_id)) {
    echo json_encode(['error' => '無效的時段 ID']);
    exit;
}

#拿到會員的能預約的數量
$limitMap = ['none' => 2, 'bronze' => 5, 'silver' => 7, 'gold' => 10];
$stmt = $pdo->prepare('SELECT membership FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$membership = $stmt->fetchColumn() ?: 'none';
$bookingLimit = $limitMap[$membership];

// 檢查此時段是否仍開放
$stmt = $pdo->prepare("SELECT * FROM timeslots WHERE slot_id = ? AND payment_status = 'open'");
$stmt->execute([$slot_id]);
$slot = $stmt->fetch();

if (!$slot) {
    echo json_encode(['error' => '此時段已被預約或不存在']);
    exit;
}

// 檢查目前已預約數量
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND payment_status != 'cancelled'");
$stmt->execute([$user_id]);
$current_count = $stmt->fetchColumn();

if ($current_count >= $bookingLimit) {
    echo json_encode(['error' => '您已達預約上限']);
    exit;
}


// 檢查是否已預約過此時段
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND slot_id = ?");
$stmt->execute([$user_id, $slot_id]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['error' => '您已預約過此時段'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* 建立預約紀錄並設定繳費期限（3 天內） */
$stmt = $pdo->prepare("
    INSERT INTO bookings
          (user_id, slot_id, payment_status, created_at, payment_due)
    VALUES (?,       ?,       'unpaid',      NOW(),      DATE_ADD(NOW(), INTERVAL 3 DAY))
");
$stmt->execute([$user_id, $slot_id]);



// 更新時段狀態為已預約（可選擇是否鎖定）
// $pdo->prepare("UPDATE timeslots SET payment_status = 'booked' WHERE slot_id = ?")->execute([$slot_id]);


echo json_encode(['success' => '預約成功'], JSON_UNESCAPED_UNICODE);

?>
