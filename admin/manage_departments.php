<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$msg = "";
$error = "";

// Add department
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add") {
  $name = trim($_POST["name"] ?? "");

  if ($name === "") {
    $error = "Department name is required.";
  } else {
    // Check duplicate
    $check = $pdo->prepare("SELECT department_id FROM departments WHERE name = ? LIMIT 1");
    $check->execute([$name]);
    if ($check->fetch()) {
      $error = "That department already exists.";
    } else {
      $ins = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
      $ins->execute([$name]);
      $msg = "Department added successfully.";
    }
  }
}

// Delete department
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
  $deptId = (int)($_POST["department_id"] ?? 0);

  if ($deptId > 0) {
    try {
      $del = $pdo->prepare("DELETE FROM departments WHERE department_id = ? LIMIT 1");
      $del->execute([$deptId]);
      $msg = "Department deleted.";
    } catch (Throwable $e) {
      // Usually fails if courses exist due to FK restriction
      $error = "Cannot delete department. Delete/move its courses first.";
    }
  }
}

// Fetch departments
$departments = $pdo->query("SELECT department_id, name FROM departments ORDER BY name ASC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Departments</title>
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
      <li><a href="<?= $base ?>/admin/create_user.php">➕ Create User</a></li>
      <li><a class="active" href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
      <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Manage Departments</h2>
          <p>Create and manage departments.</p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <!-- Add Department -->
      <form class="filters" method="post">
        <input type="hidden" name="action" value="add">
        <input type="text" name="name" placeholder="Department name e.g. Computer Science" required>
        <button class="btn" type="submit">Add Department</button>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Department Name</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$departments): ?>
            <tr><td colspan="3">No departments added yet.</td></tr>
          <?php else: ?>
            <?php foreach ($departments as $d): ?>
              <tr>
                <td><?= (int)$d["department_id"] ?></td>
                <td><?= htmlspecialchars($d["name"]) ?></td>
                <td>
                  <div class="action-links">
                    <a class="action-link" href="<?= $base ?>/admin/edit_department.php?id=<?= (int)$d["department_id"] ?>">Edit</a>

                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="department_id" value="<?= (int)$d["department_id"] ?>">
                      <button class="action-link danger" type="submit" style="cursor:pointer;">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="footer-row">
        <a class="back" href="<?= $base ?>/admin/dashboard.php">← Back to Dashboard</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
