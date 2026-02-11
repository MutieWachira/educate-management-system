<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

// Stats (users)
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='STUDENT'")->fetchColumn();
$totalLecturers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='LECTURER'")->fetchColumn();

// Courses count (optional if you have courses table)
$totalCourses = 0;
try {
  $totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
} catch (Throwable $e) {
  $totalCourses = 0; // courses table not created yet
}

// Recent users
$recentStmt = $pdo->query("SELECT userID, full_name, email, role FROM users ORDER BY userID DESC LIMIT 6");
$recentUsers = $recentStmt->fetchAll();

$base = "/education%20system"; // change if your folder name differs
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css">
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
        <li><a class="active" href="<?= $base ?>/admin/dashboard.php">🏠 Dashboard</a></li>
        <li><a href="<?= $base ?>/admin/manage_users.php">👤 Manage Users</a></li>
        <li><a href="<?= $base ?>/admin/create_user.php">➕ Create User</a></li>
        <li><a href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
        <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
        <li><a href="<?= $base ?>/admin/assign_lecturers.php">🧑‍🏫 Assign Lecturers</a></li>
        <li><a href="<?= $base ?>/admin/enroll_students.php">🧾 Enroll Students</a></li>
      </ul>
    </aside>

    <!-- Content -->
    <main class="content">

      <div class="header">
        <div>
          <h2>Dashboard Overview</h2>
          <p>Quick summary of your system activity and shortcuts.</p>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="grid stats">
        <div class="card">
          <p class="stat-title">Total Users</p>
          <p class="stat-value"><?= $totalUsers ?></p>
          <p class="stat-sub">All system accounts</p>
        </div>

        <div class="card">
          <p class="stat-title">Students</p>
          <p class="stat-value"><?= $totalStudents ?></p>
          <p class="stat-sub">Registered students</p>
        </div>

        <div class="card">
          <p class="stat-title">Lecturers</p>
          <p class="stat-value"><?= $totalLecturers ?></p>
          <p class="stat-sub">Registered lecturers</p>
        </div>

        <div class="card">
          <p class="stat-title">Courses</p>
          <p class="stat-value"><?= $totalCourses ?></p>
          <p class="stat-sub"><?= $totalCourses ? "Available courses" : "Create courses next" ?></p>
        </div>
      </div>

      <!-- Main section -->
      <div class="grid section">

        <!-- Recent activity -->
        <div class="card">
          <h3>Recently Added Users</h3>

          <table class="simple-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$recentUsers): ?>
                <tr><td colspan="3">No users found.</td></tr>
              <?php else: ?>
                <?php foreach ($recentUsers as $u): ?>
                  <tr>
                    <td><?= htmlspecialchars($u["full_name"]) ?></td>
                    <td><?= htmlspecialchars($u["email"]) ?></td>
                    <td><?= htmlspecialchars($u["role"]) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Quick actions -->
        <div class="card">
          <h3>Quick Actions</h3>
          <div class="actions">
            <a class="action-btn" href="<?= $base ?>/admin/create_user.php">
              ➕ Create User
              <div class="small">Add student/lecturer accounts</div>
            </a>

            <a class="action-btn" href="<?= $base ?>/admin/manage_users.php">
              👤 Manage Users
              <div class="small">View all accounts</div>
            </a>

            <a class="action-btn" href="<?= $base ?>/admin/manage_courses.php">
              📚 Create Courses
              <div class="small">Set up courses</div>
            </a>

            <a class="action-btn" href="<?= $base ?>/admin/enroll_students.php">
              🧾 Enroll Students
              <div class="small">Enroll into courses</div>
            </a>
          </div>
        </div>

      </div>

      <div class="footer-row">
        <a class="logout" href="<?= $base ?>/auth/logout.php">Logout</a>
      </div>

    </main>
  </div>
</body>
</html>
