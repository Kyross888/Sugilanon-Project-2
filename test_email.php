<?php
header('Content-Type: text/html');

$GMAIL_USER = $_ENV['GMAIL_USER'] ?? $_SERVER['GMAIL_USER'] ?? getenv('GMAIL_USER') ?: '';
$GMAIL_PASS = $_ENV['GMAIL_PASS'] ?? $_SERVER['GMAIL_PASS'] ?? getenv('GMAIL_PASS') ?: '';

echo "<h2>Email Test</h2>";
echo "<p>GMAIL_USER: <strong>" . htmlspecialchars($GMAIL_USER ?: 'NOT SET') . "</strong></p>";
echo "<p>GMAIL_PASS: <strong>" . ($GMAIL_PASS ? 'SET (' . strlen($GMAIL_PASS) . ' chars)' : 'NOT SET') . "</strong></p>";
echo "<p>Vendor: <strong>" . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'EXISTS' : 'MISSING') . "</strong></p>";
echo "<hr>";

if (!$GMAIL_USER || !$GMAIL_PASS) {
    die("<p style='color:red'>❌ Gmail credentials not set in Railway variables.</p>");
}

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("<p style='color:red'>❌ PHPMailer not installed (vendor/autoload.php missing).</p>");
}

require_once __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $GMAIL_USER;
    $mail->Password   = $GMAIL_PASS;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPDebug  = 2; // Show full debug output
    $mail->Debugoutput = function($str, $level) {
        echo "<pre style='font-size:11px;background:#111;color:#0f0;padding:4px'>" . htmlspecialchars($str) . "</pre>";
    };
    $mail->Timeout    = 15;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom($GMAIL_USER, "Luna's POS Test");
    $mail->addAddress($GMAIL_USER); // Send test email to yourself
    $mail->Subject = 'Test Email from Luna POS';
    $mail->Body    = 'If you receive this, email is working correctly!';

    $mail->send();
    echo "<p style='color:green;font-size:18px'>✅ Email sent successfully to " . htmlspecialchars($GMAIL_USER) . "! Check your inbox.</p>";

} catch (Exception $e) {
    echo "<p style='color:red;font-size:16px'>❌ Email failed: <strong>" . htmlspecialchars($mail->ErrorInfo) . "</strong></p>";
}
