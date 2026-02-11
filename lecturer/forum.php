<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base="/education%20system";
$lecturerId=(int)$_SESSION["user"]["user_id"];
$courseId=(int)($_GET["course_id"] ?? 0);
if($courseId<=0) die("Invalid course.");

$check=$pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId,$courseId]);
if(!$check->fetch()){ http_response_code(403); die("Not allowed."); }

$cq=$pdo->prepare("SELECT course_code,title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course=$cq->fetch();

$stmt=$pdo->prepare("
  SELECT t.thread_id,t.title,t.created_at,u.full_name AS author
  FROM forum_threads t INNER JOIN users u ON u.userID=t.created_by
  WHERE t.course_id=? ORDER BY t.thread_id DESC
");
$stmt->execute([$courseId]);
$threads=$stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forum</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/lecturer.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Lecturer Panel</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • LECTURER</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/lecturer/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="#">💬 Forum</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Forum — <?= htmlspecialchars($course["course_code"] ?? "") ?></h2>
          <p><?= htmlspecialchars($course["title"] ?? "") ?></p>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead><tr><th>Title</th><th>By</th><th>Posted</th><th>Open</th></tr></thead>
          <tbody>
          <?php if(!$threads): ?>
            <tr><td colspan="4">No questions yet.</td></tr>
          <?php else: foreach($threads as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t["title"]) ?></td>
              <td><?= htmlspecialchars($t["author"]) ?></td>
              <td><?= htmlspecialchars($t["created_at"]) ?></td>
              <td><a class="action-link" href="<?= $base ?>/lecturer/thread.php?course_id=<?= $courseId ?>&thread_id=<?= (int)$t["thread_id"] ?>">View</a></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:16px;">
        <a class="back" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">← Back</a>
      </div>
    </div>
  </main>
</div>
</body>
</html>
