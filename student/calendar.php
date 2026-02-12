<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";

$base="/education%20system";
$studentId=(int)$_SESSION["user"]["user_id"];

// Events from enrolled courses only
$sql = "
  SELECT a.event_date, a.title, a.content, c.course_code
  FROM announcements a
  INNER JOIN enrollments e ON e.course_id = a.course_id
  INNER JOIN courses c ON c.course_id = a.course_id
  WHERE e.student_id=? AND a.event_date IS NOT NULL
  ORDER BY a.event_date ASC
";
$stmt=$pdo->prepare($sql);
$stmt->execute([$studentId]);
$rows=$stmt->fetchAll();

// group by date
$grouped=[];
foreach($rows as $r){
  $grouped[$r["event_date"]][]=$r;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Calendar</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/student.css">
</head>
<body>
<div class="topbar">
  <div class="brand"><div class="brand-badge">AC</div>
    <div>Academic Collaboration System<br><span style="font-size:12px;opacity:.85;">Student Portal</span></div>
  </div>
  <div class="user"><div class="pill"><?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> • STUDENT</div></div>
</div>

<div class="layout">
  <aside class="sidebar">
    <h4>Navigation</h4>
    <ul class="nav">
      <li><a href="<?= $base ?>/student/dashboard.php">🏠 Dashboard</a></li>
      <li><a class="active" href="#">📅 Calendar</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Academic Calendar</h2>
          <p>Upcoming course events from announcements.</p>
        </div>
      </div>

      <?php if(!$grouped): ?>
        <p>No upcoming events yet.</p>
      <?php else: ?>
        <?php foreach($grouped as $date=>$items): ?>
          <div style="padding:12px;border:1px solid #e5e7eb;border-radius:14px;margin-bottom:12px;background:#fff;">
            <b><?= htmlspecialchars($date) ?></b>
            <div style="margin-top:8px;">
              <?php foreach($items as $e): ?>
                <div style="margin-bottom:10px;">
                  <span style="font-weight:800;"><?= htmlspecialchars($e["course_code"]) ?>:</span>
                  <?= htmlspecialchars($e["title"]) ?><br>
                  <span style="color:#6b7280;"><?= nl2br(htmlspecialchars($e["content"])) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </main>
</div>
</body>
</html>
