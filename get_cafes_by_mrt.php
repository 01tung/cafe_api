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

// 建立 MySQLi 連線
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        'mode' => 'transit',
        'location' => '',
        'count' => 0,
        'results' => [],
        'error' => 'DB 連線失敗: ' . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 讀取捷運站參數
$station = isset($_GET['station']) ? $conn->real_escape_string($_GET['station']) : '';

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

// 搜尋 MRT 或地址包含捷運站名稱的咖啡廳
$sql = "SELECT * FROM cafe WHERE 
        ((mrt LIKE '%捷運{$station}%' OR 
          mrt LIKE '%{$station}站%' OR 
          mrt LIKE '%{$station}%') OR
         (address LIKE '%{$station}%')) 
        AND name IS NOT NULL 
        AND name != '' 
        AND address IS NOT NULL 
        AND address != ''
        ORDER BY 
            CASE 
                WHEN mrt LIKE '%捷運{$station}站%' THEN 1
                WHEN mrt LIKE '%{$station}站%' THEN 2
                WHEN mrt LIKE '%{$station}%' THEN 3
                WHEN address LIKE '%{$station}%' THEN 4
                ELSE 5
            END,
            RAND()
        LIMIT 25";

$result = $conn->query($sql);
$data = array();

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

// 如果沒找到結果，做更寬泛搜尋
if (empty($data)) {
    $station_without_suffix = str_replace(['站', '捷運'], '', $station);
    if (!empty($station_without_suffix) && $station_without_suffix !== $station) {
        $backup_sql = "SELECT * FROM cafe WHERE 
                       (mrt LIKE '%{$station_without_suffix}%' OR 
                        address LIKE '%{$station_without_suffix}%' OR
                        city LIKE '%{$station_without_suffix}%')
                       AND name IS NOT NULL 
                       AND name != ''
                       ORDER BY RAND()
                       LIMIT 20";
        $backup_result = $conn->query($backup_sql);
        if ($backup_result && $backup_result->num_rows > 0) {
            while($row = $backup_result->fetch_assoc()) {
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

// 回傳 JSON
$response = [
    'mode' => 'transit',
    'location' => $station,
    'count' => count($data),
    'results' => $data
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
