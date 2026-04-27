<?php
// ============================================================
//  forgot_password.php  —  Password Reset via Email (Gmail SMTP)
//  GET              → HTML page
//  POST ?action=xxx → JSON API response
//
//  HOW TO SET UP (free):
//  1. Go to myaccount.google.com → Security → 2-Step Verification (enable it)
//  2. Then go to myaccount.google.com/apppasswords
//  3. Create an App Password → copy the 16-character password
//  4. In Railway Variables, add:
//       GMAIL_USER = yourgmail@gmail.com
//       GMAIL_PASS = your16charapppassword
// ============================================================
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

    // Railway: try all possible env variable sources
    $GMAIL_USER = $_ENV['GMAIL_USER'] ?? $_SERVER['GMAIL_USER'] ?? getenv('GMAIL_USER') ?: '';
    $GMAIL_PASS = $_ENV['GMAIL_PASS'] ?? $_SERVER['GMAIL_PASS'] ?? getenv('GMAIL_PASS') ?: '';

    $action = $_GET['action'] ?? '';
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    // Temp debug — remove after email works
    if ($action === 'debug') {
        $vendorExists = file_exists(__DIR__ . '/vendor/autoload.php');
        $phpmailerExists = file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php');
        respond([
            'GMAIL_USER'       => $GMAIL_USER ?: 'NOT SET',
            'GMAIL_PASS'       => $GMAIL_PASS ? 'SET (' . strlen($GMAIL_PASS) . ' chars)' : 'NOT SET',
            'vendor_exists'    => $vendorExists,
            'phpmailer_exists' => $phpmailerExists,
            'php_version'      => PHP_VERSION,
            '__DIR__'          => __DIR__,
        ]);
    }

    // ── SEND CODE ──────────────────────────────────────────
    if ($action === 'send') {
        $phone = trim($body['phone'] ?? '');
        if (!$phone) respond(['success' => false, 'error' => 'Mobile number is required.'], 400);
        if (!preg_match('/^09\d{9}$/', $phone)) {
            respond(['success' => false, 'error' => 'Enter a valid PH mobile number starting with 09.'], 400);
        }

        // Find user by phone number, get their email too
        $stmt = $pdo->prepare("SELECT id, first_name, email, phone FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        if (!$user) {
            respond(['success' => false, 'error' => 'No account found with that mobile number.'], 404);
        }

        // Generate 6-digit code
        $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        // Ensure password_resets table exists
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS password_resets (
                id         SERIAL PRIMARY KEY,
                user_id    INT NOT NULL,
                code       VARCHAR(6) NOT NULL,
                token      VARCHAR(64),
                expires_at DATETIME NOT NULL,
                used       BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );

        // Delete old codes for this user
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

        // Save new code
        $pdo->prepare("INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, ?)")
            ->execute([$user['id'], $code, $expires]);

        // Mask email: lunas@gmail.com → l***@gmail.com
        [$emailLocal, $emailDomain] = explode('@', $user['email']);
        $emailMasked = substr($emailLocal, 0, 1) . '***@' . $emailDomain;

        $devMode = ($GMAIL_USER === '' || $GMAIL_PASS === '');
        if ($devMode) {
            error_log("[DEV] Password reset code for {$user['email']}: {$code}");
            respond(['success' => true, 'email_hint' => $emailMasked, 'dev_code' => $code]);
        }

        // Always respond success immediately (code is saved in DB)
        // Email is best-effort — user can still use on-screen code if it fails
        $responseData = ['success' => true, 'email_hint' => $emailMasked];

        // Send email (10s timeout set inside sendResetEmail)
        $sent = sendResetEmail($user['email'], $user['first_name'], $code, $GMAIL_USER, $GMAIL_PASS);
        if (!$sent) {
            error_log("EMAIL FAILED: code={$code} user={$user['id']} email={$user['email']}");
            $responseData['dev_code'] = $code; // show on screen as fallback
            $responseData['email_failed'] = true;
        }

        respond($responseData);
    }

    // ── VERIFY CODE ────────────────────────────────────────
    if ($action === 'verify') {
        $phone = trim($body['phone'] ?? '');
        $code  = trim($body['code']  ?? '');
        if (!$phone || !$code) respond(['success' => false, 'error' => 'Phone and code are required.'], 400);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        if (!$user) respond(['success' => false, 'error' => 'Invalid request.'], 400);

        $stmt = $pdo->prepare(
            "SELECT id FROM password_resets
             WHERE user_id = ? AND code = ? AND used = 0 AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$user['id'], $code]);
        $reset = $stmt->fetch();
        if (!$reset) {
            respond(['success' => false, 'error' => 'Invalid or expired code. Please request a new one.'], 400);
        }

        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE password_resets SET token = ? WHERE id = ?")->execute([$token, $reset['id']]);
        respond(['success' => true, 'token' => $token]);
    }

    // ── RESET PASSWORD ─────────────────────────────────────
    if ($action === 'reset') {
        $token = trim($body['token']        ?? '');
        $newPw = trim($body['new_password'] ?? '');
        if (!$token || !$newPw) respond(['success' => false, 'error' => 'Token and new password required.'], 400);
        if (strlen($newPw) < 6) respond(['success' => false, 'error' => 'Password must be at least 6 characters.'], 400);

        $stmt = $pdo->prepare(
            "SELECT user_id FROM password_resets
             WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        if (!$reset) {
            respond(['success' => false, 'error' => 'Invalid or expired token. Please restart the reset process.'], 400);
        }

        $hash = password_hash($newPw, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $reset['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
        respond(['success' => true, 'message' => 'Password reset successfully.']);
    }

    respond(['success' => false, 'error' => 'Unknown action.'], 400);
}

// ── HELPER: Send email via PHPMailer ──────────────────────
function sendResetEmail(string $to, string $name, string $code, string $gmailUser, string $gmailPass): bool {
    // Load PHPMailer (installed via Composer in Dockerfile)
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log("PHPMailer not found at {$autoload}");
        return false;
    }
    require_once $autoload;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmailUser;
        $mail->Password   = $gmailPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0;
        $mail->Timeout    = 10;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom($gmailUser, "Luna's POS");
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = "Your Password Reset Code - Luna's POS";
        $mail->Body    = "
        <html><body style='font-family:sans-serif;background:#f8fafc;padding:20px'>
        <div style='max-width:480px;margin:0 auto;background:white;border-radius:16px;padding:32px;box-shadow:0 4px 12px rgba(0,0,0,0.08)'>
            <div style='text-align:center;margin-bottom:24px'>
                <div style='background:#eef2ff;border-radius:16px;width:64px;height:64px;display:inline-flex;align-items:center;justify-content:center;font-size:32px'>🔐</div>
            </div>
            <h2 style='color:#1e293b;text-align:center;margin:0 0 8px'>Password Reset Code</h2>
            <p style='color:#64748b;text-align:center;margin:0 0 28px'>Hi {$name}, use the code below to reset your password.</p>
            <div style='background:#eef2ff;border-radius:12px;padding:24px;text-align:center;margin-bottom:24px'>
                <div style='font-size:40px;font-weight:800;letter-spacing:12px;color:#4f46e5'>{$code}</div>
            </div>
            <p style='color:#94a3b8;font-size:13px;text-align:center'>This code expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
            <hr style='border:none;border-top:1px solid #e2e8f0;margin:24px 0'>
            <p style='color:#94a3b8;font-size:12px;text-align:center'>If you did not request a password reset, ignore this email.</p>
        </div>
        </body></html>";

        $mail->send();
        error_log("PHPMailer: email sent to {$to}");
        return true;
    } catch (\Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return false;
    }
}


