<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$studentId = (int)$_SESSION["user"]["user_id"];
$courseId  = (int)($_GET["course_id"] ?? 0);
if ($courseId <= 0) die("Invalid course.");

// Ensure enrolled
$check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not enrolled."); }

// Course info
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

// Categories
$cats = $pdo->prepare("SELECT category_id, name, weight FROM grade_categories WHERE course_id=?");
$cats->execute([$courseId]);
$categories = $cats->fetchAll();

$catWeight = [];
$totalWeight = 0.0;
foreach ($categories as $c) {
  $catWeight[(int)$c["category_id"]] = (float)$c["weight"];
  $totalWeight += (float)$c["weight"];
}

// Assignments + my submission scores
$sql = "
  SELECT a.assignment_id, a.title, a.max_score, a.due_date, a.grades_published, a.category_id,
         s.score, s.feedback, s.submitted_at
  FROM assignments a
  LEFT JOIN submissions s
    ON s.assignment_id = a.assignment_id AND s.student_id = ?
  WHERE a.course_id=?
  ORDER BY a.assignment_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId, $courseId]);
$rows = $stmt->fetchAll();

function letter_grade(float $p): string {
  if ($p >= 70) return "A";
  if ($p >= 60) return "B";
  if ($p >= 50) return "C";
  if ($p >= 40) return "D";
  return "E";
}

// Weighted total
$weightedTotal = 0.0;
$weightUsed = 0.0;

foreach ($rows as $r) {
  $published = (int)($r["grades_published"] ?? 0);
  if ($published !== 1) continue;         // hidden grades do NOT count yet

  $score = $r["score"];
  $max = (float)$r["max_score"];
  if ($score === null || $max <= 0) continue; // not graded yet

  $pct = ((float)$score / $max); // 0..1

  $catId = (int)($r["category_id"] ?? 0);
  $w = $catId && isset($catWeight[$catId]) ? $catWeight[$catId] : 0.0;

  // If no categories set, we fallback to "simple average" later
  if ($totalWeight > 0 && $w > 0) {
    $weightedTotal += ($pct * $w);
    $weightUsed += $w;
  }
}

$finalPct = 0.0;

// If weights exist and used
if ($totalWeight > 0 && $weightUsed > 0) {
  // normalize by weight used (if you haven't graded everything yet)
  $finalPct = ($weightedTotal / $weightUsed) * 100.0;
} else {
  // fallback: simple average of published graded items
  $sumPct = 0.0; $count = 0;
  foreach ($rows as $r) {
    if ((int)($r["grades_published"] ?? 0) !== 1) continue;
    if ($r["score"] === null) continue;
    $max = (float)$r["max_score"];
    if ($max <= 0) continue;
    $sumPct += (((float)$r["score"] / $max) * 100.0);
    $count++;
  }
  $finalPct = $count > 0 ? ($sumPct / $count) : 0.0;
}

$finalLetter = letter_grade($finalPct);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Grades</title>
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
      <li><a class="active" href="#">📊 Grades</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Grades — <?= htmlspecialchars((string)($course["course_code"] ?? "")) ?></h2>
          <p><?= htmlspecialchars((string)($course["title"] ?? "")) ?></p>
        </div>
      </div>

      <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin:10px 0;">
        <b>Current Overall:</b>
        <?= number_format($finalPct, 2) ?>% • <b>Grade: <?= htmlspecialchars($finalLetter) ?></b>
        <div style="color:#6b7280;font-size:13px;margin-top:6px;">
          Only <b>published</b> grades are included.
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Assessment</th>
              <th>Due</th>
              <th>Status</th>
              <th>Score</th>
              <th>Feedback</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="5">No assignments yet.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <?php $published = (int)($r["grades_published"] ?? 0); ?>
            <tr>
              <td><b><?= htmlspecialchars((string)$r["title"]) ?></b></td>
              <td><?= htmlspecialchars((string)($r["due_date"] ?? "-")) ?></td>
              <td>
                <?php
                  if ($published !== 1) echo "<span style='color:#b91c1c;font-weight:700;'>Hidden</span>";
                  elseif ($r["score"] === null) echo "<span style='color:#6b7280;font-weight:700;'>Not graded</span>";
                  else echo "<span style='color:green;font-weight:700;'>Released</span>";
                ?>
              </td>
              <td>
                <?php if ($published !== 1): ?>
                  -
                <?php elseif ($r["score"] === null): ?>
                  -
                <?php else: ?>
                  <?= htmlspecialchars((string)$r["score"]) ?> / <?= (int)$r["max_score"] ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($published !== 1): ?>
                  -
                <?php else: ?>
                  <?= nl2br(htmlspecialchars((string)($r["feedback"] ?? ""))) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:16px;">
        <a class="back" href="<?= $base ?>/student/course.php?course_id=<?= $courseId ?>">← Back to Course</a>
      </div>
    </div>
  </main>
</div>
</body>
</html>
