<?php
declare(strict_types=1);

session_start();
session_unset();
session_destroy();

header("Location: /academic_collab/auth/login.php");
exit;
    