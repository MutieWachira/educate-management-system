<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST["email"] ?? "");
  $password = $_POST["password"] ?? "";

  if ($email === "" || $password === "") {
    $error = "Email and password are required.";
  } else {
    $stmt = $pdo->prepare("SELECT userID, full_name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Plain text compare (because DB passwords are plain)
    if (!$user || $password !== $user["password"]) {
      $error = "Invalid email or password.";
    } else {
      $_SESSION["user"] = [
        "user_id" => (int)$user["userID"],
        "full_name" => $user["full_name"],
        "email" => $user["email"],
        "role" => $user["role"],
      ];

      // Fix redirect paths (change base to your actual folder name)
      $base = "/education%20system";

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
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMS Login</title>
    <link rel="stylesheet" href="/education%20system/assets/css/login.css">
</head>
<body>
    <!--add a backgound image on the login form-->
      <div class="login-container">
    <h1>Educade Management System</h1>

    <form method="POST">
      <div class="input-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="Enter your email" required>
      </div>

      <div class="input-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" required>
      </div>

      <button type="submit" name="login">Login</button>
    </form>

    <p class="note">Demo accounts: admin@hms.com | doctor@hms.com | patient@hms.com</p>
  </div>
</body>
</html>