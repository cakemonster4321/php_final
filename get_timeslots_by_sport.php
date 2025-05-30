
<?php
require_once 'db_config.php';

$sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;
if ($sport_id <= 0) {
    echo json_encode([]);
    exit;
}

$defaultSlots = [
    ['05:30:00', '07:00:00'],
    ['07:00:00', '08:30:00'],
    ['08:30:00', '10:00:00'],
    ['10:00:00', '11:30:00'],
    ['11:30:00', '13:00:00'],
    ['13:00:00', '14:30:00'],
    ['14:30:00', '16:00:00'],
    ['16:00:00', '17:30:00'],
    ['17:30:00', '19:00:00'],
    ['19:00:00', '20:30:00'],
    ['20:30:00', '22:00:00'],
    ['22:00:00', '23:30:00'],
    ['23:30:00', '01:00:00'],
];

// 取得所有對應的場地
$stmt = $pdo->prepare("SELECT facility_id FROM facility_sport WHERE sport_id = ?");
$stmt->execute([$sport_id]);
$facilityIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$today = new DateTime();
for ($i = 0; $i < 7; $i++) {
    $date = $today->format('Y-m-d');
    foreach ($facilityIds as $fid) {
        foreach ($defaultSlots as [$start, $end]) {
            // 檢查是否已存在該時段
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM timeslots
                WHERE facility_id = ? AND slot_date = ? AND start_time = ? AND end_time = ?
            ");
            $stmt->execute([$fid, $date, $start, $end]);
            if ($stmt->fetchColumn() == 0) {
                // 插入該時段
                $insert = $pdo->prepare("
                    INSERT INTO timeslots (facility_id, slot_date, start_time, end_time, payment_status)
                    VALUES (?, ?, ?, ?, 'open')
                ");
                $insert->execute([$fid, $date, $start, $end]);
            }
        }
    }
    $today->modify('+1 day');
}


$stmt = $pdo->prepare("
    SELECT 
        t.slot_id, 
        f.name AS facility_name, 
        t.slot_date, 
        TIME_FORMAT(t.start_time, '%H:%i') AS start_time, 
        TIME_FORMAT(t.end_time, '%H:%i') AS end_time
    FROM timeslots t
    JOIN facilities f ON f.facility_id = t.facility_id
    JOIN facility_sport fs ON fs.facility_id = f.facility_id
    WHERE 
        fs.sport_id = ? 
        AND t.payment_status = 'open' 
        AND t.slot_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY t.slot_date, t.start_time
");

//$data = $stmt->fetchAll();
$stmt->execute([$sport_id]);




$data = $stmt->fetchAll();
if (!$data) {
    echo json_encode([]);    // 直接回傳空陣列
    exit;
}
echo json_encode($data);


?>
