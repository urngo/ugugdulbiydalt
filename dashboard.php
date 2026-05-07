<?php
session_start();
if (!($_SESSION["auth"] ?? false)) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("127.0.0.1", "guest", "pass123", "biydaalt");
if ($conn->connect_error) die("Connection failed");

$employeeName = $_SESSION["employeeName"] ?? "Ажилтан";
$employeeID   = $_SESSION["employeeID"] ?? 1;

$errors  = [];
$success = "";
$queryRows  = [];
$queryText  = "";
$sqlOutput  = "";
$sqlError   = "";

// Logout
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add_package") {
        $trackCode   = trim($_POST["trackCode"] ?? "");
        $phoneNumber = trim($_POST["phoneNumber"] ?? "");
        $shelf       = trim($_POST["shelf"] ?? "");
        $price       = (float)($_POST["price"] ?? 0);
        $status      = $_POST["status"] ?? "Pending";

        if ($trackCode && $phoneNumber) {
            $stmt = $conn->prepare(
                "INSERT INTO packages (trackCode, phoneNumber, shelf, createdBy, status, price, createdAt, isDeleted)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)"
            );
            $stmt->bind_param("sssiss", $trackCode, $phoneNumber, $shelf, $employeeID, $status, $price);
            if ($stmt->execute()) {
                $success = "✓ Илгээмж амжилттай нэмэгдлээ.";
            } else {
                $errors[] = "Алдаа: " . $conn->error;
            }
        } else {
            $errors[] = "Трек код болон утасны дугаар шаардлагатай.";
        }

    } elseif ($action === "mark_picked") {
        $packageID = (int)($_POST["packageID"] ?? 0);
        $conn->query("UPDATE packages SET isDeleted = 1 WHERE packageID = $packageID AND isDeleted = 0");
        $success = "✓ Илгээмж авагдсан гэж тэмдэглэгдлээ.";

    } elseif ($action === "pickup_by_phone") {
        $phone = trim($_POST["pickupPhone"] ?? "");
        if ($phone) {
            $stmt = $conn->prepare("UPDATE packages SET isDeleted = 1 WHERE phoneNumber = ? AND isDeleted = 0");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $success = "✓ " . $conn->affected_rows . " илгээмж авагдсан гэж тэмдэглэгдлээ.";
        }

    } elseif ($action === "query_packages") {
        $queryText = trim($_POST["queryText"] ?? "");
        $stmt = $conn->prepare(
            "SELECT packageID, trackCode, phoneNumber, shelf, status, price, isDeleted, createdAt
             FROM packages
             WHERE trackCode LIKE ? OR phoneNumber LIKE ?
             ORDER BY createdAt DESC
             LIMIT 100"
        );
        $like = "%" . $queryText . "%";
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $queryRows[] = $row;
        if (!$queryRows) $success = "Хайлтанд тохирох илгээмж олдсонгүй.";

    } elseif ($action === "sql_query") {
        $raw = trim($_POST["queryText"] ?? "");
        try {
            $result = $conn->query($raw);
            if ($result === false) {
                $sqlError = "SQL Error: " . $conn->error;
            } elseif ($result instanceof mysqli_result) {
                $fields = $result->fetch_fields();
                $sqlOutput = "<table><thead><tr>";
                foreach ($fields as $f) $sqlOutput .= "<th>" . htmlspecialchars($f->name) . "</th>";
                $sqlOutput .= "</tr></thead><tbody>";
                while ($row = $result->fetch_assoc()) {
                    $sqlOutput .= "<tr>";
                    foreach ($row as $v) $sqlOutput .= "<td>" . htmlspecialchars($v ?? 'NULL') . "</td>";
                    $sqlOutput .= "</tr>";
                }
                $sqlOutput .= "</tbody></table>";
            } else {
                $sqlOutput = "Амжилттай. Нөлөөлсөн мөр: " . $conn->affected_rows;
            }
        } catch (Exception $e) {
            $sqlError = "Error: " . $e->getMessage();
        }
    }
}

