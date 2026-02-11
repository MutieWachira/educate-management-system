<?php
// includes/auth_guard.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function require_login(): void {
  if (empty($_SESSION['user'])) {
    header("Location: /education%20system/auth/login.php");
    exit;
  }
}

function require_role(array $roles): void {
  require_login();
  $role = $_SESSION['user']['role'] ?? '';
  if (!in_array($role, $roles, true)) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
  }
}
