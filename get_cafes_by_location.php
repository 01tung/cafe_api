<?php
header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 使用 PDO 連線資料庫，建議用環境變數
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'your_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'mode' => 'address',
        'location' => '',
        'count' => 0,
        'results' => [],
        'error' => 'DB 連線失敗: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 讀取 GET 參數 location
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

if ($location === '') {
    echo json_encode([
        'mode' => 'address',
        'location' => '',
        'count' => 0,
        'results' => [],
        'error' => '請提供搜尋地點'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 改進的搜尋邏輯
try {
    $stmt = $conn->prepare(
        "SELECT * FROM cafe 
         WHERE (city LIKE :location OR address LIKE :location OR name LIKE :location)
         AND name IS NOT NULL AND name != ''
         AND address IS NOT NULL AND address != ''
         ORDER BY 
            CASE 
                WHEN address LIKE :location THEN 1
                WHEN city LIKE :location THEN 2
                ELSE 3
            END, RAND()
         LIMIT 25"
    );
    $stmt->execute(['location' => "%$location%"]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 如果結果太少，做更寬泛搜尋
    if (count($data) < 5) {
        $broad_location = str_replace(['區', '市', '縣'], '', $location);
        if ($broad_location !== $location && strlen($broad_location) >= 2) {
            $backup_stmt = $conn->prepare(
                "SELECT * FROM cafe 
                 WHERE (city LIKE :broad OR address LIKE :broad) 
                 AND name IS NOT NULL AND name != ''
                 ORDER BY RAND()
                 LIMIT 15"
            );
            $backup_stmt->execute(['broad' => "%$broad_location%"]);
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
        'mode' => 'address',
        'location' => $location,
        'count' => count($data),
        'results' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'mode' => 'address',
        'location' => $location,
        'count' => 0,
        'results' => [],
        'error' => '查詢失敗: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

