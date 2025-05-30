<?php
// generate_hash.php - 產生加密後的密碼，用於資料庫更新

$password = '000000'; // 將這裡換成你想設定的新密碼
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>密碼 Hash 產生器</h2>";
echo "<p>原始密碼：<strong>$password</strong></p>";
echo "<p>加密後的密碼：</p>";
echo "<textarea rows='4' cols='80' readonly>$hash</textarea>";
?>
