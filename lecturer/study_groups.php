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

// Groups list with member count
$sql = "
  SELECT g.group_id, g.name, g.description, g.created_at,
         u.full_name AS creator_name,
         (SELECT COUNT(*) FROM study_group_members m WHERE m.group_id = g.group_id) AS member_count
  FROM study_groups g
  INNER JOIN users u ON u.userID = g.created_by
  WHERE g.course_id = ?
  ORDER BY g.group_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$courseId]);
$groups = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Study Groups (Lecturer)</title>
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
      <li><a class="active" href="#">👥 Study Groups</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Study Groups — <?= htmlspecialchars($course["course_code"] ?? "") ?></h2>
          <p><?= htmlspecialchars($course["title"] ?? "") ?></p>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Group</th>
              <th>Creator</th>
              <th>Members</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$groups): ?>
            <tr><td colspan="4">No study groups created yet.</td></tr>
          <?php else: ?>
            <?php foreach ($groups as $g): ?>
              <tr>
                <td>
                  <b><?= htmlspecialchars($g["name"]) ?></b><br>
                  <span style="color:#6b7280;"><?= nl2br(htmlspecialchars($g["description"] ?? "")) ?></span>
                </td>
                <td><?= htmlspecialchars($g["creator_name"]) ?></td>
                <td><?= (int)$g["member_count"] ?></td>
                <td><?= htmlspecialchars($g["created_at"]) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
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
