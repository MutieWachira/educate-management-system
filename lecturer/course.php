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
$courseId = (int)($_GET["course_id"] ?? 0);

if ($courseId <= 0) die("Invalid course.");

// Ensure lecturer assigned to this course
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) {
  http_response_code(403);
  die("You are not assigned to this course.");
}

// Course info
$stmt = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id = ? LIMIT 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) die("Course not found.");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Course - Lecturer</title>
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
      <li><a href="<?= $base ?>/lecturer/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="#">📚 Course</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2><?= htmlspecialchars($course["course_code"]) ?> - <?= htmlspecialchars($course["title"]) ?></h2>
          <p>Manage materials, announcements and forum for this course.</p>
        </div>
      </div>

      <div class="actions">
        <a class="action-btn" href="<?= $base ?>/lecturer/upload_material.php?course_id=<?= $courseId ?>">
          ⬆ Upload Material
          <div class="small">Add PDF/DOC for students</div>
        </a>

        <a class="action-btn" href="<?= $base ?>/lecturer/materials.php?course_id=<?= $courseId ?>">
          📄 View Materials
          <div class="small">Manage uploaded files</div>
        </a>

        <a class="action-btn" href="#">
          📢 Announcements
          <div class="small">Coming next</div>
        </a>

        <a class="action-btn" href="#">
          💬 Forum Q&A
          <div class="small">Coming next</div>
        </a>
      </div>

      <div class="footer-row">
        <a class="back" href="<?= $base ?>/lecturer/dashboard.php">← Back to My Courses</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
