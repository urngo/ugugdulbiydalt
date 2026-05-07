<?php
$conn = new mysqli(getenv("MYSQLHOST"), getenv("MYSQLUSER"), getenv("MYSQLPASSWORD"), getenv("MYSQLDATABASE"), (int)getenv("MYSQLPORT"));
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$query = $_GET["q"] ?? "";
$message = "";
$packages = [];
$totalPrice = 0.0;
if ($query !== "") {
    $stmt = $conn->prepare(
        "SELECT trackCode, phoneNumber, price, createdAt
         FROM packages
         WHERE isDeleted = 0
           AND (trackCode LIKE ? OR phoneNumber LIKE ?)
         ORDER BY createdAt DESC"
    );
    $like = "%" . $query . "%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
    if (!$packages) {
        $message = "Илгээмж олдсонгүй.";
    } else {
        foreach ($packages as $p) $totalPrice += $p["price"];
        $message = count($packages) . " илгээмж олдлоо.";
    }
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Карго — Илгээмж хайлт</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0d0f14;
    --surface: #161920;
    --border: #252830;
    --accent: #f0c040;
    --accent2: #e05a20;
    --text: #e8eaf0;
    --muted: #6b7280;
    --card: #1a1d25;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
  }
 
  /* BG GRID */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(240,192,64,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(240,192,64,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
  }
 
  .container {
    position: relative;
    z-index: 1;
    max-width: 860px;
    margin: 0 auto;
    padding: 48px 20px 80px;
  }
 
  /* HEADER */
  header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 60px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 24px;
  }
  .logo {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 2.6rem;
    letter-spacing: 3px;
    color: var(--accent);
    line-height: 1;
  }
  .logo span { color: var(--accent2); }
  nav a {
    color: var(--muted);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    border: 1px solid var(--border);
    padding: 8px 16px;
    border-radius: 4px;
    transition: all 0.2s;
  }
  nav a:hover { color: var(--accent); border-color: var(--accent); }
 
  /* HERO */
  .hero {
    margin-bottom: 48px;
  }
  .hero h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(2.5rem, 8vw, 5rem);
    letter-spacing: 2px;
    line-height: 0.95;
    margin-bottom: 16px;
  }
  .hero h1 em {
    font-style: normal;
    color: var(--accent);
    -webkit-text-stroke: 0px;
    display: block;
  }
  .hero p { color: var(--muted); font-size: 1rem; max-width: 420px; }
 
  /* SEARCH */
  .search-wrap {
    display: flex;
    gap: 0;
    margin-bottom: 36px;
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
    transition: border-color 0.2s;
  }
  .search-wrap:focus-within { border-color: var(--accent); }
  .search-wrap input {
    flex: 1;
    background: var(--surface);
    color: var(--text);
    border: none;
    outline: none;
    padding: 16px 20px;
    font-size: 1rem;
    font-family: 'DM Sans', sans-serif;
  }
  .search-wrap input::placeholder { color: var(--muted); }
  .search-wrap button {
    background: var(--accent);
    color: #0d0f14;
    border: none;
    padding: 16px 28px;
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.1rem;
    letter-spacing: 2px;
    cursor: pointer;
    transition: background 0.2s;
  }
  .search-wrap button:hover { background: #f8d060; }
 
  /* STATUS BAR */
  .status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9rem;
  }
  .status .count { color: var(--accent); font-weight: 600; }
  .status .total { color: var(--muted); }
  .status .total strong { color: var(--text); }
 
  /* CARDS */
  .packages { display: flex; flex-direction: column; gap: 12px; }
  .pkg-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px 24px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px 24px;
    align-items: center;
    animation: fadeUp 0.3s ease both;
    transition: border-color 0.2s, transform 0.2s;
  }
  .pkg-card:hover { border-color: var(--accent); transform: translateX(4px); }
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .pkg-card:nth-child(1) { animation-delay: 0.05s; }
  .pkg-card:nth-child(2) { animation-delay: 0.10s; }
  .pkg-card:nth-child(3) { animation-delay: 0.15s; }
  .pkg-card:nth-child(4) { animation-delay: 0.20s; }
  .pkg-card:nth-child(5) { animation-delay: 0.25s; }
 
  .pkg-track {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.1rem;
    letter-spacing: 2px;
    color: var(--accent);
    margin-bottom: 6px;
  }
  .pkg-meta { display: flex; gap: 20px; flex-wrap: wrap; }
  .pkg-meta span { font-size: 0.85rem; color: var(--muted); }
  .pkg-meta span strong { color: var(--text); font-weight: 500; }
  .pkg-price {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.6rem;
    letter-spacing: 1px;
    color: var(--text);
    text-align: right;
    white-space: nowrap;
  }
  .pkg-price small {
    display: block;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.7rem;
    color: var(--muted);
    font-weight: 400;
    letter-spacing: 0;
    margin-top: -2px;
  }
 
  .empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
  }
  .empty .icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.4; }
  .empty p { font-size: 0.95rem; }
 
  footer {
    margin-top: 80px;
    border-top: 1px solid var(--border);
    padding-top: 20px;
    color: var(--muted);
    font-size: 0.8rem;
    text-align: center;
  }
</style>
</head>
<body>
<div class="container">
  <header>
    <div class="logo">КАРГО<span>.</span>МН</div>
    <nav>
      <a href="login.php">Нэвтрэх →</a>
    </nav>
  </header>
 
  <div class="hero">
    <h1>ИЛГЭЭМЖЭЭ<em>ХЯНАХ</em></h1>
    <p>Трек код эсвэл утасны дугаараар хайж, илгээмжийнхээ мэдээллийг авна уу.</p>
  </div>
 
  <form method="get" action="index.php">
    <div class="search-wrap">
      <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Трек код эсвэл утасны дугаар...">
      <button type="submit">ХАЙХ</button>
    </div>
  </form>
 
  <?php if ($query !== ""): ?>
    <div class="status">
      <span class="count"><?= $message ?></span>
      <?php if ($packages): ?>
        <span class="total">Нийт дүн: <strong>₮<?= number_format($totalPrice, 0, '.', ',') ?></strong></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
 
  <?php if ($packages): ?>
    <div class="packages">
      <?php foreach ($packages as $p): ?>
        <div class="pkg-card">
          <div>
            <div class="pkg-track"><?= htmlspecialchars($p["trackCode"]) ?></div>
            <div class="pkg-meta">
              <span>Утас: <strong><?= htmlspecialchars($p["phoneNumber"]) ?></strong></span>
              <span>Огноо: <strong><?= $p["createdAt"] ?></strong></span>
            </div>
          </div>
          <div class="pkg-price">
            ₮<?= number_format((float)$p["price"], 0, '.', ',') ?>
            <small>ҮНЭ</small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif ($query !== ""): ?>
    <div class="empty">
      <div class="icon">📦</div>
      <p>Тохирох илгээмж олдсонгүй.<br>Трек код эсвэл утасны дугаараа шалгана уу.</p>
    </div>
  <?php else: ?>
    <div class="empty">
      <div class="icon">🔍</div>
      <p>Дээрх талбарт трек код эсвэл утасны дугаараа бичнэ үү.</p>
    </div>
  <?php endif; ?>
 
  <footer>© 2026 Карго.МН — Бүх эрх хуулиар хамгаалагдсан</footer>
</div>
</body>
</html>
