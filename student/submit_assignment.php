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
$studentId = (int)($_SESSION["user"]["user_id"] ?? 0);
$studentName = (string)($_SESSION["user"]["full_name"] ?? "Student");

$courseId = (int)($_GET["course_id"] ?? 0);
$assignmentId = (int)($_GET["assignment_id"] ?? 0);

if ($studentId <= 0) { http_response_code(401); die("Not authenticated."); }
if ($courseId <= 0 || $assignmentId <= 0) die("Invalid request.");

$check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not enrolled."); }

$aq = $pdo->prepare("
  SELECT a.assignment_id, a.title, a.description, a.due_date, a.max_score, a.file_path,
         a.submission_type,
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

$submissionType = (string)($assignment["submission_type"] ?? "INDIVIDUAL");

$msg = "";
$error = "";
if (($_GET["ok"] ?? "") === "1") $msg = "Submission uploaded successfully.";

// If GROUP assignment, find student's group_id for this course (required to see group submission)
$groupId = null;
if ($submissionType === "GROUP") {
  $gs = $pdo->prepare("
    SELECT sgm.group_id
    FROM study_group_members sgm
    INNER JOIN study_groups sg ON sg.group_id = sgm.group_id
    WHERE sgm.student_id=? AND sg.course_id=? LIMIT 1
  ");
  $gs->execute([$studentId, $courseId]);
  $gRow = $gs->fetch();
  if (!$gRow) { http_response_code(403); die("Not allowed (not in a course study group)."); }
  $groupId = (int)$gRow["group_id"];
}

// Load existing submission:
// - INDIVIDUAL: by assignment_id + student_id
// - GROUP: by assignment_id + group_id  (so it reflects to all members)
if ($submissionType === "GROUP") {
  $my = $pdo->prepare("
    SELECT submission_id, file_path, text_answer, submitted_at, score, feedback, graded_at, submitted_by
    FROM submissions
    WHERE assignment_id=? AND group_id=? LIMIT 1
  ");
  $my->execute([$assignmentId, $groupId]);
} else {
  $my = $pdo->prepare("
    SELECT submission_id, file_path, text_answer, submitted_at, score, feedback, graded_at, submitted_by
    FROM submissions
    WHERE assignment_id=? AND student_id=? LIMIT 1
  ");
  $my->execute([$assignmentId, $studentId]);
}
$mine = $my->fetch();

// OPTIONAL UI improvement: show who submitted in group mode (no logic change)
$submittedByName = "";
if ($submissionType === "GROUP" && $mine && !empty($mine["submitted_by"])) {
  $sn = $pdo->prepare("SELECT full_name FROM users WHERE userID=? LIMIT 1");
  $sn->execute([(int)$mine["submitted_by"]]);
  $submittedByName = (string)($sn->fetchColumn() ?: "");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $text_answer = trim((string)($_POST["text_answer"] ?? ""));

  if (!isset($_FILES["file"]) || !is_array($_FILES["file"])) {
    $error = "File is required.";
  } else {
    $file = $_FILES["file"];
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $error = "Upload failed (error code: " . (int)($file["error"] ?? -1) . ").";
    } else {
      $allowedExt = ["pdf","doc","docx","zip"];
      $maxBytes = 15 * 1024 * 1024;

      $size = (int)($file["size"] ?? 0);
      $originalName = (string)($file["name"] ?? "");
      $tmp = (string)($file["tmp_name"] ?? "");

      if ($size > $maxBytes) {
        $error = "File too large. Max 15MB.";
      } else {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
          $error = "Only PDF/DOC/DOCX/ZIP allowed.";
        } else {
          $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
          $safeName = trim((string)$safeName, "_");
          if ($safeName === "") $safeName = "submission";

          // Name includes group or student for clarity
          $suffix = ($submissionType === "GROUP") ? "G{$groupId}" : "S{$studentId}";
          $newName = "Sub_{$suffix}_A{$assignmentId}_" . $safeName . "_" . time() . "." . $ext;

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
                if ($submissionType === "GROUP") {
                  $upd = $pdo->prepare("
                    UPDATE submissions
                    SET file_path=?, text_answer=?, submitted_at=NOW(),
                        submitted_by=?,
                        score=NULL, feedback=NULL, graded_at=NULL, graded_by=NULL
                    WHERE submission_id=? AND group_id=? LIMIT 1
                  ");
                  $upd->execute([
                    $relativePath,
                    ($text_answer === "" ? null : $text_answer),
                    $studentId,
                    (int)$mine["submission_id"],
                    $groupId
                  ]);
                } else {
                  $upd = $pdo->prepare("
                    UPDATE submissions
                    SET file_path=?, text_answer=?, submitted_at=NOW(),
                        submitted_by=?,
                        score=NULL, feedback=NULL, graded_at=NULL, graded_by=NULL
                    WHERE submission_id=? AND student_id=? LIMIT 1
                  ");
                  $upd->execute([
                    $relativePath,
                    ($text_answer === "" ? null : $text_answer),
                    $studentId,
                    (int)$mine["submission_id"],
                    $studentId
                  ]);
                }
              } else {
                if ($submissionType === "GROUP") {
                  $ins = $pdo->prepare("
                    INSERT INTO submissions (assignment_id, student_id, group_id, submitted_by, file_path, text_answer)
                    VALUES (?, ?, ?, ?, ?, ?)
                  ");
                  $ins->execute([
                    $assignmentId,
                    $studentId,
                    $groupId,
                    $studentId,
                    $relativePath,
                    ($text_answer === "" ? null : $text_answer)
                  ]);
                } else {
                  $ins = $pdo->prepare("
                    INSERT INTO submissions (assignment_id, student_id, submitted_by, file_path, text_answer)
                    VALUES (?, ?, ?, ?, ?)
                  ");
                  $ins->execute([
                    $assignmentId,
                    $studentId,
                    $studentId,
                    $relativePath,
                    ($text_answer === "" ? null : $text_answer)
                  ]);
                }
              }

              // Notify lecturer
              $lecturerId = (int)$assignment["created_by"];
              $link = "{$base}/lecturer/grade_submissions.php?course_id={$courseId}&assignment_id={$assignmentId}";

              $note = "Student: {$studentName}\nAssignment: " . (string)$assignment["title"];
              if ($submissionType === "GROUP") $note .= "\nType: GROUP (Group ID: {$groupId})";

              notify_user(
                $pdo,
                $lecturerId,
                "SUBMISSION",
                "New submission received",
                $note,
                $link,
                true
              );

              // Log correctly (no undefined vars)
              $details = "Course: $courseId | Assignment: $assignmentId";
              if ($submissionType === "GROUP") $details .= " | Group: $groupId";
              log_activity($pdo, $studentId, "SUBMIT_ASSIGNMENT", $details);

              $pdo->commit();

              header("Location: {$base}/student/submit_assignment.php?course_id={$courseId}&assignment_id={$assignmentId}&ok=1");
              exit;

            } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              error_log("SUBMIT_ASSIGNMENT ERROR: " . $e->getMessage());
              $error = "Submission failed. Please try again.";
            }
          }
        }
      }
    }
  }
}

// refresh after any submit attempt
if ($submissionType === "GROUP") {
  $my->execute([$assignmentId, $groupId]);
} else {
  $my->execute([$assignmentId, $studentId]);
}
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
  <div class="user"><div class="pill"><?= htmlspecialchars($studentName) ?> • STUDENT</div></div>
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
          <p>
            Lecturer: <?= htmlspecialchars((string)$assignment["lecturer_name"]) ?> •
            Max: <?= (int)$assignment["max_score"] ?> •
            Due: <?= htmlspecialchars((string)($assignment["due_date"] ?? "-")) ?>
            <?php if ($submissionType === "GROUP"): ?>
              • <b>GROUP</b> (Group ID: <?= (int)$groupId ?>)
            <?php endif; ?>
          </p>
        </div>
      </div>

      <p style="color:#374151; margin-bottom:14px;"><?= nl2br(htmlspecialchars((string)$assignment["description"])) ?></p>

      <?php if (!empty($assignment["file_path"])): ?>
        <p><a class="action-link" href="<?= $base ?>/<?= htmlspecialchars((string)$assignment["file_path"]) ?>" target="_blank">Download Assignment File</a></p>
      <?php endif; ?>

      <?php if ($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <?php if ($mine): ?>
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin:12px 0;">
          <b><?= $submissionType === "GROUP" ? "Group submission:" : "Your latest submission:" ?></b><br>
          Submitted: <?= htmlspecialchars((string)$mine["submitted_at"]) ?><br>
          <?php if ($submissionType === "GROUP" && $submittedByName): ?>
            Submitted by: <b><?= htmlspecialchars($submittedByName) ?></b><br>
          <?php endif; ?>
          <?php if (!empty($mine["file_path"])): ?>
            File: <a class="action-link" href="<?= $base ?>/<?= htmlspecialchars((string)$mine["file_path"]) ?>" target="_blank">Open</a><br>
          <?php endif; ?>

          <?php if ($mine["score"] !== null): ?>
            <div style="margin-top:8px;">
              <b>Score:</b> <?= htmlspecialchars((string)$mine["score"]) ?> / <?= (int)$assignment["max_score"] ?><br>
              <b>Feedback:</b> <?= nl2br(htmlspecialchars((string)($mine["feedback"] ?? ""))) ?>
            </div>
          <?php else: ?>
            <div style="margin-top:8px;color:#6b7280;">
              <?= $submissionType === "GROUP" ? "Submitted ✅ (waiting to be graded)" : "Not graded yet." ?>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php if ($submissionType === "GROUP"): ?>
          <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin:12px 0;color:#6b7280;">
            Group work not submitted yet.
          </div>
        <?php endif; ?>
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