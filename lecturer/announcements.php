<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);

if ($courseId <= 0) die("Invalid course.");

// Ensure lecturer assigned
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not allowed."); }

// Course info
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

$msg = ""; $error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = trim($_POST["title"] ?? "");
  $content = trim($_POST["content"] ?? "");
  $event_date = trim($_POST["event_date"] ?? "");

  if ($title === "" || $content === "") {
    $error = "Title and content are required.";
  } else {
    $dateVal = ($event_date === "") ? null : $event_date;
    $ins = $pdo->prepare("INSERT INTO announcements (course_id, posted_by, title, content, event_date) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$courseId, $lecturerId, $title, $content, $dateVal]);
    $msg = "Posted successfully.";
  }
}

// List
$sql = "
  SELECT announcement_id, title, content, event_date, created_at
  FROM announcements
  WHERE course_id=? AND posted_by=?
  ORDER BY announcement_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$courseId, $lecturerId]);
$items = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Announcements</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/lecturer.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Lecturer Panel</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • LECTURER</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/lecturer/dashboard.php">🏠 Dashboard</a></li>
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

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post">
        <input type="text" name="title" placeholder="Title e.g. CAT 1 Reminder" required>
        <input type="date" name="event_date" placeholder="Event date (optional)">
        <textarea name="content" placeholder="Write announcement details..." required style="width:100%;min-height:110px;padding:12px;border-radius:12px;border:1px solid #e5e7eb;"></textarea>
        <button class="btn" type="submit">Post</button>
        <a class="back" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">Back</a>
      </form>

      <div class="table-wrap" style="margin-top:16px;">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Event Date</th>
              <th>Posted</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$items): ?>
            <tr><td colspan="4">No announcements yet.</td></tr>
          <?php else: ?>
            <?php foreach ($items as $a): ?>
              <tr>
                <td><?= htmlspecialchars($a["title"]) ?></td>
                <td><?= htmlspecialchars($a["event_date"] ?? "-") ?></td>
                <td><?= htmlspecialchars($a["created_at"]) ?></td>
                <td>
                  <a class="action-link danger" href="<?= $base ?>/lecturer/delete_announcement.php?course_id=<?= $courseId ?>&id=<?= (int)$a["announcement_id"] ?>">Delete</a>
                </td>
              </tr>
              <tr>
                <td colspan="4" style="color:#6b7280;">
                  <?= nl2br(htmlspecialchars($a["content"])) ?>
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
