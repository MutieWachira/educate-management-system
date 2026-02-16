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

// Get student's enrolled courses (for dropdown)
$cstmt = $pdo->prepare("
  SELECT c.course_id, c.course_code, c.title
  FROM enrollments e
  INNER JOIN courses c ON c.course_id = e.course_id
  WHERE e.student_id=?
  ORDER BY c.course_code ASC
");
$cstmt->execute([$studentId]);
$courses = $cstmt->fetchAll();

$courseId = (int)($_GET["course_id"] ?? 0);

// If course selected, ensure student enrolled
if ($courseId > 0) {
  $chk = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
  $chk->execute([$studentId, $courseId]);
  if (!$chk->fetch()) {
    http_response_code(403);
    die("Not enrolled in this course.");
  }
}

// Fetch grades (all or per course)
$params = [$studentId];
$where = "g.student_id=?";
if ($courseId > 0) {
  $where .= " AND g.course_id=?";
  $params[] = $courseId;
}

$stmt = $pdo->prepare("
  SELECT
    g.grade_id, g.course_id, g.item_name, g.score, g.max_score, g.remarks, g.created_at,
    c.course_code, c.title AS course_title
  FROM grades g
  INNER JOIN courses c ON c.course_id = g.course_id
  WHERE $where
  ORDER BY c.course_code ASC, g.created_at DESC
");
$stmt->execute($params);
$grades = $stmt->fetchAll();

// Summary calculations
$totalPct = 0.0;
$count = 0;

foreach ($grades as $g) {
  $max = (float)$g["max_score"];
  $sc  = (float)$g["score"];
  if ($max > 0) {
    $totalPct += ($sc / $max) * 100.0;
    $count++;
  }
}
$avgPct = $count ? round($totalPct / $count, 2) : null;

function gradeBadge(float $pct): string {
  if ($pct >= 70) return "✅ Distinction";
  if ($pct >= 60) return "🟦 Credit";
  if ($pct >= 50) return "🟨 Pass";
  return "🟥 Improve";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Grades</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">

  <style>
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px;}
    .input{padding:12px 14px;border:1px solid #e5e7eb;border-radius:12px;outline:none;min-width:220px;}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#f3f4f6;font-size:12px;font-weight:900;color:#111827;}
    .muted{color:#6b7280;font-size:13px;margin-top:8px;}
    .hidden{display:none !important;}
    table td{vertical-align:top;}
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
      <li><a href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="<?= $base ?>/student/grades.php">📈 Grades</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>My Grades</h2>
          <p>View your CATs, assignments, and exams.</p>

          <div class="row">
            <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <select class="input" name="course_id" onchange="this.form.submit()">
                <option value="0">All My Courses</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= (int)$c["course_id"] ?>" <?= $courseId===(int)$c["course_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($c["course_code"]) ?> — <?= htmlspecialchars($c["title"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>

            <input id="search" class="input" type="text" placeholder="Search grade item or course..." autocomplete="off">
            <span class="pill" id="countPill">0</span>
            <a class="action-link" href="<?= $base ?>/student/dashboard.php">← Back</a>
          </div>

          <?php if ($avgPct !== null): ?>
            <div class="muted">
              <b>Average:</b> <?= htmlspecialchars((string)$avgPct) ?>% • <?= gradeBadge((float)$avgPct) ?>
            </div>
          <?php else: ?>
            <div class="muted">No grades found yet.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="table-wrap" style="margin-top:12px;">
        <table>
          <thead>
            <tr>
              <th>Course</th>
              <th>Item</th>
              <th>Score</th>
              <th>%</th>
              <th>Remarks</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody id="tbody">
            <?php if (!$grades): ?>
              <tr><td colspan="6">No grades available.</td></tr>
            <?php else: ?>
              <?php foreach ($grades as $g): ?>
                <?php
                  $max = (float)$g["max_score"];
                  $sc  = (float)$g["score"];
                  $pct = $max > 0 ? round(($sc/$max)*100, 2) : 0.0;

                  $searchKey = strtolower(
                    trim(
                      ($g["course_code"] ?? "") . " " .
                      ($g["course_title"] ?? "") . " " .
                      ($g["item_name"] ?? "") . " " .
                      ($g["remarks"] ?? "")
                    )
                  );
                ?>
                <tr class="gRow" data-search="<?= htmlspecialchars($searchKey, ENT_QUOTES) ?>">
                  <td>
                    <b><?= htmlspecialchars((string)$g["course_code"]) ?></b><br>
                    <span style="color:#6b7280;"><?= htmlspecialchars((string)$g["course_title"]) ?></span>
                  </td>
                  <td><?= htmlspecialchars((string)$g["item_name"]) ?></td>
                  <td><?= htmlspecialchars((string)$sc) ?> / <?= htmlspecialchars((string)$max) ?></td>
                  <td><b><?= htmlspecialchars((string)$pct) ?>%</b> <span style="color:#6b7280;"><?= gradeBadge((float)$pct) ?></span></td>
                  <td><?= htmlspecialchars((string)($g["remarks"] ?? "-")) ?></td>
                  <td><?= htmlspecialchars((string)$g["created_at"]) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <p class="muted hidden" id="noMatch">No grades match your search.</p>
      </div>
    </div>
  </main>
</div>

<script>
(() => {
  const search = document.getElementById('search');
  const rows = Array.from(document.querySelectorAll('.gRow'));
  const countPill = document.getElementById('countPill');
  const noMatch = document.getElementById('noMatch');

  function filter() {
    const q = (search.value || '').trim().toLowerCase();
    let visible = 0;

    rows.forEach(r => {
      const hay = r.getAttribute('data-search') || '';
      const ok = q === '' || hay.includes(q);
      r.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });

    if (countPill) countPill.textContent = String(visible);
    if (noMatch) noMatch.classList.toggle('hidden', visible !== 0);
  }

  if (countPill) countPill.textContent = String(rows.length);

  if (search) {
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { search.value = ''; filter(); }
    });
  }
})();
</script>

</body>
</html>
