<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/notifier.php";

$base = "/education%20system";
$studentId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);
$threadId = (int)($_GET["thread_id"] ?? 0);

if ($courseId <= 0 || $threadId <= 0) die("Invalid request.");

// Ensure student enrolled
$check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not enrolled."); }

// Thread details
$tq = $pdo->prepare("
  SELECT t.title, t.body, t.created_at, t.created_by,
         u.full_name AS author, u.role AS author_role
  FROM forum_threads t
  INNER JOIN users u ON u.userID = t.created_by
  WHERE t.thread_id=? AND t.course_id=? LIMIT 1
");
$tq->execute([$threadId, $courseId]);
$thread = $tq->fetch();
if (!$thread) die("Thread not found.");

$msg = "";
$error = "";

// Handle reply (student)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $body = trim($_POST["body"] ?? "");

  if ($body === "") {
    $error = "Reply cannot be empty.";
  } else {
    try {
      $pdo->beginTransaction();

      // Insert reply
      $ins = $pdo->prepare("INSERT INTO forum_replies (thread_id, replied_by, body) VALUES (?,?,?)");
      $ins->execute([$threadId, $studentId, $body]);

      // Notify lecturers assigned to this course
      $lecStmt = $pdo->prepare("SELECT lecturer_id FROM lecturer_courses WHERE course_id=?");
      $lecStmt->execute([$courseId]);
      $lecturers = $lecStmt->fetchAll();

      $link = "{$base}/lecturer/thread.php?course_id={$courseId}&thread_id={$threadId}";

      $preview = mb_substr($body, 0, 140);
      if (mb_strlen($body) > 140) $preview .= "...";

      foreach ($lecturers as $l) {
        $lecturerId = (int)$l["lecturer_id"];
        notify_user(
          $pdo,
          $lecturerId,
          "FORUM_STUDENT_REPLY",
          "New student reply: " . (string)$thread["title"],
          "Student: " . (string)$_SESSION["user"]["full_name"] . "\nReply: " . $preview,
          $link,
          true
        );
      }

      $pdo->commit();

      // Prevent duplicate submission on refresh
      header("Location: {$base}/student/thread.php?course_id={$courseId}&thread_id={$threadId}&sent=1");
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = "Failed to post reply.";
      // Optional debug:
      // error_log($e->getMessage());
    }
  }
}

// Show success message after redirect
if (($_GET["sent"] ?? "") === "1") {
  $msg = "Reply posted and lecturer notified.";
}

// Fetch replies
$replies = $pdo->prepare("
  SELECT r.body, r.created_at, u.full_name AS author, u.role
  FROM forum_replies r
  INNER JOIN users u ON u.userID = r.replied_by
  WHERE r.thread_id=?
  ORDER BY r.reply_id ASC
");
$replies->execute([$threadId]);
$rows = $replies->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Thread</title>
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
      <li><a class="active" href="#">💬 Thread</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2><?= htmlspecialchars((string)$thread["title"]) ?></h2>
          <p>
            By <?= htmlspecialchars((string)$thread["author"]) ?>
            (<?= htmlspecialchars((string)$thread["author_role"]) ?>)
            • <?= htmlspecialchars((string)$thread["created_at"]) ?>
          </p>
        </div>
      </div>

      <p style="color:#374151; margin-bottom:14px;"><?= nl2br(htmlspecialchars((string)$thread["body"])) ?></p>

      <?php if ($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post">
        <textarea name="body" placeholder="Write a reply..." required
          style="width:100%;min-height:90px;padding:12px;border-radius:12px;border:1px solid #e5e7eb;"></textarea>
        <button class="btn" type="submit">Reply</button>
        <a class="back" href="<?= $base ?>/student/forum.php?course_id=<?= $courseId ?>">Back</a>
      </form>

      <div style="margin-top:16px;">
        <h3 style="margin-bottom:10px;">Replies</h3>
        <?php if (!$rows): ?>
          <p>No replies yet.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Reply</th><th>By</th><th>When</th></tr></thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= nl2br(htmlspecialchars((string)$r["body"])) ?></td>
                  <td><?= htmlspecialchars((string)$r["author"]) ?> (<?= htmlspecialchars((string)$r["role"]) ?>)</td>
                  <td><?= htmlspecialchars((string)$r["created_at"]) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

</body>
</html>
