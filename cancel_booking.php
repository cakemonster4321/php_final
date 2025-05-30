<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;

if (!$booking_id || !is_numeric($booking_id)) {
    header("Location: dashboard.php?error=invalid_id");
    exit;
}

// 檢查是否該預約屬於目前用戶
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    header("Location: dashboard.php?error=not_found");
    exit;
}

// 將預約狀態改為 cancelled
$stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'cancelled' WHERE booking_id = ?");
$stmt->execute([$booking_id]);

// 將該時段重新標示為 open
$stmt = $pdo->prepare("UPDATE timeslots SET payment_status = 'open' WHERE slot_id = ?");
$stmt->execute([$booking['slot_id']]);

header("Location: dashboard.php?cancel=success");
exit;

