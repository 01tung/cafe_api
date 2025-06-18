<?php
header('Content-Type: application/json');

// cafes.json 相對於這個 PHP 檔案要在同一個資料夾
$filename = 'cafes.json';

if (!file_exists($filename)) {
    http_response_code(404);
    echo json_encode(["error" => "cafes.json not found"]);
    exit;
}

$data = file_get_contents($filename);
echo $data;
?>
