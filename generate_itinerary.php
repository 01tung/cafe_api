<?php
header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 健康檢查端點
if (isset($_GET['health']) || $_SERVER['REQUEST_URI'] === '/health') {
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'curl_available' => function_exists('curl_init') ? 'yes' : 'no'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// OpenAI API Key - 從環境變數讀取
$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

// 如果環境變數沒有設置，使用預設值（開發用）
if (empty($apiKey)) {
    $apiKey = "sk-xxxxxx..."; // 開發環境預設值
    error_log("Warning: OPENAI_API_KEY environment variable not set, using default value");
}

// 讀取前端資料
$location = $_POST['location'] ?? $_REQUEST['location'] ?? '';
$cafes_json = $_POST['cafes'] ?? $_REQUEST['cafes'] ?? '';
$preferences_json = $_POST['preferences'] ?? '[]';
$search_mode = $_POST['search_mode'] ?? $_REQUEST['search_mode'] ?? 'address';
$style_preference = $_POST['style'] ?? $_REQUEST['style'] ?? '文青';
$time_preference = $_POST['time_preference'] ?? $_REQUEST['time_preference'] ?? '標準';

if (empty($location) || empty($cafes_json)) {
    echo json_encode([
        'reason' => null,
        'itinerary' => null,
        'raw_text' => '缺少必要參數 (location 或 cafes)',
        'error' => '缺少必要參數'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 解析資料
$cafes = json_decode($cafes_json, true);
$preferences = json_decode($preferences_json, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($cafes)) {
    echo json_encode([
        'reason' => null,
        'itinerary' => null,
        'raw_text' => '咖啡廳資料解析失敗或為空',
        'error' => 'JSON 解析錯誤'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 根據偏好篩選咖啡廳（如果有提供偏好）
$filtered_cafes = filterCafesByPreferences($cafes, $preferences);
if (count($filtered_cafes) < 2) {
    // 如果篩選後不足2間，使用原始清單
    $filtered_cafes = $cafes;
}

// 時間偏好設定
$timeSettings = [
    "早鳥" => ["start" => "09:00", "end" => "18:00"],
    "標準" => ["start" => "10:00", "end" => "20:00"],
    "夜貓" => ["start" => "13:00", "end" => "23:00"]
];

$startTime = $timeSettings[$time_preference]["start"] ?? "10:00";
$endTime = $timeSettings[$time_preference]["end"] ?? "20:00";

// 準備咖啡廳清單文字
$cafe_list = "";
$selected_cafes = array_slice($filtered_cafes, 0, 5); // 最多選5間咖啡廳

foreach ($selected_cafes as $index => $cafe) {
    $features = [];
    if (isset($cafe['Wifi']) && $cafe['Wifi'] === 'yes') $features[] = 'WiFi';
    if (isset($cafe['Socket']) && $cafe['Socket'] === 'yes') $features[] = '插座';
    if (isset($cafe['Quiet']) && $cafe['Quiet'] === 'yes') $features[] = '安靜';
    if (isset($cafe['Limited_time']) && $cafe['Limited_time'] === 'no') $features[] = '不限時';
    
    $cafe_list .= ($index + 1) . ". " . $cafe['Name'] . "\n";
    $cafe_list .= "   地址: " . ($cafe['Address'] ?? '未知') . "\n";
    
    if (!empty($cafe['Mrt'])) {
        $cafe_list .= "   捷運: " . $cafe['Mrt'] . "\n";
    }
    
    if (!empty($features)) {
        $cafe_list .= "   特色: " . implode('、', $features) . "\n";
    }
    $cafe_list .= "\n";
}

// 偏好文字
$preference_text = "";
if (!empty($preferences)) {
    $pref_map = [
        'quiet' => '安靜環境',
        'socket' => '有插座',
        'no_time_limit' => '不限時',
        'wifi' => 'WiFi',
        'photo_friendly' => '適合拍照',
        'outdoor_seating' => '戶外座位',
        'pet_friendly' => '寵物友善'
    ];
    
    $pref_texts = [];
    foreach ($preferences as $pref) {
        if (isset($pref_map[$pref])) {
            $pref_texts[] = $pref_map[$pref];
        }
    }
    
    if (!empty($pref_texts)) {
        $preference_text = "用戶偏好: " . implode('、', $pref_texts) . "\n";
    }
}

$search_info = $search_mode === 'transit' ? "以捷運站「{$location}」為中心" : "在「{$location}」地區";

// GPT Prompt，整合你的原始需求
$prompt = "你是一個專業旅遊規劃師，請根據使用者偏好與場所清單生成一日行程。

規劃地點：{$search_info}
{$preference_text}
使用者風格：{$style_preference}
時間偏好：{$time_preference}（從 {$startTime} 到 {$endTime}）

可用的咖啡廳清單：
{$cafe_list}

規劃要求：
1. 上午安排 1 間咖啡廳，下午安排 1 間咖啡廳
2. 其他時間安排與使用者偏好/活動風格相關的場所，GPT 自行分析推薦，不需要資料庫欄位
3. 所有安排符合使用者選擇的時間偏好：{$time_preference}，時間從 {$startTime} 到 {$endTime}
4. 夜市或夜間活動必須安排在18:00之後，符合營業時間邏輯
5. 一天最多安排5個地點
6. 請依使用者風格給出行程比例參考：
   - 文青路線：咖啡廳/書店/文創小店/展覽館
   - 青少年路線：特色餐廳/娛樂場所/運動場/夜市
   - 追星族路線：音樂專輯店/明星打卡景點/演出場地
   - 網美路線：拍照景點/咖啡廳/特色小店
   - 情侶路線：浪漫咖啡廳/景點/餐廳/戶外活動
   - 親子路線：親子餐廳/遊樂場/動物園/公園
   - 寵物友善路線：寵物友善咖啡廳/公園/商店

請生成 JSON 格式：
{
  \"reason\": \"為什麼推薦這樣安排...\",
  \"itinerary\": [
    {
      \"time\": \"09:00\",
      \"place\": \"XXX 咖啡廳\",
      \"activity\": \"享受早餐咖啡時光\",
      \"transport\": \"步行 5 分鐘\",
      \"period\": \"morning\"
    }
  ]
}

其中 period 只能是 \"morning\"（12:00前）或 \"afternoon\"（12:00後）。";

// 呼叫 OpenAI API
$ai_response = callOpenAI($apiKey, $prompt);

if ($ai_response === false) {
    // API 調用失敗，使用備用行程
    $fallback_result = generateFallbackItinerary($location, $selected_cafes, $search_mode, $style_preference, $startTime, $endTime);
    echo json_encode($fallback_result, JSON_UNESCAPED_UNICODE);
    exit;
}

// 解析 AI 回應
$result = parseAIResponse($ai_response, $location, $selected_cafes);
echo json_encode($result, JSON_UNESCAPED_UNICODE);

/**
 * 根據偏好篩選咖啡廳
 */
function filterCafesByPreferences($cafes, $preferences) {
    if (empty($preferences)) {
        return $cafes;
    }
    
    $filtered = [];
    foreach ($cafes as $cafe) {
        $match_score = 0;
        $total_preferences = count($preferences);
        
        foreach ($preferences as $pref) {
            switch ($pref) {
                case 'quiet':
                    if (isset($cafe['Quiet']) && strtolower($cafe['Quiet']) === 'yes') {
                        $match_score++;
                    }
                    break;
                case 'socket':
                    if (isset($cafe['Socket']) && strtolower($cafe['Socket']) === 'yes') {
                        $match_score++;
                    }
                    break;
                case 'no_time_limit':
                    if (isset($cafe['Limited_time']) && strtolower($cafe['Limited_time']) === 'no') {
                        $match_score++;
                    }
                    break;
                case 'wifi':
                    if (isset($cafe['Wifi']) && strtolower($cafe['Wifi']) === 'yes') {
                        $match_score++;
                    }
                    break;
                case 'photo_friendly':
                    // 根據名稱判斷是否適合拍照
                    $photo_keywords = ['美', '景', '風格', '文青', '網美', 'IG', '打卡'];
                    foreach ($photo_keywords as $keyword) {
                        if (strpos($cafe['Name'], $keyword) !== false) {
                            $match_score++;
                            break;
                        }
                    }
                    break;
                case 'outdoor_seating':
                    if (isset($cafe['Outdoor_seating']) && strtolower($cafe['Outdoor_seating']) === 'yes') {
                        $match_score++;
                    }
                    break;
                case 'pet_friendly':
                    if (isset($cafe['Pet_friendly']) && strtolower($cafe['Pet_friendly']) === 'yes') {
                        $match_score++;
                    }
                    break;
            }
        }
        
        // 如果符合至少30%的偏好，就加入篩選結果
        if ($total_preferences === 0 || $match_score >= ($total_preferences * 0.3)) {
            $cafe['match_score'] = $match_score;
            $filtered[] = $cafe;
        }
    }
    
    // 根據匹配分數排序
    usort($filtered, function($a, $b) {
        return ($b['match_score'] ?? 0) - ($a['match_score'] ?? 0);
    });
    
    return $filtered;
}

/**
 * 呼叫 OpenAI API
 */
function callOpenAI($apiKey, $prompt) {
    // 檢查 API Key 是否有效
    if (empty($apiKey) || $apiKey === "sk-xxxxxx...") {
        error_log("OpenAI API Key is not properly configured");
        return false;
    }
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiKey}"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "gpt-3.5-turbo",
            "messages" => [
                ["role" => "system", "content" => "你是一個專業的旅遊行程規劃師，能依據使用者偏好與場所資訊推薦行程。"],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.8,
            "max_tokens" => 1500
        ])
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("OpenAI API Error: HTTP {$http_code} - {$response}");
        return false;
    }
    
    // 解析回傳
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        error_log("Invalid OpenAI API response: " . $response);
        return false;
    }
    
    return $data['choices'][0]['message']['content'];
}

/**
 * 解析 AI 回應
 */
function parseAIResponse($ai_response, $location, $cafes) {
    // 嘗試提取 JSON
    $json_start = strpos($ai_response, '{');
    $json_end = strrpos($ai_response, '}');
    
    if ($json_start !== false && $json_end !== false) {
        $json_str = substr($ai_response, $json_start, $json_end - $json_start + 1);
        $parsed = json_decode($json_str, true);
        
        if ($parsed && isset($parsed['itinerary'])) {
            return [
                'reason' => $parsed[



