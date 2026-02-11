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
$courseId = (int)($_GET["course_id"] ?? 0);

if ($courseId <= 0) die("Invalid course.");

// Ensure student enrolled
$check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1");
$check->execute([$studentId, $courseId]);
if (!$check->fetch()) {
  http_response_code(403);
  die("You are not enrolled in this course.");
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
  <title>Course</title>
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
      <li><a href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="#">📚 Course</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2><?= htmlspecialchars($course["course_code"]) ?> - <?= htmlspecialchars($course["title"]) ?></h2>
          <p>Access materials and collaborate with your class.</p>
        </div>
      </div>

      <div class="actions">
        <a class="action-btn" href="<?= $base ?>/student/materials.php?course_id=<?= $courseId ?>">
          📄 Course Materials
          <div class="small">Download notes & files</div>
        </a>

        <a class="action-btn" href="<?= $base ?>/student/announcements.php?course_id=<?= $courseId ?>">
          📢 Announcements / Events
          <div class="small">See course updates</div>
        </a>

        <a class="action-btn" href="<?= $base ?>/student/forum.php?course_id=<?= $courseId ?>">
          💬 Forum Q&amp;A
          <div class="small">Ask questions and reply</div>
        </a>

        <a class="action-btn" href="<?= $base ?>/student/study_groups.php?course_id=<?= $courseId ?>">
          👥 Study Groups
          <div class="small">Create and join study groups</div>
        </a>
      </div>

      <div style="margin-top:16px;">
        <a class="back" href="<?= $base ?>/student/dashboard.php">← Back to Dashboard</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
