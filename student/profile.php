<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php"; // must ensure user is logged in
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$userId = (int)($_SESSION["user"]["user_id"] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  die("Not logged in.");
}

$msg = "";
$error = "";

// CSRF
if (empty($_SESSION["csrf"])) {
  $_SESSION["csrf"] = bin2hex(random_bytes(16));
}

/**
 * Verify password with compatibility:
 * - bcrypt/argon hashes: password_verify
 * - md5: compare md5($plain)
 * - plain: compare directly
 */
function verifyPasswordCompat(string $plain, string $stored): bool {
  $stored = (string)$stored;

  // Detect modern hash format
  if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
    return password_verify($plain, $stored);
  }

  // Detect MD5 (32 hex chars)
  if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
    return hash_equals(strtolower($stored), md5($plain));
  }

  // Fallback: plain text
  return hash_equals($stored, $plain);
}

/**
 * Upgrade legacy password storage to password_hash()
 * Only call after a successful verification.
 */
function upgradePasswordHash(PDO $pdo, int $userId, string $plain): void {
  $newHash = password_hash($plain, PASSWORD_DEFAULT);
  $upd = $pdo->prepare("UPDATE users SET password=? WHERE userID=? LIMIT 1");
  $upd->execute([$newHash, $userId]);
}

// Fetch latest user info from DB (source of truth)
$stmt = $pdo->prepare("SELECT userID, full_name, email, role, admission_no, password FROM users WHERE userID=? LIMIT 1");
$stmt->execute([$userId]);
$dbUser = $stmt->fetch();
if (!$dbUser) {
  http_response_code(404);
  die("User not found.");
}

$role = (string)$dbUser["role"];
$admissionNo = (string)($dbUser["admission_no"] ?? "");

// Handle profile update (name/email)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_profile") {
  if (!hash_equals($_SESSION["csrf"], (string)($_POST["csrf"] ?? ""))) {
    die("Invalid CSRF token.");
  }

  $full_name = trim((string)($_POST["full_name"] ?? ""));
  $email = trim((string)($_POST["email"] ?? ""));

  if ($full_name === "" || $email === "") {
    $error = "Full name and email are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Enter a valid email address.";
  } else {
    // Email uniqueness (exclude me)
    $check = $pdo->prepare("SELECT 1 FROM users WHERE email=? AND userID<>? LIMIT 1");
    $check->execute([$email, $userId]);
    if ($check->fetch()) {
      $error = "That email is already used by another account.";
    } else {
      $upd = $pdo->prepare("UPDATE users SET full_name=?, email=? WHERE userID=? LIMIT 1");
      $upd->execute([$full_name, $email, $userId]);

      // Refresh session display name/email
      $_SESSION["user"]["full_name"] = $full_name;
      $_SESSION["user"]["email"] = $email;

      $msg = "Profile updated successfully.";

      // Refresh db user
      $stmt->execute([$userId]);
      $dbUser = $stmt->fetch();
      $role = (string)$dbUser["role"];
      $admissionNo = (string)($dbUser["admission_no"] ?? "");
    }
  }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "change_password") {
  if (!hash_equals($_SESSION["csrf"], (string)($_POST["csrf"] ?? ""))) {
    die("Invalid CSRF token.");
  }

  $current = (string)($_POST["current_password"] ?? "");
  $new1 = (string)($_POST["new_password"] ?? "");
  $new2 = (string)($_POST["confirm_password"] ?? "");

  if ($current === "" || $new1 === "" || $new2 === "") {
    $error = "All password fields are required.";
  } elseif (strlen($new1) < 6) {
    $error = "New password must be at least 6 characters.";
  } elseif ($new1 !== $new2) {
    $error = "New password and confirm password do not match.";
  } else {
    $stored = (string)$dbUser["password"];

    if (!verifyPasswordCompat($current, $stored)) {
      $error = "Current password is incorrect.";
    } else {
      // Always store as secure hash
      $newHash = password_hash($new1, PASSWORD_DEFAULT);
      $pupd = $pdo->prepare("UPDATE users SET password=?, must_change_password=0 WHERE userID=? LIMIT 1");
      $pupd->execute([$newHash, $userId]);

      $msg = "Password changed successfully.";

      // Refresh db user
      $stmt->execute([$userId]);
      $dbUser = $stmt->fetch();

      // If legacy format was used, we already replaced it.
    }
  }
}

// Auto-upgrade legacy password hash on login is ideal,
// but you asked for Profile too: we can upgrade here opportunistically.
// (If stored is legacy and user just verified current password, it’s handled above.)

// Stats (role-based)
$stats = [
  "courses" => 0,
  "materials" => 0,
  "announcements" => 0,
  "threads" => 0,
  "replies" => 0,
  "groups" => 0,
  "notifications" => 0,
];

