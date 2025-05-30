<?php
session_start();
require_once 'db_config.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pwd1     = $_POST['password'] ?? '';
    $pwd2     = $_POST['confirm']  ?? '';

    /* --- 基本檢查 --- */
    if ($username === '')               $errors[] = '帳號不可空白';
    if (strlen($pwd1) < 6)              $errors[] = '密碼至少 6 字元';
    if ($pwd1 !== $pwd2)                $errors[] = '二次密碼不相符';

    /* --- 檢查帳號是否重複 --- */
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) $errors[] = '帳號已存在，請換一個';
    }

    /* --- 寫入資料庫 --- */
    if (!$errors) {
        $hash = password_hash($pwd1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash) VALUES (?, ?)'
        );
        $stmt->execute([$username, $hash]);
        $_SESSION['success'] = '註冊成功，請登入！';
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<title>註冊 – 運動中心線上預約</title>
<link rel="stylesheet" href="main.css">
</head>
<body>
<h2>會員註冊</h2>
<?php foreach ($errors as $e) echo "<p class='error'>$e</p>"; ?>
<form method="post">
  <label>帳號：<input type="text" name="username" required></label><br>
  <label>密碼：<input type="password" name="password" required></label><br>
  <label>確認：<input type="password" name="confirm"  required></label><br>
  <button type="submit">送出</button>
</form>
<p><a href="index.php">返回登入</a></p>
</body>
</html>
