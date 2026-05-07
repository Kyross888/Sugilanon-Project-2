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
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="icon" href="favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <link rel="stylesheet" href="login.css">
</head>
<body>

<div id="loadingOverlay">
    <div class="spinner"></div>
    <span style="color:#64748b;font-weight:500;">Signing you in…</span>
</div>

<div class="login-card">
    <div class="brand-icon"><img src="lunas.jpg" alt="Luna's Logo"></div>
    <h2>Welcome Back</h2>
    <p>Please enter your details to sign in.</p>

    <div class="role-selector">
        <button type="button" class="role-btn active" onclick="setRole('admin',this)">Admin</button>
        <button type="button" class="role-btn" onclick="setRole('staff',this)">Staff</button>
    </div>

    <!-- Google Sign-In -->
    <button class="google-btn" onclick="signInWithGoogle()">
        <svg width="20" height="20" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continue with Google
    </button>

    <div class="divider">or sign in with email</div>

    <form id="loginForm">
        <div class="input-group">
            <label>Email / Username</label>
            <input type="text" placeholder="Enter your email" required>
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" placeholder="password" required>
        </div>
        <button type="submit" class="login-btn">Sign In</button>
    </form>

    <div class="footer-link">
        Forgot password? <a href="forgot_password.php">Reset Password</a><br style="margin:6px 0;display:block;">
        <span>Don't have an account? <a href="register.php">Create Account</a></span>
    </div>
</div>

<script src="js/api.js"></script>
<script>
    // ── REPLACE with your Google OAuth Client ID ──────────────────────────
    // Get it from: https://console.cloud.google.com → APIs & Services → Credentials
    const GOOGLE_CLIENT_ID = '916893963118-1bu7l2rctucb87isdvv1h97n8athen0f.apps.googleusercontent.com';

    let currentRole = 'admin';

    function setRole(role, btn) {
        currentRole = role;
        document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    function signInWithGoogle() {
        if (GOOGLE_CLIENT_ID.startsWith('YOUR_')) {
            alert('Google Client ID not configured yet.\nPlease read the GOOGLE_SETUP.md file included in this update.');
            return;
        }
        const client = google.accounts.oauth2.initTokenClient({
            client_id: GOOGLE_CLIENT_ID,
            scope: 'email profile openid',
            callback: handleGoogleToken,
        });
        client.requestAccessToken();
    }

    async function handleGoogleToken(tokenResponse) {
        if (tokenResponse.error) {
            alert('Google sign-in was cancelled or failed.');
            return;
        }
        document.getElementById('loadingOverlay').classList.add('active');
        try {
            const infoRes = await fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
                headers: { Authorization: 'Bearer ' + tokenResponse.access_token }
            });
            const googleUser = await infoRes.json();

            const res = await fetch('google_auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    google_id:  googleUser.sub,
                    email:      googleUser.email,
                    first_name: googleUser.given_name  || '',
                    last_name:  googleUser.family_name || '',
                    picture:    googleUser.picture     || '',
                    role:       currentRole,
                })
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = data.user.role === 'admin' ? 'admin.php' : 'dashboard.php';
            } else {
                alert(data.error || 'Google sign-in failed. Please try again.');
            }
        } catch (err) {
            alert('Connection error: ' + (err.message || 'Could not reach the server.'));
        } finally {
            document.getElementById('loadingOverlay').classList.remove('active');
        }
    }

    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn      = this.querySelector('.login-btn');
        const email    = this.querySelector('input[type="text"]').value.trim();
        const password = this.querySelector('input[type="password"]').value;

        if (!email || !password) { alert('Please enter your email and password.'); return; }

        btn.textContent = 'Signing in…';
        btn.disabled = true;

        try {
            const res = await api.auth.login(email, password, currentRole);
            if (res.success) {
                window.location.href = res.user.role === 'admin' ? 'admin.php' : 'dashboard.php';
            } else {
                alert(res.error || 'Invalid credentials. Check your email, password, and selected role.');
                btn.textContent = 'Sign In';
                btn.disabled = false;
            }
        } catch (err) {
            alert('Connection error: ' + (err.message || 'Could not reach the server.'));
            btn.textContent = 'Sign In';
            btn.disabled = false;
        }
    });
</script>
<script src="js/pwa.js"></script>
</body>
</html>
