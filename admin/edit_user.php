<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$userId = (int)($_GET["id"] ?? 0);
if ($userId <= 0) {
  http_response_code(400);
  die("Invalid user ID.");
}

// Fetch user
$stmt = $pdo->prepare("SELECT userID, full_name, email, role FROM users WHERE userID = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
  http_response_code(404);
  die("User not found.");
}

$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $full_name = trim($_POST["full_name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $role = trim($_POST["role"] ?? "");
  $new_password = trim($_POST["new_password"] ?? "");

  if ($full_name === "" || $email === "" || $role === "") {
    $error = "Full name, email and role are required.";
  } elseif (!in_array($role, ["ADMIN", "LECTURER", "STUDENT"], true)) {
    $error = "Invalid role selected.";
  } else {
    // Ensure email is not taken by another user
    $check = $pdo->prepare("SELECT userID FROM users WHERE email = ? AND userID <> ? LIMIT 1");
    $check->execute([$email, $userId]);
    if ($check->fetch()) {
      $error = "That email is already used by another account.";
    } else {
      // Update with or without password
      if ($new_password !== "") {
        if (strlen($new_password) < 4) {
          $error = "New password must be at least 4 characters.";
        } else {
          $upd = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, password=? WHERE userID=?");
          $upd->execute([$full_name, $email, $role, $new_password, $userId]);
          $msg = "User updated (including password).";
        }
      } else {
        $upd = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE userID=?");
        $upd->execute([$full_name, $email, $role, $userId]);
        $msg = "User updated.";
      }

      // Refresh user data for form display
      $stmt = $pdo->prepare("SELECT userID, full_name, email, role FROM users WHERE userID = ? LIMIT 1");
      $stmt->execute([$userId]);
      $user = $stmt->fetch();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit User</title>
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
            <h2>Edit User</h2>
            <p>Update account details. Leave password empty to keep current password.</p>
          </div>
        </div>

        <?php if ($msg): ?>
          <p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
          <p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" class="filters" style="margin-top:10px;">
          <input type="text" name="full_name" placeholder="Full name" value="<?= htmlspecialchars($user["full_name"]) ?>" required>
          <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($user["email"]) ?>" required>

          <select name="role" required>
            <option value="ADMIN" <?= $user["role"]==="ADMIN" ? "selected" : "" ?>>ADMIN</option>
            <option value="LECTURER" <?= $user["role"]==="LECTURER" ? "selected" : "" ?>>LECTURER</option>
            <option value="STUDENT" <?= $user["role"]==="STUDENT" ? "selected" : "" ?>>STUDENT</option>
          </select>

          <input type="text" name="new_password" placeholder="New password (optional)">

          <button class="btn" type="submit">Save Changes</button>
          <a class="back" href="<?= $base ?>/admin/manage_users.php" style="display:inline-flex;align-items:center;">Cancel</a>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
