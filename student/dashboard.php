<?php
require_once __DIR__ . "/../includes/auth_guard.php";
require_role(["STUDENT"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <!--navbar-->
    <!--make the navbar sticky-->
    <div class="navbar">
        <ul>
            <li><a href="/student/dashboard.html">Home</a></li>
            <li><a href="/student/my_courses.html">My Courses</a></li>
            <li><a href="/education%20system/auth/logout.php">Log out</a></li>
        </ul>
    </div>
    <!--main section-->
    <div class="main">
        <p>Welcome <?= htmlspecialchars($_SESSION["user"]["full_name"]) ?> (STUDENT)</p>
        <!--lets start without 2 containers then move to 2 if rec -->
        .up
    </div>
</body>
</html>