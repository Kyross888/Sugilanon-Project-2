<?php
// ============================================================
//  forgot_password.php  —  Password Reset via Resend API
// ============================================================
function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

ob_start();

$isApiRequest = ($_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['action']);

if ($isApiRequest) {
    ob_end_clean();
    error_reporting(E_ERROR);
    ini_set('display_errors', '0');

    require_once 'db.php';

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');

    $RESEND_KEY  = $_ENV['RESEND_API_KEY'] ?? $_SERVER['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: '';
    $RESEND_FROM = $_ENV['RESEND_FROM']    ?? $_SERVER['RESEND_FROM']    ?? getenv('RESEND_FROM')    ?: 'onboarding@resend.dev';

    $action = $_GET['action'] ?? '';
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'debug') {
        respond([
            'RESEND_API_KEY' => $RESEND_KEY ? 'SET (' . strlen($RESEND_KEY) . ' chars)' : 'NOT SET',
            'RESEND_FROM'    => $RESEND_FROM,
            'php_version'    => PHP_VERSION,
        ]);
    }

    if ($action === 'send') {
        $email = trim($body['email'] ?? '');
        if (!$email) respond(['success' => false, 'error' => 'Email address is required.'], 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'error' => 'Enter a valid email address.'], 400);
        }

        $stmt = $pdo->prepare("SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            respond(['success' => false, 'error' => 'No account found with that email address.'], 404);
        }

        $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600);

        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id SERIAL PRIMARY KEY, user_id INT NOT NULL, code VARCHAR(6) NOT NULL,
            token VARCHAR(64), expires_at DATETIME NOT NULL,
            used BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
        $pdo->prepare("INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, ?)")
            ->execute([$user['id'], $code, $expires]);

        [$emailLocal, $emailDomain] = explode('@', $user['email']);
        $emailMasked = substr($emailLocal, 0, 1) . '***@' . $emailDomain;

        if (!$RESEND_KEY) {
            error_log("[DEV] Reset code for {$user['email']}: {$code}");
            respond(['success' => true, 'email_hint' => $emailMasked, 'dev_code' => $code]);
        }

        $responseData = ['success' => true, 'email_hint' => $emailMasked];
        $sent = sendResetEmail($user['email'], $user['first_name'], $code, $RESEND_KEY, $RESEND_FROM);
        if (!$sent) {
            error_log("EMAIL FAILED: code={$code} user={$user['id']}");
            $responseData['dev_code'] = $code;
            $responseData['email_failed'] = true;
        }
        respond($responseData);
    }

    if ($action === 'verify') {
        $email = trim($body['email'] ?? '');
        $code  = trim($body['code']  ?? '');
        if (!$email || !$code) respond(['success' => false, 'error' => 'Email and code are required.'], 400);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) respond(['success' => false, 'error' => 'Invalid request.'], 400);

        $stmt = $pdo->prepare("SELECT id FROM password_resets WHERE user_id = ? AND code = ? AND used = FALSE AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$user['id'], $code]);
        $reset = $stmt->fetch();
        if (!$reset) respond(['success' => false, 'error' => 'Invalid or expired code. Please request a new one.'], 400);

        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE password_resets SET token = ? WHERE id = ?")->execute([$token, $reset['id']]);
        respond(['success' => true, 'token' => $token]);
    }

    if ($action === 'reset') {
        $token = trim($body['token']        ?? '');
        $newPw = trim($body['new_password'] ?? '');
        if (!$token || !$newPw) respond(['success' => false, 'error' => 'Token and new password required.'], 400);
        if (strlen($newPw) < 6) respond(['success' => false, 'error' => 'Password must be at least 6 characters.'], 400);

        $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND used = FALSE AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        if (!$reset) respond(['success' => false, 'error' => 'Invalid or expired token. Please restart.'], 400);

        $hash = password_hash($newPw, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $reset['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?")->execute([$token]);
        respond(['success' => true, 'message' => 'Password reset successfully.']);
    }

    respond(['success' => false, 'error' => 'Unknown action.'], 400);
}

function sendResetEmail(string $to, string $name, string $code, string $resendKey, string $fromEmail): bool {
    $html = "<html><body style='font-family:sans-serif;background:#f8fafc;padding:20px'>
    <div style='max-width:480px;margin:0 auto;background:white;border-radius:16px;padding:32px;box-shadow:0 4px 12px rgba(0,0,0,0.08)'>
        <h2 style='color:#1e293b;text-align:center;margin:0 0 8px'>Password Reset Code</h2>
        <p style='color:#64748b;text-align:center;margin:0 0 28px'>Hi {$name}, use the code below to reset your password.</p>
        <div style='background:#eef2ff;border-radius:12px;padding:24px;text-align:center;margin-bottom:24px'>
            <div style='font-size:40px;font-weight:800;letter-spacing:12px;color:#4f46e5'>{$code}</div>
        </div>
        <p style='color:#94a3b8;font-size:13px;text-align:center'>This code expires in <strong>10 minutes</strong>.</p>
    </div></body></html>";

    $payload = json_encode([
        'from'    => "Luna's POS <{$fromEmail}>",
        'to'      => [$to],
        'subject' => "Your Password Reset Code - Luna's POS",
        'html'    => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $resendKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) { error_log("Resend curl error: {$curlErr}"); return false; }
    if ($httpCode < 200 || $httpCode >= 300) { error_log("Resend error HTTP {$httpCode}: {$response}"); return false; }

    error_log("Resend: email sent to {$to}");
    return true;
}

if (!$isApiRequest) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Luna's POS">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon-96x96.png">
    <link rel="icon" href="favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4f46e5; --bg: #f8fafc; --success: #38a169; --danger: #e53e3e; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'SF Pro Display', sans-serif; }
        body { background: var(--bg); height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: white; width: 100%; max-width: 420px; padding: 40px; border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center; }
        .logo { width: 80px; height: 80px; background: #eef2ff; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; color: var(--primary); }
        h2 { color: #1e293b; margin-bottom: 6px; font-size: 1.6rem; }
        p.sub { color: #64748b; margin-bottom: 28px; font-size: 14px; }
        .step { display: none; } .step.active { display: block; }
        .form-group { text-align: left; margin-bottom: 18px; }
        label { display: block; margin-bottom: 7px; font-weight: 500; color: #475569; font-size: 14px; }
        input { width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; outline: none; transition: 0.2s; }
        input:focus { border-color: var(--primary); outline: 2px solid #c7d2fe; }
        .btn { width: 100%; padding: 15px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 6px; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); } .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .back-link { margin-top: 22px; color: #64748b; font-size: 14px; }
        .back-link a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; text-align: left; }
        .alert-error { background: #fff5f5; color: var(--danger); border: 1px solid #fed7d7; }
        .alert-success { background: #f0fff4; color: var(--success); border: 1px solid #c6f6d5; }
        .alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .code-inputs { display: flex; gap: 10px; justify-content: center; margin-bottom: 20px; }
        .code-inputs input { width: 50px; height: 56px; text-align: center; font-size: 22px; font-weight: 700; border-radius: 10px; padding: 0; }
        .note { background: #eef2ff; color: #4338ca; padding: 12px; border-radius: 10px; font-size: 12px; text-align: left; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo"><i class="fa-solid fa-lock"></i></div>

    <div class="step active" id="step1">
        <h2>Forgot Password?</h2>
        <p class="sub">Enter your registered email address and we'll send a reset code to your inbox.</p>
        <div id="msg1"></div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" id="email-input" placeholder="you@example.com" autocomplete="email">
        </div>
        <button class="btn" onclick="sendCode()">
            <i class="fa-solid fa-envelope"></i> Send Reset Code
        </button>
        <div class="back-link">Remember your password? <a href="login.php">Sign In</a></div>
    </div>

    <div class="step" id="step2">
        <h2>Check Your Email</h2>
        <p class="sub" id="step2-sub">A 6-digit code was sent to your email.</p>
        <div id="msg2"></div>
        <div class="note">
            <i class="fa-solid fa-circle-info"></i> The code expires in <strong>10 minutes</strong>. Check your inbox and spam folder.
        </div>
        <div class="code-inputs">
            <input type="text" inputmode="numeric" maxlength="1" class="code-digit" id="d1" oninput="nextDigit(this,'d2')" onkeydown="prevDigit(event,'')">
            <input type="text" inputmode="numeric" maxlength="1" class="code-digit" id="d2" oninput="nextDigit(this,'d3')" onkeydown="prevDigit(event,'d1')">
            <input type="text" inputmode="numeric" maxlength="1" class="code-digit" id="d3" oninput="nextDigit(this,'d4')" onkeydown="prevDigit(event,'d2')">
            <input type="text" inputmode="numeric" maxlength="1" class="code-digit" id="d4" oninput="nextDigit(this,'d5')" onkeydown="prevDigit(event,'d3')">
            <input type="text" inputmode="numeric" maxlength="1" class="code-digit" id="d5" oninput="nextDigit(this,'d6')" onkeydown="prevDigit(event,'d4')">
            <input type="text" inputmode="numeric" maxlength="1" class="code-digit" id="d6" oninput="nextDigit(this,'')" onkeydown="prevDigit(event,'d5')">
        </div>
        <button class="btn" onclick="verifyCode()">Verify Code</button>
        <div class="back-link" style="margin-top:14px;">
            <a href="#" onclick="goStep(1)">← Back</a> &nbsp;|&nbsp;
            <a href="#" onclick="sendCode(true)">Resend Code</a>
        </div>
    </div>

    <div class="step" id="step3">
        <h2>New Password</h2>
        <p class="sub">Create a strong new password for your account.</p>
        <div id="msg3"></div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" id="new-pw" placeholder="At least 6 characters">
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" id="confirm-pw" placeholder="Repeat new password">
        </div>
        <button class="btn" onclick="resetPassword()">
            <i class="fa-solid fa-check"></i> Reset Password
        </button>
    </div>

    <div class="step" id="step4">
        <div style="font-size:60px;color:var(--success);margin-bottom:20px;">✅</div>
        <h2>Password Reset!</h2>
        <p class="sub">Your password has been changed successfully.</p>
        <a href="login.php" class="btn" style="display:block;text-decoration:none;margin-top:10px;">Go to Login</a>
    </div>
</div>

<script>
    let resetEmail = '';
    let resetToken = '';

    function goStep(n) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + n).classList.add('active');
    }

    function showMsg(stepNum, msg, type) {
        const el = document.getElementById('msg' + stepNum);
        el.innerHTML = msg ? `<div class="alert alert-${type}">${msg}</div>` : '';
    }

    async function sendCode(isResend = false) {
        const email = document.getElementById('email-input').value.trim();
        if (!email) { showMsg(1, 'Please enter your email address.', 'error'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showMsg(1, 'Enter a valid email address.', 'error'); return; }

        const btn = document.querySelector('#step1 .btn');
        btn.disabled = true; btn.textContent = 'Sending…';

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 20000);
        const res = await fetch('forgot_password.php?action=send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email }),
            signal: controller.signal,
        }).then(r => r.json()).catch(() => null);
        clearTimeout(timer);

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-envelope"></i> Send Reset Code';

        if (!res || !res.success) { showMsg(1, res?.error || 'Failed to send code. Please try again.', 'error'); return; }

        resetEmail = email;

        if (res.dev_code) {
            document.getElementById('step2-sub').textContent = '⚠️ Email not configured. Test code: ' + res.dev_code;
            showMsg(2, `<strong>Dev Mode:</strong> Your code is: <strong style="font-size:18px">${res.dev_code}</strong>`, 'warn');
        } else if (res.email_failed) {
            document.getElementById('step2-sub').textContent = '⚠️ Email delivery failed. Test code: ' + res.dev_code;
            showMsg(2, `<strong>Email failed.</strong> Your code is: <strong style="font-size:18px">${res.dev_code}</strong>`, 'warn');
        } else {
            document.getElementById('step2-sub').textContent = `A 6-digit code was sent to ${res.email_hint}. Check your inbox.`;
        }

        if (isResend) showMsg(2, '✓ New code sent! Check your email.', 'success');
        goStep(2);
    }

    function nextDigit(el, nextId) {
        el.value = el.value.replace(/\D/, '');
        if (el.value && nextId) document.getElementById(nextId).focus();
    }

    function prevDigit(e, prevId) {
        if (e.key === 'Backspace' && !e.target.value && prevId) document.getElementById(prevId).focus();
    }

    async function verifyCode() {
        const code = ['d1','d2','d3','d4','d5','d6'].map(id => document.getElementById(id).value).join('');
        if (code.length < 6) { showMsg(2, 'Enter all 6 digits.', 'error'); return; }

        const btn = document.querySelector('#step2 .btn');
        btn.disabled = true; btn.textContent = 'Verifying…';

        const res = await fetch('forgot_password.php?action=verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: resetEmail, code }),
        }).then(r => r.json()).catch(() => null);

        btn.disabled = false; btn.textContent = 'Verify Code';
        if (!res || !res.success) { showMsg(2, res?.error || 'Invalid or expired code.', 'error'); return; }
        resetToken = res.token;
        goStep(3);
    }

    async function resetPassword() {
        const newPw  = document.getElementById('new-pw').value;
        const confPw = document.getElementById('confirm-pw').value;
        if (!newPw || !confPw) { showMsg(3, 'Both fields are required.', 'error'); return; }
        if (newPw !== confPw)  { showMsg(3, 'Passwords do not match.', 'error'); return; }
        if (newPw.length < 6)  { showMsg(3, 'Password must be at least 6 characters.', 'error'); return; }

        const btn = document.querySelector('#step3 .btn');
        btn.disabled = true; btn.textContent = 'Resetting…';

        const res = await fetch('forgot_password.php?action=reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: resetToken, new_password: newPw }),
        }).then(r => r.json()).catch(() => null);

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Reset Password';
        if (!res || !res.success) { showMsg(3, res?.error || 'Reset failed. Try again.', 'error'); return; }
        goStep(4);
    }
</script>
<script src="pwa.js"></script>
</body>
</html>
<?php } ?>
