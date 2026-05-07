<?php
session_start();
if ($_SESSION["auth"] ?? false) {
    header("Location: dashboard.php");
    exit;
}

$conn = new mysqli("127.0.0.1", "guest", "pass123", "biydaalt");
if ($conn->connect_error) die("Connection failed");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";

    if (strlen($username) < 3) {
        $error = "Нэвтрэх нэр хамгийн багадаа 3 тэмдэгт байх ёстой.";
    } elseif (strlen($password) < 6) {
        $error = "Нууц үг хамгийн багадаа 6 тэмдэгт байх ёстой.";
    } elseif ($password !== $confirm) {
        $error = "Нууц үгнүүд таарахгүй байна.";
    } else {
        // Check duplicate
        $check = $conn->prepare("SELECT employeeID FROM employees WHERE employeeName = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = "Энэ нэвтрэх нэр аль хэдийн бүртгэлтэй байна.";
        } else {
            $hashed = md5($password);
            $stmt = $conn->prepare("INSERT INTO employees (employeeName, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashed);
            if ($stmt->execute()) {
                $success = "Бүртгэл амжилттай! Нэвтрэх хуудас руу шилжиж байна...";
                header("Refresh: 2; URL=login.php");
            } else {
                $error = "Бүртгэхэд алдаа гарлаа. Дахин оролдоно уу.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Бүртгүүлэх — Карго.МН</title>
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
    --success: #22c55e;
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
  .deco {
    position: fixed;
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(8rem, 20vw, 18rem);
    color: rgba(224,90,32,0.04);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    white-space: nowrap;
    pointer-events: none;
    user-select: none;
    letter-spacing: 8px;
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
  .brand { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; letter-spacing: 4px; color: var(--accent); margin-bottom: 4px; }
  .brand span { color: var(--accent2); }
  .subtitle { color: var(--muted); font-size: 0.85rem; margin-bottom: 36px; }

  .field { margin-bottom: 20px; }
  .field label { display: block; font-size: 0.78rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
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

  .hint { font-size: 0.75rem; color: var(--muted); margin-top: 5px; }

  .error-box { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); color: var(--error); padding: 12px 16px; border-radius: 6px; font-size: 0.88rem; margin-bottom: 20px; }
  .success-box { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.3); color: var(--success); padding: 12px 16px; border-radius: 6px; font-size: 0.88rem; margin-bottom: 20px; }

  .btn { width: 100%; padding: 14px; background: var(--accent2); color: #fff; border: none; border-radius: 6px; font-family: 'Bebas Neue', sans-serif; font-size: 1.1rem; letter-spacing: 3px; cursor: pointer; transition: background 0.2s, transform 0.1s; margin-top: 4px; }
  .btn:hover { background: #f06830; }
  .btn:active { transform: scale(0.99); }

  hr { border: none; border-top: 1px solid var(--border); margin: 28px 0 24px; }
  .footer-links { display: flex; justify-content: space-between; font-size: 0.83rem; }
  .footer-links a { color: var(--muted); text-decoration: none; transition: color 0.2s; }
  .footer-links a:hover { color: var(--accent); }
</style>
</head>
<body>
<div class="deco">CARGO</div>
<div class="card">
  <div class="brand">КАРГО<span>.</span>МН</div>
  <div class="subtitle">Шинэ ажилтан бүртгүүлэх</div>

  <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="success-box"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="post" action="signup.php">
    <div class="field">
      <label for="username">Нэвтрэх нэр</label>
      <input id="username" type="text" name="username" value="<?= htmlspecialchars($_POST["username"] ?? "") ?>" required autocomplete="username" minlength="3">
      <div class="hint">Хамгийн багадаа 3 тэмдэгт</div>
    </div>
    <div class="field">
      <label for="password">Нууц үг</label>
      <input id="password" type="password" name="password" required autocomplete="new-password" minlength="6">
      <div class="hint">Хамгийн багадаа 6 тэмдэгт</div>
    </div>
    <div class="field">
      <label for="confirm">Нууц үг давтах</label>
      <input id="confirm" type="password" name="confirm" required autocomplete="new-password">
    </div>
    <button class="btn" type="submit">БҮРТГҮҮЛЭХ</button>
  </form>
  <?php endif; ?>

  <hr>
  <div class="footer-links">
    <a href="index.php">← Хайлт руу буцах</a>
    <a href="login.php">Нэвтрэх →</a>
  </div>
</div>
</body>
</html>
