<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system"; // change if your folder name differs

// Filters
$q = trim($_GET["q"] ?? "");
$role = trim($_GET["role"] ?? "");

// Build query (safe prepared statements)
$sql = "SELECT userID, full_name, email, role FROM users WHERE 1=1";
$params = [];

if ($q !== "") {
  $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

if (in_array($role, ["ADMIN", "LECTURER", "STUDENT"], true)) {
  $sql .= " AND role = ?";
  $params[] = $role;
}

$sql .= " ORDER BY userID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

function roleBadgeClass(string $r): string {
  $r = strtoupper($r);
  if ($r === "ADMIN") return "admin";
  if ($r === "LECTURER") return "lecturer";
  return "student";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Users</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/manage_users.css">
</head>
<body>

  <!-- Topbar -->
  <div class="topbar">
    <div class="brand">
      <div class="brand-badge">AC</div>
      <div>
        Academic Collaboration System<br>
        <span style="font-size:12px;opacity:.85;">Admin Panel</span>
      </div>
    </div>

    <div class="user">
      <div class="pill">
        <?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • ADMIN
      </div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
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

    <!-- Content -->
    <main class="content">
      <div class="card">
        <div class="header">
          <div>
            <h2>Manage Users</h2>
            <p>Search and view accounts in the system.</p>
          </div>
          <div>
            <a class="btn" href="<?= $base ?>/admin/create_user.php">+ Create User</a>
          </div>
        </div>

        <form class="filters" method="get">
          <input type="text" name="q" placeholder="Search name or email..." value="<?= htmlspecialchars($q) ?>">
          <select name="role">
            <option value="">All Roles</option>
            <option value="ADMIN" <?= $role==="ADMIN" ? "selected" : "" ?>>ADMIN</option>
            <option value="LECTURER" <?= $role==="LECTURER" ? "selected" : "" ?>>LECTURER</option>
            <option value="STUDENT" <?= $role==="STUDENT" ? "selected" : "" ?>>STUDENT</option>
          </select>
          <button class="btn" type="submit">Search</button>
        </form>

        <div class="table-wrap">
          <table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Full Name</th>
      <th>Email</th>
      <th>Role</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$users): ?>
      <tr><td colspan="5">No users found.</td></tr>
    <?php else: ?>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= (int)$u["userID"] ?></td>
          <td><?= htmlspecialchars($u["full_name"]) ?></td>
          <td><?= htmlspecialchars($u["email"]) ?></td>
          <td>
            <span class="badge <?= roleBadgeClass((string)$u["role"]) ?>">
              <?= htmlspecialchars($u["role"]) ?>
            </span>
          </td>
          <td>
            <div class="action-links">
              <a class="action-link" href="<?= $base ?>/admin/edit_user.php?id=<?= (int)$u["userID"] ?>">Edit</a>
              <a class="action-link danger" href="<?= $base ?>/admin/delete_user.php?id=<?= (int)$u["userID"] ?>">Delete</a>
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
