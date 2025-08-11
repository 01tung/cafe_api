<?php
header("Content-Type: application/json; charset=UTF-8");
//這個檔案就是你 Android callAIItineraryPlanner() 要呼叫的 API
//先做假資料版本，等你要串 ChatGPT 再改
// 讀取前端傳來的資料
$location = $_GET['location'] ?? '';
$cafes = $_GET['cafes'] ?? '';

// 假的行程規劃
$plan = "上午：咖啡廳A（文青風）\n中午：咖啡廳B（甜點推薦）\n下午：咖啡廳C（安靜適合工作）";

// 要串 ChatGPT 時用(假的計畫改成呼叫 OpenAI API）
//$plan = chatgpt_generate_itinerary($location, $cafes);


// 回傳 JSON 格式
echo json_encode([
    "location" => $location,
    "plan" => $plan
], JSON_UNESCAPED_UNICODE);
?>