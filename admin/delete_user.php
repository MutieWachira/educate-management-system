<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$userId = (int)($_GET["id"] ?? 0);
if ($userId <= 0) {
  http_response_code(400);
  die("Invalid user ID.");
}

// Prevent deleting yourself (recommended)
$loggedInId = (int)($_SESSION["user"]["user_id"] ?? 0);
if ($userId === $loggedInId) {
  die("You cannot delete your own admin account while logged in.");
}

// Fetch user for confirmation
$stmt = $pdo->prepare("SELECT userID, full_name, email, role FROM users WHERE userID = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
  http_response_code(404);
  die("User not found.");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Delete
  $del = $pdo->prepare("DELETE FROM users WHERE userID = ? LIMIT 1");
  $del->execute([$userId]);

  header("Location: {$base}/admin/manage_users.php");
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Delete User</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/manage_users.css">
</head>
<body>

  <div class="topbar">
    <div class="brand">
      <div class="brand-badge">AC</div>
      <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Admin Panel</span></div>
    </div>
    <div class="user">
      <div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • ADMIN</div>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <h4>Navigation</h4>
      <ul class="nav">
        <li><a href="<?= $base ?>/admin/dashboard.php">🏠 Dashboard</a></li>
        <li><a class="active" href="<?= $base ?>/admin/manage_users.php">👤 Manage Users</a></li>
        <li><a href="<?= $base ?>/admin/create_user.php">➕ Create User</a></li>
      </ul>
    </aside>

    <main class="content">
      <div class="card">
        <div class="header">
          <div>
            <h2>Confirm Delete</h2>
            <p>This action cannot be undone.</p>
          </div>
        </div>

        <p><b>User:</b> <?= htmlspecialchars($user["full_name"]) ?></p>
        <p><b>Email:</b> <?= htmlspecialchars($user["email"]) ?></p>
        <p><b>Role:</b> <?= htmlspecialchars($user["role"]) ?></p>

        <form method="post" style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
          <button class="btn" type="submit" style="background:rgba(239,68,68,0.12);color:#b91c1c;border-color:rgba(239,68,68,0.25);">
            Confirm Delete
          </button>
          <a class="back" href="<?= $base ?>/admin/manage_users.php">Cancel</a>
        </form>
      </div>
    </main>
  </div>

</body>
</html>
