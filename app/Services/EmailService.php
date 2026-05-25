<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
private PHPMailer $mail;

public function __construct()
{
$this->mail = new PHPMailer(true);

$this->mail->isSMTP();
$this->mail->Host = $_ENV['SMTP_HOST'] ?? 'admongas.com.mx';
$this->mail->SMTPAuth = true;
$this->mail->Username = $_ENV['SMTP_USER'] ?? '';
$this->mail->Password = $_ENV['SMTP_PASS'] ?? '';
$this->mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
$this->mail->Port = (int) ($_ENV['SMTP_PORT'] ?? 587);
$this->mail->Timeout = 30;

$this->mail->SMTPOptions = [
'ssl' => [
'verify_peer' => false,
'verify_peer_name' => false,
'allow_self_signed' => true,
],
];

$this->mail->setFrom(
$_ENV['SMTP_FROM_EMAIL'] ?? 'portal@admongas.com.mx',
$_ENV['SMTP_FROM_NAME'] ?? 'Portal AdmonGas'
);

$this->mail->isHTML(true);
$this->mail->CharSet = 'UTF-8';
}

public function sendToken(string $email, string $token): bool
{
try {
$this->mail->clearAddresses();
$this->mail->addAddress($email);

$this->mail->Subject = 'Token web - Corte Diario';
$this->mail->Body = 'AdmonGas: Usa el siguiente token para firmar la solicitud de corte diario. Token: <b>' . $token . '</b>';
$this->mail->AltBody = 'AdmonGas: Usa el siguiente token para firmar la solicitud de corte diario. Token: ' . $token;

$this->mail->send();
return true;

} catch (Exception $e) {
$errorMsg = 'Error SMTP: ' . $this->mail->ErrorInfo;
error_log('[EmailService] ' . $errorMsg);

return false;
}
}

public function getLastError(): string
{
return $this->mail->ErrorInfo;
}
}
