<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$q = trim($_GET["q"] ?? "");
$action = trim($_GET["action"] ?? "");
$role = trim($_GET["role"] ?? "");

$sql = "
  SELECT l.log_id, l.action, l.details, l.role, l.ip_address, l.created_at,
         u.full_name, u.email
  FROM activity_logs l
  LEFT JOIN users u ON u.userID = l.user_id
  WHERE 1=1
";
$params = [];

if ($q !== "") {
  $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR l.details LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

if ($action !== "") {
  $sql .= " AND l.action = ?";
  $params[] = $action;
}

if (in_array($role, ["ADMIN","LECTURER","STUDENT"], true)) {
  $sql .= " AND l.role = ?";
  $params[] = $role;
}

$sql .= " ORDER BY l.log_id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// For dropdown
$actionsStmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Activity Logs</title>
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
        <li><a href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
        <li><a class="active" href="<?= $base ?>/admin/activity_logs.php">🧾 Activity Logs</a></li>
        <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
        <li><a href="<?= $base ?>/admin/profile.php">👤 Profile</a></li>
        <li><a href="<?= $base ?>/admin/assign_lecturers.php">🧑‍🏫 Assign Lecturers</a></li>
        <li><a href="<?= $base ?>/admin/enroll_students.php">🧾 Enroll Students</a></li>
        <li><a href="<?= $base ?>/auth/logout.php">🚪 Log Out</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Activity Logs</h2>
          <p>Tracks logins, updates, uploads, submissions, grading and admin actions.</p>
        </div>
      </div>

      <form class="filters" method="get">
        <input type="text" name="q" placeholder="Search name/email/details..." value="<?= htmlspecialchars($q) ?>">
        <select name="role">
          <option value="">All Roles</option>
          <option value="ADMIN" <?= $role==="ADMIN"?"selected":"" ?>>ADMIN</option>
          <option value="LECTURER" <?= $role==="LECTURER"?"selected":"" ?>>LECTURER</option>
          <option value="STUDENT" <?= $role==="STUDENT"?"selected":"" ?>>STUDENT</option>
        </select>

        <select name="action">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= htmlspecialchars((string)$a) ?>" <?= $action===$a?"selected":"" ?>>
              <?= htmlspecialchars((string)$a) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Filter</button>
      </form>

      <div class="table-wrap" style="margin-top:14px;">
        <table>
          <thead>
            <tr>
              <th>Time</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Details</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$logs): ?>
            <tr><td colspan="6">No logs found.</td></tr>
          <?php else: foreach ($logs as $l): ?>
            <tr>
              <td><?= htmlspecialchars((string)$l["created_at"]) ?></td>
              <td>
                <?= htmlspecialchars((string)($l["full_name"] ?? "Deleted/Unknown User")) ?><br>
                <span style="color:#6b7280;font-size:13px;"><?= htmlspecialchars((string)($l["email"] ?? "")) ?></span>
              </td>
              <td><?= htmlspecialchars((string)($l["role"] ?? "-")) ?></td>
              <td><b><?= htmlspecialchars((string)$l["action"]) ?></b></td>
              <td style="color:#374151;"><?= nl2br(htmlspecialchars((string)($l["details"] ?? ""))) ?></td>
              <td><?= htmlspecialchars((string)($l["ip_address"] ?? "-")) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>

</body>
</html>