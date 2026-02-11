<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);
$materialId = (int)($_GET["id"] ?? 0);

if ($courseId <= 0 || $materialId <= 0) die("Invalid request.");

// Ensure lecturer assigned
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) {
  http_response_code(403);
  die("Not allowed.");
}

// Fetch material (ensure owned by lecturer)
$stmt = $pdo->prepare("SELECT file_path FROM materials WHERE material_id=? AND course_id=? AND uploaded_by=? LIMIT 1");
$stmt->execute([$materialId, $courseId, $lecturerId]);
$row = $stmt->fetch();

if ($row) {
  // Delete DB record
  $del = $pdo->prepare("DELETE FROM materials WHERE material_id=? LIMIT 1");
  $del->execute([$materialId]);

  // Delete file
  $file = __DIR__ . "/../" . $row["file_path"];
  if (is_file($file)) {
    @unlink($file);
  }
}

header("Location: {$base}/lecturer/materials.php?course_id={$courseId}");
exit;
