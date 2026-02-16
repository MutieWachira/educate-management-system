<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$studentId = (int)$_SESSION["user"]["user_id"];

$msg = "";
$error = "";

// Enroll action
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "enroll") {
  $courseId = (int)($_POST["course_id"] ?? 0);
  if ($courseId <= 0) {
    $error = "Invalid course.";
  } else {
    try {
      $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
      $stmt->execute([$studentId, $courseId]);
      $msg = "Enrolled successfully!";
    } catch (Throwable $e) {
      $error = "You are already enrolled (or enrollment failed).";
    }
  }
}

// Fetch courses + whether student is enrolled
$sql = "
  SELECT c.course_id, c.course_code, c.title, d.name AS department_name,
         EXISTS(
           SELECT 1 FROM enrollments e
           WHERE e.course_id = c.course_id AND e.student_id = ?
         ) AS is_enrolled
  FROM courses c
  INNER JOIN departments d ON d.department_id = c.department_id
  ORDER BY c.course_code ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId]);
$courses = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Course Hub</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">

  <style>
    /* Card layout */
    .hub-top { display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-top:12px; }
    .hub-search {
      flex:1; min-width:240px;
      padding:12px 14px;
      border:1px solid #e5e7eb;
      border-radius:12px;
      outline:none;
    }
    .grid {
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap:14px;
      margin-top:16px;
    }
    .course-card {
      border:1px solid #e5e7eb;
      border-radius:16px;
      padding:14px;
      background:#fff;
      box-shadow: 0 8px 18px rgba(0,0,0,0.04);
    }
    .course-code { font-weight:900; letter-spacing:.5px; }
    .course-title { margin:6px 0 10px; font-weight:700; color:#111827; }
    .dept { color:#6b7280; font-size:13px; }
    .card-actions { margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .pill {
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      background:#f3f4f6;
      font-size:12px;
      font-weight:800;
      color:#111827;
    }
    .pill.green { background:#ecfdf5; color:#065f46; border:1px solid #bbf7d0; }
    .muted { color:#6b7280; font-size:13px; }
    .hidden { display:none !important; }
  </style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Student Portal</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • STUDENT</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="<?= $base ?>/student/course_hub.php">📚 Course Hub</a></li>
      <li><a href="<?= $base ?>/student/grades.php">📈 Grades</a></li>
      <li><a href="<?= $base ?>/student/calendar.php">🗓 Calendar</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Course Hub</h2>
          <p>Search courses and enroll instantly.</p>

          <div class="hub-top">
            <input id="courseSearch" class="hub-search" type="text"
                   placeholder="Search by code, title, or department..." autocomplete="off">
            <span class="pill" id="countPill">0</span>
          </div>

          <?php if ($msg): ?><p style="color:green;font-weight:800;margin-top:10px;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
          <?php if ($error): ?><p style="color:#b91c1c;font-weight:800;margin-top:10px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        </div>
      </div>

      <div class="grid" id="courseGrid">
        <?php foreach ($courses as $c): ?>
          <?php
            $search = strtolower(trim(($c["course_code"] ?? "")." ".($c["title"] ?? "")." ".($c["department_name"] ?? "")));
            $isEnrolled = ((int)$c["is_enrolled"] === 1);
          ?>
          <div class="course-card course-item"
               data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
            <div class="course-code"><?= htmlspecialchars($c["course_code"]) ?></div>
            <div class="course-title"><?= htmlspecialchars($c["title"]) ?></div>
            <div class="dept">Department: <?= htmlspecialchars($c["department_name"]) ?></div>

            <div class="card-actions">
              <?php if ($isEnrolled): ?>
                <span class="pill green">Enrolled</span>
                <a class="action-link" href="<?= $base ?>/student/course.php?course_id=<?= (int)$c["course_id"] ?>">Open</a>
              <?php else: ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="enroll">
                  <input type="hidden" name="course_id" value="<?= (int)$c["course_id"] ?>">
                  <button class="btn" type="submit">Enroll</button>
                </form>
              <?php endif; ?>
            </div>

            <div class="muted" style="margin-top:10px;">
              Tip: After enrolling, open the course to access materials, announcements, forum and study groups.
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="muted hidden" id="noMatch" style="margin-top:12px;">No course matches your search.</p>
    </div>
  </main>
</div>

<script>
(() => {
  const input = document.getElementById('courseSearch');
  const items = Array.from(document.querySelectorAll('.course-item'));
  const countPill = document.getElementById('countPill');
  const noMatch = document.getElementById('noMatch');

  function applyFilter(){
    const q = (input.value || '').trim().toLowerCase();
    let visible = 0;

    items.forEach(card => {
      const hay = card.getAttribute('data-search') || '';
      const ok = q === '' || hay.includes(q);
      card.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });

    countPill.textContent = String(visible);
    noMatch.classList.toggle('hidden', visible !== 0);
  }

  // initial count
  countPill.textContent = String(items.length);

  input.addEventListener('input', applyFilter);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { input.value = ''; applyFilter(); }
  });
})();
</script>

</body>
</html>
