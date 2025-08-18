<?php
header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'db.php';

// 讀取 GET 參數 location
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';

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

// 改進的搜尋邏輯，增加更多搜尋條件提高命中率
$sql = "SELECT * FROM cafe WHERE 
        (city LIKE '%$location%' OR 
         address LIKE '%$location%' OR 
         name LIKE '%$location%') 
        AND name IS NOT NULL 
        AND name != '' 
        AND address IS NOT NULL 
        AND address != ''
        ORDER BY 
            CASE 
                WHEN address LIKE '%$location%' THEN 1
                WHEN city LIKE '%$location%' THEN 2
                ELSE 3
            END,
            RAND()
        LIMIT 25";

$result = $conn->query($sql);
$data = array();

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // 轉換資料型別，保持與原有格式相容
        $formatted_row = [
            'ID' => $row['id'] ?? $row['ID'] ?? '',
            'Name' => $row['name'] ?? $row['Name'] ?? '',
            'City' => $row['city'] ?? $row['City'] ?? '',
            'Wifi' => isset($row['wifi']) ? (intval($row['wifi']) ? 'yes' : 'no') : '',
            'Seat' => isset($row['seat']) ? strval(floatval($row['seat'])) : '',
            'Quiet' => isset($row['quiet']) ? (intval($row['quiet']) ? 'yes' : 'no') : '',
            'Tasty' => isset($row['tasty']) ? strval(floatval($row['tasty'])) : '',
            'Cheap' => isset($row['cheap']) ? strval(floatval($row['cheap'])) : '',
            'Music' => isset($row['music']) ? strval(floatval($row['music'])) : '',
            'Url' => $row['url'] ?? $row['Url'] ?? '',
            'Address' => $row['address'] ?? $row['Address'] ?? '',
            'Latitude' => isset($row['latitude']) ? strval(floatval($row['latitude'])) : '',
            'longitude' => isset($row['longitude']) ? strval(floatval($row['longitude'])) : '',
            'Limited_time' => $row['limited_time'] ?? $row['Limited_time'] ?? '',
            'Socket' => $row['socket'] ?? $row['Socket'] ?? '',
            'Standing_desk' => $row['standing_desk'] ?? $row['Standing_desk'] ?? '',
            'Mrt' => $row['mrt'] ?? $row['Mrt'] ?? '',
            'Open_time' => $row['open_time'] ?? $row['Open_time'] ?? ''
        ];
        $data[] = $formatted_row;
    }
}

// 如果結果太少，嘗試更寬泛的搜尋
if (count($data) < 5) {
    // 去掉區域後綴，進行更寬泛搜尋
    $broad_location = str_replace(['區', '市', '縣'], '', $location);
    if ($broad_location !== $location && strlen($broad_location) >= 2) {
        $backup_sql = "SELECT * FROM cafe WHERE 
                       (city LIKE '%$broad_location%' OR 
                        address LIKE '%$broad_location%') 
                       AND name IS NOT NULL 
                       AND name != ''
                       ORDER BY RAND() 
                       LIMIT 15";
        
        $backup_result = $conn->query($backup_sql);
        if ($backup_result && $backup_result->num_rows > 0) {
            while($row = $backup_result->fetch_assoc()) {
                $formatted_row = [
                    'ID' => $row['id'] ?? $row['ID'] ?? '',
                    'Name' => $row['name'] ?? $row['Name'] ?? '',
                    'City' => $row['city'] ?? $row['City'] ?? '',
                    'Wifi' => isset($row['wifi']) ? (intval($row['wifi']) ? 'yes' : 'no') : '',
                    'Seat' => isset($row['seat']) ? strval(floatval($row['seat'])) : '',
                    'Quiet' => isset($row['quiet']) ? (intval($row['quiet']) ? 'yes' : 'no') : '',
                    'Tasty' => isset($row['tasty']) ? strval(floatval($row['tasty'])) : '',
                    'Cheap' => isset($row['cheap']) ? strval(floatval($row['cheap'])) : '',
                    'Music' => isset($row['music']) ? strval(floatval($row['music'])) : '',
                    'Url' => $row['url'] ?? $row['Url'] ?? '',
                    'Address' => $row['address'] ?? $row['Address'] ?? '',
                    'Latitude' => isset($row['latitude']) ? strval(floatval($row['latitude'])) : '',
                    'longitude' => isset($row['longitude']) ? strval(floatval($row['longitude'])) : '',
                    'Limited_time' => $row['limited_time'] ?? $row['Limited_time'] ?? '',
                    'Socket' => $row['socket'] ?? $row['Socket'] ?? '',
                    'Standing_desk' => $row['standing_desk'] ?? $row['Standing_desk'] ?? '',
                    'Mrt' => $row['mrt'] ?? $row['Mrt'] ?? '',
                    'Open_time' => $row['open_time'] ?? $row['Open_time'] ?? ''
                ];
                
                // 避免重複
                $duplicate = false;
                foreach ($data as $existing) {
                    if ($existing['ID'] === $formatted_row['ID']) {
                        $duplicate = true;
                        break;
                    }
                }
                if (!$duplicate) {
                    $data[] = $formatted_row;
                }
            }
        }
    }
}

// 回傳標準格式
$response = [
    'mode' => 'address',
    'location' => $location,
    'count' => count($data),
    'results' => $data
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
