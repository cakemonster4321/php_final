<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

/* 會員卡與預約限制 */
$limitMap = ['none' => 2, 'bronze' => 5, 'silver' => 7, 'gold' => 10];
$stmt = $pdo->prepare('SELECT membership FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$membership = $stmt->fetchColumn() ?: 'none';
$bookingLimit = $limitMap[$membership];

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN timeslots t ON t.slot_id = b.slot_id
    WHERE b.user_id = ? AND b.payment_status IN ('unpaid','paid') AND t.slot_date >= CURDATE()
");
$stmt->execute([$user_id]);
$currentBookings = (int) $stmt->fetchColumn();

/* 運動類別與項目 */
$catSql = "SELECT c.category_id, c.name_zh AS cat_name,
                  s.sport_id,  s.name_zh AS sport_name, s.sport_id
           FROM venue_categories c
           JOIN sports s ON s.category_id = c.category_id
           ORDER BY c.category_id, s.sport_id";
$catRows = $pdo->query($catSql)->fetchAll();
$cats = [];
foreach ($catRows as $row) {
    $cid = $row['category_id'];
    if (!isset($cats[$cid])) {
        $cats[$cid] = ['name' => $row['cat_name'], 'sports' => []];
    }
    $cats[$cid]['sports'][] = ['id' => $row['sport_id'], 'name' => $row['sport_name']];
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>場地預約</title>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 99;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 2rem;
            width: 80%;
            max-width: 800px;
            max-height: 70vh;  /* 限制最大高度為視口高度的 70% */
            overflow-y: auto;  /* 啟用垂直捲動條 */
            border-radius: 8px;
        }
        
        #timeslotList {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    margin-top: 1rem;
}

