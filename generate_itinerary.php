<?php
header("Content-Type: application/json; charset=UTF-8");

// 從環境變數取得 OpenAI API Key（更安全）
$apiKey = getenv("OPENAI_API_KEY");
if (!$apiKey) {
    echo json_encode(["error" => "找不到 OpenAI API 金鑰，請設定環境變數 OPENAI_API_KEY"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 讀取前端資料
$location = $_REQUEST['location'] ?? '';
$cafes = $_REQUEST['cafes'] ?? '';
$stylePreference = $_REQUEST['style'] ?? '文青';
$timePreference = $_REQUEST['time_preference'] ?? '標準';

if (empty($location)) {
    echo json_encode(["error" => "缺少地點參數"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 時間偏好設定
$timeSettings = [
    "早鳥" => ["start" => "09:00", "end" => "18:00"],
    "標準" => ["start" => "10:00", "end" => "20:00"],
    "夜貓" => ["start" => "13:00", "end" => "23:00"]
];
$startTime = $timeSettings[$timePreference]["start"] ?? "10:00";
$endTime = $timeSettings[$timePreference]["end"] ?? "20:00";

// GPT Prompt
$prompt = "你是一個專業旅遊規劃師，請根據使用者偏好與場所清單生成一日行程。
規則：
1. 上午安排 1 間咖啡廳，下午安排 1 間咖啡廳。
2. 其他時間安排與使用者偏好/活動風格型相關的場所。
3. 所有安排符合時間偏好：{$timePreference}，時間為 {$startTime} 至 {$endTime}。
4. 使用者風格：{$stylePreference}。
5. 可選咖啡廳清單：{$cafes}。
6. 行程比例依據風格調整，例如：
   - 文青路線：咖啡廳/書店/文創小店/展覽館
   - 青少年路線：特色餐廳/娛樂場所/運動場/夜市
   - 追星族路線：音樂專輯店/明星打卡景點/演出場地
   - 網美路線：拍照景點/咖啡廳/特色小店
   - 情侶路線：浪漫咖啡廳/景點/餐廳/戶外活動
   - 親子路線：親子餐廳/遊樂場/動物園/公園
   - 寵物友善路線：寵物友善咖啡廳/公園/商店
7. 請用 JSON 格式輸出，範例：
{
  \"reason\": \"為什麼推薦這樣安排...\",
  \"itinerary\": [
    {
      \"time\": \"09:00\",
      \"place\": \"XXX 咖啡廳\",
      \"activity\": \"吃早餐\",
      \"transport\": \"步行 5 分鐘\",
      \"style\": \"文青\",
      \"preference\": \"文青路線\"
    }
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
        ["role" => "system", "content" => "你是一個專業的旅遊行程規劃師，能依據使用者偏好與場所資訊推薦行程。"],
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

// 解析回傳
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
?>



