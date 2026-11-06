<?php

declare(strict_types=1);

namespace FoxCraft\Core;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class MailService
{
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): void {
        $configFile = '/etc/foxcraft/mail.php';

        if (!is_readable($configFile)) {
            throw new RuntimeException('La configuración de correo no está disponible.');
        }

        $config = require $configFile;

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->Port = (int) $config['port'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;

        $mail->send();
    }
}
