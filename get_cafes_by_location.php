<?php
header("Content-Type: application/json; charset=UTF-8");

// 取得 GET 參數
$city = $_GET['city'] ?? '';
$district = $_GET['district'] ?? '';
$road = $_GET['road'] ?? '';
$preferences = isset($_GET['preferences']) ? explode(',', $_GET['preferences']) : [];

// 讀取 cafes.json
$data = json_decode(file_get_contents('cafes.json'), true);

// 轉換 0/1 -> true/false，符合你的資料規則
foreach ($data as &$cafe) {
    $cafe['limited_time'] = $cafe['limited_time'] == 0;    // 0 = 不限時 → true
    $cafe['socket'] = $cafe['socket'] == 1;                // 1 = 有插座 → true
    $cafe['minimum_charge'] = $cafe['minimum_charge'] == 0; // 0 = 無低消 → true
    $cafe['pet_friendly'] = $cafe['pet_friendly'] == 1;    // 1 = 寵物友善 → true
    $cafe['outdoor_seating'] = $cafe['outdoor_seating'] == 1; // 1 = 有戶外座位 → true
}
unset($cafe);

// 篩選函式
$results = array_filter($data, function($cafe) use ($city, $district, $road, $preferences){
    // 地址匹配：縣市 + 區域 + 路名（路名可不填）
    if ($city && stripos($cafe['city'], $city) === false) return false;
    if ($district && stripos($cafe['address'], $district) === false) return false;
    if ($road && stripos($cafe['address'], $road) === false) return false;

    // 偏好篩選
    foreach ($preferences as $pref) {
        switch($pref) {
            case "不限時":
                if (!$cafe['limited_time']) return false; // 使用者選不限時，咖啡廳必須不限時
                break;
            case "有插座":
                if (!$cafe['socket']) return false;
                break;
            case "無低消":
                if (!$cafe['minimum_charge']) return false; 
                break;
            case "寵物友善":
                if (!$cafe['pet_friendly']) return false;
                break;
            case "戶外座位":
                if (!$cafe['outdoor_seating']) return false;
                break;
        }
    }

    return true;
});

// 回傳 JSON
echo json_encode(array_values($results), JSON_UNESCAPED_UNICODE);



