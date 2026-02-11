<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];

$sql = "
  SELECT c.course_id, c.course_code, c.title, d.name AS department_name
  FROM lecturer_courses lc
  INNER JOIN courses c ON c.course_id = lc.course_id
  INNER JOIN departments d ON d.department_id = c.department_id
  WHERE lc.lecturer_id = ?
  ORDER BY c.course_code ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$lecturerId]);
$courses = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Lecturer Dashboard</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/lecturer.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Lecturer Panel</span></div>
  </div>
  <div class="user">
    <div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • LECTURER</div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a class="active" href="<?= $base ?>/lecturer/dashboard.php">🏠 Dashboard</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>My Courses</h2>
          <p>Courses assigned to you by the admin.</p>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Title</th>
              <th>Department</th>
              <th>Open</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$courses): ?>
            <tr><td colspan="4">No courses assigned yet.</td></tr>
          <?php else: ?>
            <?php foreach ($courses as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c["course_code"]) ?></td>
                <td><?= htmlspecialchars($c["title"]) ?></td>
                <td><?= htmlspecialchars($c["department_name"]) ?></td>
                <td>
                  <a class="action-link" href="<?= $base ?>/lecturer/course.php?course_id=<?= (int)$c["course_id"] ?>">Manage</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

</body>
</html>
