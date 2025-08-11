<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

// 讀取 GET 參數 location
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';

if ($location === '') {
    echo json_encode([]);
    exit;
}

// 用 LIKE 模糊搜尋,這樣輸入「台北市」或「台北市中山區」都能找到符合的資料,（你可以改欄位名稱）
//把查詢結果放入陣列，並把特定欄位轉型（int 或 float）
// 用 city 跟 address 都模糊搜尋，增加命中率
$sql = "SELECT * FROM cafe WHERE city LIKE '%$location%' OR address LIKE '%$location%'";

$result = $conn->query($sql);

$data = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['wifi'] = intval($row['wifi']);
        $row['seat'] = floatval($row['seat']);
        $row['quiet'] = intval($row['quiet']);
        $row['tasty'] = floatval($row['tasty']);
        $row['cheap'] = floatval($row['cheap']);
        $row['music'] = floatval($row['music']);
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $data[] = $row;
    }
}
//回傳 JSON 編碼的資料
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>