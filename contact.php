<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function clean_input(string $value): string {
    $value = trim($value);
    $value = str_replace(["\r", "\n"], ' ', $value);
    return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$name = isset($_POST['name']) ? clean_input((string) $_POST['name']) : '';
$email = isset($_POST['email']) ? filter_var(trim((string) $_POST['email']), FILTER_VALIDATE_EMAIL) : false;
$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

if ($name === '' || $email === false || $message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid form fields']);
    exit;
}

$to = 'kadampan@nova-websolutions.com';
$subject = 'New consultation request - Nova Web Solutions';

$bodyLines = [
    'You received a new consultation request from the website form.',
    '',
    'Name: ' . $name,
    'Email: ' . $email,
    '',
    'Message:',
    $message,
    '',
    'Submitted on: ' . date('Y-m-d H:i:s')
];
$body = implode(PHP_EOL, $bodyLines);

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: Nova Web Solutions <no-reply@nova-websolutions.com>';
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'X-Mailer: PHP/' . phpversion();

$sent = mail($to, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
