<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["LECTURER"]);
require_once __DIR__ . "/../config/db.php";

$base = "/education%20system";
$lecturerId = (int)$_SESSION["user"]["user_id"];

$sql = "
  SELECT c.course_id, c.course_code, c.title, d.name AS department_name
  FROM lecturer_courses lc
  INNER JOIN courses c ON c.course_id = lc.course_id
  INNER JOIN departments d ON d.department_id = c.department_id
  WHERE lc.lecturer_id = ?
  ORDER BY c.course_code ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$lecturerId]);
$courses = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Lecturer Dashboard</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/lecturer.css">
</head>

<body>

  <div class="topbar">
    <div class="brand">
      <div class="brand-badge">AC</div>
      <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Lecturer Panel</span></div>
    </div>
    <div class="user">
      <div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • LECTURER</div>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <h4>Navigation</h4>
      <ul class="nav">
        <li><a class="active" href="<?= $base ?>/lecturer/dashboard.php">🏠 Dashboard</a></li>
        <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
      </ul>
    </aside>

    <main class="content">
      <div class="card">
        <div class="header">
          <div>
            <h2>My Courses</h2>
            <p>Courses assigned to you by the admin.</p>

            <!-- ✅ Live search (client-side) -->
            <div class="searchbar">
              <input
                type="text"
                id="courseSearch"
                placeholder="Search by code, title, or department..."
                autocomplete="off">
              <span class="pill-small" id="resultCount">0</span>
            </div>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Department</th>
                <th>Open</th>
              </tr>
            </thead>
            <tbody id="coursesTbody">
              <?php if (!$courses): ?>
                <tr id="noCoursesRow">
                  <td colspan="4">No courses assigned yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($courses as $c): ?>
                  <tr class="course-row"
                    data-search="<?= htmlspecialchars(
                                    strtolower(trim(
                                      ($c["course_code"] ?? "") . " " .
                                        ($c["title"] ?? "") . " " .
                                        ($c["department_name"] ?? "")
                                    )),
                                    ENT_QUOTES
                                  ) ?>">
                    <td><?= htmlspecialchars($c["course_code"]) ?></td>
                    <td><?= htmlspecialchars($c["title"]) ?></td>
                    <td><?= htmlspecialchars($c["department_name"]) ?></td>
                    <td>
                      <a class="action-link" href="<?= $base ?>/lecturer/course.php?course_id=<?= (int)$c["course_id"] ?>">Manage</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <p class="muted" id="noMatchMsg" style="display:none;margin-top:10px;">
          No courses match your search.
        </p>
      </div>
    </main>
  </div>

  <script>
    (function() {
      const input = document.getElementById('courseSearch');
      const rows = Array.from(document.querySelectorAll('.course-row'));
      const resultCount = document.getElementById('resultCount');
      const noMatchMsg = document.getElementById('noMatchMsg');
      const noCoursesRow = document.getElementById('noCoursesRow');

      function updateCount(n) {
        if (resultCount) resultCount.textContent = String(n);
      }

      // initial count (if there are courses)
      if (!noCoursesRow) updateCount(rows.length);
      else updateCount(0);

      // If there are no courses, nothing to filter
      if (rows.length === 0) return;

      function filterNow() {
        const q = (input.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach((tr) => {
          const hay = (tr.getAttribute('data-search') || '');
          const ok = q === '' || hay.includes(q);
          tr.style.display = ok ? '' : 'none';
          if (ok) visible++;
        });

        updateCount(visible);
        noMatchMsg.style.display = (visible === 0) ? '' : 'none';
      }

      // react while typing
      input.addEventListener('input', filterNow);

      // nice UX: press ESC to clear search
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          input.value = '';
          filterNow();
        }
      });
    })();
  </script>

</body>

</html>