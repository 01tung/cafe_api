<?php
header('Content-Type: application/json; charset=utf-8');

// ✅ 資料庫連線設定（本機 XAMPP 使用）
$mysqli = new mysqli('localhost', 'root', 'root1234', 'cafe_app');

// ✅ 檢查連線
if ($mysqli->connect_errno) {
    echo json_encode(['error' => '無法連接資料庫：' . $mysqli->connect_error], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ 設定 UTF-8 編碼，避免亂碼
$mysqli->set_charset("utf8");

// ✅ 取得搜尋條件（GET）
$city = isset($_GET['city']) ? $mysqli->real_escape_string($_GET['city']) : null;
$min_wifi = isset($_GET['min_wifi']) ? (int)$_GET['min_wifi'] : null;
$max_wifi = isset($_GET['max_wifi']) ? (int)$_GET['max_wifi'] : null;
$min_seat = isset($_GET['min_seat']) ? (float)$_GET['min_seat'] : null;
$max_seat = isset($_GET['max_seat']) ? (float)$_GET['max_seat'] : null;
$min_score = isset($_GET['min_score']) ? (float)$_GET['min_score'] : null; // tasty

// ✅ 組合 SQL 查詢條件
$conditions = [];
if ($city) $conditions[] = "City = '$city'";
if (!is_null($min_wifi)) $conditions[] = "Wifi >= $min_wifi";
if (!is_null($max_wifi)) $conditions[] = "Wifi <= $max_wifi";
if (!is_null($min_seat)) $conditions[] = "Seat >= $min_seat";
if (!is_null($max_seat)) $conditions[] = "Seat <= $max_seat";
if (!is_null($min_score)) $conditions[] = "Tasty >= $min_score";

// ✅ 組合 WHERE 子句
$where_sql = '';
if (count($conditions) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $conditions);
}

// ✅ 查詢資料（最多100筆）
$sql = "SELECT * FROM cafe $where_sql LIMIT 100";
$result = $mysqli->query($sql);

// ✅ 查詢失敗處理
if (!$result) {
    echo json_encode(['error' => '資料查詢失敗：' . $mysqli->error], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ 解析資料
$cafes = [];
while ($row = $result->fetch_assoc()) {
    $cafes[] = $row;
}

// ✅ 回傳結果
echo json_encode(['cafes' => $cafes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
