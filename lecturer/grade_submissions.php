<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
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

$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not allowed."); }

$aq = $pdo->prepare("
  SELECT a.assignment_id, a.title, a.max_score, a.due_date, c.course_code
  FROM assignments a
  INNER JOIN courses c ON c.course_id = a.course_id
  WHERE a.assignment_id=? AND a.course_id=? AND a.created_by=? LIMIT 1
");
$aq->execute([$assignmentId, $courseId, $lecturerId]);
$assignment = $aq->fetch();
if (!$assignment) die("Assignment not found.");

$msg = "";
$error = "";

if (($_GET["ok"] ?? "") === "1") $msg = "Grade saved and student notified.";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
      $error = "Score must be between 0 and {$assignment["max_score"]}.";
    } else {
      try {
        $pdo->beginTransaction();

        // Get submission + student
        $sq = $pdo->prepare("
          SELECT s.submission_id, s.student_id, u.full_name, u.email, u.admission_no
          FROM submissions s
          INNER JOIN users u ON u.userID = s.student_id
          WHERE s.submission_id=? AND s.assignment_id=? LIMIT 1
        ");
        $sq->execute([$submissionId, $assignmentId]);
        $sub = $sq->fetch();
        if (!$sub) {
          $pdo->rollBack();
          $error = "Submission not found.";
        } else {
          $upd = $pdo->prepare("
            UPDATE submissions
            SET score=?, feedback=?, graded_at=NOW(), graded_by=?
            WHERE submission_id=? LIMIT 1
          ");
          $upd->execute([$scoreVal, ($feedback === "" ? null : $feedback), $lecturerId, $submissionId]);

          // Notify student
          $studentId = (int)$sub["student_id"];
          $link = "{$base}/student/grades.php?course_id={$courseId}";
          $notifTitle = "Grade posted - " . (string)$assignment["course_code"];
          $notifMessage =
            "Assignment: " . (string)$assignment["title"] . "\n" .
            "Score: {$scoreVal} / " . (string)$assignment["max_score"] . "\n" .
            (($feedback !== "") ? ("Feedback:\n{$feedback}") : "Feedback: -");

          notify_user(
            $pdo,
            $studentId,
            "GRADE_POSTED",
            $notifTitle,
            $notifMessage,
            $link,
            true
          );

          log_activity($pdo, $lecturerId, "GRADE_SUBMISSION", "Assignment: $assignmentId | Submission: $submissionId | Score: $scoreVal");

          $pdo->commit();

          header("Location: {$base}/lecturer/grade_submissions.php?course_id={$courseId}&assignment_id={$assignmentId}&ok=1");
          exit;
        }
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Failed to save grade.";
      }
    }
  }
}

$list = $pdo->prepare("
  SELECT s.submission_id, s.file_path, s.text_answer, s.submitted_at, s.score, s.feedback, s.graded_at,
         u.full_name, u.admission_no
  FROM submissions s
  INNER JOIN users u ON u.userID = s.student_id
  WHERE s.assignment_id=?
  ORDER BY s.submitted_at DESC
");
$list->execute([$assignmentId]);
$subs = $list->fetchAll();
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
      <li><a class="active" href="#">✅ Grade</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Grade — <?= htmlspecialchars((string)$assignment["course_code"]) ?></h2>
          <p><?= htmlspecialchars((string)$assignment["title"]) ?> • Max: <?= (int)$assignment["max_score"] ?> • Due: <?= htmlspecialchars((string)($assignment["due_date"] ?? "-")) ?></p>
        </div>
        <div>
          <a class="btn" href="<?= $base ?>/lecturer/assignments.php?course_id=<?= $courseId ?>">← Back</a>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Adm No</th>
              <th>Submitted</th>
              <th>Work</th>
              <th>Score</th>
              <th>Grade</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$subs): ?>
            <tr><td colspan="6">No submissions yet.</td></tr>
          <?php else: foreach ($subs as $s): ?>
            <tr>
              <td><?= htmlspecialchars((string)$s["full_name"]) ?></td>
              <td><?= htmlspecialchars((string)($s["admission_no"] ?? "-")) ?></td>
              <td><?= htmlspecialchars((string)$s["submitted_at"]) ?></td>
              <td>
                <?php if ($s["file_path"]): ?>
                  <a class="action-link" target="_blank" href="<?= $base ?>/<?= htmlspecialchars((string)$s["file_path"]) ?>">Open file</a>
                <?php else: ?>
                  -
                <?php endif; ?>
                <?php if ($s["text_answer"]): ?>
                  <div style="margin-top:6px;color:#6b7280;"><?= nl2br(htmlspecialchars((string)$s["text_answer"])) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= ($s["score"] === null) ? "-" : htmlspecialchars((string)$s["score"]) ?>
              </td>
              <td style="min-width:260px;">
                <form method="post" style="display:grid;gap:8px;">
                  <input type="hidden" name="submission_id" value="<?= (int)$s["submission_id"] ?>">
                  <input type="number" name="score" step="0.01" min="0" max="<?= (int)$assignment["max_score"] ?>"
                         placeholder="Score / <?= (int)$assignment["max_score"] ?>" required
                         value="<?= $s["score"] === null ? "" : htmlspecialchars((string)$s["score"]) ?>">
                  <textarea name="feedback" placeholder="Feedback (optional)"
                    style="width:100%;min-height:70px;padding:10px;border-radius:12px;border:1px solid #e5e7eb;"><?= htmlspecialchars((string)($s["feedback"] ?? "")) ?></textarea>
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
