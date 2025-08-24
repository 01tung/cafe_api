<?php
header('Content-Type: application/json; charset=utf-8');

// 資料庫連線設定
$host = 'localhost';
$dbname = 'cafe_app';
$user = 'root';
$password = 'root1234'; // 如果你有設密碼，就填上去

try {
    // 建立 PDO 連線
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 從資料表撈資料
    $stmt = $pdo->query("SELECT * FROM cafe");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 存成 JSON 檔案
    file_put_contents('cafes.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo "✅ 成功匯出 cafes.json，共有 " . count($data) . " 筆資料";
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>
