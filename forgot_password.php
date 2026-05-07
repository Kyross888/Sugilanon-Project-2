<?php
// ============================================================
//  forgot_password.php  —  Password Reset via EmailJS
// ============================================================

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    handleApi();
} else {
    showPage();
}

function handleApi(): void {
    global $pdo;
    error_reporting(0);
    ini_set('display_errors', '0');
    header('Content-Type: application/json');

    $action = $_GET['action'] ?? '';
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id         SERIAL PRIMARY KEY,
            user_id    INT NOT NULL,
            code       VARCHAR(6) NOT NULL,
            token      VARCHAR(64),
            expires_at TIMESTAMP NOT NULL,
            used       BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        if ($action === 'send') {
            $email = trim($body['email'] ?? '');
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
                jsonOut(['success' => false, 'error' => 'Enter a valid email address.'], 400);

            $stmt = $pdo->prepare("SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user)
                jsonOut(['success' => false, 'error' => 'No account found with that email address.'], 404);

            $code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 600);

            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
            $pdo->prepare("INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['id'], $code, $expires]);

            [$local, $domain] = explode('@', $user['email']);
            $masked = substr($local, 0, 1) . '***@' . $domain;

            // Return code + masked email — EmailJS sends from the browser
            jsonOut([
                'success'    => true,
                'email_hint' => $masked,
                'to_email'   => $user['email'],
                'to_name'    => $user['first_name'],
                'passcode'   => $code,
                'time'       => date('M d, Y h:i A'),
            ]);
        }

        if ($action === 'verify') {
            $email = trim($body['email'] ?? '');
            $code  = trim($body['code']  ?? '');
            if (!$email || !$code) jsonOut(['success' => false, 'error' => 'Email and code are required.'], 400);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) jsonOut(['success' => false, 'error' => 'Invalid request.'], 400);

            $stmt = $pdo->prepare(
                "SELECT id FROM password_resets
                 WHERE user_id = ? AND code = ? AND used = FALSE AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$user['id'], $code]);
            $reset = $stmt->fetch();
            if (!$reset) jsonOut(['success' => false, 'error' => 'Invalid or expired code. Please request a new one.'], 400);

            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE password_resets SET token = ? WHERE id = ?")->execute([$token, $reset['id']]);
            jsonOut(['success' => true, 'token' => $token]);
        }

        if ($action === 'reset') {
            $token = trim($body['token']        ?? '');
            $newPw = trim($body['new_password'] ?? '');
            if (!$token || !$newPw) jsonOut(['success' => false, 'error' => 'Token and new password required.'], 400);
            if (strlen($newPw) < 6) jsonOut(['success' => false, 'error' => 'Password must be at least 6 characters.'], 400);

            $stmt = $pdo->prepare(
                "SELECT user_id FROM password_resets
                 WHERE token = ? AND used = FALSE AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            if (!$reset) jsonOut(['success' => false, 'error' => 'Invalid or expired token. Please restart.'], 400);

            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $reset['user_id']]);
            $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?")->execute([$token]);
            jsonOut(['success' => true, 'message' => 'Password reset successfully.']);
        }

        jsonOut(['success' => false, 'error' => 'Unknown action.'], 400);

    } catch (Throwable $e) {
        error_log('[ForgotPW] ' . $e->getMessage());
        jsonOut(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

function jsonOut(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function showPage(): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Luna's POS">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="icon" href="favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- EmailJS SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <style>
        :root { --primary:#4f46e5; --bg:#f8fafc; --success:#16a34a; --danger:#dc2626; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'SF Pro Display',sans-serif; }
        body { background:var(--bg); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:white; width:100%; max-width:420px; padding:40px; border-radius:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,.1); text-align:center; }
        .logo { width:72px; height:72px; background:#eef2ff; border-radius:20px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:30px; }
        h2 { color:#1e293b; margin-bottom:6px; font-size:1.55rem; }
        p.sub { color:#64748b; margin-bottom:28px; font-size:.9rem; line-height:1.5; }
        .step { display:none; } .step.active { display:block; }
        .form-group { text-align:left; margin-bottom:18px; }
        label { display:block; margin-bottom:7px; font-weight:500; color:#475569; font-size:.9rem; }
        input[type=email],input[type=password] { width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:12px; font-size:1rem; outline:none; transition:.2s; }
        input:focus { border-color:var(--primary); outline:2px solid #c7d2fe; }
        .btn { width:100%; padding:15px; background:var(--primary); color:white; border:none; border-radius:12px; font-size:1rem; font-weight:700; cursor:pointer; transition:.2s; margin-top:6px; }
        .btn:hover { opacity:.9; transform:translateY(-1px); }
        .btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
        .back-link { margin-top:22px; color:#64748b; font-size:.85rem; }
        .back-link a { color:var(--primary); text-decoration:none; font-weight:600; }
        .alert { padding:12px 16px; border-radius:10px; font-size:.85rem; margin-bottom:16px; text-align:left; }
        .alert-error   { background:#fef2f2; color:var(--danger); border:1px solid #fecaca; }
        .alert-success { background:#f0fdf4; color:var(--success); border:1px solid #bbf7d0; }
        .code-inputs { display:flex; gap:10px; justify-content:center; margin-bottom:22px; }
        .code-inputs input { width:48px; height:58px; text-align:center; font-size:22px; font-weight:700; border-radius:12px; padding:0; border:2px solid #e2e8f0; transition:.2s; }
        .code-inputs input:focus { border-color:var(--primary); outline:none; background:#eef2ff; }
        .note { background:#eef2ff; color:#4338ca; padding:12px 14px; border-radius:10px; font-size:.8rem; text-align:left; margin-bottom:22px; line-height:1.5; }
        .progress { display:flex; justify-content:center; gap:8px; margin-bottom:28px; }
        .dot { width:8px; height:8px; border-radius:50%; background:#e2e8f0; transition:.3s; }
        .dot.active { background:var(--primary); width:24px; border-radius:4px; }
        .success-icon { font-size:64px; margin-bottom:16px; }
    </style>
</head>
<body>
<div class="card">
    <div class="progress">
        <div class="dot active" id="dot1"></div>
        <div class="dot" id="dot2"></div>
        <div class="dot" id="dot3"></div>
    </div>

    <div class="step active" id="step1">
        <div class="logo">🔐</div>
        <h2>Forgot Password?</h2>
        <p class="sub">Enter your registered email and we'll send a 6-digit reset code to your Gmail inbox.</p>
        <div id="msg1"></div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" id="email-input" placeholder="you@gmail.com" autocomplete="email">
        </div>
        <button class="btn" onclick="sendCode()">
            <i class="fa-solid fa-paper-plane"></i> Send Code to Gmail
        </button>
        <div class="back-link">Remember your password? <a href="login.php">Sign In</a></div>
    </div>

    <div class="step" id="step2">
        <div class="logo">📬</div>
        <h2>Check Your Gmail</h2>
        <p class="sub" id="step2-sub">A 6-digit code was sent to your Gmail inbox.</p>
        <div id="msg2"></div>
        <div class="note">
            <i class="fa-solid fa-circle-info"></i>&nbsp;
            Expires in <strong>10 minutes</strong>. Check your <strong>Inbox</strong> and <strong>Spam</strong> folder.
        </div>
        <div class="code-inputs">
            <input type="text" inputmode="numeric" maxlength="1" id="d1" oninput="nextDigit(this,'d2')" onkeydown="prevDigit(event,'')">
            <input type="text" inputmode="numeric" maxlength="1" id="d2" oninput="nextDigit(this,'d3')" onkeydown="prevDigit(event,'d1')">
            <input type="text" inputmode="numeric" maxlength="1" id="d3" oninput="nextDigit(this,'d4')" onkeydown="prevDigit(event,'d2')">
            <input type="text" inputmode="numeric" maxlength="1" id="d4" oninput="nextDigit(this,'d5')" onkeydown="prevDigit(event,'d3')">
            <input type="text" inputmode="numeric" maxlength="1" id="d5" oninput="nextDigit(this,'d6')" onkeydown="prevDigit(event,'d4')">
            <input type="text" inputmode="numeric" maxlength="1" id="d6" oninput="nextDigit(this,'')"  onkeydown="prevDigit(event,'d5')">
        </div>
        <button class="btn" onclick="verifyCode()">Verify Code</button>
        <div class="back-link" style="margin-top:16px;">
            <a href="#" onclick="goStep(1);return false;">← Back</a>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <a href="#" onclick="sendCode(true);return false;">Resend Code</a>
        </div>
    </div>

    <div class="step" id="step3">
        <div class="logo">🔑</div>
        <h2>Set New Password</h2>
        <p class="sub">Almost done! Create a strong new password.</p>
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
            <i class="fa-solid fa-lock"></i> Reset Password
        </button>
    </div>

    <div class="step" id="step4">
        <div class="success-icon">✅</div>
        <h2>Password Reset!</h2>
        <p class="sub">Your password has been changed. You can now sign in with your new password.</p>
        <a href="login.php" class="btn" style="display:block;text-decoration:none;margin-top:10px;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Go to Login
        </a>
    </div>
</div>

<script>
    // ── EmailJS credentials ───────────────────────────────────
    const EMAILJS_PUBLIC_KEY  = 'N784JfmP7JQjtoR06';
    const EMAILJS_SERVICE_ID  = 'service_m9cpb7x';
    const EMAILJS_TEMPLATE_ID = 'template_rk0ek1e';

    emailjs.init({ publicKey: EMAILJS_PUBLIC_KEY });

    let resetEmail = '';
    let resetToken = '';

    function goStep(n) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + n).classList.add('active');
        for (let i = 1; i <= 3; i++) {
            const dot = document.getElementById('dot' + i);
            if (dot) dot.classList.toggle('active', i === n);
        }
        if (n === 2) document.getElementById('d1').focus();
    }

    function showMsg(stepNum, msg, type) {
        const el = document.getElementById('msg' + stepNum);
        if (el) el.innerHTML = msg ? `<div class="alert alert-${type}">${msg}</div>` : '';
    }

    async function sendCode(isResend = false) {
        const email = document.getElementById('email-input').value.trim();
        if (!email) { showMsg(1, 'Please enter your email address.', 'error'); return; }

        const btn = document.querySelector('#step1 .btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';

        try {
            // Step 1: get code from PHP
            const r = await fetch('forgot_password.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email }),
            });
            const res = await r.json();

            if (!res.success) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Code to Gmail';
                showMsg(1, res.error || 'Failed. Please try again.', 'error');
                return;
            }

            // Step 2: send email via EmailJS from browser
            await emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_TEMPLATE_ID, {
                email:    res.to_email,
                to_name:  res.to_name,
                passcode: res.passcode,
                time:     res.time,
            });

            resetEmail = email;
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Code to Gmail';

            document.getElementById('step2-sub').textContent = `A 6-digit code was sent to ${res.email_hint}. Check your inbox and spam folder.`;
            if (isResend) showMsg(2, '✓ New code sent! Check your Gmail.', 'success');
            goStep(2);

        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Code to Gmail';
            showMsg(1, 'Failed to send email: ' + (err.text || err.message || 'Please try again.'), 'error');
        }
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
        if (code.length < 6) { showMsg(2, 'Please enter all 6 digits.', 'error'); return; }

        const btn = document.querySelector('#step2 .btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying…';

        try {
            const res = await fetch('forgot_password.php?action=verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: resetEmail, code }),
            }).then(r => r.json());

            btn.disabled = false;
            btn.textContent = 'Verify Code';
            if (!res.success) { showMsg(2, res.error || 'Invalid or expired code.', 'error'); return; }
            resetToken = res.token;
            goStep(3);
        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Verify Code';
            showMsg(2, 'Connection error. Try again.', 'error');
        }
    }

    async function resetPassword() {
        const newPw  = document.getElementById('new-pw').value;
        const confPw = document.getElementById('confirm-pw').value;
        if (!newPw || !confPw) { showMsg(3, 'Both fields are required.', 'error'); return; }
        if (newPw !== confPw)  { showMsg(3, 'Passwords do not match.', 'error'); return; }
        if (newPw.length < 6)  { showMsg(3, 'Password must be at least 6 characters.', 'error'); return; }

        const btn = document.querySelector('#step3 .btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Resetting…';

        try {
            const res = await fetch('forgot_password.php?action=reset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: resetToken, new_password: newPw }),
            }).then(r => r.json());

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-lock"></i> Reset Password';
            if (!res.success) { showMsg(3, res.error || 'Reset failed.', 'error'); return; }
            goStep(4);
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-lock"></i> Reset Password';
            showMsg(3, 'Connection error. Try again.', 'error');
        }
    }
</script>
<script src="js/pwa.js"></script>
</body>
</html>
<?php } ?>
