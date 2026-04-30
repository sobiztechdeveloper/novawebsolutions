<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_input(string $value): string
{
    $value = trim($value);
    $value = preg_replace("/[\r\n]+/", ' ', $value) ?? '';
    return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

function safe_log(string $message): void
{
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    $errorFile = $logDir . DIRECTORY_SEPARATOR . 'errors.log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($errorFile, '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Basic anti-spam honeypot check.
$website = isset($_POST['website']) ? trim((string) $_POST['website']) : '';
if ($website !== '') {
    respond(200, ['success' => true, 'message' => 'Request accepted']);
}

// Basic per-session + per-IP rate limit (max 5 requests / 10 minutes).
$now = time();
$windowSeconds = 600;
$maxRequests = 5;
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$sessionKey = 'form_request_times';
if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = [];
}
$_SESSION[$sessionKey] = array_filter($_SESSION[$sessionKey], static function ($timestamp) use ($now, $windowSeconds) {
    return is_int($timestamp) && ($now - $timestamp) < $windowSeconds;
});
if (count($_SESSION[$sessionKey]) >= $maxRequests) {
    respond(429, ['success' => false, 'message' => 'Too many requests. Please wait and try again.']);
}
$_SESSION[$sessionKey][] = $now;

$name = isset($_POST['name']) ? clean_input((string) $_POST['name']) : '';
$emailRaw = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

if ($name === '' || $email === false || $message === '') {
    respond(422, ['success' => false, 'message' => 'Invalid form fields']);
}

if (strlen($name) > 120 || strlen($message) > 4000) {
    respond(422, ['success' => false, 'message' => 'Input is too long']);
}

$leadLogDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
$leadLogFile = $leadLogDir . DIRECTORY_SEPARATOR . 'leads.log';
if (!is_dir($leadLogDir)) {
    @mkdir($leadLogDir, 0755, true);
}

$logPayload = [
    'timestamp' => date('c'),
    'name' => $name,
    'email' => $email,
    'message' => $message,
    'ip' => $clientIp
];
@file_put_contents($leadLogFile, json_encode($logPayload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);

$phpMailerBase = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
$requiredFiles = [
    $phpMailerBase . 'Exception.php',
    $phpMailerBase . 'PHPMailer.php',
    $phpMailerBase . 'SMTP.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        safe_log('PHPMailer dependency missing: ' . $file);
        respond(500, ['success' => false, 'message' => 'Mail service is not configured yet.']);
    }
    require_once $file;
}

$smtpConfigPath = __DIR__ . '/smtp-config.php';
if (!file_exists($smtpConfigPath)) {
    safe_log('Missing smtp-config.php file');
    respond(500, ['success' => false, 'message' => 'SMTP configuration missing.']);
}
require $smtpConfigPath;

$requiredConfig = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_SECURE', 'SMTP_FROM', 'SMTP_TO'];
foreach ($requiredConfig as $constName) {
    if (!defined($constName) || constant($constName) === '') {
        safe_log('SMTP constant missing/empty: ' . $constName);
        respond(500, ['success' => false, 'message' => 'SMTP configuration invalid.']);
    }
}

try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->Port = (int) SMTP_PORT;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Timeout = 12;
    $mail->SMTPAutoTLS = true;

    $mail->setFrom(SMTP_FROM, 'Nova Web Solutions');
    $mail->addAddress(SMTP_TO, 'Nova Web Solutions');
    $mail->addReplyTo($email, $name);
    $mail->Subject = 'New consultation request - Nova Web Solutions';

    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeIp = htmlspecialchars($clientIp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $submittedAt = date('Y-m-d H:i:s');
    $safeSubmittedAt = htmlspecialchars($submittedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = htmlspecialchars(str_replace(["\r\n", "\r"], "\n", $message), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $formattedMessage = nl2br($safeMessage, false);

    $mail->isHTML(true);
    $mail->Body = '
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f1f5f9;padding:24px 0;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
        <tr>
          <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="680" style="max-width:680px;width:100%;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
              <tr>
                <td style="background:#0f172a;padding:20px 24px;">
                  <h2 style="margin:0;font-size:20px;line-height:1.3;color:#ffffff;">New Consultation Request</h2>
                  <p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:#cbd5e1;">Submitted from the Nova Web Solutions website form</p>
                </td>
              </tr>
              <tr>
                <td style="padding:24px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                    <tr>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;width:180px;font-size:13px;color:#475569;"><strong>Name</strong></td>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">' . $safeName . '</td>
                    </tr>
                    <tr>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;width:180px;font-size:13px;color:#475569;"><strong>Email</strong></td>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">' . $safeEmail . '</td>
                    </tr>
                    <tr>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;width:180px;font-size:13px;color:#475569;"><strong>IP Address</strong></td>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">' . $safeIp . '</td>
                    </tr>
                    <tr>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;width:180px;font-size:13px;color:#475569;"><strong>Submitted Date</strong></td>
                      <td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">' . $safeSubmittedAt . '</td>
                    </tr>
                  </table>
                  <div style="margin-top:20px;">
                    <p style="margin:0 0 8px;font-size:13px;color:#475569;"><strong>Message</strong></p>
                    <div style="padding:14px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;font-size:14px;line-height:1.6;color:#0f172a;">' . $formattedMessage . '</div>
                  </div>
                </td>
              </tr>
              <tr>
                <td style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;">
                  <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;">
                    Nova Web Solutions (PVT) LTD<br>
                    <a href="https://nova-websolutions.com" style="color:#0ea5e9;text-decoration:none;">https://nova-websolutions.com</a>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>';

    $mail->AltBody = "New consultation request from website form\n\n"
        . "Name: {$name}\n"
        . "Email: {$email}\n"
        . "IP Address: {$clientIp}\n"
        . "Submitted Date: {$submittedAt}\n\n"
        . "Message:\n" . str_replace(["\r\n", "\r"], "\n", $message) . "\n\n"
        . "Nova Web Solutions (PVT) LTD\n"
        . "https://nova-websolutions.com";

    $mail->send();
    respond(200, ['success' => true, 'message' => 'Email sent successfully']);
} catch (Exception $exception) {
    safe_log('Mailer exception: ' . $exception->getMessage());
    respond(500, ['success' => false, 'message' => 'Failed to send email. Lead saved to server log.']);
}