// Stats
$stats = [];
$r = $conn->query("SELECT COUNT(*) as total, SUM(price) as revenue FROM packages WHERE isDeleted = 0");
$row = $r->fetch_assoc();
$stats["active"]   = $row["total"] ?? 0;
$stats["revenue"]  = $row["revenue"] ?? 0;

$r2 = $conn->query("SELECT COUNT(*) as c FROM packages WHERE isDeleted = 1");
$stats["picked"]   = $r2->fetch_assoc()["c"] ?? 0;

$r3 = $conn->query("SELECT COUNT(*) as c FROM packages WHERE isDeleted = 0 AND (status = 'Pending' OR status IS NULL)");
$stats["pending"]  = $r3->fetch_assoc()["c"] ?? 0;
?>
<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Карго.МН</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0d0f14;
    --surface: #161920;
    --surface2: #1a1d25;
    --border: #252830;
    --accent: #f0c040;
    --accent2: #e05a20;
    --text: #e8eaf0;
    --muted: #6b7280;
    --success: #22c55e;
    --error: #ef4444;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
  }
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(240,192,64,0.025) 1px, transparent 1px),
      linear-gradient(90deg, rgba(240,192,64,0.025) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
  }

  /* LAYOUT */
  .layout { display: flex; min-height: 100vh; position: relative; z-index: 1; }

  /* SIDEBAR */
  .sidebar {
    width: 220px;
    background: var(--surface);
    border-right: 1px solid var(--border);
    padding: 32px 0;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
  }
  .sidebar-logo {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1.6rem;
    letter-spacing: 3px;
    color: var(--accent);
    padding: 0 24px 28px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
  }
  .sidebar-logo span { color: var(--accent2); }
  .sidebar-user {
    padding: 0 24px 20px;
    font-size: 0.8rem;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
  }
  .sidebar-user strong { display: block; color: var(--text); font-size: 0.9rem; margin-bottom: 2px; }

  nav.sidebar-nav { flex: 1; }
  .nav-item {
    display: block;
    padding: 11px 24px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--muted);
    text-decoration: none;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    letter-spacing: 0.03em;
    transition: color 0.2s, background 0.2s;
  }
  .nav-item:hover, .nav-item.active {
    color: var(--accent);
    background: rgba(240,192,64,0.06);
  }
  .nav-item .icon { margin-right: 10px; }

  .sidebar-bottom { padding: 20px 24px; border-top: 1px solid var(--border); }
  .logout-btn {
    display: block;
    text-align: center;
    padding: 10px 16px;
    background: transparent;
    color: var(--muted);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.82rem;
    text-decoration: none;
    transition: all 0.2s;
  }
  .logout-btn:hover { color: var(--error); border-color: var(--error); }

  /* MAIN */
  .main { margin-left: 220px; padding: 40px 36px; flex: 1; }

  /* TOPBAR */
  .topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 36px;
  }
  .page-title { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; letter-spacing: 2px; }

  /* STATS */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 36px;
  }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px 22px;
  }
  .stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted); margin-bottom: 8px; }
  .stat-value { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; letter-spacing: 1px; line-height: 1; }
  .stat-value.yellow { color: var(--accent); }
  .stat-value.orange { color: var(--accent2); }
  .stat-value.green  { color: var(--success); }

  /* PANELS */
  .panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 24px;
    overflow: hidden;
  }
  .panel-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--muted);
    font-weight: 600;
  }
  .panel-body { padding: 22px; }

  /* FORM GRID */
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .form-grid.cols3 { grid-template-columns: 1fr 1fr 1fr; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .field label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); font-weight: 600; }
  .field input, .field select {
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 0.9rem;
    font-family: 'DM Sans', sans-serif;
    outline: none;
    transition: border-color 0.2s;
  }
  .field input:focus, .field select:focus { border-color: var(--accent); }
  .field select option { background: var(--surface); }

  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border: none; border-radius: 6px; font-family: 'Bebas Neue', sans-serif; font-size: 0.95rem; letter-spacing: 2px; cursor: pointer; transition: all 0.2s; }
  .btn-primary { background: var(--accent); color: #0d0f14; }
  .btn-primary:hover { background: #f8d060; }
  .btn-danger { background: rgba(239,68,68,0.12); color: var(--error); border: 1px solid rgba(239,68,68,0.3); font-family: 'DM Sans', sans-serif; font-size: 0.8rem; font-weight: 600; letter-spacing: 0; padding: 6px 12px; }
  .btn-danger:hover { background: rgba(239,68,68,0.2); }
  .btn-search { background: var(--surface2); color: var(--accent); border: 1px solid var(--border); }
  .btn-search:hover { border-color: var(--accent); }

  .form-row { display: flex; gap: 12px; align-items: flex-end; margin-top: 0; }
  .form-row .field { flex: 1; }

  /* ALERTS */
  .alert { padding: 14px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 0.9rem; animation: fadeUp 0.3s; }
  .alert-success { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.25); color: var(--success); }
  .alert-error   { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); color: var(--error); }
  @keyframes fadeUp { from { opacity:0; transform: translateY(-6px); } to { opacity:1; transform: translateY(0); } }

  /* TABLE */
  .data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
  .data-table th { padding: 10px 14px; text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); border-bottom: 1px solid var(--border); font-weight: 600; }
  .data-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
  .data-table tr:last-child td { border-bottom: none; }
  .data-table tr:hover td { background: rgba(240,192,64,0.03); }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
  .badge-active { background: rgba(34,197,94,0.12); color: var(--success); }
  .badge-deleted { background: rgba(107,114,128,0.12); color: var(--muted); }
  .badge-pending { background: rgba(240,192,64,0.12); color: var(--accent); }
  .mono { font-family: 'Bebas Neue', sans-serif; letter-spacing: 1px; font-size: 0.9rem; color: var(--accent); }

  /* SQL output table */
  #sql-result table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
  #sql-result th { background: var(--bg); padding: 10px 12px; text-align: left; border: 1px solid var(--border); font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
  #sql-result td { padding: 9px 12px; border: 1px solid var(--border); }
  #sql-result tr:nth-child(even) td { background: rgba(255,255,255,0.015); }

  .section { display: none; }
  .section.active { display: block; }

  @media (max-width: 900px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .form-grid, .form-grid.cols3 { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">КАРГО<span>.</span>МН</div>
    <div class="sidebar-user">
      <strong><?= htmlspecialchars($employeeName) ?></strong>
      Ажилтан
    </div>
    <nav class="sidebar-nav">
      <button class="nav-item active" onclick="showSection('overview')"><span class="icon">📊</span>Тойм</button>
      <button class="nav-item" onclick="showSection('add')"><span class="icon">📦</span>Нэмэх</button>
      <button class="nav-item" onclick="showSection('pickup')"><span class="icon">✅</span>Авах</button>
      <button class="nav-item" onclick="showSection('search')"><span class="icon">🔍</span>Хайх</button>
      <button class="nav-item" onclick="showSection('sql')"><span class="icon">🗃️</span>SQL</button>
    </nav>
    <div class="sidebar-bottom">
      <a href="index.php" class="logout-btn" style="margin-bottom:8px; display:block; text-align:center; text-decoration:none; color:var(--muted); font-size:0.82rem;">← Хайлт руу</a>
      <a href="?logout=1" class="logout-btn">Гарах</a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="topbar">
      <div class="page-title" id="page-title">ТОЙМ</div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <!-- OVERVIEW -->
    <div class="section active" id="section-overview">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Идэвхтэй илгээмж</div>
          <div class="stat-value yellow"><?= $stats["active"] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Хүлээгдэж буй</div>
          <div class="stat-value orange"><?= $stats["pending"] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Авагдсан</div>
          <div class="stat-value green"><?= $stats["picked"] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Нийт орлого</div>
          <div class="stat-value" style="font-size:1.5rem; color:var(--text)">₮<?= number_format($stats["revenue"], 0, '.', ',') ?></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">Сүүлийн 10 илгээмж</div>
        <div class="panel-body" style="padding:0;">
          <?php
            $recent = $conn->query("SELECT packageID, trackCode, phoneNumber, shelf, status, price, isDeleted, createdAt FROM packages ORDER BY createdAt DESC LIMIT 10");
          ?>
          <table class="data-table">
            <thead><tr>
              <th>Трек код</th><th>Утас</th><th>Shelf</th><th>Статус</th><th>Үнэ</th><th>Огноо</th><th></th>
            </tr></thead>
            <tbody>
            <?php while ($row = $recent->fetch_assoc()): ?>
              <tr>
                <td class="mono"><?= htmlspecialchars($row["trackCode"]) ?></td>
                <td><?= htmlspecialchars($row["phoneNumber"]) ?></td>
                <td><?= htmlspecialchars($row["shelf"] ?? "—") ?></td>
                <td>
                  <?php if ($row["isDeleted"]): ?>
                    <span class="badge badge-deleted">Авагдсан</span>
                  <?php elseif ($row["status"] === "Arrived"): ?>
                    <span class="badge badge-active">Ирсэн</span>
                  <?php else: ?>
                    <span class="badge badge-pending">Хүлээгдэж буй</span>
                  <?php endif; ?>
                </td>
                <td>₮<?= number_format((float)$row["price"], 0, '.', ',') ?></td>
                <td style="color:var(--muted); font-size:0.82rem;"><?= $row["createdAt"] ?></td>
                <td>
                  <?php if (!$row["isDeleted"]): ?>
                    <form method="post" style="margin:0;">
                      <input type="hidden" name="action" value="mark_picked">
                      <input type="hidden" name="packageID" value="<?= $row["packageID"] ?>">
                      <button type="submit" class="btn btn-danger">Авагдсан</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ADD PACKAGE -->
    <div class="section" id="section-add">
      <div class="panel">
        <div class="panel-header">Шинэ илгээмж нэмэх</div>
        <div class="panel-body">
          <form method="post">
            <input type="hidden" name="action" value="add_package">
            <div class="form-grid cols3" style="margin-bottom:16px;">
              <div class="field">
                <label>Трек код</label>
                <input type="text" name="trackCode" maxlength="40" required placeholder="SF1234567890">
              </div>
              <div class="field">
                <label>Утасны дугаар</label>
                <input type="text" name="phoneNumber" required placeholder="99112233">
              </div>
              <div class="field">
                <label>Shelf</label>
                <input type="text" name="shelf" maxlength="50" placeholder="A-1">
              </div>
              <div class="field">
                <label>Үнэ (₮)</label>
                <input type="number" name="price" min="0" step="0.01" required placeholder="0">
              </div>
              <div class="field">
                <label>Статус</label>
                <select name="status">
                  <option value="Pending">Pending</option>
                  <option value="Arrived">Arrived</option>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">ХАДГАЛАХ</button>
          </form>
        </div>
      </div>
    </div>

    <!-- PICKUP -->
    <div class="section" id="section-pickup">
      <div class="panel">
        <div class="panel-header">Утасны дугаараар авах</div>
        <div class="panel-body">
          <p style="color:var(--muted); font-size:0.875rem; margin-bottom:20px;">Тухайн утасны дугаартай бүх идэвхтэй илгээмжийг авагдсан болгоно.</p>
          <form method="post">
            <input type="hidden" name="action" value="pickup_by_phone">
            <div class="form-row">
              <div class="field">
                <label>Утасны дугаар</label>
                <input type="text" name="pickupPhone" required placeholder="99112233">
              </div>
              <button type="submit" class="btn btn-primary">ТЭМДЭГЛЭХ</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- SEARCH -->
    <div class="section" id="section-search">
      <div class="panel">
        <div class="panel-header">Илгээмж хайх</div>
        <div class="panel-body" style="padding-bottom:0;">
          <form method="post">
            <input type="hidden" name="action" value="query_packages">
            <div class="form-row" style="margin-bottom:20px;">
              <div class="field">
                <label>Трек код эсвэл утасны дугаар</label>
                <input type="text" name="queryText" value="<?= htmlspecialchars($queryText) ?>" required placeholder="Хайх утга...">
              </div>
              <button type="submit" class="btn btn-search">ХАЙХ</button>
            </div>
          </form>
        </div>
        <?php if ($queryRows): ?>
          <table class="data-table">
            <thead><tr>
              <th>ID</th><th>Трек код</th><th>Утас</th><th>Shelf</th><th>Үнэ</th><th>Статус</th><th>Огноо</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($queryRows as $row): ?>
              <tr>
                <td style="color:var(--muted); font-size:0.82rem;">#<?= $row["packageID"] ?></td>
                <td class="mono"><?= htmlspecialchars($row["trackCode"]) ?></td>
                <td><?= htmlspecialchars($row["phoneNumber"]) ?></td>
                <td><?= htmlspecialchars($row["shelf"] ?? "—") ?></td>
                <td>₮<?= number_format((float)$row["price"], 0, '.', ',') ?></td>
                <td>
                  <?php if ($row["isDeleted"]): ?>
                    <span class="badge badge-deleted">Авагдсан</span>
                  <?php else: ?>
                    <span class="badge badge-active"><?= htmlspecialchars($row["status"] ?? "Active") ?></span>
                  <?php endif; ?>
                </td>
                <td style="color:var(--muted); font-size:0.82rem;"><?= $row["createdAt"] ?></td>
                <td>
                  <?php if (!$row["isDeleted"]): ?>
                    <form method="post" style="margin:0;">
                      <input type="hidden" name="action" value="mark_picked">
                      <input type="hidden" name="packageID" value="<?= $row["packageID"] ?>">
                      <button type="submit" class="btn btn-danger">Авагдсан</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- SQL -->
    <div class="section" id="section-sql">
      <div class="panel">
        <div class="panel-header">SQL Query</div>
        <div class="panel-body">
          <form method="post">
            <input type="hidden" name="action" value="sql_query">
            <div class="form-row" style="margin-bottom:16px;">
              <div class="field">
                <label>SQL команд</label>
                <input type="text" name="queryText" required placeholder="SELECT * FROM packages LIMIT 10">
              </div>
              <button type="submit" class="btn btn-search">АЖИЛЛУУЛАХ</button>
            </div>
          </form>
          <?php if ($sqlError): ?>
            <div class="alert alert-error" style="margin-top:12px;"><?= htmlspecialchars($sqlError) ?></div>
          <?php endif; ?>
          <?php if ($sqlOutput): ?>
            <div id="sql-result" style="margin-top:16px; overflow-x:auto;"><?= $sqlOutput ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
const titles = {
  overview: 'ТОЙМ',
  add: 'НЭМЭХ',
  pickup: 'АВАХ',
  search: 'ХАЙХ',
  sql: 'SQL QUERY'
};

function showSection(id) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('section-' + id).classList.add('active');
  document.querySelectorAll('.nav-item')[
    ['overview','add','pickup','search','sql'].indexOf(id)
  ].classList.add('active');
  document.getElementById('page-title').textContent = titles[id];
}

<?php
// Auto-navigate to correct section based on action
$actionMap = ['add_package'=>'add','mark_picked'=>'search','pickup_by_phone'=>'pickup','query_packages'=>'search','sql_query'=>'sql'];
$postAction = $_POST['action'] ?? '';
$targetSection = $actionMap[$postAction] ?? 'overview';
if ($postAction) echo "showSection('$targetSection');";
?>
</script>
</body>
</html>
