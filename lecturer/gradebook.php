<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)($_SESSION["user"]["user_id"] ?? 0);
$lecturerName = (string)($_SESSION["user"]["full_name"] ?? "Lecturer");
$courseId = (int)($_GET["course_id"] ?? 0);

if ($lecturerId <= 0) { http_response_code(401); die("Not authenticated."); }
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

// Assignments (course-wide) - include submission_type for group logic
$as = $pdo->prepare("
  SELECT assignment_id, title, max_score, grades_published, submission_type
  FROM assignments
  WHERE course_id=?
  ORDER BY assignment_id ASC
");
$as->execute([$courseId]);
$assignments = $as->fetchAll();

// Map: student -> group_id for this course (needed to reflect group grades for every member)
$gm = $pdo->prepare("
  SELECT sgm.student_id, sgm.group_id
  FROM study_group_members sgm
  INNER JOIN study_groups sg ON sg.group_id = sgm.group_id
  WHERE sg.course_id=?
");
$gm->execute([$courseId]);
$groupRows = $gm->fetchAll();

$studentGroup = []; // studentGroup[studentId] = groupId
foreach ($groupRows as $gr) {
  $studentGroup[(int)$gr["student_id"]] = (int)$gr["group_id"];
}

// Submissions for this course's assignments (include group_id)
$sub = $pdo->prepare("
  SELECT s.assignment_id, s.student_id, s.group_id, s.score
  FROM submissions s
  WHERE s.assignment_id IN (
    SELECT assignment_id FROM assignments WHERE course_id=?
  )
");
$sub->execute([$courseId]);
$subs = $sub->fetchAll();

// Build maps:
// - individualScore[studentId][assignmentId] = score
// - groupScore[groupId][assignmentId] = score
$individualScore = [];
$groupScore = [];

foreach ($subs as $row) {
  $aid = (int)$row["assignment_id"];
  $gid = (int)($row["group_id"] ?? 0);
  $sid = (int)$row["student_id"];

  if ($gid > 0) {
    $groupScore[$gid][$aid] = $row["score"];  // may be null
  } else {
    $individualScore[$sid][$aid] = $row["score"]; // may be null
  }
}

// Final score map used by UI: scoreMap[studentId][assignmentId] = score
$scoreMap = [];
foreach ($students as $s) {
  $sid = (int)$s["userID"];
  $gid = (int)($studentGroup[$sid] ?? 0);

  foreach ($assignments as $a) {
    $aid = (int)$a["assignment_id"];
    $type = (string)($a["submission_type"] ?? "INDIVIDUAL");

    if ($type === "GROUP") {
      // If student is in a group, use group's submission score
      $scoreMap[$sid][$aid] = ($gid > 0) ? ($groupScore[$gid][$aid] ?? null) : null;
    } else {
      // INDIVIDUAL
      $scoreMap[$sid][$aid] = $individualScore[$sid][$aid] ?? null;
    }
  }
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
  <div class="user"><div class="pill"><?= htmlspecialchars($lecturerName) ?> • LECTURER</div></div>
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
          <a class="btn" href="<?= $base ?>/lecturer/grade_categories.php?course_id=<?= (int)$courseId ?>">⚖ Weights</a>
          <a class="btn" href="<?= $base ?>/lecturer/course.php?course_id=<?= (int)$courseId ?>">← Back</a>
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
                  <span style="font-size:12px;color:#6b7280;">
                    /<?= (int)$a["max_score"] ?>
                    <?= ((int)$a["grades_published"]===1) ? "✅" : "🔒" ?>
                    <?= ((string)($a["submission_type"] ?? "INDIVIDUAL") === "GROUP") ? " • Group" : "" ?>
                  </span>
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

                  if ($score !== null && $max > 0) {
                    $sum += ((float)$score / $max) * 100.0;
                    $count++;
                  }
                ?>
                <td><?= $score === null ? "-" : htmlspecialchars((string)$score) ?></td>
              <?php endforeach; ?>

              <?php $avg = $count > 0 ? ($sum / $count) : 0.0; ?>
              <td><?= number_format($avg, 2) ?>%</td>
              <td><b><?= htmlspecialchars(letter_grade($avg)) ?></b></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:12px;color:#6b7280;font-size:13px;">
        🔒 = grades hidden from students • ✅ = published to students • “Group” = group submission score shared across members.
      </div>
    </div>
  </main>
</div>

</body>
</html>