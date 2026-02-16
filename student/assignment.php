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

$check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not enrolled."); }

// Course info
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();
if (!$course) die("Course not found.");

// List assignments + whether student has submitted
$sql = "
  SELECT
    a.assignment_id,
    a.title,
    a.due_date,
    a.max_score,
    a.created_at,
    EXISTS(
      SELECT 1 FROM submissions s
      WHERE s.assignment_id=a.assignment_id AND s.student_id=?
    ) AS has_submitted
  FROM assignments a
  WHERE a.course_id=?
  ORDER BY a.assignment_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId, $courseId]);
$assignments = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Assignments</title>
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
      <li><a class="active" href="#">📝 Assignments</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Assignments — <?= htmlspecialchars((string)$course["course_code"]) ?></h2>
          <p><?= htmlspecialchars((string)$course["title"]) ?></p>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Due</th>
              <th>Max</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$assignments): ?>
            <tr><td colspan="5">No assignments posted yet.</td></tr>
          <?php else: foreach ($assignments as $a): ?>
            <tr>
              <td><?= htmlspecialchars((string)$a["title"]) ?></td>
              <td><?= htmlspecialchars((string)($a["due_date"] ?? "-")) ?></td>
              <td><?= (int)$a["max_score"] ?></td>
              <td>
                <?php if ((int)$a["has_submitted"] === 1): ?>
                  <span style="font-weight:800;color:#065f46;">Submitted</span>
                <?php else: ?>
                  <span style="font-weight:800;color:#b45309;">Not submitted</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="action-link"
                   href="<?= $base ?>/student/submit_assignment.php?course_id=<?= (int)$courseId ?>&assignment_id=<?= (int)$a["assignment_id"] ?>">
                  Open
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:16px;">
        <a class="back" href="<?= $base ?>/student/course.php?course_id=<?= (int)$courseId ?>">← Back to Course</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
