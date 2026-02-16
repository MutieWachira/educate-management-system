<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/notifier.php";
require_once __DIR__ . "/../includes/logger.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);
$assignmentId = (int)($_GET["assignment_id"] ?? 0);

if ($courseId <= 0 || $assignmentId <= 0) die("Invalid request.");

// Ensure assigned
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not allowed."); }

// CSRF
if (empty($_SESSION["csrf"])) $_SESSION["csrf"] = bin2hex(random_bytes(16));

$msg = "";
$error = "";

// Show messages after redirects
if (($_GET["ok"] ?? "") === "published") $msg = "Grades published. Students can now see scores.";
if (($_GET["ok"] ?? "") === "hidden")    $msg = "Grades hidden. Students cannot see scores.";
if (($_GET["ok"] ?? "") === "graded")    $msg = "Saved grade.";

// Fetch assignment
$aq = $pdo->prepare("
  SELECT assignment_id, title, due_date, max_score, grades_published
  FROM assignments
  WHERE assignment_id=? AND course_id=? AND created_by=?
  LIMIT 1
");
$aq->execute([$assignmentId, $courseId, $lecturerId]);
$assignment = $aq->fetch();
if (!$assignment) die("Assignment not found or not yours.");

$published = (int)$assignment["grades_published"];

// POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!hash_equals($_SESSION["csrf"], (string)($_POST["csrf"] ?? ""))) die("Invalid CSRF token.");

  $action = (string)($_POST["action"] ?? "");

  // ✅ Publish / Unpublish
  if ($action === "publish" || $action === "unpublish") {
    $val = ($action === "publish") ? 1 : 0;

    $upd = $pdo->prepare("
      UPDATE assignments
      SET grades_published=?
      WHERE assignment_id=? AND course_id=? AND created_by=?
      LIMIT 1
    ");
    $upd->execute([$val, $assignmentId, $courseId, $lecturerId]);

    log_activity($pdo, $lecturerId, "TOGGLE_GRADES", "Assignment: $assignmentId | Published: $val");

    $ok = $val ? "published" : "hidden";
    header("Location: {$base}/lecturer/grade_submissions.php?course_id={$courseId}&assignment_id={$assignmentId}&ok={$ok}");
    exit;
  }

  // ✅ Grade one submission (SECURE: ensure submission belongs to this assignment)
  if ($action === "grade") {
    $submissionId = (int)($_POST["submission_id"] ?? 0);
    $score = trim((string)($_POST["score"] ?? ""));
    $feedback = trim((string)($_POST["feedback"] ?? ""));

    if ($submissionId <= 0) {
      $error = "Invalid submission.";
    } elseif ($score === "" || !is_numeric($score)) {
      $error = "Score must be a number.";
    } else {
      $scoreVal = (float)$score;
      $max = (float)$assignment["max_score"];

      if ($scoreVal < 0 || $scoreVal > $max) {
        $error = "Score must be between 0 and $max.";
      } else {
        // ✅ extra validation: only update rows under THIS assignment
        $upd = $pdo->prepare("
          UPDATE submissions
          SET score=?, feedback=?, graded_at=NOW(), graded_by=?
          WHERE submission_id=? AND assignment_id=?
          LIMIT 1
        ");
        $upd->execute([
          $scoreVal,
          ($feedback === "" ? null : $feedback),
          $lecturerId,
          $submissionId,
          $assignmentId
        ]);

        log_activity($pdo, $lecturerId, "GRADE_SUBMISSION", "Sub: $submissionId | Score: $scoreVal/$max");

        header("Location: {$base}/lecturer/grade_submissions.php?course_id={$courseId}&assignment_id={$assignmentId}&ok=graded");
        exit;
      }
    }
  }
}

// List submissions (include student admission_no)
$sql = "
  SELECT s.submission_id, s.file_path, s.text_answer, s.submitted_at, s.score, s.feedback, s.graded_at,
         u.full_name, u.admission_no
  FROM submissions s
  INNER JOIN users u ON u.userID = s.student_id
  WHERE s.assignment_id=?
  ORDER BY s.submitted_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$assignmentId]);
