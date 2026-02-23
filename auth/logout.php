<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/logger.php";


session_start();
log_activity($pdo, (int)($_SESSION["user"]["user_id"] ?? 0), "LOGOUT", "User logged out");
session_unset();
session_destroy();

header("Location: /education%20system/auth/login.php");
exit;
    