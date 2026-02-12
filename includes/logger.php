<?php
declare(strict_types=1);

function log_activity(PDO $pdo, ?int $userId, string $action, ?string $details = null): void {
  $st = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?,?,?)");
  $st->execute([$userId, $action, $details]);
}
