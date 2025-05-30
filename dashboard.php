<?php
session_start();
require_once 'db_config.php';



/* ---------- 處理會員升級 / 續約 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade'])) {
    $level = $_POST['membership'];          // bronze / silver / gold
    $today = date('Y-m-d H:i:s');
    // 有效期 1 年，你可以自行改成付款成功後才寫入
    $end   = date('Y-m-d H:i:s', strtotime('+1 year'));

    $stmt = $pdo->prepare('UPDATE users SET membership = ?, membership_start = ?, membership_end = ? WHERE user_id = ?');
    $stmt->execute([$level, $today, $end, $_SESSION['user_id']]);

    // 立即反映在畫面
    $_SESSION['membership'] = $level;
    header('Location: dashboard.php');
    exit;
    }

/* ---------- 尚未登入 ---------- */
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

/* ---------- 依身分轉向 ---------- */
switch ($_SESSION['role'] ?? 'user') {
    case 'admin':
        header('Location: admin.php');   exit;
    case 'manager':
        header('Location: manager.php'); exit;
}

$user_id = (int)$_SESSION['user_id'];


/* ---------- 一般會員畫面 ---------- */

/* 取得即將來臨的預約 ─ 依日期排序 */
$stmt = $pdo->prepare("
    SELECT b.booking_id, f.name AS facility, t.slot_date,
           TIME_FORMAT(t.start_time, '%H:%i') AS st,
           TIME_FORMAT(t.end_time, '%H:%i') AS ed,
           b.payment_status
    FROM bookings b
    JOIN timeslots t ON b.slot_id = t.slot_id
    JOIN facilities f ON t.facility_id = f.facility_id
    WHERE b.user_id = ?
    ORDER BY b.booking_id ASC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

/* 讀取 flash 訊息（如付款成功） */
$flash = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cancelled'])) {
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE user_id = ? AND payment_status = 'cancelled'");
    $stmt->execute([$user_id]);
    //$_SESSION['success'] = "已清除所有取消的預約";
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>會員首頁 – 運動中心線上預約</title>
    <link rel="stylesheet" href="main.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 0.5rem;
            text-align: center;
        }
        th {
            background-color: #e8f5e9;
        }

        a.btn {
  background: var(--green-500);
  color: #fff;
  padding: .45rem 1.1rem;
  border-radius: var(--radius);
  font-weight: 600;
  display: inline-block;
  text-align: center;
  text-decoration: none;
  transition: background .2s;
}

a.btn:hover {
  background: var(--green-600);
}



    </style>
</head>
<body>
<center>
    <h2>🤘💖歡迎👋🫰  <?= htmlspecialchars($_SESSION['username'] ?? '') ?></h2>
    <hr width="2000" color="green" size="5">

    <?php
    // 重新抓會員卡資訊
    $levelRow = $pdo->prepare('SELECT membership, membership_end FROM users WHERE user_id = ?');
    $levelRow->execute([$_SESSION['user_id']]);
    list($myLevel, $endDate) = $levelRow->fetch(PDO::FETCH_NUM);

    $levelName = [
    'none'   => '一般會員（無卡）',
    'bronze' => '銅卡會員',
    'silver' => '銀卡會員',
    'gold'   => '金卡會員'
    ][$myLevel];
    ?>
    
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <div style="flex: 1;"></div> <!-- 左邊空白 -->

    <div style="flex: 1; text-align: center;">
        目前等級：<strong><?= $levelName ?></strong>
        <?php if ($myLevel !== 'none'): ?>
            　有效至：<?= date('Y-m-d', strtotime($endDate)) ?>
        <?php endif; ?>
    </div>

    <div style="flex: 1; text-align: right; padding-top: 8px;">  <!-- 這裡加上 padding -->
        <form method="post">
            <button type="submit" name="clear_cancelled" value="1" class="btn btn-danger">
                清除已取消的預約
            </button>
        </form>
    </div>
</div>

    



    <?php if ($myLevel === 'none'): ?>
    <!-- 升級選單：無會員才顯示 -->
    <form method="post" style="margin:1rem 0;">
    <label>升級為
        <select name="membership" required>
        <option value="bronze">銅卡 NT$1,000/年</option>
        <option value="silver">銀卡 NT$2,000/年</option>
        <option value="gold">金卡 NT$3,000/年</option>
        </select>
    </label>
    <button name="upgrade" value="1">立即升級</button>
    </form>
    <?php elseif (strtotime($endDate) < time()): ?>
    <!-- 會員已到期，可續約 -->
    <form method="post" style="margin:1rem 0;">
    <input type="hidden" name="membership" value="<?= $myLevel ?>">
    <button name="upgrade" value="1">續約一年</button>
    </form>
    <?php endif; ?>

    <?php if ($flash) echo "<p class='success'>$flash</p>"; ?>


    <div class="container">
        <h1>我的預約紀錄</h1>
        <nav>
            <a href="booking.php">預約場地</a> |
            <a href="logout.php">登出</a>
        </nav>

        
        <table>
            <tr>
                <th>編號</th>
                <th>場地</th>
                <th>日期</th>
                <th>時間</th>
                <th>付款狀態</th>
                <th>付款/取消</th>

            </tr>
            <?php if (!$bookings): ?>
                <tr><td colspan="5">尚無預約紀錄</td></tr>
            <?php else: ?>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><?= $b['booking_id'] ?></td>
                    <td><?= htmlspecialchars($b['facility']) ?></td>
                    <td><?= $b['slot_date'] ?></td>
                    <td><?= $b['st'] . ' - ' . $b['ed'] ?></td>
                    <td><?= $b['payment_status'] ?></td>

                    

                    <td>
  <?php if ($b['payment_status'] !== 'cancelled'): ?>
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <?php if ($b['payment_status'] === 'unpaid'): ?>
      <form method="get" action="payment.php">
    <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
    <button type="submit" class="btn btn-small">立即付款</button>
  </form>
      <?php endif; ?>

      <form method="post" action="cancel_booking.php" onsubmit="return confirm('確定要取消這筆預約嗎？');">
        <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
        <button type="submit" class="btn btn-danger btn-small">取消</button>
      </form>
    </div>
  <?php else: ?>
    —
  <?php endif; ?>
</td>


                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
    </center>
</body>
</html>
