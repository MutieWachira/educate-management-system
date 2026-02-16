<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/logger.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];
$courseId = (int)($_GET["course_id"] ?? 0);
if ($courseId <= 0) die("Invalid course.");

// Ensure lecturer assigned
$check = $pdo->prepare("SELECT 1 FROM lecturer_courses WHERE lecturer_id=? AND course_id=? LIMIT 1");
$check->execute([$lecturerId, $courseId]);
if (!$check->fetch()) { http_response_code(403); die("Not allowed."); }

// CSRF
if (empty($_SESSION["csrf"])) $_SESSION["csrf"] = bin2hex(random_bytes(16));

$msg = "";
$error = "";

// Create / update category
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!hash_equals($_SESSION["csrf"], (string)($_POST["csrf"] ?? ""))) die("Invalid CSRF token.");

  $action = (string)($_POST["action"] ?? "");
  if ($action === "save") {
    $name = trim((string)($_POST["name"] ?? ""));
    $weight = (float)($_POST["weight"] ?? 0);

    if ($name === "") $error = "Category name required.";
    elseif ($weight < 0 || $weight > 100) $error = "Weight must be between 0 and 100.";
    else {
      $stmt = $pdo->prepare("
        INSERT INTO grade_categories (course_id, name, weight)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE weight=VALUES(weight)
      ");
      $stmt->execute([$courseId, $name, $weight]);

      log_activity($pdo, $lecturerId, "UPDATE_GRADE_CATEGORY", "Course: $courseId | $name = $weight%");
      $msg = "Saved category.";
    }
  }

  // Assign category to an assignment
  if ($action === "assign") {
    $assignmentId = (int)($_POST["assignment_id"] ?? 0);
    $categoryId = (int)($_POST["category_id"] ?? 0);

    if ($assignmentId <= 0) $error = "Invalid assignment.";
    else {
      $upd = $pdo->prepare("
        UPDATE assignments
        SET category_id=?
        WHERE assignment_id=? AND course_id=? AND created_by=?
        LIMIT 1
      ");
      $upd->execute([$categoryId ?: null, $assignmentId, $courseId, $lecturerId]);
      $msg = "Assignment category updated.";
    }
  }
}

// Course info
$cq = $pdo->prepare("SELECT course_code, title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course = $cq->fetch();

// Categories
$cats = $pdo->prepare("SELECT category_id, name, weight FROM grade_categories WHERE course_id=? ORDER BY name ASC");
$cats->execute([$courseId]);
$categories = $cats->fetchAll();

// Lecturer assignments
$as = $pdo->prepare("
  SELECT assignment_id, title, max_score, category_id
  FROM assignments
  WHERE course_id=? AND created_by=?
  ORDER BY assignment_id DESC
");
$as->execute([$courseId, $lecturerId]);
$assignments = $as->fetchAll();

// Total weight
$totalWeight = 0.0;
foreach ($categories as $c) $totalWeight += (float)$c["weight"];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Grade Categories</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/lecturer.css">
</head>
<body>
<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Lecturer Panel</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • LECTURER</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/lecturer/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="#">📊 Grade Categories</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Grade Categories — <?= htmlspecialchars($course["course_code"] ?? "") ?></h2>
          <p><?= htmlspecialchars($course["title"] ?? "") ?></p>
          <p style="color:#6b7280;font-size:13px;">Total weight: <b><?= number_format($totalWeight, 2) ?>%</b> (recommended = 100%)</p>
        </div>
        <div>
          <a class="btn" href="<?= $base ?>/lecturer/course.php?course_id=<?= $courseId ?>">← Back</a>
        </div>
      </div>

      <?php if($msg): ?><p style="color:green;font-weight:800;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if($error): ?><p style="color:#b91c1c;font-weight:800;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <h3 style="margin-top:10px;">Create / Update Category</h3>
      <form class="filters" method="post" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
        <input type="hidden" name="action" value="save">
        <input type="text" name="name" placeholder="Category e.g. CAT, QUIZ, ASSIGNMENT" required>
        <input type="number" step="0.01" name="weight" placeholder="Weight e.g. 30" required>
        <button class="btn" type="submit">Save</button>
      </form>

      <h3 style="margin-top:18px;">Assign Category to Assignment</h3>
      <form class="filters" method="post" style="margin-top:10px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
        <input type="hidden" name="action" value="assign">

        <select name="assignment_id" required>
          <option value="">Select Assignment</option>
          <?php foreach ($assignments as $a): ?>
            <option value="<?= (int)$a["assignment_id"] ?>">
              <?= htmlspecialchars((string)$a["title"]) ?> (Max <?= (int)$a["max_score"] ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <select name="category_id">
          <option value="0">No category</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c["category_id"] ?>">
              <?= htmlspecialchars((string)$c["name"]) ?> (<?= number_format((float)$c["weight"], 2) ?>%)
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Assign</button>
      </form>

      <div class="table-wrap" style="margin-top:16px;">
        <table>
          <thead><tr><th>Category</th><th>Weight (%)</th></tr></thead>
          <tbody>
          <?php if(!$categories): ?>
            <tr><td colspan="2">No categories yet.</td></tr>
          <?php else: foreach($categories as $c): ?>
            <tr>
              <td><?= htmlspecialchars((string)$c["name"]) ?></td>
              <td><?= number_format((float)$c["weight"], 2) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:12px;color:#6b7280;font-size:13px;">
        Tip: A common setup is <b>CAT 30%</b>, <b>Assignments 20%</b>, <b>Quiz 10%</b>, <b>Exam 40%</b>.
      </div>
    </div>
  </main>
</div>
</body>
</html>