try {
  // Notifications (all)
  $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
  $st->execute([$userId]);
  $stats["notifications"] = (int)$st->fetchColumn();

  if ($role === "STUDENT") {
    $st = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id=?");
    $st->execute([$userId]);
    $stats["courses"] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM study_group_members WHERE student_id=?");
    $st->execute([$userId]);
    $stats["groups"] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM forum_threads WHERE created_by=?");
    $st->execute([$userId]);
    $stats["threads"] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM forum_replies WHERE replied_by=?");
    $st->execute([$userId]);
    $stats["replies"] = (int)$st->fetchColumn();
  }

  if ($role === "LECTURER") {
    $st = $pdo->prepare("SELECT COUNT(*) FROM lecturer_courses WHERE lecturer_id=?");
    $st->execute([$userId]);
    $stats["courses"] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE uploaded_by=?");
    $st->execute([$userId]);
    $stats["materials"] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE posted_by=?");
    $st->execute([$userId]);
    $stats["announcements"] = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM forum_replies WHERE replied_by=?");
    $st->execute([$userId]);
    $stats["replies"] = (int)$st->fetchColumn();
  }

  if ($role === "ADMIN") {
    // Example admin stats: total users + total courses
    $st = $pdo->query("SELECT COUNT(*) FROM users");
    $stats["users"] = (int)$st->fetchColumn();

    $st = $pdo->query("SELECT COUNT(*) FROM courses");
    $stats["courses_total"] = (int)$st->fetchColumn();
  }
} catch (Throwable $e) {
  // Don’t break profile if stats fail
}

function navItem(string $href, string $label, string $base): string {
  return '<li><a href="'.$base.$href.'">'.$label.'</a></li>';
}

// Sidebar links by role
$roleHome = "/student/dashboard.php";
if ($role === "ADMIN") $roleHome = "/admin/dashboard.php";
if ($role === "LECTURER") $roleHome = "/lecturer/dashboard.php";

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/profile.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>
      Academic Collaboration System<br>
      <span class="sub">My Profile</span>
    </div>
  </div>
  <div class="user">
    <div class="pill"><?= htmlspecialchars((string)$_SESSION["user"]["full_name"]) ?> • <?= htmlspecialchars($role) ?></div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base . $roleHome ?>">🏠 Dashboard</a></li>
      <li><a class="active" href="#">👤 Profile</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="grid">
      <!-- Profile card -->
      <div class="card">
        <div class="header">
          <div>
            <h2>Account Details</h2>
            <p>View and update your account information.</p>
          </div>
        </div>

        <?php if ($msg): ?>
          <div class="alert success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
          <input type="hidden" name="action" value="update_profile">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">

          <div class="row">
            <label>Full Name</label>
            <input type="text" name="full_name" required value="<?= htmlspecialchars((string)$dbUser["full_name"]) ?>">
          </div>

          <div class="row">
            <label>Email</label>
            <input type="email" name="email" required value="<?= htmlspecialchars((string)$dbUser["email"]) ?>">
          </div>

          <div class="row">
            <label>Role</label>
            <input type="text" value="<?= htmlspecialchars($role) ?>" disabled>
            <div class="hint">Role cannot be edited from profile.</div>
          </div>

          <?php if ($role === "STUDENT"): ?>
            <div class="row">
              <label>Admission Number</label>
              <input type="text" value="<?= htmlspecialchars($admissionNo) ?>" disabled>
              <div class="hint">Admission number is system-generated.</div>
            </div>
          <?php endif; ?>

          <button class="btn" type="submit">Save Profile</button>
        </form>
      </div>

      <!-- Stats card -->
      <div class="card">
        <div class="header">
          <div>
            <h2>Quick Stats</h2>
            <p>Summary of your activity.</p>
          </div>
        </div>

        <div class="stats">
          <?php if ($role === "ADMIN"): ?>
            <div class="stat"><div class="k">Users</div><div class="v"><?= (int)($stats["users"] ?? 0) ?></div></div>
            <div class="stat"><div class="k">Courses</div><div class="v"><?= (int)($stats["courses_total"] ?? 0) ?></div></div>
          <?php else: ?>
            <div class="stat"><div class="k">Courses</div><div class="v"><?= (int)$stats["courses"] ?></div></div>
          <?php endif; ?>

          <?php if ($role === "LECTURER"): ?>
            <div class="stat"><div class="k">Materials</div><div class="v"><?= (int)$stats["materials"] ?></div></div>
            <div class="stat"><div class="k">Announcements</div><div class="v"><?= (int)$stats["announcements"] ?></div></div>
            <div class="stat"><div class="k">Replies</div><div class="v"><?= (int)$stats["replies"] ?></div></div>
          <?php elseif ($role === "STUDENT"): ?>
            <div class="stat"><div class="k">Study Groups</div><div class="v"><?= (int)$stats["groups"] ?></div></div>
            <div class="stat"><div class="k">Threads</div><div class="v"><?= (int)$stats["threads"] ?></div></div>
            <div class="stat"><div class="k">Replies</div><div class="v"><?= (int)$stats["replies"] ?></div></div>
          <?php endif; ?>

          <div class="stat"><div class="k">Notifications</div><div class="v"><?= (int)$stats["notifications"] ?></div></div>
        </div>
      </div>

      <!-- Password card -->
      <div class="card wide">
        <div class="header">
          <div>
            <h2>Change Password</h2>
            <p>Passwords are stored securely using hashing.</p>
          </div>
        </div>

        <form method="post" class="form grid2">
          <input type="hidden" name="action" value="change_password">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">

          <div class="row">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
            <div class="hint">If your account used MD5/plain before, it will be upgraded automatically after success.</div>
          </div>

          <div class="row">
            <label>New Password</label>
            <input type="password" name="new_password" required minlength="6">
          </div>

          <div class="row">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6">
          </div>

          <div class="row end">
            <button class="btn" type="submit">Update Password</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
