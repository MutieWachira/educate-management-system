<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/autoload.php";

/**
 * Configure SMTP here.
 * If using Gmail, use an App Password (NOT your normal password).
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
  $mail = new PHPMailer(true);

  try {
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = "mutiewachira@gmail.com";
    $mail->Password = "odcxuwdgywbkvpwo"; // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    //$mail->SMTPDebug = 2;
    //$mail->Debugoutput = 'html';

    $mail->setFrom("mutiewachira@gmail.com", "Academic Collaboration System");
    $mail->addAddress($toEmail, $toName);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;

    


    $mail->send();
    return true;
  } catch (Exception $e) {
    // For debugging (optional)
    //error_log("Mail error: " . $mail->ErrorInfo);
    return false;
  }
}
