<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Invalid department.");

$stmt = $pdo->prepare("SELECT department_id, name FROM departments WHERE department_id = ? LIMIT 1");
$stmt->execute([$id]);
$dept = $stmt->fetch();
if (!$dept) die("Department not found.");

$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST["name"] ?? "");
  if ($name === "") {
    $error = "Department name is required.";
  } else {
    $check = $pdo->prepare("SELECT department_id FROM departments WHERE name = ? AND department_id <> ? LIMIT 1");
    $check->execute([$name, $id]);
    if ($check->fetch()) {
      $error = "That department name already exists.";
    } else {
      $upd = $pdo->prepare("UPDATE departments SET name = ? WHERE department_id = ?");
      $upd->execute([$name, $id]);
      $msg = "Department updated.";

      $stmt->execute([$id]);
      $dept = $stmt->fetch();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Department</title>
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
      <li><a class="active" href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
      <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Edit Department</h2>
          <p>Update department details.</p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post">
        <input type="text" name="name" value="<?= htmlspecialchars($dept["name"]) ?>" required>
        <button class="btn" type="submit">Save</button>
        <a class="back" href="<?= $base ?>/admin/manage_departments.php">Cancel</a>
      </form>
    </div>
  </main>
</div>

</body>
</html>
