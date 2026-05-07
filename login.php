<?php
session_start();
if ($_SESSION["auth"] ?? false) {
    header("Location: dashboard.php");
    exit;
}
 
$conn = new mysqli(getenv("MYSQLHOST"), getenv("MYSQLUSER"), getenv("MYSQLPASSWORD"), getenv("MYSQLDATABASE"), (int)getenv("MYSQLPORT"));
if ($conn->connect_error) die("Connection failed");
 
$error = "";
 
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
 
    $stmt = $conn->prepare("SELECT employeeID, employeeName, password FROM employees WHERE employeeName = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
 
    if ($user && $user["password"] === md5($password)) {
        $_SESSION["auth"] = true;
        $_SESSION["employeeID"] = $user["employeeID"];
        $_SESSION["employeeName"] = $user["employeeName"];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Нэвтрэх нэр эсвэл нууц үг буруу байна.";
    }
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Нэвтрэх — Карго.МН</title>
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
    --error: #ef4444;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(240,192,64,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(240,192,64,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
  }
 
  /* big decorative text */
  .deco {
    position: fixed;
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(8rem, 20vw, 18rem);
    letter-spacing: 8px;
    color: rgba(240,192,64,0.04);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    white-space: nowrap;
    pointer-events: none;
    user-select: none;
  }
 
  .card {
    position: relative;
    z-index: 1;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 48px 44px;
    width: 100%;
    max-width: 420px;
    animation: rise 0.5s ease;
  }
  @keyframes rise {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }
 
  .brand {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 2rem;
    letter-spacing: 4px;
    color: var(--accent);
    margin-bottom: 4px;
  }
  .brand span { color: var(--accent2); }
  .subtitle {
    color: var(--muted);
    font-size: 0.85rem;
    margin-bottom: 36px;
  }
 
  .field { margin-bottom: 20px; }
  .field label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
  }
  .field input {
    width: 100%;
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 13px 16px;
    font-size: 0.95rem;
    font-family: 'DM Sans', sans-serif;
    outline: none;
    transition: border-color 0.2s;
  }
  .field input:focus { border-color: var(--accent); }
 
  .error-box {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.3);
    color: var(--error);
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 0.88rem;
    margin-bottom: 20px;
  }
 
  .btn {
    width: 100%;
    padding: 14px;
    background: var(--accent);
    color: #0d0f14;
    border: none;
    border-radius: 6px;
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.1rem;
    letter-spacing: 3px;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    margin-top: 4px;
  }
  .btn:hover { background: #f8d060; }
  .btn:active { transform: scale(0.99); }
 
  .footer-links {
    margin-top: 28px;
    display: flex;
    justify-content: space-between;
    font-size: 0.83rem;
  }
  .footer-links a {
    color: var(--muted);
    text-decoration: none;
    transition: color 0.2s;
  }
  .footer-links a:hover { color: var(--accent); }
 
  .divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 28px 0 24px;
  }
  .signup-prompt {
    text-align: center;
    font-size: 0.85rem;
    color: var(--muted);
  }
  .signup-prompt a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 600;
  }
</style>
</head>
<body>
<div class="deco">CARGO</div>
<div class="card">
  <div class="brand">КАРГО<span>.</span>МН</div>
  <div class="subtitle">Ажилтны нэвтрэх хэсэг</div>
 
  <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
 
  <form method="post" action="login.php">
    <div class="field">
      <label for="username">Нэвтрэх нэр</label>
      <input id="username" type="text" name="username" value="<?= htmlspecialchars($_POST["username"] ?? "") ?>" required autocomplete="username">
    </div>
    <div class="field">
      <label for="password">Нууц үг</label>
      <input id="password" type="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn" type="submit">НЭВТРЭХ</button>
  </form>
 
  <hr class="divider">
  <div class="footer-links">
    <a href="index.php">← Хайлт руу буцах</a>
    <a href="signup.php">Бүртгүүлэх →</a>
  </div>
</div>
</body>
</html>
