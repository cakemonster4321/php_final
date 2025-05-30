<?php
// 用 CLI 或 Cron 執行即可，不需 session
require_once __DIR__.'/../db_config.php';

$sth = $pdo->prepare("
  SELECT booking_id, slot_id
  FROM bookings
  WHERE payment_status='unpaid' AND payment_due < NOW()
");
$sth->execute();
$expired = $sth->fetchAll();

foreach ($expired as $bk) {
    $pdo->beginTransaction();
    /* 1) 取消預約 */
    $pdo->prepare("
      UPDATE bookings SET payment_status='cancelled'
      WHERE booking_id=?
    ")->execute([$bk['booking_id']]);
    /* 2) 將時段重新開放 */
    $pdo->prepare("
      UPDATE timeslots SET payment_status='open'
      WHERE slot_id=?
    ")->execute([$bk['slot_id']]);
    $pdo->commit();
}

echo date('[Y-m-d H:i:s] '), 'Auto‑cancel finished, ',
     count($expired), " booking(s) cancelled.\n";
