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

// Enrolled courses
$sql = "
  SELECT c.course_id, c.course_code, c.title, d.name AS department_name
  FROM enrollments e
  INNER JOIN courses c ON c.course_id = e.course_id
  INNER JOIN departments d ON d.department_id = c.department_id
  WHERE e.student_id = ?
  ORDER BY c.course_code ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId]);
$courses = $stmt->fetchAll();

// Upcoming events (announcements with event_date) for student courses
$eventsStmt = $pdo->prepare("
  SELECT a.title, a.event_date, c.course_code
  FROM announcements a
  INNER JOIN courses c ON c.course_id = a.course_id
  INNER JOIN enrollments e ON e.course_id = a.course_id
  WHERE e.student_id = ?
    AND a.event_date IS NOT NULL
    AND a.event_date >= CURDATE()
  ORDER BY a.event_date ASC
  LIMIT 6
");
$eventsStmt->execute([$studentId]);
$events = $eventsStmt->fetchAll();

// Recent announcements (latest across student courses)
$annStmt = $pdo->prepare("
  SELECT a.title, a.created_at, c.course_code, a.course_id
  FROM announcements a
  INNER JOIN courses c ON c.course_id = a.course_id
  INNER JOIN enrollments e ON e.course_id = a.course_id
  WHERE e.student_id = ?
  ORDER BY a.created_at DESC
  LIMIT 6
");
$annStmt->execute([$studentId]);
$recentAnnouncements = $annStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">

  <style>
    .dash-grid{
      display:grid;
      grid-template-columns: 2fr 1fr;
      gap:14px;
    }
    @media(max-width: 1000px){
      .dash-grid{ grid-template-columns: 1fr; }
    }

    .search-row{
      display:flex; gap:10px; flex-wrap:wrap;
      margin-top:10px; align-items:center;
    }
    .search-input{
      flex:1; min-width:240px;
      padding:12px 14px;
      border:1px solid #e5e7eb;
      border-radius:12px;
      outline:none;
    }
    .count-pill{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      background:#f3f4f6;
      font-size:12px;
      font-weight:800;
      color:#111827;
    }

    .course-grid{
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap:14px;
      margin-top:14px;
    }
    .course-card{
      border:1px solid #e5e7eb;
      border-radius:16px;
      padding:14px;
      background:#fff;
      box-shadow: 0 8px 18px rgba(0,0,0,0.04);
    }
    .code{ font-weight:900; letter-spacing:.5px; }
    .title{ margin:6px 0 8px; font-weight:800; color:#111827; }
    .dept{ color:#6b7280; font-size:13px; }
    .actions{ margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .muted{ color:#6b7280; font-size:13px; }
    .hidden{ display:none !important; }

    .widget{
      border:1px solid #e5e7eb;
      border-radius:16px;
      padding:14px;
      background:#fff;
      box-shadow: 0 8px 18px rgba(0,0,0,0.04);
    }
    .widget h3{
      margin:0 0 8px 0;
      font-size:16px;
    }
    .item{
      padding:10px 0;
      border-top:1px solid #f3f4f6;
    }
    .item:first-of-type{ border-top:none; }
    .tag{
      display:inline-block;
      font-size:12px;
      font-weight:800;
      padding:4px 8px;
      border-radius:999px;
      background:#f3f4f6;
      margin-right:6px;
    }
    .tag.green{ background:#ecfdf5; color:#065f46; border:1px solid #bbf7d0; }
    .quick-actions{
      display:flex; gap:10px; flex-wrap:wrap;
      margin-top:10px;
    }
    .qa{
      display:inline-block;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid #e5e7eb;
      background:#fff;
      font-weight:800;
      text-decoration:none;
      color:#111827;
    }
    .qa:hover{ background:#f9fafb; }
  </style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Student Portal</span></div>
  </div>
  <div class="user">
    <div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • STUDENT</div>
  </div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a class="active" href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a href="<?= $base ?>/student/course_hub.php">📚 Course Hub</a></li>
      <li><a href="<?= $base ?>/student/study_groups.php">👥 Study Groups</a></li>
      <li><a href="<?= $base ?>/student/calendar.php">🗓 Calendar</a></li>
              <li><a href="<?= $base ?>/student/profile.php">👤 Profile</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="dash-grid">

      <!-- LEFT: Courses -->
      <div class="card">
        <div class="header">
          <div>
            <h2>My Courses</h2>
            <p>Courses you are enrolled in. Search as you type.</p>

            <div class="quick-actions">
              <a class="qa" href="<?= $base ?>/student/course_hub.php">➕ Enroll to a Course</a>
              <a class="qa" href="<?= $base ?>/student/grades.php">📈 View Grades</a>
              <a class="qa" href="<?= $base ?>/student/calendar.php">🗓 Event Calendar</a>
            </div>

            <div class="search-row">
              <input id="courseSearch" class="search-input" type="text"
                     placeholder="Search by code, title or department..." autocomplete="off">
              <span class="count-pill" id="countPill">0</span>
            </div>
          </div>
        </div>

        <?php if (!$courses): ?>
          <p class="muted" style="margin-top:10px;">You are not enrolled in any courses yet.</p>
          <a class="btn" href="<?= $base ?>/student/course_hub.php" style="margin-top:10px;display:inline-block;">Go to Course Hub</a>
        <?php else: ?>
          <div class="course-grid" id="courseGrid">
            <?php foreach ($courses as $c): ?>
              <?php
                $search = strtolower(trim(($c["course_code"] ?? "")." ".($c["title"] ?? "")." ".($c["department_name"] ?? "")));
              ?>
              <div class="course-card course-item" data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                <div class="code"><?= htmlspecialchars($c["course_code"]) ?></div>
                <div class="title"><?= htmlspecialchars($c["title"]) ?></div>
                <div class="dept">Department: <?= htmlspecialchars($c["department_name"]) ?></div>

                <div class="actions">
                  <a class="action-link" href="<?= $base ?>/student/course.php?course_id=<?= (int)$c["course_id"] ?>">Open</a>
                  <a class="action-link" href="<?= $base ?>/student/materials.php?course_id=<?= (int)$c["course_id"] ?>">Materials</a>
                  <a class="action-link" href="<?= $base ?>/student/announcements.php?course_id=<?= (int)$c["course_id"] ?>">Announcements</a>
                </div>

                <div class="muted" style="margin-top:10px;">
                  Use Open to access forum + study groups for this course.
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <p class="muted hidden" id="noMatch" style="margin-top:12px;">No course matches your search.</p>
        <?php endif; ?>
      </div>

      <!-- RIGHT: Widgets -->
      <div style="display:flex; flex-direction:column; gap:14px;">

        <div class="widget">
          <h3>Upcoming Events</h3>
          <?php if (!$events): ?>
            <p class="muted">No upcoming events.</p>
          <?php else: ?>
            <?php foreach ($events as $e): ?>
              <div class="item">
                <span class="tag green"><?= htmlspecialchars($e["course_code"]) ?></span>
                <b><?= htmlspecialchars($e["title"]) ?></b>
                <div class="muted" style="margin-top:4px;">📅 <?= htmlspecialchars((string)$e["event_date"]) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="widget">
          <h3>Recent Announcements</h3>
          <?php if (!$recentAnnouncements): ?>
            <p class="muted">No announcements yet.</p>
          <?php else: ?>
            <?php foreach ($recentAnnouncements as $a): ?>
              <div class="item">
                <span class="tag"><?= htmlspecialchars($a["course_code"]) ?></span>
                <b><?= htmlspecialchars($a["title"]) ?></b>
                <div class="muted" style="margin-top:4px;">
                  <?= htmlspecialchars((string)$a["created_at"]) ?> •
                  <a class="action-link" href="<?= $base ?>/student/announcements.php?course_id=<?= (int)$a["course_id"] ?>">View</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
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

    if (countPill) countPill.textContent = String(visible);
    if (noMatch) noMatch.classList.toggle('hidden', visible !== 0);
  }

  // initial count
  if (countPill) countPill.textContent = String(items.length);

  if (input) {
    input.addEventListener('input', applyFilter);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { input.value = ''; applyFilter(); }
    });
  }
})();
</script>

</body>
</html>
