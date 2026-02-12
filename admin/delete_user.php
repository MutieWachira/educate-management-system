<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$userId = (int)($_GET["id"] ?? 0);
if ($userId <= 0) {
  http_response_code(400);
  die("Invalid user ID.");
}

// Prevent deleting yourself
$loggedInId = (int)($_SESSION["user"]["user_id"] ?? 0);
if ($userId === $loggedInId) {
  die("You cannot delete your own admin account while logged in.");
}

// Fetch user for confirmation
$stmt = $pdo->prepare("SELECT userID, full_name, email, role FROM users WHERE userID = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
  http_response_code(404);
  die("User not found.");
}

$error = "";

// Helper: safe delete
function execDelete(PDO $pdo, string $sql, array $params): void {
  $st = $pdo->prepare($sql);
  $st->execute($params);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  try {
    $pdo->beginTransaction();

    // 1) Remove enrollments (student)
    execDelete($pdo, "DELETE FROM enrollments WHERE student_id = ?", [$userId]);

    // 2) Remove lecturer assignments (lecturer)
    execDelete($pdo, "DELETE FROM lecturer_courses WHERE lecturer_id = ?", [$userId]);

    // 3) Study groups: remove memberships first, then groups created by user
    execDelete($pdo, "DELETE FROM study_group_members WHERE student_id = ?", [$userId]);

    // If the user created groups, remove members of those groups, then the groups
    // (Prevents FK problems if study_group_members references study_groups)
    $groupIdsStmt = $pdo->prepare("SELECT group_id FROM study_groups WHERE created_by = ?");
    $groupIdsStmt->execute([$userId]);
    $groupIds = $groupIdsStmt->fetchAll(PDO::FETCH_COLUMN);

    if ($groupIds) {
      foreach ($groupIds as $gid) {
        execDelete($pdo, "DELETE FROM study_group_members WHERE group_id = ?", [(int)$gid]);
      }
      execDelete($pdo, "DELETE FROM study_groups WHERE created_by = ?", [$userId]);
    }

    // 4) Forum: delete replies made by user, then threads made by user
    execDelete($pdo, "DELETE FROM forum_replies WHERE replied_by = ?", [$userId]);

    // If threads have replies FK, delete replies under threads created by this user
    $threadIdsStmt = $pdo->prepare("SELECT thread_id FROM forum_threads WHERE created_by = ?");
    $threadIdsStmt->execute([$userId]);
    $threadIds = $threadIdsStmt->fetchAll(PDO::FETCH_COLUMN);

    if ($threadIds) {
      foreach ($threadIds as $tid) {
        execDelete($pdo, "DELETE FROM forum_replies WHERE thread_id = ?", [(int)$tid]);
      }
      execDelete($pdo, "DELETE FROM forum_threads WHERE created_by = ?", [$userId]);
    }

    // 5) Announcements/materials posted by user (lecturer)
    execDelete($pdo, "DELETE FROM announcements WHERE posted_by = ?", [$userId]);
    execDelete($pdo, "DELETE FROM materials WHERE uploaded_by = ?", [$userId]);

    // 6) Notifications for user
    execDelete($pdo, "DELETE FROM notifications WHERE user_id = ?", [$userId]);

    // 7) Finally delete user
    execDelete($pdo, "DELETE FROM users WHERE userID = ? LIMIT 1", [$userId]);

    $pdo->commit();

    header("Location: {$base}/admin/manage_users.php");
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    // Friendly message + keep debug in logs
    error_log("Delete user failed: " . $e->getMessage());
    $error = "Cannot delete this user because they are linked to other records. Remove related data first or contact admin.";
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Delete User</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/manage_users.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Admin Panel</span></div>
  </div>
  <div class="user">
    <div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • ADMIN</div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/admin/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="<?= $base ?>/admin/manage_users.php">👤 Manage Users</a></li>
      <li><a href="<?= $base ?>/admin/create_user.php">➕ Create User</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Confirm Delete</h2>
          <p>This action cannot be undone.</p>
        </div>
      </div>

      <?php if ($error): ?>
        <div style="border:1px solid #fecaca;background:#fef2f2;padding:12px;border-radius:12px;margin-bottom:12px;">
          <p style="color:#b91c1c;font-weight:900;margin:0;"><?= htmlspecialchars($error) ?></p>
        </div>
      <?php endif; ?>

      <p><b>User:</b> <?= htmlspecialchars($user["full_name"]) ?></p>
      <p><b>Email:</b> <?= htmlspecialchars($user["email"]) ?></p>
      <p><b>Role:</b> <?= htmlspecialchars($user["role"]) ?></p>

      <form method="post" style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn" type="submit" style="background:rgba(239,68,68,0.12);color:#b91c1c;border-color:rgba(239,68,68,0.25);">
          Confirm Delete
        </button>
        <a class="back" href="<?= $base ?>/admin/manage_users.php">Cancel</a>
      </form>
    </div>
  </main>
</div>

</body>
</html>
