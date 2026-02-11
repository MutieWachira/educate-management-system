<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["ADMIN"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Invalid course.");

$departments = $pdo->query("SELECT department_id, name FROM departments ORDER BY name ASC")->fetchAll();

$stmt = $pdo->prepare("SELECT course_id, course_code, title, department_id FROM courses WHERE course_id = ? LIMIT 1");
$stmt->execute([$id]);
$course = $stmt->fetch();
if (!$course) die("Course not found.");

$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $code = strtoupper(trim($_POST["course_code"] ?? ""));
  $title = trim($_POST["title"] ?? "");
  $deptId = (int)($_POST["department_id"] ?? 0);

  if ($code === "" || $title === "" || $deptId <= 0) {
    $error = "Course code, title, and department are required.";
  } else {
    // Unique course code check
    $check = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = ? AND course_id <> ? LIMIT 1");
    $check->execute([$code, $id]);
    if ($check->fetch()) {
      $error = "That course code already exists.";
    } else {
      $upd = $pdo->prepare("UPDATE courses SET course_code=?, title=?, department_id=? WHERE course_id=?");
      $upd->execute([$code, $title, $deptId, $id]);
      $msg = "Course updated.";

      $stmt->execute([$id]);
      $course = $stmt->fetch();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Course</title>
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
      <li><a class="active" href="<?= $base ?>/admin/manage_courses.php">📚 Courses</a></li>
      <li><a href="<?= $base ?>/admin/manage_departments.php">🏫 Departments</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Edit Course</h2>
          <p>Update course details.</p>
        </div>
      </div>

      <?php if ($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <form class="filters" method="post">
        <input type="text" name="course_code" value="<?= htmlspecialchars($course["course_code"]) ?>" required>
        <input type="text" name="title" value="<?= htmlspecialchars($course["title"]) ?>" required>

        <select name="department_id" required>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d["department_id"] ?>"
              <?= ((int)$course["department_id"] === (int)$d["department_id"]) ? "selected" : "" ?>>
              <?= htmlspecialchars($d["name"]) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Save</button>
        <a class="back" href="<?= $base ?>/admin/manage_courses.php">Cancel</a>
      </form>
    </div>
  </main>
</div>

</body>
</html>
