<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Nevypisovat chyby na produkci

// Load data.json to get recipient email
$dataFile = __DIR__ . '/data.json';
$data = [];
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
}

// Get recipient email (prefer email_reservation, fallback to email)
$recipientEmail = $data['contact']['email_reservation'] ?? $data['contact']['email'] ?? 'info@americapodvezi.cz';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#reservation');
    exit;
}

// Rate limiting - allow max 3 submissions per IP per hour
$rateLimitFile = __DIR__ . '/rate_limit.json';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$currentTime = time();
$rateLimit = [];

if (file_exists($rateLimitFile)) {
    $rateLimit = json_decode(file_get_contents($rateLimitFile), true) ?: [];
}

// Clean old entries (older than 1 hour)
$rateLimit = array_filter($rateLimit, function($timestamp) use ($currentTime) {
    return ($currentTime - $timestamp) < 3600;
});

// Check if IP exceeded limit
if (isset($rateLimit[$clientIP]) && is_array($rateLimit[$clientIP])) {
    if (count($rateLimit[$clientIP]) >= 3) {
        header('Location: index.php?reservation=rate_limit#reservation');
        exit;
    }
} else {
    $rateLimit[$clientIP] = [];
}

// Honeypot check (bot trap field)
if (!empty($_POST['website'])) {
    // Bot filled honeypot field - silently reject
    header('Location: index.php?reservation=success#reservation');
    exit;
}

// Sanitize and validate inputs
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$note = trim($_POST['note'] ?? '');

// Validation
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'name';
}

if (empty($phone) || !preg_match('/^[\d\s\+\-\(\)]{9,}$/', $phone)) {
    $errors[] = 'phone';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email';
}

if (empty($note) || strlen($note) < 10) {
    $errors[] = 'note';
}

// If validation failed, redirect back with error
if (!empty($errors)) {
    header('Location: index.php?reservation=invalid&fields=' . implode(',', $errors) . '#reservation');
    exit;
}

// Prepare email content
$emailSubject = "Nová rezervace akce z webu - " . $name;

$emailBody = "=================================\n";
$emailBody .= "NOVÁ REZERVACE AKCE Z WEBU\n";
$emailBody .= "=================================\n\n";
$emailBody .= "Jméno: " . $name . "\n";
$emailBody .= "Telefon: " . $phone . "\n";
$emailBody .= "E-mail: " . $email . "\n\n";
$emailBody .= "Vaše představa:\n";
$emailBody .= $note . "\n\n";
$emailBody .= "---\n";
$emailBody .= "Odesláno: " . date('d.m.Y H:i') . "\n";
$emailBody .= "IP adresa: " . $clientIP . "\n";

// Email headers
$headers = "From: " . $email . "\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Send email
$mailSent = mail($recipientEmail, $emailSubject, $emailBody, $headers);

if ($mailSent) {
    // Update rate limit
    $rateLimit[$clientIP][] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($rateLimit));
    
    // Success - redirect with success message
    header('Location: index.php?reservation=success#reservation');
} else {
    // Error - redirect with error message
    header('Location: index.php?reservation=error#reservation');
}

exit;
