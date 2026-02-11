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

// Ensure enrolled
$check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not enrolled."); }

// Course info
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

// List announcements
$sql = "
  SELECT a.title, a.content, a.event_date, a.created_at, u.full_name AS lecturer_name
  FROM announcements a
  INNER JOIN users u ON u.userID = a.posted_by
  WHERE a.course_id=?
  ORDER BY a.announcement_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$courseId]);
$items = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Announcements</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Student Portal</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • STUDENT</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="#">📢 Announcements</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Announcements — <?= htmlspecialchars($course["course_code"] ?? "") ?></h2>
          <p><?= htmlspecialchars($course["title"] ?? "") ?></p>
        </div>
      </div>

      <?php if (!$items): ?>
        <p>No announcements yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>By</th>
                <th>Event Date</th>
                <th>Posted</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a["title"]) ?></td>
                  <td><?= htmlspecialchars($a["lecturer_name"]) ?></td>
                  <td><?= htmlspecialchars($a["event_date"] ?? "-") ?></td>
                  <td><?= htmlspecialchars($a["created_at"]) ?></td>
                </tr>
                <tr>
                  <td colspan="4" style="color:#6b7280;">
                    <?= nl2br(htmlspecialchars($a["content"])) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div style="margin-top:16px;">
        <a class="back" href="<?= $base ?>/student/course.php?course_id=<?= $courseId ?>">← Back</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
