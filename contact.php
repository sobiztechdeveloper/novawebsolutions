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

    $safeMessage = str_replace(["\r\n", "\r"], "\n", $message);
    $mail->Body = "You received a new consultation request from the website form.\n\n"
        . "Name: {$name}\n"
        . "Email: {$email}\n"
        . "IP: {$clientIp}\n"
        . "Submitted on: " . date('Y-m-d H:i:s') . "\n\n"
        . "Message:\n{$safeMessage}\n";
    $mail->AltBody = $mail->Body;

    $mail->send();
    respond(200, ['success' => true, 'message' => 'Email sent successfully']);
} catch (Exception $exception) {
    safe_log('Mailer exception: ' . $exception->getMessage());
    respond(500, ['success' => false, 'message' => 'Failed to send email. Lead saved to server log.']);
}
