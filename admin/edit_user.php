<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Invalid user ID.");

$msg = "";
$error = "";

// Fetch user
$stmt = $pdo->prepare("SELECT userID, full_name, email, role FROM users WHERE userID = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) die("User not found.");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $full_name = trim($_POST["full_name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $role = trim($_POST["role"] ?? "");
  $new_password = (string)($_POST["new_password"] ?? "");

  if ($full_name === "" || $email === "" || $role === "") {
    $error = "Full name, email, and role are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Enter a valid email address.";
  } elseif (!in_array($role, ["ADMIN", "LECTURER", "STUDENT"], true)) {
    $error = "Invalid role selected.";
  } else {
    // Check email uniqueness (exclude current user)
    $check = $pdo->prepare("SELECT userID FROM users WHERE email = ? AND userID <> ? LIMIT 1");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
      $error = "Email already exists for another user.";
    } else {
      // Update profile fields
      $upd = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE userID=? LIMIT 1");
      $upd->execute([$full_name, $email, $role, $id]);

      //  If password provided, hash and update
      if (trim($new_password) !== "") {
        if (strlen($new_password) < 4) {
          $error = "Password must be at least 4 characters.";
        } else {
          $hash = password_hash($new_password, PASSWORD_DEFAULT);
          $pupd = $pdo->prepare("UPDATE users SET password=? WHERE userID=? LIMIT 1");
          $pupd->execute([$hash, $id]);
        }
      }

      if ($error === "") {
        $msg = "User updated successfully!";
        // Refresh user data
        $stmt->execute([$id]);
        $user = $stmt->fetch();
      }
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
      <li><a href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
      <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
      <li><a href="<?= $base ?>/admin/assign_lecturers.php">🧑‍🏫 Assign Lecturers</a></li>
      <li><a href="<?= $base ?>/admin/enroll_students.php">🧾 Enroll Students</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Edit User</h2>
          <p>Update user details and optionally change password (hashed).</p>
        </div>
        <div>
          <a class="btn" href="<?= $base ?>/admin/manage_users.php">← Back</a>
        </div>
      </div>

      <?php if ($msg): ?>
        <p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p>
      <?php endif; ?>

      <?php if ($error): ?>
        <p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="post" class="filters" style="margin-top:12px;">
        <input type="text" name="full_name" placeholder="Full Name" required
               value="<?= htmlspecialchars($user["full_name"]) ?>">

        <input type="email" name="email" placeholder="Email" required
               value="<?= htmlspecialchars($user["email"]) ?>">

        <select name="role" required>
          <option value="STUDENT"  <?= $user["role"]==="STUDENT" ? "selected" : "" ?>>STUDENT</option>
          <option value="LECTURER" <?= $user["role"]==="LECTURER" ? "selected" : "" ?>>LECTURER</option>
          <option value="ADMIN"    <?= $user["role"]==="ADMIN" ? "selected" : "" ?>>ADMIN</option>
        </select>

        <input type="password" name="new_password" placeholder="New Password (leave blank to keep current)">

        <button class="btn" type="submit">Save Changes</button>
      </form>

      <div style="margin-top:12px;color:#6b7280;font-size:13px;">
        Passwords are stored securely using <b>password_hash()</b>.
      </div>
    </div>
  </main>
</div>

</body>
</html>
