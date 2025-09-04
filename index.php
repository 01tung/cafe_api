<?php
header("Content-Type: text/html; charset=UTF-8");
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>☕ 咖啡廳 API 入口</title>
  <style>
    body {
      font-family: 'Segoe UI', 'Microsoft JhengHei', sans-serif;
      background-color: #fdfdfd;
      padding: 30px;
      color: #333;
    }
    h1 {
      color: #7B3F00;
    }
    section {
      margin-bottom: 25px;
    }
    a {
      display: inline-block;
      margin-top: 5px;
      color: #0066cc;
      text-decoration: none;
    }
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <h1>☕ 咖啡廳 API 入口</h1>

  <section>
    <h2>1️⃣ 取得所有咖啡廳</h2>
    <a href="get_cafes.php" target="_blank">查看 API</a>
  </section>

  <section>
    <h2>2️⃣ 搜尋附近咖啡廳</h2>
    <a href="get_cafes_by_location.php" target="_blank">查看 API</a>
  </section>

  <section>
    <h2>3️⃣ 依捷運站搜尋</h2>
    <a href="get_cafes_by_mrt.php" target="_blank">查看 API</a>
  </section>

  <section>
    <h2>4️⃣ 關鍵字搜尋咖啡廳</h2>
    <a href="search_cafes.php" target="_blank">查看 API</a>
  </section>

  <section>
    <h2>5️⃣ 自動生成旅遊行程</h2>
    <a href="generate_itinerary.php" target="_blank">查看 API</a>
  </section>

</body>
</html>

