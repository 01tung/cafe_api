<?php
// 設定回傳 JSON 格式，避免亂碼
header('Content-Type: application/json; charset=utf-8');

// 資料庫連線設定
$host = 'localhost';
$db   = 'cafe_app';
$user = 'root';
$pass = 'root1234';  // ← 請根據實際密碼調整
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    // 建立 PDO 連線
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 查詢資料表 cafes（注意：不是 cafe）
    $stmt = $pdo->query("SELECT * FROM cafes");
    $cafes = $stmt->fetchAll();

    // 回傳 JSON，保留中文不編碼
    echo json_encode($cafes, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // 錯誤處理
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

