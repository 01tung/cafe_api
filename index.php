<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>咖啡館 API 入口</title>
    <style>
        body { font-family: sans-serif; background: #f8f8f8; padding: 40px; line-height: 1.8; }
        h1 { color: #4CAF50; }
        a { color: #0066cc; text-decoration: none; }
        code { background: #eee; padding: 2px 6px; border-radius: 4px; }
        section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<h1>☕ 咖啡館 API 專案</h1>
<p>以下是目前可用的 API 介面與功能說明：</p>

<section>
    <h2>📍 1. 根據地點搜尋咖啡館</h2>
    <p>查詢特定地點附近的咖啡廳</p>
    <p>✅ 範例：<br>
    <a href="get_cafes_by_location.php?lat=25.04&lng=121.56&radius=3000" target="_blank">
        get_cafes_by_location.php?lat=25.04&lng=121.56&radius=3000
    </a></p>
</section>

<section>
    <h2>🚇 2. 根據捷運站搜尋咖啡館</h2>
    <p>輸入捷運站名稱（例如：中山、忠孝復興...）</p>
    <p>✅ 範例：<br>
    <a href="get_cafes_by_mrt.php?station=中山" target="_blank">
        get_cafes_by_mrt.php?station=中山
    </a></p>
</section>

<section>
    <h2>📋 3. 所有咖啡館資料</h2>
    <p>列出所有咖啡廳 JSON 資料</p>
    <p>✅ 範例：<br>
    <a href="get_cafes.php" target="_blank">
        get_cafes.php
    </a></p>
</section>

<section>
    <h2>🔍 4. 搜尋咖啡館（模糊關鍵字）</h2>
    <p>依咖啡館名稱模糊搜尋</p>
    <p>✅ 範例：<br>
    <a href="search_cafes.php?keyword=興波" target="_blank">
        search_cafes.php?keyword=興波
    </a></p>
</section>

<section>
    <h2>🧠 5. 自動生成旅遊行程</h2>
    <p>使用 OpenAI 規劃行程（需設定金鑰）</p>
    <p>✅ 範例：<br>
    <a href="generate_itinerary.php?location=台北市&cafes=興波咖啡,貓空咖啡&style=文青&time_preference=標準" target="_blank">
        generate_itinerary.php?location=台北市&cafes=興波咖啡,貓空咖啡&style=文青&time_preference=標準
    </a></p>
</section>

</body>
</html>
