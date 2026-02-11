<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$msg = "";
$error = "";

// Dropdown data
$lecturers = $pdo->query("SELECT userID, full_name, email FROM users WHERE role='LECTURER' ORDER BY full_name ASC")->fetchAll();
$courses = $pdo->query("SELECT course_id, course_code, title FROM courses ORDER BY course_code ASC")->fetchAll();

// Assign
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "assign") {
  $lecturerId = (int)($_POST["lecturer_id"] ?? 0);
  $courseId = (int)($_POST["course_id"] ?? 0);

  if ($lecturerId <= 0 || $courseId <= 0) {
    $error = "Select a lecturer and a course.";
  } else {
    try {
      $ins = $pdo->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
      $ins->execute([$lecturerId, $courseId]);
      $msg = "Lecturer assigned successfully.";
    } catch (Throwable $e) {
      $error = "This lecturer is already assigned to that course.";
    }
  }
}

// Remove assignment
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "remove") {
  $lcId = (int)($_POST["lecturer_course_id"] ?? 0);
  if ($lcId > 0) {
    $del = $pdo->prepare("DELETE FROM lecturer_courses WHERE lecturer_course_id = ? LIMIT 1");
    $del->execute([$lcId]);
    $msg = "Assignment removed.";
  }
}

// List assignments
$sql = "
  SELECT lc.lecturer_course_id, u.full_name AS lecturer_name, u.email,
         c.course_code, c.title
  FROM lecturer_courses lc
  INNER JOIN users u ON u.userID = lc.lecturer_id
  INNER JOIN courses c ON c.course_id = lc.course_id
  ORDER BY c.course_code ASC, u.full_name ASC
";
$assignments = $pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Assign Lecturers</title>
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
      <li><a class="active" href="<?= $base ?>/admin/assign_lecturers.php">🧑‍🏫 Assign Lecturers</a></li>
      <li><a href="<?= $base ?>/admin/enroll_students.php">🧾 Enroll Students</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Assign Lecturers</h2>
          <p>Assign lecturers to courses.</p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post">
        <input type="hidden" name="action" value="assign">

        <select name="lecturer_id" required>
          <option value="">Select Lecturer</option>
          <?php foreach ($lecturers as $l): ?>
            <option value="<?= (int)$l["userID"] ?>">
              <?= htmlspecialchars($l["full_name"]) ?> (<?= htmlspecialchars($l["email"]) ?>)
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

        <button class="btn" type="submit">Assign</button>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Course</th>
              <th>Lecturer</th>
              <th>Email</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$assignments): ?>
              <tr><td colspan="4">No lecturer assignments yet.</td></tr>
            <?php else: ?>
              <?php foreach ($assignments as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a["course_code"]) ?> - <?= htmlspecialchars($a["title"]) ?></td>
                  <td><?= htmlspecialchars($a["lecturer_name"]) ?></td>
                  <td><?= htmlspecialchars($a["email"]) ?></td>
                  <td>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="lecturer_course_id" value="<?= (int)$a["lecturer_course_id"] ?>">
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
