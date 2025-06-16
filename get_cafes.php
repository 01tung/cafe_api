<?php
header("Content-Type: application/json");
include("db.php");  // 引入連線資料庫的設定

$sql = "SELECT 
    ID, 
    Name, 
    City, 
    Wifi, 
    Seat, 
    Quiet, 
    Tasty, 
    Cheap, 
    Music, 
    Url, 
    Address, 
    Latitude, 
    Longitude, 
    Limited_time, 
    Socket, 
    Standing_desk, 
    Mrt, 
    Open_time 
FROM cafe";  // 你的資料表名稱為 cafe

$result = $conn->query($sql);

$data = array();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>