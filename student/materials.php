<?php
declare(strict_types=1);

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
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

// Materials list
$sql = "
  SELECT m.material_id, m.title, m.file_path, m.uploaded_at,
         u.full_name AS lecturer_name
  FROM materials m
  INNER JOIN users u ON u.userID = m.uploaded_by
  WHERE m.course_id = ?
  ORDER BY m.material_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$courseId]);
$materials = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Course Materials</title>
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
      <li><a class="active" href="#">📄 Materials</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Materials — <?= htmlspecialchars($course["course_code"] ?? "") ?></h2>
          <p><?= htmlspecialchars($course["title"] ?? "") ?></p>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Uploaded By</th>
              <th>Date</th>
              <th>Download</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$materials): ?>
              <tr><td colspan="4">No materials uploaded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($materials as $m): ?>
                <tr>
                  <td><?= htmlspecialchars($m["title"]) ?></td>
                  <td><?= htmlspecialchars($m["lecturer_name"]) ?></td>
                  <td><?= htmlspecialchars($m["uploaded_at"]) ?></td>
                  <td>
                    <a class="action-link" href="<?= $base ?>/<?= htmlspecialchars($m["file_path"]) ?>" target="_blank">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:16px;">
        <a class="back" href="<?= $base ?>/student/course.php?course_id=<?= $courseId ?>">← Back</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
