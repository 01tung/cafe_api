<?php
$servername = "localhost";
$username = "root";
$password = "root1234";  // 例如 root1234
$dbname = "cafe_app";  // 你剛剛建立的資料庫名稱

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}
?>