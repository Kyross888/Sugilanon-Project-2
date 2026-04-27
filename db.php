<?php
// ============================================================
//  db.php  —  Supabase PostgreSQL Connection (Session Pooler)
// ============================================================

define('DB_HOST',    'aws-1-ap-southeast-1.pooler.supabase.com');
define('DB_NAME',    'postgres');
define('DB_USER',    'postgres.luzzuclmtjfphkcjrjzc');
define('DB_PASS',    'Luna@POS2026!');
define('DB_PORT',    6543);

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
