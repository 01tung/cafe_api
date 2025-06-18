<?php
header("Content-Type: application/json");

// 資料庫連線參數
$host = "localhost";
$user = "root";
$password = "root1234"; // 改成你的密碼
$database = "cafe_app";

// 建立連線
$conn = new mysqli($host, $user, $password, $database);

// 檢查連線
if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// 查詢資料
$sql = "SELECT * FROM cafe";
$result = $conn->query($sql);

// 處理結果
$data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["message" => "No data found"]);
}

$conn->close();
?>

