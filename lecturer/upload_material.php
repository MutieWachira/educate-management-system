<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/notifier.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);


log_activity($pdo, $lecturerId, "UPLOAD_MATERIAL", "Course: $courseId | $title");

if ($courseId <= 0) die("Invalid course.");

// Ensure lecturer assigned
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) {
  http_response_code(403);
  die("You are not assigned to this course.");
}

$msg = "";
$error = "";

// Show success after redirect
if (($_GET["ok"] ?? "") === "1") {
  $msg = "Material uploaded successfully and students notified.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = trim($_POST["title"] ?? "");

  if ($title === "") {
    $error = "Title is required.";
  } elseif (!isset($_FILES["file"]) || !is_array($_FILES["file"])) {
    $error = "File upload failed.";
  } else {
    $file = $_FILES["file"];

    if ($file["error"] !== UPLOAD_ERR_OK) {
      $error = "File upload failed (error code: " . (int)$file["error"] . ").";
    } else {
      // ✅ Limit size (e.g., 15MB)
      $maxBytes = 15 * 1024 * 1024;
      if ((int)$file["size"] > $maxBytes) {
        $error = "File too large. Maximum allowed is 15MB.";
      } else {
        $allowedExt = ["pdf","doc","docx","ppt","pptx"];
        $originalName = (string)$file["name"];
        $tmp = (string)$file["tmp_name"];

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
          $error = "Only PDF/DOC/DOCX/PPT/PPTX files allowed.";
        } else {
          // ✅ Extra MIME validation (helps against fake extensions)
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $mime = $finfo ? finfo_file($finfo, $tmp) : "";
          if ($finfo) finfo_close($finfo);

          $allowedMimes = [
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-powerpoint",
            "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "application/octet-stream" // sometimes appears for office docs
          ];

          if ($mime !== "" && !in_array($mime, $allowedMimes, true)) {
            $error = "Unsupported file type uploaded.";
          } else {
            // Safer file naming
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $safeName = trim($safeName, "_");
            if ($safeName === "") $safeName = "material";

            // Include lecturer + course for uniqueness
            $newName = "Lec{$lecturerId}_C{$courseId}_" . $safeName . "_" . time() . "." . $ext;

            $uploadDir = __DIR__ . "/../assets/uploads/";
            if (!is_dir($uploadDir)) {
              mkdir($uploadDir, 0777, true);
            }

            $destPath = $uploadDir . $newName;

            if (!move_uploaded_file($tmp, $destPath)) {
              $error = "Failed to save uploaded file (check folder permissions).";
            } else {
              // Store relative path
              $relativePath = "assets/uploads/" . $newName;

              try {
                $pdo->beginTransaction();

                // Insert into materials
                $ins = $pdo->prepare("INSERT INTO materials (course_id, uploaded_by, title, file_path) VALUES (?, ?, ?, ?)");
                $ins->execute([$courseId, $lecturerId, $title, $relativePath]);

                // ✅ Notify enrolled students AFTER success
                $studentsStmt = $pdo->prepare("SELECT student_id FROM enrollments WHERE course_id=?");
                $studentsStmt->execute([$courseId]);
                $students = $studentsStmt->fetchAll();

                $link = "{$base}/student/materials.php?course_id={$courseId}";
                $notifTitle = "New course material uploaded";
                $notifMessage = "Title: {$title}\nFile: {$newName}";

                foreach ($students as $s) {
                  notify_user(
                    $pdo,
                    (int)$s["student_id"],
                    "NEW_MATERIAL",
                    $notifTitle,
                    $notifMessage,
                    $link,
                    true
                  );
                }

                $pdo->commit();

                // ✅ Avoid double upload on refresh
                header("Location: {$base}/lecturer/upload_material.php?course_id={$courseId}&ok=1");
                exit;

              } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Material saved but notification failed. Try again.";
                // Optional debug:
                // error_log($e->getMessage());
              }
            }
          }
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Upload Material</title>
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
      <li><a class="active" href="#">⬆ Upload Material</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Upload Material</h2>
          <p>Upload course materials for students.</p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Material title e.g. Week 1 Notes" required>
        <input type="file" name="file" required>
        <button class="btn" type="submit">Upload</button>
        <a class="back" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">Back</a>
      </form>

      <div style="margin-top:12px;color:#6b7280;font-size:13px;">
        Allowed: PDF/DOC/DOCX/PPT/PPTX • Max size: 15MB
      </div>
    </div>
  </main>
</div>

</body>
</html>
