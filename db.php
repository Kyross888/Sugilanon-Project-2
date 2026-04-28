<?php
// ============================================================
//  db.php  —  Supabase PostgreSQL Connection (Session Pooler)
// ============================================================

define('DB_HOST',    'aws-1-ap-southeast-1.pooler.supabase.com');
define('DB_NAME',    'postgres');
define('DB_USER',    'postgres.luzzuclmtjfphkcjrjzc');
define('DB_PASS',    'Luna@POS2026!');
define('DB_PORT',    6543);

// ── Supabase Auth (REST API) ──────────────────────────────────
// Find these in: Supabase Dashboard → Project Settings → API
// SUPABASE_URL  = https://YOUR-PROJECT-REF.supabase.co
// SUPABASE_ANON = your anon/public key (starts with eyJ...)
define('SUPABASE_URL',  'https://luzzuclmtjfphkcjrjzc.supabase.co');
define('SUPABASE_ANON', 'YOUR_SUPABASE_ANON_KEY_HERE'); // ← paste your anon key

/**
 * Register a user with Supabase Auth so they appear in
 * Authentication → Users in the Supabase dashboard.
 * Returns the Supabase UID on success, null on failure.
 */
function supabaseAuthSignUp(string $email, string $password): ?string {
    $url  = SUPABASE_URL . '/auth/v1/admin/users';
    // Use the service_role key for admin user creation (no email confirm needed)
    // NOTE: Replace SUPABASE_ANON with your service_role key for this to work
    $body = json_encode([
        'email'            => $email,
        'password'         => $password,
        'email_confirm'    => true,   // auto-confirm so they can log in immediately
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: '        . SUPABASE_ANON,
            'Authorization: Bearer ' . SUPABASE_ANON,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        return $data['id'] ?? null;  // Supabase UID
    }
    // Log error but don't block registration — user still saves to your DB
    error_log("Supabase Auth signup failed (HTTP $httpCode): $response");
    return null;
}

$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 8,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec("SET TIME ZONE 'UTC'");
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => 'DB Error: ' . $e->getMessage()
    ]);
    exit;
}

// ── Session helpers ──────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function requireAuth(): array {
    if (empty($_SESSION['user'])) {
        respond(['success' => false, 'error' => 'Not authenticated'], 401);
    }
    return $_SESSION['user'];
}
