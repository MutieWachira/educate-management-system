<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/notifier.php";

$base = "/education%20system";

// Send reminders for events happening tomorrow
$tomorrow = (new DateTime("tomorrow"))->format("Y-m-d");

// Get announcements with event_date = tomorrow AND reminder not sent
$sql = "
  SELECT a.announcement_id, a.course_id, a.title, a.content, a.event_date,
         c.course_code, c.title AS course_title
  FROM announcements a
  INNER JOIN courses c ON c.course_id = a.course_id
  WHERE a.event_date = ? AND a.reminder_sent = 0
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tomorrow]);
$events = $stmt->fetchAll();

if (!$events) {
  echo "No reminders to send.\n";
  exit;
}

try {
  $pdo->beginTransaction();

  foreach ($events as $e) {
    $announcementId = (int)$e["announcement_id"];
    $courseId = (int)$e["course_id"];

    // Students enrolled in that course
    $st = $pdo->prepare("SELECT student_id FROM enrollments WHERE course_id=?");
    $st->execute([$courseId]);
    $students = $st->fetchAll();

    $link = "{$base}/student/announcements.php?course_id={$courseId}";

    $emailTitle = "⏰ Reminder: {$e["course_code"]} event tomorrow";
    $emailMessage =
      "Course: {$e["course_code"]} - {$e["course_title"]}\n" .
      "Event Date: {$e["event_date"]}\n" .
      "Title: {$e["title"]}\n\n" .
      "Message:\n{$e["content"]}";

    foreach ($students as $s) {
      notify_user(
        $pdo,
        (int)$s["student_id"],
        "EVENT_REMINDER",
        $emailTitle,
        $emailMessage,
        $link,
        true // send email
      );
    }

    // Mark as reminder sent (so it won’t resend)
    $upd = $pdo->prepare("UPDATE announcements SET reminder_sent=1 WHERE announcement_id=? LIMIT 1");
    $upd->execute([$announcementId]);
  }

  $pdo->commit();
  echo "Reminder emails sent successfully.\n";
} catch (Throwable $ex) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log("Reminder cron error: " . $ex->getMessage());
  echo "Failed sending reminders.\n";
}
