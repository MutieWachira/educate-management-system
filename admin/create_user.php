<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/mail.php";
require_once __DIR__ . "/../includes/logger.php";

$base = "/education%20system";

$msg = "";
$error = "";

// CSRF
if (empty($_SESSION["csrf"])) {
  $_SESSION["csrf"] = bin2hex(random_bytes(16));
}

// For displaying after success
$generatedPassword = "";
$generatedAdmissionNo = "";
$generatedStaffNo = "";
$createdEmail = "";
$createdRole = "";
$createdName = "";
$emailStatus = "";

function generatePasswordFromName(string $fullName): string {
  $fullName = trim($fullName);
  $first = preg_split('/\s+/', $fullName)[0] ?? "User";
  $first = preg_replace('/[^a-zA-Z]/', '', $first);
  if ($first === "") $first = "User";
  $first = ucfirst(strtolower($first));
  return $first . random_int(1000, 9999);
}

/**
 * Admission No = ADM-YYYY-XXXX (unique) for STUDENT
 */
function generateAdmissionNo(PDO $pdo): string {
  $year = date("Y");

  for ($i = 0; $i < 10; $i++) {
    $adm = "ADM-$year-" . random_int(1000, 9999);
    $check = $pdo->prepare("SELECT 1 FROM users WHERE admission_no=? LIMIT 1");
    $check->execute([$adm]);
    if (!$check->fetch()) return $adm;
  }

  return "ADM-$year-" . random_int(10000, 99999);
}

/**
 * Staff No = PREFIX-YYYY-XXXX (unique) for LECTURER/ADMIN
 * Examples: LEC-2026-4831, ADM-2026-1022
 */
