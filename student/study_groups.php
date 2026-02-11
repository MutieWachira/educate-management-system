<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
require_once __DIR__ . "/../config/db.php";

$base="/education%20system";
$studentId=(int)$_SESSION["user"]["user_id"];
$courseId=(int)($_GET["course_id"] ?? 0);
if($courseId<=0) die("Invalid course.");

$check=$pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=? LIMIT 1");
$check->execute([$studentId,$courseId]);
if(!$check->fetch()){ http_response_code(403); die("Not enrolled."); }

$cq=$pdo->prepare("SELECT course_code,title FROM courses WHERE course_id=? LIMIT 1");
$cq->execute([$courseId]);
$course=$cq->fetch();

$msg=""; $error="";

// Create group
if($_SERVER["REQUEST_METHOD"]==="POST" && ($_POST["action"]??"")==="create"){
  $name=trim($_POST["name"]??"");
  $desc=trim($_POST["description"]??"");
  if($name===""){ $error="Group name is required."; }
  else{
    $ins=$pdo->prepare("INSERT INTO study_groups (course_id, created_by, name, description) VALUES (?,?,?,?)");
    $ins->execute([$courseId,$studentId,$name,$desc===""?null:$desc]);
    $newGroupId=(int)$pdo->lastInsertId();

    // Auto-join creator
    $join=$pdo->prepare("INSERT INTO study_group_members (group_id, student_id) VALUES (?,?)");
    $join->execute([$newGroupId,$studentId]);

    $msg="Group created and you joined.";
  }
}

// Join group
if($_SERVER["REQUEST_METHOD"]==="POST" && ($_POST["action"]??"")==="join"){
  $groupId=(int)($_POST["group_id"]??0);
  if($groupId>0){
    try{
      $join=$pdo->prepare("INSERT INTO study_group_members (group_id, student_id) VALUES (?,?)");
      $join->execute([$groupId,$studentId]);
      $msg="Joined group.";
    } catch(Throwable $e){
      $error="You are already a member.";
    }
  }
}

// Leave group
if($_SERVER["REQUEST_METHOD"]==="POST" && ($_POST["action"]??"")==="leave"){
  $groupId=(int)($_POST["group_id"]??0);
  if($groupId>0){
    $leave=$pdo->prepare("DELETE FROM study_group_members WHERE group_id=? AND student_id=? LIMIT 1");
    $leave->execute([$groupId,$studentId]);
    $msg="Left group.";
  }
}

// List groups + membership
$sql="
  SELECT g.group_id,g.name,g.description,g.created_at,u.full_name AS creator,
    EXISTS(SELECT 1 FROM study_group_members m WHERE m.group_id=g.group_id AND m.student_id=?) AS is_member
  FROM study_groups g
  INNER JOIN users u ON u.userID=g.created_by
  WHERE g.course_id=?
  ORDER BY g.group_id DESC
";
$stmt=$pdo->prepare($sql);
$stmt->execute([$studentId,$courseId]);
$groups=$stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Study Groups</title>
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
      <li><a class="active" href="#">👥 Study Groups</a></li>
      <li><a href="<?= $base ?>/auth/logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="card">
      <div class="header">
        <div>
          <h2>Study Groups — <?= htmlspecialchars($course["course_code"] ?? "") ?></h2>
          <p><?= htmlspecialchars($course["title"] ?? "") ?></p>
        </div>
      </div>

      <?php if($msg): ?><p style="color:green;font-weight:700;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
      <?php if($error): ?><p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

      <!-- Create group -->
      <form class="filters" method="post">
        <input type="hidden" name="action" value="create">
        <input type="text" name="name" placeholder="Group name e.g. CAT Revision Team" required>
        <textarea name="description" placeholder="Short description (optional)" style="width:100%;min-height:80px;padding:12px;border-radius:12px;border:1px solid #e5e7eb;"></textarea>
        <button class="btn" type="submit">Create Group</button>
        <a class="back" href="<?= $base ?>/student/course.php?course_id=<?= $courseId ?>">Back</a>
      </form>

      <div class="table-wrap" style="margin-top:16px;">
        <table>
          <thead><tr><th>Group</th><th>Creator</th><th>Created</th><th>Membership</th></tr></thead>
          <tbody>
          <?php if(!$groups): ?>
            <tr><td colspan="4">No groups yet. Create one!</td></tr>
          <?php else: foreach($groups as $g): ?>
            <tr>
              <td>
                <b><?= htmlspecialchars($g["name"]) ?></b><br>
                <span style="color:#6b7280;"><?= nl2br(htmlspecialchars($g["description"] ?? "")) ?></span>
              </td>
              <td><?= htmlspecialchars($g["creator"]) ?></td>
              <td><?= htmlspecialchars($g["created_at"]) ?></td>
              <td>
                <?php if((int)$g["is_member"] === 1): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="leave">
                    <input type="hidden" name="group_id" value="<?= (int)$g["group_id"] ?>">
                    <button class="action-link danger" type="submit" style="cursor:pointer;">Leave</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="join">
                    <input type="hidden" name="group_id" value="<?= (int)$g["group_id"] ?>">
                    <button class="action-link" type="submit" style="cursor:pointer;">Join</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
