<?php
header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// MySQLi 連線設定
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'your_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        'mode' => 'address',
        'location' => '',
        'count' => 0,
        'results' => [],
        'error' => 'DB 連線失敗: ' . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 讀取 GET 參數 location
$location = isset($_GET['location']) ? $conn->real_escape_string(trim($_GET['location'])) : '';

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

// 主搜尋
$sql = "SELECT * FROM cafe 
        WHERE (city LIKE '%$location%' OR address LIKE '%$location%' OR name LIKE '%$location%')
        AND name IS NOT NULL AND name != ''
        AND address IS NOT NULL AND address != ''
        ORDER BY 
            CASE 
                WHEN address LIKE '%$location%' THEN 1
                WHEN city LIKE '%$location%' THEN 2
                ELSE 3
            END,
            RAND()
        LIMIT 25";

$result = $conn->query($sql);
$data = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = [
            'ID' => $row['id'] ?? '',
            'Name' => $row['name'] ?? '',
            'City' => $row['city'] ?? '',
            'Wifi' => isset($row['wifi']) ? (intval($row['wifi']) ? 'yes' : 'no') : '',
            'Seat' => isset($row['seat']) ? strval(floatval($row['seat'])) : '',
            'Quiet' => isset($row['quiet']) ? (intval($row['quiet']) ? 'yes' : 'no') : '',
            'Tasty' => isset($row['tasty']) ? strval(floatval($row['tasty'])) : '',
            'Cheap' => isset($row['cheap']) ? strval(floatval($row['cheap'])) : '',
            'Music' => isset($row['music']) ? strval(floatval($row['music'])) : '',
            'Url' => $row['url'] ?? '',
            'Address' => $row['address'] ?? '',
            'Latitude' => isset($row['latitude']) ? strval(floatval($row['latitude'])) : '',
            'longitude' => isset($row['longitude']) ? strval(floatval($row['longitude'])) : '',
            'Limited_time' => $row['limited_time'] ?? '',
            'Socket' => $row['socket'] ?? '',
            'Standing_desk' => $row['standing_desk'] ?? '',
            'Mrt' => $row['mrt'] ?? '',
            'Open_time' => $row['open_time'] ?? ''
        ];
    }
}

// 如果結果太少，做更寬泛搜尋
if (count($data) < 5) {
    $broad_location = str_replace(['區', '市', '縣'], '', $location);
    if ($broad_location !== $location && strlen($broad_location) >= 2) {
        $backup_sql = "SELECT * FROM cafe 
                       WHERE (city LIKE '%$broad_location%' OR address LIKE '%$broad_location%')
                       AND name IS NOT NULL AND name != ''
                       ORDER BY RAND()
                       LIMIT 15";
        $backup_result = $conn->query($backup_sql);
        if ($backup_result && $backup_result->num_rows > 0) {
            $existing_ids = array_column($data, 'ID');
            while($row = $backup_result->fetch_assoc()) {
                if (!in_array($row['id'], $existing_ids)) {
                    $data[] = [
                        'ID' => $row['id'] ?? '',
                        'Name' => $row['name'] ?? '',
                        'City' => $row['city'] ?? '',
                        'Wifi' => isset($row['wifi']) ? (intval($row['wifi']) ? 'yes' : 'no') : '',
                        'Seat' => isset($row['seat']) ? strval(floatval($row['seat'])) : '',
                        'Quiet' => isset($row['quiet']) ? (intval($row['quiet']) ? 'yes' : 'no') : '',
                        'Tasty' => isset($row['tasty']) ? strval(floatval($row['tasty'])) : '',
                        'Cheap' => isset($row['cheap']) ? strval(floatval($row['cheap'])) : '',
                        'Music' => isset($row['music']) ? strval(floatval($row['music'])) : '',
                        'Url' => $row['url'] ?? '',
                        'Address' => $row['address'] ?? '',
                        'Latitude' => isset($row['latitude']) ? strval(floatval($row['latitude'])) : '',
                        'longitude' => isset($row['longitude']) ? strval(floatval($row['longitude'])) : '',
                        'Limited_time' => $row['limited_time'] ?? '',
                        'Socket' => $row['socket'] ?? '',
                        'Standing_desk' => $row['standing_desk'] ?? '',
                        'Mrt' => $row['mrt'] ?? '',
                        'Open_time' => $row['open_time'] ?? ''
                    ];
                }
            }
        }
    }
}

// 回傳 JSON
$response = [
    'mode' => 'address',
    'location' => $location,
    'count' => count($data),
    'results' => $data
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>


