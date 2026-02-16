<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/notifier.php";
require_once __DIR__ . "/../includes/logger.php";

$base = "/education%20system";
$studentId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);
$assignmentId = (int)($_GET["assignment_id"] ?? 0);

if ($courseId <= 0 || $assignmentId <= 0) die("Invalid request.");

$check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not enrolled."); }

$aq = $pdo->prepare("
  SELECT a.assignment_id, a.title, a.description, a.due_date, a.max_score, a.file_path,
         c.course_code, c.title AS course_title,
         u.full_name AS lecturer_name, a.created_by
  FROM assignments a
  INNER JOIN courses c ON c.course_id = a.course_id
  INNER JOIN users u ON u.userID = a.created_by
  WHERE a.assignment_id=? AND a.course_id=? LIMIT 1
");
$aq->execute([$assignmentId, $courseId]);
$assignment = $aq->fetch();
if (!$assignment) die("Assignment not found.");

$msg = "";
$error = "";

if (($_GET["ok"] ?? "") === "1") $msg = "Submission uploaded successfully.";

$my = $pdo->prepare("
  SELECT submission_id, file_path, text_answer, submitted_at, score, feedback, graded_at
  FROM submissions
  WHERE assignment_id=? AND student_id=? LIMIT 1
");
$my->execute([$assignmentId, $studentId]);
$mine = $my->fetch();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $text_answer = trim($_POST["text_answer"] ?? "");

  if (!isset($_FILES["file"]) || !is_array($_FILES["file"])) {
    $error = "File is required.";
  } else {
    $file = $_FILES["file"];
    if ($file["error"] !== UPLOAD_ERR_OK) {
      $error = "Upload failed (error code: " . (int)$file["error"] . ").";
    } else {
      $allowedExt = ["pdf","doc","docx","zip"];
      $maxBytes = 15 * 1024 * 1024;

      if ((int)$file["size"] > $maxBytes) {
        $error = "File too large. Max 15MB.";
      } else {
        $originalName = (string)$file["name"];
        $tmp = (string)$file["tmp_name"];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
          $error = "Only PDF/DOC/DOCX/ZIP allowed.";
        } else {
          $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
          $safeName = trim($safeName, "_");
          if ($safeName === "") $safeName = "submission";

          $newName = "Sub_S{$studentId}_A{$assignmentId}_" . $safeName . "_" . time() . "." . $ext;

          $uploadDir = __DIR__ . "/../assets/uploads/submissions/";
          if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

          $destPath = $uploadDir . $newName;

          if (!move_uploaded_file($tmp, $destPath)) {
            $error = "Failed to save file (check permissions).";
          } else {
            $relativePath = "assets/uploads/submissions/" . $newName;

            try {
              $pdo->beginTransaction();

              // If already submitted -> update (resubmit)
              if ($mine) {
                $upd = $pdo->prepare("
                  UPDATE submissions
                  SET file_path=?, text_answer=?, submitted_at=NOW(),
                      score=NULL, feedback=NULL, graded_at=NULL, graded_by=NULL
                  WHERE submission_id=? AND student_id=? LIMIT 1
                ");
                $upd->execute([$relativePath, ($text_answer === "" ? null : $text_answer), (int)$mine["submission_id"], $studentId]);
              } else {
                $ins = $pdo->prepare("
                  INSERT INTO submissions (assignment_id, student_id, file_path, text_answer)
                  VALUES (?, ?, ?, ?)
                ");
                $ins->execute([$assignmentId, $studentId, $relativePath, ($text_answer === "" ? null : $text_answer)]);
              }

              // Notify lecturer
              $lecturerId = (int)$assignment["created_by"];
              $link = "{$base}/lecturer/grade_submissions.php?course_id={$courseId}&assignment_id={$assignmentId}";

              notify_user(
                $pdo,
                $lecturerId,
                "SUBMISSION",
                "New submission received",
                "Student: " . $_SESSION["user"]["full_name"] . "\nAssignment: " . (string)$assignment["title"],
                $link,
                true
              );

              log_activity($pdo, $studentId, "SUBMIT_ASSIGNMENT", "Course: $courseId | Assignment: $assignmentId");

              $pdo->commit();

              header("Location: {$base}/student/submit_assignment.php?course_id={$courseId}&assignment_id={$assignmentId}&ok=1");
              exit;

            } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              $error = "Submission saved but notification failed.";
            }
          }
        }
      }
    }
  }
}

$my->execute([$assignmentId, $studentId]);
$mine = $my->fetch();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Submit Assignment</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">
</head>
<body>

<div class="topbar">
  <div class="brand"><div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Student Portal</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • STUDENT</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="#">📤 Submit Assignment</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2><?= htmlspecialchars((string)$assignment["course_code"]) ?> — <?= htmlspecialchars((string)$assignment["title"]) ?></h2>
          <p>Lecturer: <?= htmlspecialchars((string)$assignment["lecturer_name"]) ?> • Max: <?= (int)$assignment["max_score"] ?> • Due: <?= htmlspecialchars((string)($assignment["due_date"] ?? "-")) ?></p>
        </div>
      </div>

      <p style="color:#374151; margin-bottom:14px;"><?= nl2br(htmlspecialchars((string)$assignment["description"])) ?></p>

      <?php if ($assignment["file_path"]): ?>
        <p><a class="action-link" href="<?= $base ?>/<?= htmlspecialchars((string)$assignment["file_path"]) ?>" target="_blank">Download Assignment File</a></p>
      <?php endif; ?>

      <?php if ($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <?php if ($mine): ?>
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin:12px 0;">
          <b>Your latest submission:</b><br>
          Submitted: <?= htmlspecialchars((string)$mine["submitted_at"]) ?><br>
          <?php if ($mine["file_path"]): ?>
            File: <a class="action-link" href="<?= $base ?>/<?= htmlspecialchars((string)$mine["file_path"]) ?>" target="_blank">Open</a><br>
          <?php endif; ?>
          <?php if ($mine["score"] !== null): ?>
            <div style="margin-top:8px;">
              <b>Score:</b> <?= htmlspecialchars((string)$mine["score"]) ?> / <?= (int)$assignment["max_score"] ?><br>
              <b>Feedback:</b> <?= nl2br(htmlspecialchars((string)($mine["feedback"] ?? ""))) ?>
            </div>
          <?php else: ?>
            <div style="margin-top:8px;color:#6b7280;">Not graded yet.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form class="filters" method="post" enctype="multipart/form-data">
        <textarea name="text_answer" placeholder="Optional: add a short message or text answer..."
          style="width:100%;min-height:90px;padding:12px;border-radius:12px;border:1px solid #e5e7eb;"></textarea>

        <input type="file" name="file" required>
        <button class="btn" type="submit"><?= $mine ? "Resubmit" : "Submit" ?></button>
        <a class="back" href="<?= $base ?>/student/course.php?course_id=<?= $courseId ?>">Back</a>
      </form>

      <div style="margin-top:12px;color:#6b7280;font-size:13px;">
        Allowed: PDF/DOC/DOCX/ZIP • Max size: 15MB
      </div>
    </div>
  </main>
</div>

</body>
</html>

