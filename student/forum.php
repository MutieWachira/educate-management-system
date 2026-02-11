<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";

$base="/education%20system";
$studentId=(int)$_SESSION["user"]["user_id"];
$courseId=(int)($_GET["course_id"] ?? 0);
if ($courseId<=0) die("Invalid course.");

$check=$pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId,$courseId]);
if(!$check->fetch()){ http_response_code(403); die("Not enrolled."); }

$cq=$pdo->prepare("SELECT course_code,title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course=$cq->fetch();

$msg=""; $error="";
if($_SERVER["REQUEST_METHOD"]==="POST"){
  $title=trim($_POST["title"]??"");
  $body=trim($_POST["body"]??"");
  if($title===""||$body===""){ $error="Title and question are required."; }
  else{
    $ins=$pdo->prepare("INSERT INTO forum_threads (course_id, created_by, title, body) VALUES (?,?,?,?)");
    $ins->execute([$courseId,$studentId,$title,$body]);
    $msg="Question posted.";
  }
}

$sql="
  SELECT t.thread_id,t.title,t.body,t.created_at,u.full_name AS author
  FROM forum_threads t
  INNER JOIN users u ON u.userID=t.created_by
  WHERE t.course_id=?
  ORDER BY t.thread_id DESC
";
$stmt=$pdo->prepare($sql);
$stmt->execute([$courseId]);
$threads=$stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forum</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Student Portal</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • STUDENT</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
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

      <?php if($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post">
        <input type="text" name="title" placeholder="Question title" required>
        <textarea name="body" placeholder="Ask your question..." required style="width:100%;min-height:110px;padding:12px;border-radius:12px;border:1px solid #e5e7eb;"></textarea>
        <button class="btn" type="submit">Post Question</button>
        <a class="back" href="<?= $base ?>/student/course.php?course_id=<?= $courseId ?>">Back</a>
      </form>

      <div class="table-wrap" style="margin-top:16px;">
        <table>
          <thead>
            <tr><th>Title</th><th>By</th><th>Posted</th><th>Open</th></tr>
          </thead>
          <tbody>
          <?php if(!$threads): ?>
            <tr><td colspan="4">No questions yet.</td></tr>
          <?php else: foreach($threads as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t["title"]) ?></td>
              <td><?= htmlspecialchars($t["author"]) ?></td>
              <td><?= htmlspecialchars($t["created_at"]) ?></td>
              <td><a class="action-link" href="<?= $base ?>/student/thread.php?course_id=<?= $courseId ?>&thread_id=<?= (int)$t["thread_id"] ?>">View</a></td>
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
