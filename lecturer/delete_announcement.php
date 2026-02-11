<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);
$id = (int)($_GET["id"] ?? 0);

if ($courseId <= 0 || $id <= 0) die("Invalid request.");

$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not allowed."); }

$del = $pdo->prepare("DELETE FROM announcements WHERE announcement_id=? AND course_id=? AND posted_by=? LIMIT 1");
$del->execute([$id, $courseId, $lecturerId]);

header("Location: {$base}/lecturer/announcements.php?course_id={$courseId}");
exit;
