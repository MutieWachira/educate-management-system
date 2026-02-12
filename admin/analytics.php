<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base="/education%20system";

function countQ(PDO $pdo, string $sql, array $params=[]): int {
  $st=$pdo->prepare($sql);
  $st->execute($params);
  return (int)($st->fetch()["c"] ?? 0);
}

$students = countQ($pdo, "SELECT COUNT(*) c FROM users WHERE role='STUDENT'");
$lecturers = countQ($pdo, "SELECT COUNT(*) c FROM users WHERE role='LECTURER'");
$admins = countQ($pdo, "SELECT COUNT(*) c FROM users WHERE role='ADMIN'");
$departments = countQ($pdo, "SELECT COUNT(*) c FROM departments");
$courses = countQ($pdo, "SELECT COUNT(*) c FROM courses");
$enrollments = countQ($pdo, "SELECT COUNT(*) c FROM enrollments");
$materials = countQ($pdo, "SELECT COUNT(*) c FROM materials");
$announcements = countQ($pdo, "SELECT COUNT(*) c FROM announcements");
$threads = countQ($pdo, "SELECT COUNT(*) c FROM forum_threads");
$replies = countQ($pdo, "SELECT COUNT(*) c FROM forum_replies");
$groups = countQ($pdo, "SELECT COUNT(*) c FROM study_groups");

// Courses with most enrollments (top 7)
$top = $pdo->query("
  SELECT c.course_code, COUNT(e.enrollment_id) AS total
  FROM courses c
  LEFT JOIN enrollments e ON e.course_id=c.course_id
  GROUP BY c.course_id
  ORDER BY total DESC
  LIMIT 7
")->fetchAll();

$labels = array_map(fn($r)=>$r["course_code"], $top);
$values = array_map(fn($r)=>(int)$r["total"], $top);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Analytics</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/manage_users.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="topbar">
  <div class="brand"><div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Admin Panel</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • ADMIN</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/admin/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="<?= $base ?>/admin/analytics.php">📊 Analytics</a></li>
      <li><a href="<?= $base ?>/admin/manage_users.php">👤 Manage Users</a></li>
      <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>System Analytics</h2>
          <p>High-level usage and activity overview.</p>
        </div>
      </div>

      <div class="actions">
        <div class="action-btn">Students<div class="small"><?= $students ?></div></div>
        <div class="action-btn">Lecturers<div class="small"><?= $lecturers ?></div></div>
        <div class="action-btn">Admins<div class="small"><?= $admins ?></div></div>
        <div class="action-btn">Departments<div class="small"><?= $departments ?></div></div>
        <div class="action-btn">Courses<div class="small"><?= $courses ?></div></div>
        <div class="action-btn">Enrollments<div class="small"><?= $enrollments ?></div></div>
        <div class="action-btn">Materials<div class="small"><?= $materials ?></div></div>
        <div class="action-btn">Announcements<div class="small"><?= $announcements ?></div></div>
        <div class="action-btn">Threads<div class="small"><?= $threads ?></div></div>
        <div class="action-btn">Replies<div class="small"><?= $replies ?></div></div>
        <div class="action-btn">Study Groups<div class="small"><?= $groups ?></div></div>
      </div>

      <div style="margin-top:18px;">
        <h3 style="margin-bottom:10px;">Top Courses by Enrollment</h3>
        <canvas id="chart"></canvas>
      </div>
    </div>
  </main>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const values = <?= json_encode($values) ?>;

new Chart(document.getElementById('chart'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{ label: 'Enrollments', data: values }]
  }
});
</script>

</body>
</html>
