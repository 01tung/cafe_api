<?php
header("Content-Type: application/json; charset=UTF-8");

// 資料庫連線設定
$host = "localhost";
$user = "root";
$pass = "root1234";
$dbname = "cafe_app";

// 接收 GET 參數
$station = $_GET['station'] ?? '';

if (empty($station)) {
    echo json_encode([
        "mode" => "mrt",
        "location" => "",
        "count" => 0,
        "results" => []
    ]);
    exit;
}

// 建立資料庫連線
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        "mode" => "mrt",
        "location" => $station,
        "count" => 0,
        "results" => [],
        "error" => "無法連接資料庫：" . $conn->connect_error
    ]);
    exit;
}

// 搜尋捷運開頭名稱
$searchPrefix = '捷運' . $station;
$stmt = $conn->prepare("SELECT * FROM cafes WHERE Mrt LIKE CONCAT(?, '%')");
$stmt->bind_param("s", $searchPrefix);
$stmt->execute();
$result = $stmt->get_result();

// 整理資料
$cafes = [];
while ($row = $result->fetch_assoc()) {
    $cafes[] = $row;
}

// 回傳 JSON 結果
echo json_encode([
    "mode" => "mrt",
    "location" => $station,
    "count" => count($cafes),
    "results" => $cafes
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
