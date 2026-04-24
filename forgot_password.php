<?php
// ============================================================
//  forgot_password.php  —  Password Reset UI + SMS API
//  GET              → HTML page
//  POST ?action=xxx → JSON API response
// ============================================================
ob_start(); // Buffer output so headers can still be sent

// Handle API requests FIRST — before any HTML is output
$isApiRequest = ($_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['action']);

if ($isApiRequest) {
    ob_end_clean(); // Discard any accidental output
    // Suppress notices/warnings so they don't corrupt JSON output
    error_reporting(E_ERROR);
    ini_set('display_errors', '0');

    require_once 'db.php'; // db.php also defines respond() — do NOT redefine it here

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');

    if (!defined('SMS_API_KEY')) define('SMS_API_KEY', ''YOUR_SEMAPHORE_API_KEY_HERE'');
    if (!defined('SMS_SENDER'))  define('SMS_SENDER',  'LunasPOS');

    $action = $_GET['action'] ?? '';
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── SEND CODE ──────────────────────────────────────────
    if ($action === 'send') {
        $phone = trim($body['phone'] ?? '');
        if (!$phone) respond(['success' => false, 'error' => 'Mobile number is required.'], 400);
        if (!preg_match('/^09\d{9}$/', $phone)) {
            respond(['success' => false, 'error' => 'Enter a valid PH mobile number starting with 09.'], 400);
        }

        $stmt = $pdo->prepare("SELECT id, first_name, phone FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        if (!$user) respond(['success' => false, 'error' => 'No account found with that mobile number.'], 404);

        $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600);

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS password_resets (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NOT NULL,
                code       VARCHAR(6) NOT NULL,
                token      VARCHAR(64),
                expires_at DATETIME NOT NULL,
                used       TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );

        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
        $pdo->prepare("INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, ?)")
            ->execute([$user['id'], $code, $expires]);

        $phoneMasked = substr($phone, 0, 4) . '***' . substr($phone, -4);
        $devMode = (SMS_API_KEY === 'YOUR_SEMAPHORE_API_KEY_HERE');

        if ($devMode) {
            // SMS not configured — log code so admin can see it, and return it to UI for testing
            error_log("[DEV] Password reset code for {$phone}: {$code}");
            respond(['success' => true, 'phone_hint' => $phoneMasked, 'dev_code' => $code]);
        }

        $smsSent = sendSMS($phone, "Luna's POS: Your password reset code is {$code}. Valid for 10 minutes. Do not share this code.");
        if (!$smsSent) {
            error_log("FORGOT PASSWORD SMS FAILED: code={$code} for user_id={$user['id']} phone={$phone}");
            // SMS failed but code is saved — tell user to contact admin
            respond(['success' => true, 'phone_hint' => $phoneMasked, 'sms_failed' => true]);
        }

        respond(['success' => true, 'phone_hint' => $phoneMasked]);
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
        if (!$reset) respond(['success' => false, 'error' => 'Invalid or expired code. Please request a new one.'], 400);

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
        if (!$reset) respond(['success' => false, 'error' => 'Invalid or expired token. Please restart the reset process.'], 400);

        $hash = password_hash($newPw, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $reset['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
        respond(['success' => true, 'message' => 'Password reset successfully.']);
    }

    respond(['success' => false, 'error' => 'Unknown action.'], 400);
}

// ── HELPER: Send SMS via Semaphore ─────────────────────────
function sendSMS(string $number, string $message): bool {
    if (SMS_API_KEY === 'YOUR_SEMAPHORE_API_KEY_HERE') {
        error_log("SMS not configured. Set SMS_API_KEY in forgot_password.php");
        return false;
    }
    $number = preg_replace('/\D/', '', $number);
    if (str_starts_with($number, '0')) $number = '63' . substr($number, 1);

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'apikey'     => SMS_API_KEY,
            'number'     => $number,
            'message'    => $message,
            'sendername' => SMS_SENDER,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    error_log("Semaphore SMS response [{$httpCode}]: " . $response);
    return $httpCode === 200;
}

// respond() is already defined in db.php — no redefinition needed

// ── HTML PAGE (GET requests only) ──────────────────────────
if (!$isApiRequest) {
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- PWA Manifest & Theme -->
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
         :root {
            --primary: #4f46e5;
            --bg: #f8fafc;
            --success: #38a169;
            --danger: #e53e3e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'SF Pro Display', sans-serif;
        }
        
        body {
            background: var(--bg);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .card {
            background: white;
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: #eef2ff;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: var(--primary);
        }
        
        h2 {
            color: #1e293b;
            margin-bottom: 6px;
            font-size: 1.6rem;
        }
        
        p.sub {
            color: #64748b;
            margin-bottom: 28px;
            font-size: 14px;
        }
        
        .step {
            display: none;
        }
        
        .step.active {
            display: block;
        }
        
        .form-group {
            text-align: left;
            margin-bottom: 18px;
        }
        
        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 500;
            color: #475569;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            outline: none;
            transition: 0.2s;
        }
        
        input:focus {
            border-color: var(--primary);
            outline: 2px solid #c7d2fe;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 6px;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            margin-top: 22px;
            color: #64748b;
            font-size: 14px;
        }
        
        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
            text-align: left;
        }
        
        .alert-error {
            background: #fff5f5;
            color: var(--danger);
            border: 1px solid #fed7d7;
        }
        
        .alert-success {
            background: #f0fff4;
            color: var(--success);
            border: 1px solid #c6f6d5;
        }
        
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .code-inputs input {
            width: 50px;
            height: 56px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            border-radius: 10px;
            padding: 0;
        }
        
        .note {
            background: #eef2ff;
            color: #4338ca;
            padding: 12px;
            border-radius: 10px;
            font-size: 12px;
            text-align: left;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="logo"><i class="fa-solid fa-lock"></i></div>

        <!-- Step 1: Enter phone number -->
        <div class="step active" id="step1">
            <h2>Forgot Password?</h2>
            <p class="sub">Enter your registered mobile number and we'll send an SMS reset code to that number.</p>
            <div id="msg1"></div>
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="tel" id="phone-input" placeholder="09171234567" maxlength="11">
            </div>
            <button class="btn" onclick="sendCode()">
                <i class="fa-solid fa-paper-plane"></i> Send Reset Code
            </button>
            <div class="back-link">Remember your password? <a href="login.php">Sign In</a></div>
        </div>

        <!-- Step 2: Enter SMS code -->
        <div class="step" id="step2">
            <h2>Enter Code</h2>
            <p class="sub" id="step2-sub">A 6-digit code was sent to your phone.</p>
            <div id="msg2"></div>
            <div class="note">
                <i class="fa-solid fa-circle-info"></i> The code expires in <strong>10 minutes</strong>. Check your SMS inbox.
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

        <!-- Step 3: Set new password -->
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
            <p class="sub">Your password has been changed successfully. You can now log in with your new password.</p>
            <a href="login.php" class="btn" style="display:block;text-decoration:none;margin-top:10px;">
                Go to Login
            </a>
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
            if (!phone) {
                showMsg(1, 'Please enter your mobile number.', 'error');
                return;
            }
            if (!/^09\d{9}$/.test(phone)) {
                showMsg(1, 'Enter a valid PH mobile number starting with 09 (e.g. 09171234567).', 'error');
                return;
            }

            const btn = document.querySelector('#step1 .btn');
            btn.disabled = true;
            btn.textContent = 'Sending…';

            const res = await fetch('forgot_password.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone }),
            }).then(r => r.json()).catch(() => null);

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Reset Code';

            if (!res || !res.success) {
                showMsg(1, res?.error || 'Failed to send code. Check your number and try again.', 'error');
                return;
            }

            resetPhone = phone;

            // Dev mode: SMS not configured — show code on screen so admin can test
            if (res.dev_code) {
                document.getElementById('step2-sub').textContent =
                    `⚠️ SMS not configured. Your test code is: ${res.dev_code}`;
                showMsg(2, `<strong>Dev Mode:</strong> SMS API key not set. Code: <strong>${res.dev_code}</strong>`, 'success');
            } else if (res.sms_failed) {
                document.getElementById('step2-sub').textContent =
                    `SMS delivery failed. Please contact your admin for the code sent to ${res.phone_hint}.`;
            } else {
                document.getElementById('step2-sub').textContent =
                    `A 6-digit code was sent to ${res.phone_hint}.`;
            }

            if (isResend) showMsg(2, '✓ New code sent!', 'success');
            goStep(2);
        }

        function nextDigit(el, nextId) {
            // Only allow digits
            el.value = el.value.replace(/\D/, '');
            if (el.value && nextId) document.getElementById(nextId).focus();
        }

        function prevDigit(e, prevId) {
            if (e.key === 'Backspace' && !e.target.value && prevId) document.getElementById(prevId).focus();
        }

        async function verifyCode() {
            const code = ['d1', 'd2', 'd3', 'd4', 'd5', 'd6'].map(id => document.getElementById(id).value).join('');
            if (code.length < 6) {
                showMsg(2, 'Enter all 6 digits.', 'error');
                return;
            }

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

            if (!res || !res.success) {
                showMsg(2, res?.error || 'Invalid or expired code.', 'error');
                return;
            }

            resetToken = res.token;
            goStep(3);
        }

        async function resetPassword() {
            const newPw = document.getElementById('new-pw').value;
            const confPw = document.getElementById('confirm-pw').value;
            if (!newPw || !confPw) {
                showMsg(3, 'Both fields are required.', 'error');
                return;
            }
            if (newPw !== confPw) {
                showMsg(3, 'Passwords do not match.', 'error');
                return;
            }
            if (newPw.length < 6) {
                showMsg(3, 'Password must be at least 6 characters.', 'error');
                return;
            }

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

            if (!res || !res.success) {
                showMsg(3, res?.error || 'Reset failed. Try again.', 'error');
                return;
            }
            goStep(4);
        }
    </script>

    <!-- PWA Registration -->
    <script src="pwa.js"></script>
</body>

</html><?php } // end HTML page ?>
