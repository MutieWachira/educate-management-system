<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);

require_once __DIR__ . "/../config/db.php";

$studentId = (int)$_SESSION["user"]["user_id"];

// Fetch enrolled courses
$sql = "
  SELECT c.course_id, c.course_code, c.title
  FROM enrollments e
  INNER JOIN courses c ON c.course_id = e.course_id
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
  <link rel="stylesheet" href="/education%20system/assets/css/dashboard.css">
</head>
<body>

  <div class="container">
    <h2>Student Dashboard</h2>
    <p>Welcome, <b><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?></b></p>

    <div class="card">
      <h3>My Courses</h3>

      <?php if (!$courses): ?>
        <p>You are not enrolled in any courses yet.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($courses as $c): ?>
            <li>
              <a href="/education%20system/student/course.php?course_id=<?= (int)$c["course_id"] ?>">
                <?= htmlspecialchars($c["course_code"]) ?> - <?= htmlspecialchars($c["title"]) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div style="margin-top:20px;">
      <a href="/education%20system/auth/logout.php">Logout</a>
    </div>

  </div>

</body>
</html>
