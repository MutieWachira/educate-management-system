<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = trim($_POST["title"] ?? "");

  if ($title === "") {
    $error = "Title is required.";
  } elseif (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    $error = "File upload failed.";
  } else {
    $allowed = ["pdf","doc","docx","ppt","pptx"];
    $originalName = $_FILES["file"]["name"];
    $tmp = $_FILES["file"]["tmp_name"];

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
      $error = "Only PDF/DOC/DOCX/PPT/PPTX files allowed.";
    } else {
      $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
      $newName = $safeName . "_" . time() . "." . $ext;

      $uploadDir = __DIR__ . "/../assets/uploads/";
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }

      $destPath = $uploadDir . $newName;
      if (!move_uploaded_file($tmp, $destPath)) {
        $error = "Failed to save uploaded file.";
      } else {
        // Store relative path
        $relativePath = "assets/uploads/" . $newName;

        $ins = $pdo->prepare("INSERT INTO materials (course_id, uploaded_by, title, file_path) VALUES (?, ?, ?, ?)");
        $ins->execute([$courseId, $lecturerId, $title, $relativePath]);

        $msg = "Material uploaded successfully.";
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

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Material title e.g. Week 1 Notes" required>
        <input type="file" name="file" required>
        <button class="btn" type="submit">Upload</button>
        <a class="back" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">Back</a>
      </form>
    </div>
  </main>
</div>

</body>
</html>
