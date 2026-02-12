<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../config/db.php";

$error = "";
$base = "/education%20system";

/**
 * Detect if a string looks like an MD5 hash (32 hex chars)
 */
function looksLikeMd5(string $value): bool {
  return (bool)preg_match('/^[a-f0-9]{32}$/i', $value);
}

/**
 * Detect if a string looks like a password_hash output
 * (bcrypt $2y$, $2a$, or Argon2 $argon2...)
 */
function looksLikePasswordHash(string $value): bool {
  return str_starts_with($value, '$2y$') ||
         str_starts_with($value, '$2a$') ||
         str_starts_with($value, '$argon2');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST["email"] ?? "");
  $password = (string)($_POST["password"] ?? "");

  if ($email === "" || $password === "") {
    $error = "Email and password are required.";
  } else {
    $stmt = $pdo->prepare("SELECT userID, full_name, email, password, role, must_change_password FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
      $error = "Invalid email or password.";
    } else {
      $stored = (string)$user["password"];
      $ok = false;

      // Case 1: already hashed using password_hash()
      if (looksLikePasswordHash($stored)) {
        $ok = password_verify($password, $stored);
      }
      // Case 2: stored as MD5
      elseif (looksLikeMd5($stored)) {
        $ok = (md5($password) === strtolower($stored));
      }
      //  Case 3: stored as plain text
      else {
        $ok = hash_equals($stored, $password);
      }

      if (!$ok) {
        $error = "Invalid email or password.";
      } else {
        // Auto-upgrade to password_hash if not already hashed
        if (!looksLikePasswordHash($stored)) {
          $newHash = password_hash($password, PASSWORD_DEFAULT);
          $upd = $pdo->prepare("UPDATE users SET password = ? WHERE userID = ? LIMIT 1");
          $upd->execute([$newHash, (int)$user["userID"]]);
        }

        // Create session
        $_SESSION["user"] = [
          "user_id" => (int)$user["userID"],
          "full_name" => $user["full_name"],
          "email" => $user["email"],
          "role" => $user["role"],
        ];

        //  Redirect based on role
        if ($user["role"] === "ADMIN") {
          header("Location: {$base}/admin/dashboard.php");
        } elseif ($user["role"] === "LECTURER") {
          header("Location: {$base}/lecturer/dashboard.php");
        } else {
          header("Location: {$base}/student/dashboard.php");
        }
        exit;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/login.css">
</head>
<body>

<div class="login-container">
  <h1>Academic Collaboration System</h1>

  <?php if ($error): ?>
    <p style="color:#b91c1c;font-weight:800;margin-bottom:10px;">
      <?= htmlspecialchars($error) ?>
    </p>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <div class="input-group">
      <label>Email</label>
      <input type="email" name="email" placeholder="Enter your email" required value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
    </div>

    <div class="input-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Enter your password" required>
    </div>

    <button type="submit" name="login">Login</button>
  </form>

  <p class="note">
    If this is your first login, use the temporary password provided by Admin.
  </p>
</div>

</body>
</html>
