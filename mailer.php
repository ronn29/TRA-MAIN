<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function sendMail(array $data): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER');
        $mail->Password   = getenv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
        $mail->CharSet    = 'UTF-8';

        $debugLevel = getenv('SMTP_DEBUG');
        if ($debugLevel !== false) {
            $mail->SMTPDebug  = (int)$debugLevel;
            $mail->Debugoutput = static function ($str) {
                error_log('SMTP: ' . $str);
            };
        }

        $fromEmail = getenv('MAIL_FROM');
        $fromName  = getenv('MAIL_FROM_NAME') ?: 'Tragabay';
        $mail->setFrom($fromEmail, $fromName);

        $mail->addAddress($data['to'], $data['name'] ?? '');

        $mail->isHTML(true);
        $mail->Subject = $data['subject'] ?? '';
        $mail->Body    = $data['body'] ?? '';
        $mail->AltBody = $data['alt'] ?? strip_tags($data['body'] ?? '');

        $mail->send();

        return ['success' => true];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        error_log('SMTP send failed: ' . $error);
        return ['success' => false, 'error' => $error];
    }
}
