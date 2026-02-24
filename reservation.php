<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Load data.json to get recipient email
$dataFile = __DIR__ . '/data.json';
$data = [];
if (file_exists($dataFile)) {
    $data = json_decode(file_get_contents($dataFile), true) ?: [];
}

$recipientEmail = $data['contact']['email_reservation'] ?? null;

if (!$recipientEmail) {
    echo json_encode(['success' => false, 'message' => 'Email pro rezervace není nastaven']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Neplatný požadavek']);
    exit;
}

// SECURITY FIX: Rate limiting with proper file locking to prevent race conditions
$rateLimitFile = __DIR__ . '/rate_limit.json';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$currentTime = time();
$rateLimit = [];

// Create file if it doesn't exist
if (!file_exists($rateLimitFile)) {
    file_put_contents($rateLimitFile, json_encode([]));
}

// Open file with exclusive lock for reading and writing
$fp = fopen($rateLimitFile, 'r+');
if ($fp === false) {
    error_log('Failed to open rate limit file');
    echo json_encode(['success' => false, 'message' => 'Chyba serveru. Zkuste to prosím později.']);
    exit;
}

// Acquire exclusive lock (blocks until lock is available)
if (flock($fp, LOCK_EX)) {
    // Read current rate limit data
    $filesize = filesize($rateLimitFile);
    if ($filesize > 0) {
        $content = fread($fp, $filesize);
        $rateLimit = json_decode($content, true) ?: [];
    }
    
    // Clean up old entries (older than 1 hour)
    $rateLimit = array_filter($rateLimit, function($timestamps) use ($currentTime) {
        if (!is_array($timestamps)) return false;
        // Filter timestamps within last hour
        $filtered = array_filter($timestamps, function($ts) use ($currentTime) {
            return ($currentTime - $ts) < 3600;
        });
        return !empty($filtered);
    });
    
    // Check rate limit for this IP
    if (isset($rateLimit[$clientIP]) && is_array($rateLimit[$clientIP])) {
        // Remove old timestamps for this IP
        $rateLimit[$clientIP] = array_filter($rateLimit[$clientIP], function($ts) use ($currentTime) {
            return ($currentTime - $ts) < 3600;
        });
        $rateLimit[$clientIP] = array_values($rateLimit[$clientIP]); // Re-index array
        
        if (count($rateLimit[$clientIP]) >= 3) {
            // Release lock and close file
            flock($fp, LOCK_UN);
            fclose($fp);
            
            echo json_encode(['success' => false, 'message' => 'Příliš mnoho požadavků. Zkuste to prosím za chvíli.']);
            exit;
        }
    } else {
        $rateLimit[$clientIP] = [];
    }
    
    // Store this for later (after successful email send)
    $shouldUpdateRateLimit = true;
} else {
    // Could not acquire lock
    fclose($fp);
    error_log('Failed to acquire lock on rate limit file');
    echo json_encode(['success' => false, 'message' => 'Chyba serveru. Zkuste to prosím později.']);
    exit;
}

// Honeypot check
if (!empty($_POST['website'])) {
    // Release lock and close file before exit
    flock($fp, LOCK_UN);
    fclose($fp);
    
    echo json_encode(['success' => true, 'message' => 'Rezervace odeslána']);
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

if (!empty($errors)) {
    // Release lock and close file before exit
    flock($fp, LOCK_UN);
    fclose($fp);
    
    echo json_encode(['success' => false, 'message' => 'Zkontrolujte prosím vyplněné údaje']);
    exit;
}

// SECURITY FIX: Sanitize email to prevent header injection
// Remove any newline characters that could be used for header injection
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$email = str_replace(array("\r", "\n", "%0a", "%0d"), '', $email);

// Double-check after sanitization
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Release lock and close file before exit
    flock($fp, LOCK_UN);
    fclose($fp);
    
    echo json_encode(['success' => false, 'message' => 'Neplatná emailová adresa']);
    exit;
}

// Sanitize name for email headers (no newlines)
$name = str_replace(array("\r", "\n", "%0a", "%0d"), '', $name);

// Prepare email
$emailSubject = "Nová rezervace akce z webu - " . $name;

$emailBody = "=================================\n";
$emailBody .= "NOVÁ REZERVACE AKCE Z WEBU\n";
$emailBody .= "=================================\n\n";
$emailBody .= "Jméno: " . $name . "\n";
$emailBody .= "Telefon: " . $phone . "\n";
$emailBody .= "E-mail: " . $email . "\n\n";
$emailBody .= "Popis:\n";
$emailBody .= $note . "\n\n";
$emailBody .= "---\n";
$emailBody .= "Odesláno: " . date('d.m.Y H:i') . "\n";
$emailBody .= "IP adresa: " . $clientIP . "\n";

// SECURITY FIX: Use safe email headers without X-Mailer (hides PHP version)
$headers = "From: " . $email . "\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Send email
$mailSent = mail($recipientEmail, $emailSubject, $emailBody, $headers);

if ($mailSent) {
    // Update rate limit only if email was successfully sent
    $rateLimit[$clientIP][] = $currentTime;
    
    // Truncate file and write updated data
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($rateLimit));
    
    // Release lock and close file
    flock($fp, LOCK_UN);
    fclose($fp);
    
    echo json_encode(['success' => true, 'message' => 'Rezervace byla úspěšně odeslána!']);
} else {
    // Release lock and close file without updating rate limit
    flock($fp, LOCK_UN);
    fclose($fp);
    
    echo json_encode(['success' => false, 'message' => 'Nepodařilo se odeslat email. Zkuste to prosím znovu nebo zavolejte.']);
}

exit;
