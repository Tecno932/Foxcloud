<?php

declare(strict_types=1);

namespace FoxCraft\Core;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class Mailer
{
    public static function send(
        string $recipient,
        string $subject,
        string $htmlBody,
        string $altBody
    ): void {
        $configFile = '/etc/foxcraft/mail.php';

        if (!is_readable($configFile)) {
            throw new RuntimeException('La configuración SMTP no está disponible.');
        }

        $config = require $configFile;

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $config['port'];
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody;

        $mail->send();
    }
}
