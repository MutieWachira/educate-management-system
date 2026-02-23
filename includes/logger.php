<?php
declare(strict_types=1);

function log_activity(PDO $pdo, ?int $userId, string $action, ?string $details = null): void {
$role = $_SESSION["user"]["role"]?? NULL; 
$ip = $_SERVER["REMOTE_ADDR"] ?? NULL;
$ua = $SERVER["HTTP_USER_AGENT"] ?? NULL;


$stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, role, action, details, ip_address, user_agent) VALUES (?,?,?,?,?,?)");
  
  $stmt->execute([$userId, $role, $action, $details, $ip, $ua]);
}
