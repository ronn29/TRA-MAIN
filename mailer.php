<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function smtpEnv($key, $default = '') {
    return getenv($key) ?: ($_ENV[$key] ?? ($_SERVER[$key] ?? $default));
}

function sendMail(array $data): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = smtpEnv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = smtpEnv('SMTP_USER');
        $mail->Password   = smtpEnv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = smtpEnv('SMTP_PORT', 587);
        $mail->CharSet    = 'UTF-8';

        $debugLevel = smtpEnv('SMTP_DEBUG');
        if ($debugLevel !== '') {
            $mail->SMTPDebug  = (int)$debugLevel;
            $mail->Debugoutput = static function ($str) {
                error_log('SMTP: ' . $str);
            };
        }

        $fromEmail = smtpEnv('MAIL_FROM');
        $fromName  = smtpEnv('MAIL_FROM_NAME', 'Tragabay');
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
