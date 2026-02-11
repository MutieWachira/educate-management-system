<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $full_name = trim($_POST["full_name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $role = trim($_POST["role"] ?? "");
  $password = trim($_POST["password"] ?? "");

  if ($full_name === "" || $email === "" || $password === "" || $role === "") {
    $error = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Enter a valid email address.";
  } elseif (!in_array($role, ["ADMIN", "LECTURER", "STUDENT"], true)) {
    $error = "Invalid role selected.";
  } elseif (strlen($password) < 4) {
    $error = "Password must be at least 4 characters.";
  } else {
    $check = $pdo->prepare("SELECT userID FROM users WHERE email = ? LIMIT 1");
    $check->execute([$email]);

    if ($check->fetch()) {
      $error = "Email already exists.";
    } else {
      // ✅ Plain-text password storage (as requested)
      $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
      $stmt->execute([$full_name, $email, $password, $role]);
      $msg = "User created successfully!";
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create User</title>
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
      <li><a href="<?= $base ?>/admin/manage_users.php">👤 Manage Users</a></li>
      <li><a class="active" href="<?= $base ?>/admin/create_user.php">➕ Create User</a></li>

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
          <h2>Create User</h2>
          <p>Add a new Admin, Lecturer, or Student account.</p>
        </div>
      </div>

      <?php if ($msg): ?>
        <p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="post" class="filters" style="margin-top:10px;">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email Address" required>

        <select name="role" required>
          <option value="">Select Role</option>
          <option value="STUDENT">STUDENT</option>
          <option value="LECTURER">LECTURER</option>
          <option value="ADMIN">ADMIN</option>
        </select>

        <input type="text" name="password" placeholder="Temporary Password" required>

        <button class="btn" type="submit">Create Account</button>
        <a class="back" href="<?= $base ?>/admin/manage_users.php">View Users</a>
      </form>
    </div>
  </main>
</div>

</body>
</html>
