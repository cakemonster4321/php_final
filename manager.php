<?php
/**
 * manager.php  ‑ 2025‑05‑07
 *
 * 場館管理後台（Manager 角色）
 * ───────────────────────────────
 *  ✅ 僅顯示該管理員負責的「運動項目 → 場地」
 *  ✅ 建立 / 刪除時段（避免時間重疊）
 *  ✅ 當日預約記錄：簽到 / 取消
 *  ✅ 顯示目前報名人數 / 容量
 *
 *  需要的資料表欄位：
 *    users.manager_sport_id  (INT, FK → sports.sport_id)
 *    facilities.sport_id    (INT, FK)
 *    timeslots.status ENUM('open','booked') DEFAULT 'open'
 *    timeslots.quota  INT   (可容納人數，booking.php 不使用，但後台顯示)
 */

session_start();
require_once 'db_config.php';

/* ---------- 權限檢查 ---------- */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: index.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

/* ---------- 取得負責運動項目 ---------- */
$mgrSport = (int)($pdo->prepare('SELECT manager_sport_id FROM users WHERE user_id = ?')
                  ->execute([$user_id]) ? $pdo->query('SELECT manager_sport_id FROM users WHERE user_id = '.$user_id)->fetchColumn() : 0);
if (!$mgrSport) {
    die('尚未指派運動項目，請聯絡系統管理員');
}
// 讀運動項目名稱
$sportName = $pdo->query('SELECT name_zh FROM sports WHERE sport_id = '.$mgrSport)->fetchColumn();

/* ---------- 該運動項目所有場地 ---------- */
$stmt = $pdo->prepare('SELECT facility_id, name FROM facilities WHERE sport_id = ? ORDER BY facility_id');
$stmt->execute([$mgrSport]);
$facilities = $stmt->fetchAll();
if (!$facilities) die('此運動項目尚未建立任何場地');

/* ---------- URL 參數 ---------- */
$facility_id = isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : $facilities[0]['facility_id'];
$date        = $_GET['date'] ?? date('Y-m-d');

/* ---------- [POST] 操作 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add_slot') {
        $st   = $_POST['start'];
        $ed   = $_POST['end'];
        $cap  = (int)$_POST['quota'];
        // 檢查重疊
        $overlap = $pdo->prepare('SELECT 1 FROM timeslots WHERE facility_id=? AND slot_date=? AND status <> "cancelled" AND ((? < end_time AND ? > start_time))');
        $overlap->execute([$facility_id, $date, $st, $ed]);
        if ($overlap->fetch()) {
            $msg = '⚠ 時段與現有資料重疊';
        } else {
            $pdo->prepare('INSERT INTO timeslots (facility_id, slot_date, start_time, end_time, quota, status) VALUES (?,?,?,?,?, "open")')
                ->execute([$facility_id, $date, $st, $ed, $cap]);
            $msg = '✅ 新時段已建立';
        }
    }
    elseif ($act === 'del_slot') {
        $pdo->prepare('DELETE FROM timeslots WHERE slot_id = ?')->execute([(int)$_POST['slot_id']]);
    }
    elseif ($act === 'cancel_booking') {
        $bid = (int)$_POST['booking_id'];
        $pdo->beginTransaction();
        $slot = $pdo->query('SELECT slot_id FROM bookings WHERE booking_id = '.$bid)->fetchColumn();
        // 釋放時段、標註預約取消
        $pdo->prepare('UPDATE timeslots SET status = "open" WHERE slot_id = ?')->execute([$slot]);
        $pdo->prepare('UPDATE bookings SET status = "cancelled" WHERE booking_id = ?')->execute([$bid]);
        $pdo->commit();
    }
    elseif ($act === 'checkin') {
        $pdo->prepare('UPDATE bookings SET status = "checked_in" WHERE booking_id = ?')->execute([(int)$_POST['booking_id']]);
    }
    header("Location: manager.php?facility_id={$facility_id}&date={$date}");
    exit;
}

/* ---------- 讀取時段 & 當日預約 ---------- */
$slots = $pdo->prepare('SELECT * FROM timeslots WHERE facility_id = ? AND slot_date = ? ORDER BY start_time');
$slots->execute([$facility_id, $date]);
$slots = $slots->fetchAll();

