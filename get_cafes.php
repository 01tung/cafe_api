<?php
header('Content-Type: application/json; charset=utf-8');

// 取得 JSON 檔案路徑
$jsonFile = __DIR__ . '/cafes.json';

// 檢查檔案是否存在
if (!file_exists($jsonFile)) {
    echo json_encode(['error' => '找不到 cafes.json 檔案']);
    exit;
}

// 讀取 JSON 檔案內容
$jsonData = file_get_contents($jsonFile);

// 檢查讀取是否成功
if ($jsonData === false) {
    echo json_encode(['error' => '無法讀取 cafes.json']);
    exit;
}

// 輸出 JSON 資料
echo $jsonData;