.timeslot-btn:hover {
            background-color: #388e3c;
        }
        .timeslot-btn {
        width: 200px; /* 固定寬度 */
        height: 50px; /* 固定高度 */
        padding: 10px;
        background-color: var(--green-500);
        color: white ;
        border: none;
        border-radius: var(--radius);
        cursor: pointer;
        text-align: center;
        box-sizing: border-box; /* 確保 padding 不會影響佈局 */
        }

        .timeslot-btn {
    background-color: green !important;
    color: white !important;

    white-space: normal;   /* 允許換行 */
    word-break: break-word; /* 如果長字詞需要換行 */
    line-height: 1.4;       /* 行高增加讓兩行更清楚 */
    height: auto;           /* 高度根據內容調整 */
    display: flex;
align-items: center;
justify-content: center;
flex-direction: column;
padding: 10px;
font-size: 0.95rem;

        }
        .timeslot-btn:hover {
    background-color: #2e7d32; /* 或你喜歡的綠色 */
}

        
        .category-box {border:1px solid #ccc; padding:8px; margin-bottom:12px;}
        .category-box h4 {margin:4px 0;}
        .category-box a {display:inline-block; margin:2px 6px;}
    
.alert-box {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.alert-content {
    background: white;
    padding: 2rem 3rem;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.3);
    text-align: center;
}
.alert-content h3 {
    margin-top: 0;
    color: #2e7d32;
}
.alert-content button {
    margin-top: 1.5rem;
    padding: 0.5rem 1.5rem;
    font-size: 1rem;
    background: #2e7d32;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.alert-content button:hover {
    background: #1b5e20;
}


    </style>
</head>
<body>
    <center>
    <div class="container">
        <h1>運動中心線上預約系統</h1>
        <hr width="2000" color="green" size="6">
        <nav>
            <a href="dashboard.php">回首頁</a> |
            <a href="logout.php">登出</a>
        </nav>
        <p>會員卡等級：<?= htmlspecialchars($membership) ?>　
        可同時預約上限：<?= $bookingLimit ?>　
        目前已預約：<span id="bookingCount"><?= $currentBookings ?></span></p>

        <h2>選擇運動項目</h2>
        <?php
$emojiMap = [
    '籃球全場'   => '🏀',      // BASKETBALL
    '羽球雙打'   => '🏸',      // BADMINTON
    '游泳水道'   => '🏊‍♂️',    // SWIM_LANE
    '自由重量區' => '🏋️‍♂️',    // FREE_WEIGHT
    '桌球雙打'   => '🏓',      // TABLE_TENN
    '網球室外'   => '🎾',      // TENNIS
    '壁球'       => '🥎',      // SQUASH（用小球類通用）
    '水中有氧'   => '🏊‍♀️',    // AQUA_AER
    '跳水池'     => '🤿',      // DIVING
    '有氧器材區' => '💪',      // CARDIO
    '瑜珈課程'   => '🧘‍♀️',    // YOGA
    '皮拉提斯'   => '🧘',      // PILATES
    '飛輪課程'   => '🚴‍♀️',    // SPIN
    '田徑跑道'   => '🏃‍♂️',    // RUN_TRACK
    '攀岩牆'     => '🧗‍♂️',    // CLIMB_WALL
];
?>

       <?php foreach ($cats as $cat): ?>
    <div class="category-box">
        <!-- 類別名稱 -->
        <h4><?= htmlspecialchars($cat['name']) ?></h4>

        <?php foreach ($cat['sports'] as $sp): ?>
            <?php
                // 避免 XSS，要先 htmlspecialchars
                $name  = htmlspecialchars($sp['name']);
                // 從對照表抓對應 emoji；若沒有就空字串
                $emoji = $emojiMap[$sp['name']] ?? '';
            ?>
            <a href="#"
                class="sport-link"
                data-sport="<?= $sp['id'] ?>"
                style="text-decoration: none;">
            <?= $name . ' ' . $emoji ?>
            </a>

        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

    <!-- Modal -->
    <div class="modal" id="timeslotModal">
        <div class="modal-content">
            <h3>選擇時段</h3>
            <div id="timeslotList">載入中...</div>
            <br>
            <button onclick="closeModal()">關閉</button>
        </div>
    </div>

    <script>
    const modal = document.getElementById("timeslotModal");
    const timeslotList = document.getElementById("timeslotList");
    const bookingCount = document.getElementById("bookingCount");

    document.querySelectorAll(".sport-link").forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault();  // 防止默認行為

            const sportId = this.dataset.sport;
            console.log(sportId);

            fetch("get_timeslots_by_sport.php?sport_id=" + sportId)
                .then(res => res.json())
                .then(data => {
                    console.log("收到的 data：", data);
                    timeslotList.innerHTML = "";// 清空原有的時段顯示

                    if (data.length === 0) {
                        timeslotList.innerHTML = "目前沒有可預約時段";
                    } else {
                        // 按照日期分組
                        const grouped = {};
                        data.forEach(slot => {
                            if (!grouped[slot.slot_date]) {
                                grouped[slot.slot_date] = [];
                            }
                            grouped[slot.slot_date].push(slot);
                        });

                        // 依日期輸出
                        for (const date in grouped) {
                            

                            grouped[date].forEach(slot => {
                                const btn = document.createElement("button");
                                btn.className = "timeslot-btn";
                                btn.innerText = `${slot.facility_name} | ${date} | ${slot.start_time} ~ ${slot.end_time}`;
                                btn.onclick = () => bookSlot(slot.slot_id);
                                timeslotList.appendChild(btn);
                                //timeslotList.appendChild(document.createElement("br"));
                                console.log("建立的按鈕：", btn);
                            });

                        }
                    }
                    
                    modal.style.display = "block"; // 顯示 Modal
                });
        });
    });

    function closeModal() {
        modal.style.display = "none";
    }

    function bookSlot(slotId) {
        fetch("make_booking.php", {
            method: "POST",
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `slot_id=${slotId}`
        })
        .then(res => res.json())
        .then(res => {
            console.log("伺服器回傳：", res);  // ✅ 加這行觀察是否進入 success
            if (res.success) {
                showSuccess(res.success);   // 顯示成功訊息框
                closeModal();
                // 更新預約數量
            const count = parseInt(bookingCount.innerText);
            bookingCount.innerText = count + 1;
        } else if (res.error) {
            showError(res.error);      // 顯示錯誤訊息框
        }
            

        });
    }
    
    function showSuccess(message) {
    const modal = document.createElement("div");
    modal.className = "alert-box";
    modal.innerHTML = `
        <div class="alert-content">
            <h3>預約結果</h3>
            <p>${message}</p>
            <button onclick="this.parentElement.parentElement.remove()">確定</button>
        </div>
    `;
    document.body.appendChild(modal);
}

function showError(message) {
    const modal = document.createElement("div");
    modal.className = "alert-box";
    modal.innerHTML = `
        <div class="alert-content">
            <h3>錯誤</h3>
            <p>${message}</p>
            <button onclick="this.parentElement.parentElement.remove()">確定</button>
        </div>
    `;
    document.body.appendChild(modal);
}


</script>
</center>
</body>
</html>