$bookings = $pdo->prepare('SELECT b.*, u.username, t.start_time, t.end_time FROM bookings b JOIN users u USING(user_id) JOIN timeslots t USING(slot_id) WHERE t.facility_id = ? AND t.slot_date = ? ORDER BY t.start_time');
$bookings->execute([$facility_id, $date]);
$bookings = $bookings->fetchAll();

?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="main.css">
<title>場館管理 – <?= htmlspecialchars($sportName) ?></title>
<style>
body{font-family:"Noto Sans TC",sans-serif;margin:0 2rem;}
nav a{margin-right:8px;}
.table{border-collapse:collapse;width:100%;margin:10px 0;}
.table th,.table td{border:1px solid #ccc;padding:6px;text-align:center;}
.success{color:#27ae60;}
.warn{color:#e67e22;}
</style>
</head>
<body>
<h2>場館管理 – <?= htmlspecialchars($sportName) ?></h2>
<nav>
<?php foreach ($facilities as $f): ?>
  <a href="?facility_id=<?= $f['facility_id'] ?>&date=<?= $date ?>" <?= $facility_id===$f['facility_id']? 'style="font-weight:bold;text-decoration:underline"':'' ?>><?= htmlspecialchars($f['name']) ?></a>
<?php endforeach; ?> |
  <a href="logout.php">登出</a>
</nav>
<?php if (!empty($msg)) echo '<p class="'.(strpos($msg,'⚠')!==false?'warn':'success').'">'.$msg.'</p>'; ?>
<hr>
<!-- 日期切換 -->
<form method="get">
  <input type="hidden" name="facility_id" value="<?= $facility_id ?>">
  <label>日期 <input type="date" name="date" value="<?= $date ?>"></label>
  <button>切換</button>
</form>

<h3>時段管理</h3>
<table class="table">
<tr><th>開始</th><th>結束</th><th>容量</th><th>現有人數</th><th>狀態</th><th>操作</th></tr>
<?php foreach ($slots as $s):
      $cnt = $pdo->query('SELECT COUNT(*) FROM bookings WHERE slot_id = '.$s['slot_id'].' AND status <> "cancelled"')->fetchColumn(); ?>
  <tr>
    <td><?= substr($s['start_time'],0,5) ?></td>
    <td><?= substr($s['end_time'],0,5) ?></td>
    <td><?= $s['quota'] ?></td>
    <td><?= $cnt ?></td>
    <td><?= $s['status'] ?></td>
    <td>
      <form method="post" style="display:inline" onsubmit="return confirm('確定刪除?')">
        <input type="hidden" name="act" value="del_slot">
        <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
        <button>刪</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
</table>

<!-- 新增時段表單 -->
<form method="post" style="margin-top:8px;">
  <input type="hidden" name="act" value="add_slot">
  <label>開始 <input type="time" name="start" required></label>
  <label>結束 <input type="time" name="end" required></label>
  <label>容量 <input type="number" name="quota" value="4" min="1" style="width:70px"></label>
  <button>新增時段</button>
</form>

<h3>當日預約</h3>
<table class="table">
<tr><th>會員</th><th>時間</th><th>付款</th><th>狀態</th><th>操作</th></tr>
<?php foreach ($bookings as $b):
      $slotTime = substr($b['start_time'],0,5).'~'.substr($b['end_time'],0,5); ?>
  <tr>
    <td><?= htmlspecialchars($b['username']) ?></td>
    <td><?= $slotTime ?></td>
    <td><?= $b['payment_status'] ?></td>
    <td><?= $b['status'] ?></td>
    <td>
      <?php if ($b['status']==='booked'): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="act" value="checkin">
        <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
        <button>簽到</button>
      </form>
      <?php endif; ?>
      <?php if ($b['status']!=='cancelled'): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('取消此預約?')">
        <input type="hidden" name="act" value="cancel_booking">
        <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
        <button>取消</button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</table>
</body>
</html>
