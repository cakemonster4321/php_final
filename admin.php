<?php
/**
 * 系統後台 – 超級管理員
 *
 * 功能：
 *   1. 類別管理（venue_categories）
 *   2. 運動項目管理（sports）
 *   3. 場地管理（facilities）
 *   4. 會員管理（users） – 卡別 & 權限
 *   5. 預約管理（bookings） – 檢視、取消、標記付款
 *
 * 先備：db_config.php 必須已建立 PDO 連線 $pdo
 */

session_start();
require_once 'db_config.php';

// -------------------- 權限檢查 --------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

// -------------------- 小工具 --------------------
function redirect($url) {
    header("Location: $url");
    exit;
}

function input($key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

// -------------------- CRUD 動作處理 --------------------
$action = input('action');

switch ($action) {
    /* === 1. 類別 === */
    case 'add_category':
        $name = trim($_POST['name']);
        if ($name) {
            $stmt = $pdo->prepare('INSERT INTO venue_categories (name_zh) VALUES (?)');
            $stmt->execute([$name]);
        }
        redirect('admin.php?section=categories');
        break;
    case 'del_category':
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM venue_categories WHERE category_id = ?')->execute([$id]);
        redirect('admin.php?section=categories');
        break;

    /* === 2. 運動項目 === */
    case 'add_sport':
        $cat = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $dur  = (int)$_POST['duration'];
        if ($cat && $name && $dur) {
            $pdo->prepare('INSERT INTO sports (category_id, name_zh, default_duration) VALUES (?,?,?)')
                ->execute([$cat, $name, $dur]);
        }
        redirect('admin.php?section=sports');
        break;
    case 'del_sport':
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM sports WHERE sport_id = ?')->execute([$id]);
        redirect('admin.php?section=sports');
        break;

    /* === 3. 場地 === */
    case 'add_facility':
        $sport = (int)$_POST['sport_id'];
        $name  = trim($_POST['name']);
        $price   = (int)$_POST['price'];

        if ($sport && $name) {
            
                // 第一步：新增場地資料到 facilities
        $stmt = $pdo->prepare('INSERT INTO facilities (name, price) VALUES (?, ?)');
        $stmt->execute([$name, $price]);

        // 取得剛剛新增場地的 ID
        $facility_id = $pdo->lastInsertId();

        // 第二步：把場地與運動項目關聯起來（新增到 facility_sport）
        $stmt2 = $pdo->prepare('INSERT INTO facility_sport (facility_id, sport_id) VALUES (?, ?)');
        $stmt2->execute([$facility_id, $sport]);
        }
        redirect('admin.php?section=facilities');
        break;
    case 'del_facility':
        $id = (int)$_POST['id'];
        // 1. 先刪掉中介表 facility_sport 中對應的關聯
    $pdo->prepare('DELETE FROM facility_sport WHERE facility_id = ?')->execute([$id]);

    // 2. 再刪主表 facilities 的資料
    $pdo->prepare('DELETE FROM facilities WHERE facility_id = ?')->execute([$id]);

    redirect('admin.php?section=facilities');
    break;

    /* === 4. 會員 === */
    case 'set_membership':
        $uid = (int)$_POST['user_id'];
        $lvl = $_POST['membership'];
        $pdo->prepare('UPDATE users SET membership = ? WHERE user_id = ?')->execute([$lvl, $uid]);
        redirect('admin.php?section=users');
        break;

        case 'del_user':
    $uid = (int)$_POST['user_id'];

    // 若 bookings 有外鍵，需要先刪除該會員的預約紀錄
    $pdo->prepare('DELETE FROM bookings WHERE user_id = ?')->execute([$uid]);

    // 再刪除會員資料
    $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$uid]);

    redirect('admin.php?section=users');
    break;


    /* === 5. 預約 === */
    case 'cancel_booking':
        $bid = (int)$_POST['booking_id'];
        // 還原時段狀態
        $pdo->beginTransaction();
        $slot = $pdo->prepare('SELECT slot_id FROM bookings WHERE booking_id = ?')->fetchColumn();
        $pdo->prepare('UPDATE timeslots SET payment_status = "open" WHERE slot_id = ?')->execute([$slot]);
        $pdo->prepare('UPDATE bookings SET payment_status = "cancelled" WHERE booking_id = ?')->execute([$bid]);

        $pdo->commit();
        redirect('admin.php?section=bookings');
        break;
    case 'mark_paid':
        $bid = (int)$_POST['booking_id'];
        $pdo->prepare('UPDATE bookings SET payment_status = "paid" WHERE booking_id = ?')->execute([$bid]);
        redirect('admin.php?section=bookings');
        break;
}

