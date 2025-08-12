<?php
header("Content-Type: application/json; charset=UTF-8");

// 從環境變數取得 OpenAI API Key（更安全）
$apiKey = getenv("OPENAI_API_KEY");

// 驗證金鑰是否存在
if (!$apiKey) {
    echo json_encode(["error" => "找不到 OpenAI API 金鑰，請設定環境變數 OPENAI_API_KEY"], JSON_UNESCAPED_UNICODE);
    exit;
}

$location = $_REQUEST['location'] ?? '';
$cafes = $_REQUEST['cafes'] ?? '';

if (empty($location)) {
    echo json_encode(["error" => "缺少地點參數"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 建立 prompt 給 GPT
$prompt = "請根據以下資訊，幫我規劃一日行程，並用 JSON 格式回傳，包含：
1. 推薦原因（reason）
2. 行程安排（itinerary），每個時段要有地點、活動、交通方式與預計時間
地點：{$location}
咖啡廳清單（可選用）：{$cafes}
請用繁體中文回答，輸出格式範例：
{
  \"reason\": \"為什麼推薦這樣安排...\",
  \"itinerary\": [
    {\"time\": \"09:00\", \"place\": \"XXX 咖啡廳\", \"activity\": \"吃早餐\", \"transport\": \"步行 5 分鐘\"},
    {\"time\": \"11:00\", \"place\": \"YYY 景點\", \"activity\": \"拍照與參觀\", \"transport\": \"公車 10 分鐘\"}
  ]
}";

// 呼叫 OpenAI API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer {$apiKey}"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "model" => "gpt-3.5-turbo",
    "messages" => [
        ["role" => "system", "content" => "你是一個專業的旅遊行程規劃師。"],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.8
]));

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(["error" => curl_error($ch)], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}
curl_close($ch);

$data = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? null;

if (!$reply) {
    echo json_encode(["error" => "OpenAI 沒有回應"], JSON_UNESCAPED_UNICODE);
    exit;
}

$itineraryData = json_decode($reply, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["raw_text" => $reply], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($itineraryData, JSON_UNESCAPED_UNICODE);

