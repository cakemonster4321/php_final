
<?php
session_start();
require_once 'db_config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT user_id, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role']    = $user['role'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = '帳號或密碼錯誤';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<title>運動中心線上預約系統 – 登入</title>
<link rel="stylesheet" href="main.css">
</head>
<body><center>
<h1><b>🧗🏋️🏊運動無極限 預約零距離🏃🏻⛹️🤸</b></h1>
<h2>登入</h2>
<div class="auth-container">
    <div class="auth-box">
<form method="post">
    <label>帳號：<input type="text" name="username" required></label><br>
    <label>密碼：<input type="password" name="password" required></label><br>
    <button type="submit">登入</button>
    <?php if ($error) echo "<p class='error'>$error</p>"; ?>
</form>
<p class="auth-link">
      <a href="register.php">還沒有帳號？註冊</a>
    </p>
</div>
</div>
</center>
</body>
</html>
