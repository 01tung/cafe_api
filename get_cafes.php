<?php
header('Content-Type: application/json; charset=utf-8');

// 資料庫連線設定
$host = 'localhost';
$db   = 'cafe_app';
$user = 'root';
$pass = 'root1234'; // ← 這裡如果有密碼請填上
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 查詢所有 cafe 資料
    $stmt = $pdo->query("SELECT * FROM cafe");
    $cafes = $stmt->fetchAll();

    echo json_encode($cafes, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

