<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/mail.php";

/**
 * Save notification + send email (optional)
 */
function notify_user(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $link, bool $sendEmail = true): void {
  // 1) Save notification in DB
  $ins = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)");
  $ins->execute([$userId, $type, $title, $message, $link]);

  if (!$sendEmail) return;

  // 2) Fetch user email + name
  $st = $pdo->prepare("SELECT full_name, email FROM users WHERE userID=? LIMIT 1");
  $st->execute([$userId]);
  $u = $st->fetch();

  if (!$u) return;

  $toName = (string)$u["full_name"];
  $toEmail = (string)$u["email"];

  // 3) Email body
  $safeTitle = htmlspecialchars($title);
  $safeMsg = nl2br(htmlspecialchars($message));

  $html = "
  <div style='font-family:Arial,sans-serif;padding:20px;background:#f9fafb;'>
  <div style='background:#ffffff;padding:20px;border-radius:12px;border:1px solid #e5e7eb;'>
    <h2 style='margin-top:0;color:#111827;'>📢 {$safeTitle}</h2>
    <p style='color:#374151;'>{$safeMsg}</p>
    ".($link ? "<p><a href='{$link}' style='display:inline-block;padding:10px 16px;background:#111827;color:white;border-radius:8px;text-decoration:none;'>View in System</a></p>" : "")."
    <hr>
    <small style='color:#6b7280;'>Academic Collaboration System</small>
  </div>
</div>";

  // 4) Send email
  send_email($toEmail, $toName, $title, $html);
}
