<?php
header('Content-Type: application/json; charset=utf-8');

// ✅ 資料庫連線設定（Render 用）
$mysqli = new mysqli('localhost', 'root', 'root1234', 'cafe_app');
if ($mysqli->connect_errno) {
    echo json_encode(['error' => '無法連接資料庫']);
    exit;
}

// ✅ 取得搜尋條件
$city = isset($_GET['city']) ? $mysqli->real_escape_string($_GET['city']) : null;
$min_wifi = isset($_GET['min_wifi']) ? (int)$_GET['min_wifi'] : null;
$max_wifi = isset($_GET['max_wifi']) ? (int)$_GET['max_wifi'] : null;
$min_seat = isset($_GET['min_seat']) ? (float)$_GET['min_seat'] : null;
$max_seat = isset($_GET['max_seat']) ? (float)$_GET['max_seat'] : null;
$min_score = isset($_GET['min_score']) ? (float)$_GET['min_score'] : null;  // tasty 評分

// ✅ 建立 SQL 條件
$conditions = [];
if ($city) {
    $conditions[] = "city = '$city'";
}
if (!is_null($min_wifi)) {
    $conditions[] = "wifi >= $min_wifi";
}
if (!is_null($max_wifi)) {
    $conditions[] = "wifi <= $max_wifi";
}
if (!is_null($min_seat)) {
    $conditions[] = "seat >= $min_seat";
}
if (!is_null($max_seat)) {
    $conditions[] = "seat <= $max_seat";
}
if (!is_null($min_score)) {
    $conditions[] = "tasty >= $min_score";
}

// ✅ 組合查詢語句
$where_sql = '';
if (count($conditions) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $conditions);
}

$sql = "SELECT * FROM cafe $where_sql LIMIT 100";  // 最多回傳 100 筆
$result = $mysqli->query($sql);

// ✅ 查詢失敗
if (!$result) {
    echo json_encode(['error' => '資料查詢失敗']);
    exit;
}

// ✅ 整理查詢結果
$cafes = [];
while ($row = $result->fetch_assoc()) {
    $cafes[] = $row;
}

// ✅ 回傳 JSON
echo json_encode(['cafes' => $cafes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>