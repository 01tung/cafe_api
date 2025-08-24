<?php
// 設定回應標頭為 JSON 並強制 UTF-8
header('Content-Type: application/json; charset=utf-8');

// 讀取 json 檔案
$json = file_get_contents('cafes.json');

// 直接輸出內容
echo $json;
?>