$subs = $stmt->fetchAll();

// Refresh published status (in case toggled)
$aq->execute([$assignmentId, $courseId, $lecturerId]);
$assignment = $aq->fetch();
$published = (int)$assignment["grades_published"];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Grade Submissions</title>
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
      <li><a class="active" href="#">✅ Grade Submissions</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Grade: <?= htmlspecialchars((string)$assignment["title"]) ?></h2>
          <p>Max Score: <b><?= (int)$assignment["max_score"] ?></b> • Due: <?= htmlspecialchars((string)($assignment["due_date"] ?? "-")) ?></p>
          <p style="font-size:13px;color:#6b7280;">
            Status:
            <?= $published ? "<b style='color:green;'>Published</b>" : "<b style='color:#b91c1c;'>Hidden</b>" ?>
          </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a class="btn" href="<?= $base ?>/lecturer/gradebook.php?course_id=<?= $courseId ?>">📊 Gradebook</a>
          <a class="btn" href="<?= $base ?>/lecturer/grade_categories.php?course_id=<?= $courseId ?>">⚖ Weights</a>
          <a class="btn" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">← Back</a>
        </div>
      </div>

      <?php if($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
        <?php if (!$published): ?>
          <input type="hidden" name="action" value="publish">
          <button class="btn" type="submit">Publish Grades</button>
        <?php else: ?>
          <input type="hidden" name="action" value="unpublish">
          <button class="btn" type="submit" style="background:rgba(239,68,68,0.12);color:#b91c1c;border-color:rgba(239,68,68,0.25);">Hide Grades</button>
        <?php endif; ?>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Submitted</th>
              <th>Work</th>
              <th>Score</th>
              <th>Feedback</th>
              <th>Save</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$subs): ?>
            <tr><td colspan="6">No submissions yet.</td></tr>
          <?php else: foreach($subs as $s): ?>
            <tr>
              <td>
                <b><?= htmlspecialchars((string)$s["full_name"]) ?></b><br>
                <span style="color:#6b7280;font-size:13px;"><?= htmlspecialchars((string)($s["admission_no"] ?? "N/A")) ?></span>
              </td>

              <td><?= htmlspecialchars((string)$s["submitted_at"]) ?></td>

              <td>
                <?php if(!empty($s["file_path"])): ?>
                  <a class="action-link" href="<?= $base ?>/<?= htmlspecialchars((string)$s["file_path"]) ?>" target="_blank">Open File</a>
                <?php endif; ?>
                <?php if(!empty($s["text_answer"])): ?>
                  <div style="margin-top:6px;color:#6b7280;">
                    <?= nl2br(htmlspecialchars((string)$s["text_answer"])) ?>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <?= htmlspecialchars((string)($s["score"] ?? "-")) ?> / <?= (int)$assignment["max_score"] ?>
              </td>

              <td><?= nl2br(htmlspecialchars((string)($s["feedback"] ?? ""))) ?></td>

              <td>
                <form method="post" style="display:grid;gap:8px;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
                  <input type="hidden" name="action" value="grade">
                  <input type="hidden" name="submission_id" value="<?= (int)$s["submission_id"] ?>">

                  <input type="number"
                         step="0.01"
                         name="score"
                         placeholder="Score"
                         required
                         value="<?= htmlspecialchars((string)($s["score"] ?? "")) ?>">

                  <textarea name="feedback"
                            placeholder="Feedback (optional)"
                            style="min-height:70px;border-radius:12px;border:1px solid #e5e7eb;padding:10px;"><?= htmlspecialchars((string)($s["feedback"] ?? "")) ?></textarea>

                  <button class="btn" type="submit">Save</button>
                </form>
              </td>
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