// ── HTML PAGE ──────────────────────────────────────────────
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
        .step { display: none; }
        .step.active { display: block; }
        .form-group { text-align: left; margin-bottom: 18px; }
        label { display: block; margin-bottom: 7px; font-weight: 500; color: #475569; font-size: 14px; }
        input { width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; outline: none; transition: 0.2s; }
        input:focus { border-color: var(--primary); outline: 2px solid #c7d2fe; }
        .btn { width: 100%; padding: 15px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 6px; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
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

    <!-- Step 1: Enter phone number -->
    <div class="step active" id="step1">
        <h2>Forgot Password?</h2>
        <p class="sub">Enter your registered mobile number and we'll send a reset code to your email address.</p>
        <div id="msg1"></div>
        <div class="form-group">
            <label>Mobile Number</label>
            <input type="tel" id="phone-input" placeholder="09171234567" maxlength="11">
        </div>
        <button class="btn" onclick="sendCode()">
            <i class="fa-solid fa-envelope"></i> Send Reset Code
        </button>
        <div class="back-link">Remember your password? <a href="login.php">Sign In</a></div>
    </div>

    <!-- Step 2: Enter code -->
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

    <!-- Step 3: New password -->
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

    <!-- Step 4: Done -->
    <div class="step" id="step4">
        <div style="font-size:60px;color:var(--success);margin-bottom:20px;">✅</div>
        <h2>Password Reset!</h2>
        <p class="sub">Your password has been changed successfully.</p>
        <a href="login.php" class="btn" style="display:block;text-decoration:none;margin-top:10px;">Go to Login</a>
    </div>
</div>

<script>
    let resetPhone = '';
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
        const phone = document.getElementById('phone-input').value.trim();
        if (!phone) { showMsg(1, 'Please enter your mobile number.', 'error'); return; }
        if (!/^09\d{9}$/.test(phone)) { showMsg(1, 'Enter a valid PH mobile number starting with 09.', 'error'); return; }

        const btn = document.querySelector('#step1 .btn');
        btn.disabled = true;
        btn.textContent = 'Sending…';

        // 15 second timeout so button never stays stuck
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);
        const res = await fetch('forgot_password.php?action=send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone }),
            signal: controller.signal,
        }).then(r => r.json()).catch(() => null);
        clearTimeout(timer);

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-envelope"></i> Send Reset Code';

        if (!res || !res.success) {
            showMsg(1, res?.error || 'Failed to send code. Please try again.', 'error');
            return;
        }

        resetPhone = phone;

        if (res.dev_code) {
            // Gmail not configured — show code on screen
            document.getElementById('step2-sub').textContent = '⚠️ Email not configured. Test code: ' + res.dev_code;
            showMsg(2, `<strong>Dev Mode:</strong> Gmail not set up yet. Your code is: <strong style="font-size:18px">${res.dev_code}</strong>`, 'warn');
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
        btn.disabled = true;
        btn.textContent = 'Verifying…';

        const res = await fetch('forgot_password.php?action=verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: resetPhone, code }),
        }).then(r => r.json()).catch(() => null);

        btn.disabled = false;
        btn.textContent = 'Verify Code';

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
        btn.disabled = true;
        btn.textContent = 'Resetting…';

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
