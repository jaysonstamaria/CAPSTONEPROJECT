<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// --- SMTP Configuration (REPLACE WITH YOUR CREDENTIALS) ---
define('SMTP_HOST', 'smtp.gmail.com');       // Example for Gmail
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_gmail_app_password');
define('SMTP_PORT', 587);
define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);

define('EMAIL_FROM_ADDRESS', 'no-reply@carsrus.com');
define('EMAIL_FROM_NAME', 'CarsRUs Auto Dealer');

function send_email($toEmail, $subject, $bodyHTML, $bodyText = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Use DEBUG_SERVER for testing
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHTML;
        if (!empty($bodyText)) {
            $mail->AltBody = $bodyText;
        }
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>