<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$msg = "";
$error = "";

// Departments for dropdown
$departments = $pdo->query("SELECT department_id, name FROM departments ORDER BY name ASC")->fetchAll();

// Add course
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add") {
  $code = strtoupper(trim($_POST["course_code"] ?? ""));
  $title = trim($_POST["title"] ?? "");
  $deptId = (int)($_POST["department_id"] ?? 0);

  if ($code === "" || $title === "" || $deptId <= 0) {
    $error = "Course code, title, and department are required.";
  } else {
    $check = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = ? LIMIT 1");
    $check->execute([$code]);
    if ($check->fetch()) {
      $error = "That course code already exists.";
    } else {
      $ins = $pdo->prepare("INSERT INTO courses (course_code, title, department_id) VALUES (?, ?, ?)");
      $ins->execute([$code, $title, $deptId]);
      $msg = "Course added successfully.";
    }
  }
}

// Delete course
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
  $courseId = (int)($_POST["course_id"] ?? 0);
  if ($courseId > 0) {
    try {
      $del = $pdo->prepare("DELETE FROM courses WHERE course_id = ? LIMIT 1");
      $del->execute([$courseId]);
      $msg = "Course deleted.";
    } catch (Throwable $e) {
      $error = "Cannot delete course because it is linked to other records.";
    }
  }
}

// Fetch courses list
$sql = "
  SELECT c.course_id, c.course_code, c.title, d.name AS department_name
  FROM courses c
  INNER JOIN departments d ON d.department_id = c.department_id
  ORDER BY d.name ASC, c.course_code ASC
";
$courses = $pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Courses</title>
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
      <li><a href="<?= $base ?>/admin/create_user.php">➕ Create User</a></li>
      <li><a href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
      <li><a class="active" href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Manage Courses</h2>
          <p>Create and manage courses under departments.</p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <!-- Add course -->
      <form class="filters" method="post">
        <input type="hidden" name="action" value="add">

        <input type="text" name="course_code" placeholder="Course code e.g. CSC101" required>
        <input type="text" name="title" placeholder="Course title e.g. Introduction to Programming" required>

        <select name="department_id" required>
          <option value="">Select Department</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d["department_id"] ?>"><?= htmlspecialchars($d["name"]) ?></option>
          <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Add Course</button>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Title</th>
              <th>Department</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$courses): ?>
            <tr><td colspan="4">No courses added yet.</td></tr>
          <?php else: ?>
            <?php foreach ($courses as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c["course_code"]) ?></td>
                <td><?= htmlspecialchars($c["title"]) ?></td>
                <td><?= htmlspecialchars($c["department_name"]) ?></td>
                <td>
                  <div class="action-links">
                    <a class="action-link" href="<?= $base ?>/admin/edit_course.php?id=<?= (int)$c["course_id"] ?>">Edit</a>

                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="course_id" value="<?= (int)$c["course_id"] ?>">
                      <button class="action-link danger" type="submit" style="cursor:pointer;">Delete</button>
                    </form>
                  </div>
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
