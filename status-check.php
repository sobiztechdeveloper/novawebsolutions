<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: text/plain; charset=utf-8');

// IMPORTANT:
// 1) Set a strong secret token below
// 2) Access as: /status-check.php?token=YOUR_SECRET
// 3) Delete this file after testing
$accessToken = 'CHANGE_THIS_TO_A_STRONG_RANDOM_TOKEN';
$requestToken = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($requestToken === '' || !hash_equals($accessToken, $requestToken)) {
    http_response_code(403);
    echo "Forbidden: invalid token.\n";
    exit;
}

$smtpConfigPath = __DIR__ . '/smtp-config.php';
if (!file_exists($smtpConfigPath)) {
    http_response_code(500);
    echo "ERROR: smtp-config.php not found.\n";
    exit;
}
require $smtpConfigPath;

$phpMailerBase = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
$requiredFiles = [
    $phpMailerBase . 'Exception.php',
    $phpMailerBase . 'PHPMailer.php',
    $phpMailerBase . 'SMTP.php'
];
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        http_response_code(500);
        echo "ERROR: Missing PHPMailer file: " . $file . "\n";
        exit;
    }
    require_once $file;
}

try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->Port = (int) SMTP_PORT;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->SMTPAutoTLS = true;
    $mail->Timeout = 12;

    // Enable low-level debug output to browser
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = static function ($str, $level) {
        echo "[SMTP:$level] $str\n";
    };

    $mail->setFrom(SMTP_FROM, 'Nova Web Solutions SMTP Check');
    $mail->addAddress(SMTP_TO, 'Nova Web Solutions');
    $mail->Subject = 'SMTP Test - Nova Web Solutions';
    $mail->Body = "SMTP test successful.\nTime: " . date('Y-m-d H:i:s');
    $mail->AltBody = $mail->Body;

    $mail->send();
    echo "\nSUCCESS: SMTP connected and test email sent.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "\nERROR: SMTP test failed.\n";
    echo "Message: " . $e->getMessage() . "\n";
}
