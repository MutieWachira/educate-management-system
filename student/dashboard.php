<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$studentId = (int)$_SESSION["user"]["user_id"];

$sql = "
  SELECT c.course_id, c.course_code, c.title, d.name AS department_name
  FROM enrollments e
  INNER JOIN courses c ON c.course_id = e.course_id
  INNER JOIN departments d ON d.department_id = c.department_id
  WHERE e.student_id = ?
  ORDER BY c.course_code ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId]);
$courses = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Student Portal</span></div>
  </div>
  <div class="user">
    <div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • STUDENT</div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a class="active" href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>My Courses</h2>
          <p>Courses you are enrolled in.</p>
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
            <tr><td colspan="4">You are not enrolled in any courses yet.</td></tr>
          <?php else: ?>
            <?php foreach ($courses as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c["course_code"]) ?></td>
                <td><?= htmlspecialchars($c["title"]) ?></td>
                <td><?= htmlspecialchars($c["department_name"]) ?></td>
                <td>
                  <a class="action-link" href="<?= $base ?>/student/course.php?course_id=<?= (int)$c["course_id"] ?>">Open</a>
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
