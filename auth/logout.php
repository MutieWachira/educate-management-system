<?php
declare(strict_types=1);

session_start();
session_unset();
session_destroy();

header("Location: /education%20system/auth/login.php");
exit;
    