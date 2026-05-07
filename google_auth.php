<?php
// ============================================================
//  google_auth.php  —  Google Sign-In / Sign-Up handler
//  POST /google_auth.php
//
//  Accepts JSON body:
//    google_id    (string)  Google "sub" UID
//    email        (string)
//    first_name   (string)
//    last_name    (string)
//    picture      (string, optional) profile photo URL
//    role         (string) 'admin' | 'staff'
//    register_only (bool, optional) — only create account, don't start session
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$google_id = trim($body['google_id']  ?? '');
$email     = trim($body['email']      ?? '');
$firstName = trim($body['first_name'] ?? '');
$lastName  = trim($body['last_name']  ?? '');
$picture   = trim($body['picture']    ?? '');
$role      = trim($body['role']       ?? 'staff');
$registerOnly = !empty($body['register_only']);

if (!$google_id || !$email) {
    respond(['success' => false, 'error' => 'Missing Google credentials.'], 400);
}

// ── Check if a user with this email already exists ────────────
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // ── Existing user: update google_id if not already set ────
    if (empty($user['google_id'])) {
        $pdo->prepare("UPDATE users SET google_id = ?, picture = ? WHERE id = ?")
            ->execute([$google_id, $picture, $user['id']]);
    }

    if ($registerOnly) {
        respond(['success' => false, 'error' => 'An account with this email already exists. Please sign in instead.'], 409);
    }

    // Start session and return
    $_SESSION['user'] = [
        'id'        => $user['id'],
        'name'      => $user['first_name'] . ' ' . $user['last_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'branch_id' => $user['branch_id'],
    ];
    respond(['success' => true, 'user' => $_SESSION['user']]);

} else {
    // ── New user via Google ───────────────────────────────────
    // For Google sign-ups we create the account without a password.
    // branch and employee_id come from the register form if provided.
    $branch      = trim($body['branch']      ?? '');
    $employee_id = trim($body['employee_id'] ?? '');

    $branchKeyMap = [
        'festive' => 1, 'sm_central' => 2, 'gen_luna' => 3,
        'jaro'    => 4, 'molo'       => 5, 'la_paz'   => 6,
        'calumpang' => 7, 'tagbak'   => 8,
    ];
    $branch_id = isset($branchKeyMap[$branch]) ? $branchKeyMap[$branch] : null;

    // We store a random unusable password hash so the column is never NULL
    $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

    $ins = $pdo->prepare(
        "INSERT INTO users (first_name, last_name, email, password, role, employee_id, branch_id, google_id, picture)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([$firstName, $lastName, $email, $dummyHash, $role, $employee_id, $branch_id, $google_id, $picture]);
    $newId = $pdo->lastInsertId();

    if ($registerOnly) {
        respond(['success' => true, 'message' => 'Account created successfully.']);
    }

    $_SESSION['user'] = [
        'id'        => $newId,
        'name'      => "$firstName $lastName",
        'email'     => $email,
        'role'      => $role,
        'branch_id' => $branch_id,
    ];
    respond(['success' => true, 'user' => $_SESSION['user']]);
}
