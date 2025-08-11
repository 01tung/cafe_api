<?php
// 傳回 JSON & 允許跨網域（前端好串）
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 讀取查詢參數
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
if ($location === '') {
  echo json_encode([] , JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// 讀取 cafes.json
$jsonPath = __DIR__ . '/cafes.json';
if (!file_exists($jsonPath)) {
  http_response_code(500);
  echo json_encode(['error' => '找不到 cafes.json'], JSON_UNESCAPED_UNICODE);
  exit;
}
$data = json_decode(file_get_contents($jsonPath), true);
if ($data === null) {
  http_response_code(500);
  echo json_encode(['error' => 'cafes.json 解析失敗'], JSON_UNESCAPED_UNICODE);
  exit;
}

// 兼容可能的資料結構（有些版本會包一層 data）
$cafes = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : $data;

// mb_stripos 對中文大小寫不敏感；若無 mbstring 就退回 stripos
$hasMb = function_exists('mb_stripos');
$contains = function(string $haystack, string $needle) use ($hasMb) {
  if ($needle === '') return true;
  return $hasMb
    ? (mb_stripos($haystack, $needle, 0, 'UTF-8') !== false)
    : (stripos($haystack, $needle) !== false);
};

// 篩選：City 或 Address 包含 location（不分大小寫，支援中文）
$result = [];
foreach ($cafes as $row) {
  if (!is_array($row)) continue;

  // 兼容不同鍵名（City/city、Address/address）
  $city    = (string)($row['City']    ?? $row['city']    ?? '');
  $address = (string)($row['Address'] ?? $row['address'] ?? '');

  if ($contains($city, $location) || $contains($address, $location)) {
    // 盡量把常用欄位轉型，避免前端型別問題
    foreach ([
      'Wifi' => 'int','wifi' => 'int',
      'Seat' => 'float','seat' => 'float',
      'Quiet'=> 'int','quiet'=> 'int',
      'Tasty'=> 'float','tasty'=> 'float',
      'Cheap'=> 'float','cheap'=> 'float',
      'Music'=> 'float','music'=> 'float',
      'Latitude' => 'float','latitude' => 'float',
      'longitude' => 'float','Longitude' => 'float',
    ] as $k => $type) {
      if (isset($row[$k])) {
        $row[$k] = ($type === 'int') ? intval($row[$k]) : floatval($row[$k]);
      }
    }
    $result[] = $row;
  }
}

// 回傳結果（與你原本類似：純陣列）
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