function generateStaffNo(PDO $pdo, string $prefix): string {
  $prefix = strtoupper(trim($prefix));
  $year = date("Y");

  for ($i = 0; $i < 10; $i++) {
    $code = $prefix . "-" . $year . "-" . random_int(1000, 9999);
    $check = $pdo->prepare("SELECT 1 FROM users WHERE staff_no=? LIMIT 1");
    $check->execute([$code]);
    if (!$check->fetch()) return $code;
  }

  return $prefix . "-" . $year . "-" . random_int(10000, 99999);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!hash_equals($_SESSION["csrf"], (string)($_POST["csrf"] ?? ""))) {
    die("Invalid CSRF token.");
  }

  $full_name = trim($_POST["full_name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $role = trim($_POST["role"] ?? "");

  if ($full_name === "" || $email === "" || $role === "") {
    $error = "Full name, email and role are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Enter a valid email address.";
  } elseif (!in_array($role, ["ADMIN", "LECTURER", "STUDENT"], true)) {
    $error = "Invalid role selected.";
  } else {
    // email unique
    $check = $pdo->prepare("SELECT userID FROM users WHERE email = ? LIMIT 1");
    $check->execute([$email]);

    if ($check->fetch()) {
      $error = "Email already exists.";
    } else {
      $generatedPassword = generatePasswordFromName($full_name);
      $hashedPassword = password_hash($generatedPassword, PASSWORD_DEFAULT);

      // IDs
      $admissionNo = null;
      $staffNo = null;

      if ($role === "STUDENT") {
        $generatedAdmissionNo = generateAdmissionNo($pdo);
        $admissionNo = $generatedAdmissionNo;
      } elseif ($role === "LECTURER") {
        $generatedStaffNo = generateStaffNo($pdo, "LEC");
        $staffNo = $generatedStaffNo;
      } elseif ($role === "ADMIN") {
        $generatedStaffNo = generateStaffNo($pdo, "ADM"); // or "ADMIN"
        $staffNo = $generatedStaffNo;
      }

      try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
          "INSERT INTO users (full_name, email, admission_no, staff_no, password, role, must_change_password)
           VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([$full_name, $email, $admissionNo, $staffNo, $hashedPassword, $role]);

        log_activity(
          $pdo,
          (int)$_SESSION["user"]["user_id"],
          "CREATE_USER",
          "Created: $email ($role)"
        );

        $pdo->commit();

        // Display info
        $createdEmail = $email;
        $createdRole = $role;
        $createdName = $full_name;

        // Email content
        $subject = "Your account has been created";

        $idLine = "";
        if ($role === "STUDENT" && $generatedAdmissionNo !== "") {
          $idLine = "<p><b>Admission No:</b> " . htmlspecialchars($generatedAdmissionNo) . "</p>";
        }
        if (($role === "LECTURER" || $role === "ADMIN") && $generatedStaffNo !== "") {
          $idLine = "<p><b>Staff No:</b> " . htmlspecialchars($generatedStaffNo) . "</p>";
        }

        $html = "
          <div style='font-family:Arial;line-height:1.6'>
            <h3>Welcome to Academic Collaboration System</h3>
            <p>Your account has been created successfully.</p>
            $idLine
            <p><b>Email:</b> " . htmlspecialchars($email) . "</p>
            <p><b>Temporary Password:</b> " . htmlspecialchars($generatedPassword) . "</p>
            <p>Please login and change your password.</p>
          </div>
        ";

        $sent = send_email($email, $full_name, $subject, $html);
        $emailStatus = $sent ? "✅ Credentials email sent." : "⚠️ Account created, but email failed to send.";

        $msg = "User created successfully!";
        $_POST = [];

      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Failed to create user. Error: " . $e->getMessage();
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
  <title>Create User</title>
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
      <li><a href="<?= $base ?>/admin/manage_users.php">👤 Manage Users</a></li>
      <li><a class="active" href="<?= $base ?>/admin/create_user.php">➕ Create User</a></li>
      <li><a href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
      <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
      <li><a href="<?= $base ?>/admin/assign_lecturers.php">🧑‍🏫 Assign Lecturers</a></li>
      <li><a href="<?= $base ?>/admin/enroll_students.php">🧾 Enroll Students</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Create User</h2>
          <p>Student gets Admission No. Lecturer/Admin get Staff No. Password is generated & hashed.</p>
        </div>
      </div>

      <?php if ($msg): ?>
        <div style="border:1px solid #bbf7d0;background:#ecfdf5;padding:12px;border-radius:12px;margin-bottom:12px;">
          <p style="color:#065f46;font-weight:900;margin:0 0 6px 0;"><?= htmlspecialchars($msg) ?></p>
          <?php if ($emailStatus): ?>
            <p style="margin:0;color:#065f46;font-weight:700;"><?= htmlspecialchars($emailStatus) ?></p>
          <?php endif; ?>

          <p style="margin:8px 0 4px;"><b>Name:</b> <?= htmlspecialchars($createdName) ?></p>
          <p style="margin:4px 0;"><b>Email:</b> <?= htmlspecialchars($createdEmail) ?></p>
          <p style="margin:4px 0;"><b>Role:</b> <?= htmlspecialchars($createdRole) ?></p>

          <?php if ($generatedAdmissionNo !== ""): ?>
            <p style="margin:8px 0 4px;"><b>Admission No:</b>
              <span style="font-family:monospace;background:#d1fae5;padding:4px 8px;border-radius:8px;">
                <?= htmlspecialchars($generatedAdmissionNo) ?>
              </span>
            </p>
          <?php endif; ?>

          <?php if ($generatedStaffNo !== ""): ?>
            <p style="margin:8px 0 4px;"><b>Staff No:</b>
              <span style="font-family:monospace;background:#d1fae5;padding:4px 8px;border-radius:8px;">
                <?= htmlspecialchars($generatedStaffNo) ?>
              </span>
            </p>
          <?php endif; ?>

          <?php if ($generatedPassword !== ""): ?>
            <p style="margin:8px 0 4px;"><b>Temporary Password:</b>
              <span style="font-family:monospace;background:#d1fae5;padding:4px 8px;border-radius:8px;">
                <?= htmlspecialchars($generatedPassword) ?>
              </span>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div style="border:1px solid #fecaca;background:#fef2f2;padding:12px;border-radius:12px;margin-bottom:12px;">
          <p style="color:#b91c1c;font-weight:900;margin:0;"><?= htmlspecialchars($error) ?></p>
        </div>
      <?php endif; ?>

      <form method="post" class="filters" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">

        <input type="text" name="full_name" placeholder="Full Name" required
               value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>">

        <input type="email" name="email" placeholder="Email Address" required
               value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">

        <select name="role" required>
          <option value="">Select Role</option>
          <?php $sel = $_POST["role"] ?? ""; ?>
          <option value="STUDENT"  <?= $sel==="STUDENT" ? "selected" : "" ?>>STUDENT</option>
          <option value="LECTURER" <?= $sel==="LECTURER" ? "selected" : "" ?>>LECTURER</option>
          <option value="ADMIN"    <?= $sel==="ADMIN" ? "selected" : "" ?>>ADMIN</option>
        </select>

        <button class="btn" type="submit">Create Account</button>
        <a class="back" href="<?= $base ?>/admin/manage_users.php">View Users</a>
      </form>

      <div style="margin-top:12px;color:#6b7280;font-size:13px;">
        <b>Password rule:</b> First name + 4 digits (e.g., John4821).<br>
        <b>Student ID:</b> ADM-YYYY-XXXX • <b>Staff ID:</b> LEC-YYYY-XXXX / ADM-YYYY-XXXX
      </div>
    </div>
  </main>
</div>

</body>
</html>