// -------------------- 讀取資料 --------------------
$section = input('section', 'bookings');

// 共用：類別 & 運動項目下拉
$categories = $pdo->query('SELECT * FROM venue_categories ORDER BY category_id')->fetchAll();
$sports     = $pdo->query('SELECT s.*, c.name_zh AS cat_name FROM sports s JOIN venue_categories c USING(category_id) ORDER BY sport_id')->fetchAll();

?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<title>後台管理 – Admin</title>
<link rel="stylesheet" href="main.css">
<style>
    body{font-family:system-ui,\5FAE\8F6F\96C5\9ED1;} 
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
    table{border-collapse:collapse;margin:0 auto;border:3px solid black;text-align:center;} 
    th,td{border:1px solid #ccc;padding:6px;} 
    nav {
    text-align: center;
    margin: 1rem 0;
}

nav a {
    margin: 0 12px;
    font-weight: bold;
    font-size: 2rem;
    text-decoration: none;
    color: #388e3c;  /* 固定色，避免 visited 變色 */
}

nav a:hover {
    color: #2e7d32;  /* 深綠色 hover */
}

nav a:visited {
    color: #388e3c;  /* 避免點過變紫色 */
}

    h3{text-align:center;}

    
</style>
</head>
<body>
<h2>後台管理 (Admin)</h2>
<nav>
  <a href="admin.php?section=bookings">預約</a>
  <a href="admin.php?section=categories">類別</a>
  <a href="admin.php?section=sports">運動項目</a>
  <a href="admin.php?section=facilities">場地</a>
  <a href="admin.php?section=users">會員</a>
  <a href="logout.php">登出</a>
</nav>
<hr>
<?php
/* -------------------- 顯示頁面 -------------------- */
if ($section === 'categories') {
    echo "<h3>運動場地類別</h3>\n";
    echo "<table><tr><th>ID</th><th>名稱</th><th>刪除類別</th></tr>";
    foreach ($categories as $c) {
        echo "<tr><td>{$c['category_id']}</td><td>{$c['name_zh']}</td><td><form method='post' style='display:inline'><input type='hidden' name='action' value='del_category'><input type='hidden' name='id' value='{$c['category_id']}'><button onclick=\"return confirm('刪除?')\">刪除</button></form></td></tr>";
    }
    echo "</table>";
    echo "<form method='post'><h4>新增類別：</h4><input type='hidden' name='action' value='add_category'><input name='name' placeholder='名稱'><button>新增</button></form>";
}

elseif ($section === 'sports') {
    echo "<h3>運動項目</h3>";
    echo "<table><tr><th>ID</th><th>類別</th><th>名稱</th><th>預設時長</th><th>刪除運動項目</th></tr>";
    foreach ($sports as $s) {
        echo "<tr><td>{$s['sport_id']}</td><td>{$s['cat_name']}</td><td>{$s['name_zh']}</td><td>{$s['default_duration']} 分</td><td><form method='post' style='display:inline'><input type='hidden' name='action' value='del_sport'><input type='hidden' name='id' value='{$s['sport_id']}'><button onclick=\"return confirm('刪除?')\">刪除</button></form></td></tr>";
    }
    echo "</table>";
    echo "<form method='post'><h4>新增項目：</h4><input type='hidden' name='action' value='add_sport'>類別<select name='category_id'>";
    foreach ($categories as $c) {
        echo "<option value='{$c['category_id']}'>{$c['name_zh']}</option>";
    }
    echo "</select> 名稱<input name='name'> 時長(分)<input name='duration' size='3' value='60'><button>新增</button></form>";
}

elseif ($section === 'facilities') {
    echo "<h3>場地</h3>";
    $facRows = $pdo->query("
    SELECT 
        f.*,
        GROUP_CONCAT(s.name_zh SEPARATOR ', ') AS sport_name
    FROM facilities f
    JOIN facility_sport fs ON f.facility_id = fs.facility_id
    JOIN sports s ON fs.sport_id = s.sport_id
    GROUP BY f.facility_id
    ORDER BY f.facility_id
    ")->fetchAll();

    echo "<table><tr><th>ID</th><th>運動項目</th><th>名稱</th><th>場地價格</th><th>刪除場地</th></tr>";
    foreach ($facRows as $f) {
        echo "<tr><td>{$f['facility_id']}</td><td>{$f['sport_name']}</td><td>{$f['name']}</td><td>{$f['price']}</td><td><form method='post' style='display:inline'><input type='hidden' name='action' value='del_facility'><input type='hidden' name='id' value='{$f['facility_id']}'><button onclick=\"return confirm('刪除?')\">刪除</button></form></td></tr>";
    }
    echo "</table>";
    // 新增表單
    echo "<form method='post'><h4>新增場地：</h4><input type='hidden' name='action' value='add_facility'>運動<select name='sport_id'>";
    foreach ($sports as $s) {
        echo "<option value='{$s['sport_id']}'>{$s['name_zh']}</option>";
    }
    echo "</select>名稱</b><input name='name'>價格<input name='price' size='3' value='4'><button>新增</button></form>";
}


elseif ($section === 'users') {
    echo "<h3>會員</h3>";
    $rows = $pdo->query('SELECT user_id, username, membership, role FROM users ORDER BY user_id')->fetchAll();

    echo "<table>
        <tr>
            <th>ID</th>
            <th>帳號</th>
            <th>會員卡</th>
            <th>角色</th>
            <th>刪除會員</th>
        </tr>";

    foreach ($rows as $u) {
        // 會員卡更新表單
        $sel = "<form method='post' style='display:inline'>
                    <input type='hidden' name='action' value='set_membership'>
                    <input type='hidden' name='user_id' value='{$u['user_id']}'>
                    <select name='membership'>
                        <option" . ($u['membership'] == 'none' ? ' selected' : '') . ">none</option>
                        <option" . ($u['membership'] == 'bronze' ? ' selected' : '') . ">bronze</option>
                        <option" . ($u['membership'] == 'silver' ? ' selected' : '') . ">silver</option>
                        <option" . ($u['membership'] == 'gold' ? ' selected' : '') . ">gold</option>
                    </select>
                    <button>更新</button>
                </form>";

        // 刪除會員表單
        $del = "<form method='post' style='display:inline' onsubmit=\"return confirm('確定要刪除會員 {$u['username']} 嗎？')\">
                    <input type='hidden' name='action' value='del_user'>
                    <input type='hidden' name='user_id' value='{$u['user_id']}'>
                    <button>刪除</button>
                </form>";

        echo "<tr>
                <td>{$u['user_id']}</td>
                <td>{$u['username']}</td>
                <td>{$sel}</td>
                <td>{$u['role']}</td>
                <td>{$del}</td>
              </tr>";
    }

    echo "</table>";
}


else { // bookings
    echo "<h3>預約</h3>";
    $sql = "SELECT b.booking_id, u.username, s.name_zh AS sport, f.name AS facility,
           t.slot_date, t.start_time, t.end_time,
            b.payment_status
        FROM bookings b
        JOIN users u       USING(user_id)
        JOIN timeslots t   USING(slot_id)
        JOIN facilities f  USING(facility_id)
        JOIN facility_sport fs ON fs.facility_id = f.facility_id
        JOIN sports s      ON s.sport_id = fs.sport_id
        ORDER BY t.slot_date DESC, t.start_time DESC";
    $rows = $pdo->query($sql)->fetchAll();
    echo "<table><tr><th>ID</th><th>會員</th><th>運動</th><th>場地</th><th>日期</th><th>時間</th><th>狀態</th><th>付款</th><th></th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['booking_id']}</td><td>{$r['username']}</td><td>{$r['sport']}</td><td>{$r['facility']}</td><td>{$r['slot_date']}</td><td>{$r['start_time']}~{$r['end_time']}</td><td>{$r['payment_status']}</td><td>";
        if ($r['payment_status']!=='cancelled') {
            echo "<form method='post' style='display:inline'><input type='hidden' name='action' value='cancel_booking'><input type='hidden' name='booking_id' value='{$r['booking_id']}'><button onclick=\"return confirm('取消此預約?')\">取消</button></form> ";
        }
        if ($r['payment_status']==='unpaid') {
            echo "<form method='post' style='display:inline'><input type='hidden' name='action' value='mark_paid'><input type='hidden' name='booking_id' value='{$r['booking_id']}'><button>標記已付</button></form>";
        }
        echo "</td></tr>";
    }
    echo "</table>";
}
?>
</body>
</html>
