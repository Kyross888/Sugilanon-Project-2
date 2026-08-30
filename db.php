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
    // ✅ FIX 1: Use Philippine timezone so CURRENT_DATE matches PH calendar day.
    // Before this fix, UTC was used — at 8:00 AM PH time the UTC date rolls over
    // and all "today's" sales would disappear from the dashboard.
    $pdo->exec("SET TIME ZONE 'Asia/Manila'");
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
    // ✅ FIX 2: Extend session lifetime to 24 hours.
    // Default PHP session expires in ~24 minutes of inactivity, logging users
    // out and causing the dashboard to show stale/empty data on return.
    $sessionLifetime = 86400; // 24 hours in seconds
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Remember-Me auto-login ─────────────────────────────────────
// The 24-hour session above still expires (e.g. after not opening the
// app for a day). If that happens but a valid "remember me" cookie is
// present, silently log the user back in using the token stored in the
// database — no need to re-enter email/password. Works the same for
// both admin and staff accounts, since it's keyed off the logged-in
// user's row, not their role.
if (empty($_SESSION['user']) && !empty($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) === 2) {
        [$selector, $validator] = $parts;
        $stmt = $pdo->prepare(
            "SELECT * FROM users
             WHERE remember_selector = ? AND remember_expires > NOW()
             LIMIT 1"
        );
        $stmt->execute([$selector]);
        $u = $stmt->fetch();

        if ($u && hash_equals($u['remember_validator_hash'], hash('sha256', $validator))) {
            // Valid token — restore the session
            $_SESSION['user'] = [
                'id'        => $u['id'],
                'name'      => $u['first_name'] . ' ' . $u['last_name'],
                'email'     => $u['email'],
                'role'      => $u['role'],
                'branch_id' => $u['branch_id'],
            ];

            // Rotate the token for security (issue a new one, invalidate the old)
            $newSelector  = bin2hex(random_bytes(16));
            $newValidator = bin2hex(random_bytes(32));
            $expires      = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30); // 30 days

            $pdo->prepare(
                "UPDATE users SET remember_selector = ?, remember_validator_hash = ?, remember_expires = ? WHERE id = ?"
            )->execute([$newSelector, hash('sha256', $newValidator), $expires, $u['id']]);

            setcookie('remember_me', $newSelector . ':' . $newValidator, [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            // Invalid/expired token — clear the bad cookie
            setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/']);
        }
    }
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
