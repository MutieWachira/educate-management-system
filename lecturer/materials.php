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

// Ensure lecturer assigned
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) {
  http_response_code(403);
  die("You are not assigned to this course.");
}

// Course info
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

$sql = "
  SELECT material_id, title, file_path, uploaded_at
  FROM materials
  WHERE course_id = ? AND uploaded_by = ?
  ORDER BY material_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$courseId, $lecturerId]);
$materials = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Materials</title>
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
        <div>
          <a class="btn" href="<?= $base ?>/lecturer/upload_material.php?course_id=<?= $courseId ?>">+ Upload</a>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>File</th>
              <th>Uploaded</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$materials): ?>
              <tr><td colspan="4">No materials uploaded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($materials as $m): ?>
                <tr>
                  <td><?= htmlspecialchars($m["title"]) ?></td>
                  <td>
                    <a class="action-link" href="<?= $base ?>/<?= htmlspecialchars($m["file_path"]) ?>" target="_blank">Open</a>
                  </td>
                  <td><?= htmlspecialchars($m["uploaded_at"]) ?></td>
                  <td>
                    <a class="action-link danger"
                       href="<?= $base ?>/lecturer/delete_material.php?course_id=<?= $courseId ?>&id=<?= (int)$m["material_id"] ?>">
                      Delete
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="footer-row">
        <a class="back" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">← Back</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
