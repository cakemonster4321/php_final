<?php
session_start();
require_once 'db_config.php';

// 尚未登入，導回首頁
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$booking_id = $_GET['booking_id'] ?? null;
if (!$booking_id || !is_numeric($booking_id)) {
    echo "無效的預約 ID";
    exit;
}

// 查詢 booking + slot + facility
$stmt = $pdo->prepare('
    SELECT b.*, t.slot_date, t.start_time, t.end_time, f.name AS facility_name, f.price, f.facility_id,
           fs.sport_id, s.gold_price, s.silver_price, s.bronze_price, s.no_member_price, s.base_price
    FROM bookings b
    JOIN timeslots t ON b.slot_id = t.slot_id
    JOIN facilities f ON t.facility_id = f.facility_id
    LEFT JOIN facility_sport fs ON f.facility_id = fs.facility_id
    LEFT JOIN sports s ON fs.sport_id = s.sport_id
    WHERE b.booking_id = ? AND b.payment_status = "unpaid"
');
$stmt->execute([$booking_id]);
$info = $stmt->fetch();

if (!$info) {
    echo "找不到這筆預約，或已付款 / 已取消";
    exit;
}

// 計算價格
$membership = $_SESSION['membership'] ?? 'none';
switch ($membership) {
    case 'gold':
        $amount = $info['gold_price'] ?? $info['price'];
        break;
    case 'silver':
        $amount = $info['silver_price'] ?? $info['price'];
        break;
    case 'bronze':
        $amount = $info['bronze_price'] ?? $info['price'];
        break;
    case 'none':
        $amount = $info['no_member_price'] ?? $info['price'];
        break;
    default:
        $amount = $info['base_price'] ?? $info['price'];
}
if ($amount === null) {
    $amount = $info['price'] ?? 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? 'credit';

    $stmt = $pdo->prepare('INSERT INTO payments (booking_id, method, amount) VALUES (?, ?, ?)');
    $stmt->execute([$booking_id, $method, $amount]);

    $stmt = $pdo->prepare('UPDATE bookings SET payment_status = "paid" WHERE booking_id = ?');
    $stmt->execute([$booking_id]);

    header('Location: dashboard.php');
    exit;
}

// 格式化時間區段
$datetime = $info['slot_date'] . ' ' . substr($info['start_time'], 0, 5) . '-' . substr($info['end_time'], 0, 5);
$due_time = date('Y-m-d H:i:s', strtotime($info['created_at'] . ' +3 days'));
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>線上付款</title>
  <link rel="stylesheet" href="main.css">
  <style>
    .pay-box {
      max-width: 520px;
      margin: 2rem auto;
      padding: 2rem 2.5rem;
      background: #fff;
      border-radius: 12px;
      box-shadow: var(--shadow);
    }
    .pay-box h2 {
      font-size: 1.5rem;
      text-align: center;
      color: var(--g-600);
      margin-bottom: 1.2rem;
      border-bottom: 2px solid var(--g-600);
      padding-bottom: .6rem;
    }
    .pay-box table {
      width: 100%;
      margin-bottom: 1.4rem;
      font-size: 1rem;
    }
    .pay-box th {
      text-align: left;
      width: 34%;
      padding: 0.4rem 0;
      color: var(--g-700);
    }
    .pay-box td {
      padding: 0.4rem 0;
    }
    .pay-box .methods {
      margin: 1rem 0;
    }
    .pay-box label {
      display: block;
      margin: .4rem 0;
      cursor: pointer;
    }
    .text-center {
      text-align: center;
    }
    .link {
      margin-top: 1rem;
      text-align: center;
      display: block;
      color: #512da8;
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="pay-box">
    <h2>線上付款</h2>
    <table>
      <tr><th>預約編號</th><td><?= $booking_id ?></td></tr>
      <tr><th>場地</th><td><?= htmlspecialchars($info['facility_name']) ?></td></tr>
      <tr><th>日期 / 時間</th><td><?= $datetime ?></td></tr>
      <tr><th>金額</th><td>$<?= number_format($amount, 2) ?></td></tr>
      <tr><th>狀態</th><td><?= htmlspecialchars($info['payment_status']) ?></td></tr>
      <tr><th>繳費期限</th><td><?= $due_time ?></td></tr>
    </table>

    <form method="post">
      <div class="methods" align="center">
        <label><input type="radio" name="method" value="linepay"> LINE Pay</label>
        <label><input type="radio" name="method" value="applepay"> Apple Pay</label>
        <label><input type="radio" name="method" value="credit" checked> 信用卡</label>
      </div>
      <div class="text-center">
        <button type="submit" class="btn pay-btn">立即付款</button>
        <a href="dashboard.php" class="link">回首頁</a>
      </div>
    </form>
  </div>
</body>
</html>


