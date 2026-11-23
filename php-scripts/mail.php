<?php

/** One SMTP factory shared by verification, reset and deletion emails. */
function ps_mailer(string $email, string $name = ''): \PHPMailer\PHPMailer\PHPMailer
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    require __DIR__.'/email_config.php';
    $config = ps_config();
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mail_username;
    $mail->Password = $mail_password;
    $mail->SMTPSecure = $config['smtp_encryption'];
    $mail->Port = $config['smtp_port'];
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($mail_username, $config['mail_from_name']);
    $mail->addAddress($email, $name);
    return $mail;
}
function ps_mail_verification(string $email, string $name, string $code): void
{
    $mail = ps_mailer($email, $name);
    $mail->Subject = 'Plant System — Email verification / Επιβεβαίωση email';
    $mail->Body = "Hello $name,\nYour Plant System verification code is: $code\nExpires in 10 minutes.\n\nΟ κωδικός επιβεβαίωσης email είναι: $code\nΙσχύει για 10 λεπτά.\nIf you did not request this, ignore this email.";
    $mail->send();
}
