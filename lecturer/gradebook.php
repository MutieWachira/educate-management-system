<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);
if ($courseId <= 0) die("Invalid course.");

// Ensure assigned
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not allowed."); }

function letter_grade(float $p): string {
  if ($p >= 70) return "A";
  if ($p >= 60) return "B";
  if ($p >= 50) return "C";
  if ($p >= 40) return "D";
  return "E";
}

// Course
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

// Students enrolled
$st = $pdo->prepare("
  SELECT u.userID, u.full_name, u.admission_no
  FROM enrollments e
  INNER JOIN users u ON u.userID = e.student_id
  WHERE e.course_id=?
  ORDER BY u.admission_no ASC, u.full_name ASC
");
$st->execute([$courseId]);
$students = $st->fetchAll();

// Assignments (course-wide)
$as = $pdo->prepare("
  SELECT assignment_id, title, max_score, grades_published
  FROM assignments
  WHERE course_id=?
  ORDER BY assignment_id ASC
");
$as->execute([$courseId]);
$assignments = $as->fetchAll();

// Submissions map
$sub = $pdo->prepare("
  SELECT assignment_id, student_id, score
  FROM submissions
  WHERE assignment_id IN (
    SELECT assignment_id FROM assignments WHERE course_id=?
  )
");
$sub->execute([$courseId]);
$subs = $sub->fetchAll();

$scoreMap = []; // scoreMap[studentId][assignmentId] = score
foreach ($subs as $s) {
  $sid = (int)$s["student_id"];
  $aid = (int)$s["assignment_id"];
  $scoreMap[$sid][$aid] = $s["score"]; // may be null
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gradebook</title>
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
      <li><a class="active" href="#">📊 Gradebook</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Gradebook — <?= htmlspecialchars((string)($course["course_code"] ?? "")) ?></h2>
          <p><?= htmlspecialchars((string)($course["title"] ?? "")) ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a class="btn" href="<?= $base ?>/lecturer/grade_categories.php?course_id=<?= $courseId ?>">⚖ Weights</a>
          <a class="btn" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">← Back</a>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <?php foreach ($assignments as $a): ?>
                <th title="<?= htmlspecialchars((string)$a["title"]) ?>">
                  <?= htmlspecialchars((string)$a["title"]) ?><br>
                  <span style="font-size:12px;color:#6b7280;">/<?= (int)$a["max_score"] ?> <?= ((int)$a["grades_published"]===1) ? "✅" : "🔒" ?></span>
                </th>
              <?php endforeach; ?>
              <th>Total %</th>
              <th>Grade</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$students): ?>
            <tr><td colspan="<?= 3 + count($assignments) ?>">No students enrolled.</td></tr>
          <?php else: foreach ($students as $s): ?>
            <?php
              $sid = (int)$s["userID"];
              $sum = 0.0; $count = 0;
            ?>
            <tr>
              <td>
                <b><?= htmlspecialchars((string)$s["full_name"]) ?></b><br>
                <span style="color:#6b7280;font-size:13px;"><?= htmlspecialchars((string)($s["admission_no"] ?? "N/A")) ?></span>
              </td>
              <?php foreach ($assignments as $a): ?>
                <?php
                  $aid = (int)$a["assignment_id"];
                  $score = $scoreMap[$sid][$aid] ?? null;
                  $max = (float)$a["max_score"];
                  if ($score !== null && $max > 0) { $sum += ((float)$score / $max) * 100.0; $count++; }
                ?>
                <td><?= $score === null ? "-" : htmlspecialchars((string)$score) ?></td>
              <?php endforeach; ?>

              <?php
                $avg = $count > 0 ? ($sum / $count) : 0.0;
              ?>
              <td><?= number_format($avg, 2) ?>%</td>
              <td><b><?= htmlspecialchars(letter_grade($avg)) ?></b></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:12px;color:#6b7280;font-size:13px;">
        🔒 = grades hidden from students • ✅ = published to students.
      </div>
    </div>
  </main>
</div>

</body>
</html>
