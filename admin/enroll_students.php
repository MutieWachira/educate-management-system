<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$msg = "";
$error = "";

// Dropdown data
$students = $pdo->query("SELECT userID, full_name, email FROM users WHERE role='STUDENT' ORDER BY full_name ASC")->fetchAll();
$courses = $pdo->query("SELECT course_id, course_code, title FROM courses ORDER BY course_code ASC")->fetchAll();

// Enroll
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "enroll") {
  $studentId = (int)($_POST["student_id"] ?? 0);
  $courseId = (int)($_POST["course_id"] ?? 0);

  if ($studentId <= 0 || $courseId <= 0) {
    $error = "Select a student and a course.";
  } else {
    try {
      $ins = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
      $ins->execute([$studentId, $courseId]);
      $msg = "Student enrolled successfully.";
    } catch (Throwable $e) {
      $error = "This student is already enrolled in that course.";
    }
  }
}

// Remove enrollment
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "remove") {
  $enrollId = (int)($_POST["enrollment_id"] ?? 0);
  if ($enrollId > 0) {
    $del = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = ? LIMIT 1");
    $del->execute([$enrollId]);
    $msg = "Enrollment removed.";
  }
}

// List enrollments
$sql = "
  SELECT e.enrollment_id, u.full_name AS student_name, u.email,
         c.course_code, c.title
  FROM enrollments e
  INNER JOIN users u ON u.userID = e.student_id
  INNER JOIN courses c ON c.course_id = e.course_id
  ORDER BY c.course_code ASC, u.full_name ASC
";
$enrollments = $pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Enroll Students</title>
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
      <li><a href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
      <li><a href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
      <li><a href="<?= $base ?>/admin/assign_lecturers.php">🧑‍🏫 Assign Lecturers</a></li>
      <li><a class="active" href="<?= $base ?>/admin/enroll_students.php">🧾 Enroll Students</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Enroll Students</h2>
          <p>Enroll students into courses.</p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post">
        <input type="hidden" name="action" value="enroll">

        <select name="student_id" required>
          <option value="">Select Student</option>
          <?php foreach ($students as $s): ?>
            <option value="<?= (int)$s["userID"] ?>">
              <?= htmlspecialchars($s["full_name"]) ?> (<?= htmlspecialchars($s["email"]) ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <select name="course_id" required>
          <option value="">Select Course</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c["course_id"] ?>">
              <?= htmlspecialchars($c["course_code"]) ?> - <?= htmlspecialchars($c["title"]) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Enroll</button>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Course</th>
              <th>Student</th>
              <th>Email</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$enrollments): ?>
              <tr><td colspan="4">No enrollments yet.</td></tr>
            <?php else: ?>
              <?php foreach ($enrollments as $e): ?>
                <tr>
                  <td><?= htmlspecialchars($e["course_code"]) ?> - <?= htmlspecialchars($e["title"]) ?></td>
                  <td><?= htmlspecialchars($e["student_name"]) ?></td>
                  <td><?= htmlspecialchars($e["email"]) ?></td>
                  <td>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="enrollment_id" value="<?= (int)$e["enrollment_id"] ?>">
                      <button class="action-link danger" type="submit" style="cursor:pointer;">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="footer-row">
        <a class="back" href="<?= $base ?>/admin/dashboard.php">← Back to Dashboard</a>
      </div>
    </div>
  </main>
</div>

</body>
</html>
