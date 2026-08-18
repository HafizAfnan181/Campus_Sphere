<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

class Email {
    public static function sendVerification($to_email, $code) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'hafizafnan187@gmail.com'; // APNA EMAIL DAALO
            $mail->Password = 'gwcrlwnzauimdhic'; // GMAIL APP PASSWORD DAALO
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Uncomment the 2 lines below temporarily if you get a
            // "SSL certificate problem" error on XAMPP/Windows:
            // $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

            $mail->setFrom('hafizafnan187@gmail.com', 'Social Site');
            $mail->addAddress($to_email);
            $mail->isHTML(true);
            $mail->Subject = 'Email Verification Code';
            $mail->Body = "<h2>Your verification code is: <strong>$code</strong></h2>";

            $mail->send();
            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            // IMPORTANT: this used to silently "return false" with no error
            // logged anywhere — that's why the code looked like it was never
            // generated. It WAS generated and saved to the database; only the
            // email delivery was failing, and the failure was invisible.
            $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
            self::logError("sendVerification to $to_email failed: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    private static function logError($message) {
        $logFile = __DIR__ . '/../logs/email_errors.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    public static function sendPasswordReset($to_email, $token) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'your_email@gmail.com'; // APNA EMAIL DAALO
            $mail->Password = 'your_app_password'; // GMAIL APP PASSWORD DAALO
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $reset_link = "http://localhost/social-site/reset-password.html?token=$token";
            $mail->setFrom('your_email@gmail.com', 'Social Site');
            $mail->addAddress($to_email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Link';
            $mail->Body = "<h2>Click here to reset your password:</h2><br><a href='$reset_link'>$reset_link</a>";

            $mail->send();
            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
            self::logError("sendPasswordReset to $to_email failed: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
    }
}
?>