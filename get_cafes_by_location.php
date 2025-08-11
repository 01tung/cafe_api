<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 參數
$lat     = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng     = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius  = isset($_GET['radius']) ? floatval($_GET['radius']) : 1000.0; // 公尺
$keyword = isset($_GET['location']) ? trim($_GET['location']) : '';
$pretty  = isset($_GET['pretty']) ? (int)$_GET['pretty'] === 1 : false;
$jsonOpt = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0);

// 讀 JSON
$jsonPath = __DIR__ . '/cafes.json';
if (!file_exists($jsonPath)) {
  http_response_code(500);
  echo json_encode(['error' => '找不到 cafes.json'], $jsonOpt);
  exit;
}
$raw = file_get_contents($jsonPath);
$data = json_decode($raw, true);
if ($data === null) {
  http_response_code(500);
  echo json_encode(['error' => 'cafes.json 解析失敗'], $jsonOpt);
  exit;
}

/**
 * 從多種可能的結構取出「店家陣列」
 * - 直接就是陣列
 * - {'data': [...]} 包一層
 * - phpMyAdmin 匯出：頂層是 array，找出 {"type":"table","data":[...]} 的 data
 */
function extract_cafes($data) {
  if (isset($data['data']) && is_array($data['data'])) return $data['data'];
  if (is_array($data)) {
    // 可能已經是店家陣列
    $looksLikeCafe = fn($row) =>
      is_array($row) && (isset($row['latitude']) || isset($row['Latitude'])) &&
      (isset($row['longitude']) || isset($row['Longitude']));
    $allCafe = !empty($data) && array_reduce($data, fn($carry,$r)=> $carry && is_array($r), true)
             && array_reduce(array_slice($data,0,20), fn($carry,$r)=> $carry || $looksLikeCafe($r), false);
    if ($allCafe) return $data;

    // phpMyAdmin 匯出格式：在頂層 array 裡找 type=table 的 data
    foreach ($data as $item) {
      if (is_array($item) && isset($item['type']) && $item['type']==='table' && isset($item['data']) && is_array($item['data'])) {
        return $item['data'];
      }
    }
  }
  return [];
}

$cafes = extract_cafes($data);

// 小工具
function distance_m($lat1,$lon1,$lat2,$lon2){
  $R=6371000; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
  $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
  return 2*$R*atan2(sqrt($a), sqrt(1-$a));
}
$hasMb = function_exists('mb_stripos');
$contains = function($hay,$nd) use($hasMb){
  $hay=(string)$hay; $nd=(string)$nd;
  if ($nd==='') return true;
  return $hasMb ? (mb_stripos($hay,$nd,0,'UTF-8')!==false) : (stripos($hay,$nd)!==false);
};

// 模式 A：lat/lng 搜附近
if ($lat !== null && $lng !== null) {
  $out = [];
  foreach ($cafes as $row) {
    if (!is_array($row)) continue;
    $clat = isset($row['Latitude']) ? floatval($row['Latitude'])
          : (isset($row['latitude']) ? floatval($row['latitude']) : null);
    $clng = isset($row['longitude']) ? floatval($row['longitude'])
          : (isset($row['Longitude']) ? floatval($row['Longitude']) : null);
    if ($clat===null || $clng===null) continue;

    $d = distance_m($lat,$lng,$clat,$clng);
    if ($d <= $radius) {
      // 型別盡量轉好
      foreach ([
        'wifi'=>'int','Wifi'=>'int',
        'seat'=>'float','Seat'=>'float',
        'quiet'=>'int','Quiet'=>'int',
        'tasty'=>'float','Tasty'=>'float',
        'cheap'=>'float','Cheap'=>'float',
        'music'=>'float','Music'=>'float',
      ] as $k=>$t) { if(isset($row[$k])) $row[$k]=($t==='int')?intval($row[$k]):floatval($row[$k]); }
      $row['distance_m'] = round($d,2);
      $out[] = $row;
    }
  }
  usort($out, fn($a,$b)=> ($a['distance_m']??INF) <=> ($b['distance_m']??INF));
  echo json_encode(['mode'=>'geo','lat'=>$lat,'lng'=>$lng,'radius_m'=>$radius,'count'=>count($out),'results'=>$out], $jsonOpt);
  exit;
}

// 模式 B：關鍵字查 city/address
if ($keyword !== '') {
  $out = [];
  foreach ($cafes as $row) {
    if (!is_array($row)) continue;
    $city    = (string)($row['City'] ?? $row['city'] ?? '');
    $address = (string)($row['Address'] ?? $row['address'] ?? '');
    if ($contains($city,$keyword) || $contains($address,$keyword)) {
      $out[] = $row;
    }
  }
  echo json_encode(['mode'=>'keyword','location'=>$keyword,'count'=>count($out),'results'=>$out], $jsonOpt);
  exit;
}

// 沒給必要參數
http_response_code(400);
echo json_encode(['error'=>'請提供 lat/lng (+ radius) 或 location 關鍵字其中一種參數'], $jsonOpt);


// 回傳結果（與你原本類似：純陣列）
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
