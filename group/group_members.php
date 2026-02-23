<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$userId = (int)($_SESSION["user"]["user_id"] ?? 0);
$role   = (string)($_SESSION["user"]["role"] ?? "");

$courseId = (int)($_GET["course_id"] ?? 0);
$groupId  = (int)($_GET["group_id"] ?? 0);

if ($courseId <= 0 || $groupId <= 0) die("Invalid request.");

// ✅ Confirm group belongs to the course
$gq = $pdo->prepare("
  SELECT group_id, name, description, created_by, created_at
  FROM study_groups
  WHERE group_id=? AND course_id=? LIMIT 1
");
$gq->execute([$groupId, $courseId]);
$group = $gq->fetch();
if (!$group) die("Group not found.");

// ✅ Access rules:
// Student: must be enrolled in course
// Lecturer: must be assigned to course
if ($role === "STUDENT") {
  $check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
  $check->execute([$userId, $courseId]);
  if (!$check->fetch()) { http_response_code(403); die("Not enrolled."); }

} elseif ($role === "LECTURER") {
  $check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
  $check->execute([$userId, $courseId]);
  if (!$check->fetch()) { http_response_code(403); die("Not assigned to this course."); }

} else {
  http_response_code(403);
  die("Not allowed.");
}

// ✅ Fetch members
$membersStmt = $pdo->prepare("
  SELECT u.userID, u.full_name, u.email, u.admission_no, m.joined_at
  FROM study_group_members m
  INNER JOIN users u ON u.userID = m.student_id
  WHERE m.group_id=?
  ORDER BY m.joined_at ASC
");
$membersStmt->execute([$groupId]);
$members = $membersStmt->fetchAll();

// ✅ Back link based on role
$backUrl = ($role === "LECTURER")
  ? "{$base}/lecturer/course.php?course_id={$courseId}"
  : "{$base}/student/study_groups.php?course_id={$courseId}";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Group Members</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/<?= $role === "LECTURER" ? "lecturer" : "student" ?>.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>
      Academic Collaboration System<br>
      <span style="font-size:12px;opacity:.85;"><?= $role === "LECTURER" ? "Lecturer Panel" : "Student Portal" ?></span>
    </div>
  </div>
  <div class="user">
    <div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • <?= htmlspecialchars($role) ?></div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a class="active" href="#">👥 Group Members</a></li>
      <li><a href="<?= $backUrl ?>">← Back</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2><?= htmlspecialchars((string)$group["name"]) ?> — Members</h2>
          <p style="color:#6b7280;">
            <?= htmlspecialchars((string)($group["description"] ?? "No description")) ?>
          </p>
        </div>
      </div>

      <div class="table-wrap" style="margin-top:14px;">
        <table>
          <thead>
            <tr>
              <th>Member</th>
              <th>Admission No</th>
              <th>Email</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$members): ?>
            <tr><td colspan="4">No members yet.</td></tr>
          <?php else: foreach ($members as $m): ?>
            <tr>
              <td><?= htmlspecialchars((string)$m["full_name"]) ?></td>
              <td><?= htmlspecialchars((string)($m["admission_no"] ?? "-")) ?></td>
              <td><?= htmlspecialchars((string)$m["email"]) ?></td>
              <td><?= htmlspecialchars((string)$m["joined_at"]) ?></td>
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