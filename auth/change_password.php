<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION["user"])) {
  header("Location: /education%20system/auth/login.php");
  exit;
}

$base = "/education%20system";
$userId = (int)$_SESSION["user"]["user_id"];
$role = (string)$_SESSION["user"]["role"];

$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $new1 = (string)($_POST["new_password"] ?? "");
  $new2 = (string)($_POST["confirm_password"] ?? "");

  if (trim($new1) === "" || trim($new2) === "") {
    $error = "All fields are required.";
  } elseif (strlen($new1) < 6) {
    $error = "Password must be at least 6 characters.";
  } elseif ($new1 !== $new2) {
    $error = "Passwords do not match.";
  } else {
    $hash = password_hash($new1, PASSWORD_DEFAULT);

    $upd = $pdo->prepare("UPDATE users SET password=?, must_change_password=0 WHERE userID=? LIMIT 1");
    $upd->execute([$hash, $userId]);

    log_activity($pdo, $userId, "PASSWORD_CHANGED", "User changed password");

    // Redirect back to role dashboard
    if ($role === "ADMIN") header("Location: {$base}/admin/dashboard.php");
    elseif ($role === "LECTURER") header("Location: {$base}/lecturer/dashboard.php");
    else header("Location: {$base}/student/dashboard.php");
    exit;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Change Password</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <h1>Change Password</h1>
    <p class="note">For security, please set a new password before continuing.</p>

    <?php if ($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="post">
      <div class="input-group">
        <label>New Password</label>
        <input type="password" name="new_password" required placeholder="Enter new password">
      </div>

      <div class="input-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required placeholder="Confirm new password">
      </div>

      <button type="submit">Update Password</button>
    </form>
  </div>
</body>
</html>
