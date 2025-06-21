<?php
header('Content-Type: application/json; charset=utf-8');

$jsonFile = 'cafes.json';

if (file_exists($jsonFile)) {
    $json = file_get_contents($jsonFile);
    echo $json;
} else {
    echo json_encode(['error' => 'cafes.json not found']);
}
?>

