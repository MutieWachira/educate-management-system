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

if ($courseId <= 0) die("Invalid course.");

$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not allowed."); }

$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

$msg = "";
$error = "";

if (($_GET["ok"] ?? "") === "1") $msg = "Assignment created and students notified.";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = trim($_POST["title"] ?? "");
  $description = trim($_POST["description"] ?? "");
  $due_date = trim($_POST["due_date"] ?? "");
  $max_score = (int)($_POST["max_score"] ?? 100);

  if ($title === "" || $description === "") {
    $error = "Title and description are required.";
  } elseif ($max_score <= 0) {
    $error = "Max score must be greater than 0.";
  } else {
    $dateVal = ($due_date === "") ? null : $due_date;

    // Optional file
    $relativePath = null;
    if (isset($_FILES["file"]) && is_array($_FILES["file"]) && $_FILES["file"]["error"] !== UPLOAD_ERR_NO_FILE) {
      $file = $_FILES["file"];

      if ($file["error"] !== UPLOAD_ERR_OK) {
        $error = "File upload failed (error code: " . (int)$file["error"] . ").";
      } else {
        $allowedExt = ["pdf","doc","docx"];
        $maxBytes = 15 * 1024 * 1024;

        if ((int)$file["size"] > $maxBytes) {
          $error = "File too large. Max 15MB.";
        } else {
          $originalName = (string)$file["name"];
          $tmp = (string)$file["tmp_name"];
          $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

          if (!in_array($ext, $allowedExt, true)) {
            $error = "Only PDF/DOC/DOCX allowed.";
          } else {
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $safeName = trim($safeName, "_");
            if ($safeName === "") $safeName = "assignment";

            $newName = "Assign_L{$lecturerId}_C{$courseId}_{$safeName}_" . time() . "." . $ext;

            $uploadDir = __DIR__ . "/../assets/uploads/assignments/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $destPath = $uploadDir . $newName;

            if (!move_uploaded_file($tmp, $destPath)) {
              $error = "Failed to save file (check permissions).";
            } else {
              $relativePath = "assets/uploads/assignments/" . $newName;
            }
          }
        }
      }
    }

    if ($error === "") {
      try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare("
          INSERT INTO assignments (course_id, created_by, title, description, due_date, max_score, file_path)
          VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$courseId, $lecturerId, $title, $description, $dateVal, $max_score, $relativePath]);

        $assignmentId = (int)$pdo->lastInsertId();

        // Notify enrolled students
        $studentsStmt = $pdo->prepare("SELECT student_id FROM enrollments WHERE course_id=?");
        $studentsStmt->execute([$courseId]);
        $students = $studentsStmt->fetchAll();

        $link = "{$base}/student/submit_assignment.php?course_id={$courseId}&assignment_id={$assignmentId}";
        $notifTitle = "New Assignment - " . ($course["course_code"] ?? "");
        $notifMessage = "Title: {$title}\nMax Score: {$max_score}\n";
        if ($due_date !== "") $notifMessage .= "Due Date: {$due_date}\n";
        $notifMessage .= "Open to view and submit.";

        foreach ($students as $s) {
          notify_user(
            $pdo,
            (int)$s["student_id"],
            "NEW_ASSIGNMENT",
            $notifTitle,
            $notifMessage,
            $link,
            true
          );
        }

        log_activity($pdo, $lecturerId, "CREATE_ASSIGNMENT", "Course: $courseId | Assignment: $assignmentId | $title");

        $pdo->commit();

        header("Location: {$base}/lecturer/assignments.php?course_id={$courseId}&ok=1");
        exit;

      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Failed to create assignment.";
      }
    }
  }
}

// list assignments
$list = $pdo->prepare("
  SELECT assignment_id, title, due_date, max_score, created_at
  FROM assignments
  WHERE course_id=? AND created_by=?
  ORDER BY assignment_id DESC
");
$list->execute([$courseId, $lecturerId]);
$assignments = $list->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Assignments</title>
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
      <li><a class="active" href="#">📝 Assignments</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Assignments — <?= htmlspecialchars((string)($course["course_code"] ?? "")) ?></h2>
          <p><?= htmlspecialchars((string)($course["title"] ?? "")) ?></p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Assignment title e.g. OOP CAT 1" required>
        <input type="number" name="max_score" min="1" value="100" placeholder="Max Score" required>
        <input type="date" name="due_date" placeholder="Due date (optional)">
        <textarea name="description" placeholder="Instructions..." required style="width:100%;min-height:110px;padding:12px;border-radius:12px;border:1px solid #e5e7eb;"></textarea>
        <input type="file" name="file" accept=".pdf,.doc,.docx">
        <button class="btn" type="submit">Create Assignment</button>
        <a class="back" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">Back</a>
      </form>

      <div class="table-wrap" style="margin-top:16px;">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Due</th>
              <th>Max</th>
              <th>Created</th>
              <th>Grade</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$assignments): ?>
            <tr><td colspan="5">No assignments yet.</td></tr>
          <?php else: foreach ($assignments as $a): ?>
            <tr>
              <td><?= htmlspecialchars((string)$a["title"]) ?></td>
              <td><?= htmlspecialchars((string)($a["due_date"] ?? "-")) ?></td>
              <td><?= (int)$a["max_score"] ?></td>
              <td><?= htmlspecialchars((string)$a["created_at"]) ?></td>
              <td>
                <a class="action-link" href="<?= $base ?>/lecturer/grade_submissions.php?course_id=<?= $courseId ?>&assignment_id=<?= (int)$a["assignment_id"] ?>">
                  Grade Submissions
                </a>
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
