<?php
header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 使用 PDO 連線資料庫
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'your_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'mode' => 'transit',
        'location' => '',
        'count' => 0,
        'results' => [],
        'error' => 'DB 連線失敗: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 讀取捷運站參數
$station = isset($_GET['station']) ? trim($_GET['station']) : '';

if (empty($station)) {
    echo json_encode([
        'mode' => 'transit',
        'location' => '',
        'count' => 0,
        'results' => [],
        'error' => '請提供捷運站名稱'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 搜尋 MRT 欄位或地址包含捷運站名稱的咖啡廳
try {
    $stmt = $conn->prepare(
        "SELECT * FROM cafe 
         WHERE ((mrt LIKE :station1 OR mrt LIKE :station2 OR mrt LIKE :station3) 
                OR (address LIKE :station4))
         AND name IS NOT NULL AND name != ''
         AND address IS NOT NULL AND address != ''
         ORDER BY 
            CASE 
                WHEN mrt LIKE :station2 THEN 1
                WHEN mrt LIKE :station3 THEN 2
                WHEN mrt LIKE :station3 THEN 3
                WHEN address LIKE :station4 THEN 4
                ELSE 5
            END, RAND()
         LIMIT 25"
    );

    $stmt->execute([
        'station1' => "%捷運$station%",
        'station2' => "%$station站%",
        'station3' => "%$station%",
        'station4' => "%$station%"
    ]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 如果沒有找到結果，嘗試更寬泛的搜尋
    if (count($data) < 5) {
        $station_without_suffix = str_replace(['站', '捷運'], '', $station);
        if (!empty($station_without_suffix) && $station_without_suffix !== $station) {
            $backup_stmt = $conn->prepare(
                "SELECT * FROM cafe 
                 WHERE (mrt LIKE :station OR address LIKE :station OR city LIKE :station)
                 AND name IS NOT NULL AND name != ''
                 ORDER BY RAND()
                 LIMIT 20"
            );
            $backup_stmt->execute(['station' => "%$station_without_suffix%"]);
            $backup_data = $backup_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 合併避免重複
            $existing_ids = array_column($data, 'ID');
            foreach ($backup_data as $row) {
                if (!in_array($row['ID'], $existing_ids)) {
                    $data[] = $row;
                }
            }
        }
    }

    echo json_encode([
        'mode' => 'transit',
        'location' => $station,
        'count' => count($data),
        'results' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'mode' => 'transit',
        'location' => $station,
        'count' => 0,
        'results' => [],
        'error' => '查詢失敗: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
