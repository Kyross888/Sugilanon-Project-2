<?php
header('Content-Type: application/json');

$GMAIL_USER = $_ENV['GMAIL_USER'] ?? $_SERVER['GMAIL_USER'] ?? getenv('GMAIL_USER') ?: '';
$GMAIL_PASS = $_ENV['GMAIL_PASS'] ?? $_SERVER['GMAIL_PASS'] ?? getenv('GMAIL_PASS') ?: '';

$vendorExists    = file_exists(__DIR__ . '/vendor/autoload.php');
$phpmailerExists = file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php');

echo json_encode([
    'GMAIL_USER'       => $GMAIL_USER ?: 'NOT SET',
    'GMAIL_PASS'       => $GMAIL_PASS ? 'SET (' . strlen($GMAIL_PASS) . ' chars)' : 'NOT SET',
    'vendor_exists'    => $vendorExists,
    'phpmailer_exists' => $phpmailerExists,
    'php_version'      => PHP_VERSION,
    '__DIR__'          => __DIR__,
], JSON_PRETTY_PRINT);
