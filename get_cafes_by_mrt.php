<?php
header("Content-Type: application/json; charset=UTF-8");

// ✅ 讀取 OpenAI API Key（來自環境變數）
$apiKey = getenv("OPENAI_API_KEY");
if (!$apiKey) {
    echo json_encode(["error" => "缺少 OpenAI API 金鑰，請確認環境變數 OPENAI_API_KEY 是否設定"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 讀取前端資料
$station = $_REQUEST['station'] ?? ''; // 使用者選的捷運站
$cafesJson = $_REQUEST['cafes'] ?? ''; // 前端傳來的所有咖啡廳 JSON
$stylePreference = $_REQUEST['style'] ?? '文青'; // 使用者選擇風格
$timePreference = $_REQUEST['time_preference'] ?? '標準'; // 早鳥/標準/夜貓

if (empty($station)) {
    echo json_encode(["error" => "缺少 station 參數"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($cafesJson)) {
    echo json_encode(["error" => "缺少 cafes 參數"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 解析咖啡廳 JSON
$cafes = json_decode($cafesJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["error" => "cafes JSON 解析失敗"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 篩選符合捷運站前綴的咖啡廳
$matchedCafes = array_filter($cafes, function($cafe) use ($station) {
    if (!isset($cafe['Mrt'])) return false;
    // 只比對站名前綴，不管出口
    return mb_strpos($cafe['Mrt'], '捷運' . $station) === 0;
});

if (empty($matchedCafes)) {
    echo json_encode([
        "mode" => "mrt",
        "station" => $station,
        "count" => 0,
        "results" => [],
        "error" => "此捷運站無符合的咖啡廳"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 把篩選後的咖啡廳再轉成 JSON
$matchedCafesJson = json_encode(array_values($matchedCafes), JSON_UNESCAPED_UNICODE);

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
2. 其他時間安排與使用者偏好/活動風格型相關的場所，GPT 自行分析推薦。
3. 所有安排符合使用者選擇的時間偏好：{$timePreference}，時間從 {$startTime} 到 {$endTime}。
4. 使用者風格：{$stylePreference}。
5. 可選咖啡廳清單：{$matchedCafesJson}。";

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

// 嘗試解析 GPT 回傳的 JSON
$itineraryData = json_decode($reply, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // GPT 沒照 JSON 格式回
    echo json_encode(["raw_text" => $reply], JSON_UNESCAPED_UNICODE);
    exit;
}

// 成功解析，回傳給前端
echo json_encode($itineraryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>