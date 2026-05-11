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
    <link rel="icon" href="favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Account - POS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        :root { --primary: #4f46e5; --bg: #f8fafc; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'SF Pro Display',sans-serif; }
        body { background:var(--bg); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .register-card { background:white; width:100%; max-width:500px; padding:40px; border-radius:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,.1); }
        h2 { color:#1e293b; margin-bottom:8px; text-align:center; font-size:1.8rem; }
        p  { color:#64748b; margin-bottom:30px; text-align:center; }
        .input-row { display:flex; gap:15px; }
        .input-group { flex:1; text-align:left; margin-bottom:20px; }
        label { display:block; margin-bottom:8px; font-weight:500; color:#475569; font-size:.95rem; }
        input,select { width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:12px; font-size:1rem; outline:none; background:white; transition:border-color .2s; }
        input:focus,select:focus { border-color:var(--primary); }
        .register-btn { width:100%; padding:16px; background:var(--primary); color:white; border:none; border-radius:12px; font-size:1.1rem; font-weight:700; cursor:pointer; margin-top:10px; transition:opacity .2s; }
        .register-btn:hover { opacity:.9; }
        /* Divider */
        .divider { display:flex; align-items:center; gap:12px; margin:22px 0; color:#94a3b8; font-size:.85rem; }
        .divider::before,.divider::after { content:''; flex:1; height:1px; background:#e2e8f0; }
        /* Google Button */
        .google-btn { width:100%; padding:14px 16px; background:white; color:#1e293b; border:1.5px solid #e2e8f0; border-radius:12px; font-size:1rem; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:12px; transition:.2s; margin-bottom:4px; }
        .google-btn:hover { background:#f8fafc; border-color:#c7d2fe; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        /* Google pre-fill notice */
        .google-note { font-size:.8rem; color:#94a3b8; text-align:center; margin-bottom:20px; }
        .footer-link { margin-top:25px; color:#64748b; font-size:.9rem; text-align:center; }
        .footer-link a { color:var(--primary); text-decoration:none; font-weight:600; }
        /* Loading overlay */
        #loadingOverlay { display:none; position:fixed; inset:0; background:rgba(255,255,255,.75); z-index:999; align-items:center; justify-content:center; flex-direction:column; gap:12px; }
        #loadingOverlay.active { display:flex; }
        .spinner { width:40px; height:40px; border:4px solid #e2e8f0; border-top-color:var(--primary); border-radius:50%; animation:spin .7s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        /* Pre-filled field highlight */
        input.google-filled { background:#f0fdf4; border-color:#86efac; }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="spinner"></div>
    <span style="color:#64748b;font-weight:500;">Setting up your account…</span>
</div>

<div class="register-card">
    <h2>Create Profile</h2>
    <p>Join the team and start managing sales.</p>

    <!-- Google Sign-Up -->
    <button class="google-btn" onclick="signUpWithGoogle()">
        <svg width="20" height="20" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continue with Google
    </button>
    <p class="google-note">Your name and email will be filled in automatically</p>

    <div class="divider">or fill in manually</div>

    <form id="registerForm">
        <div class="input-row">
            <div class="input-group">
                <label>First Name</label>
                <input type="text" name="first_name" id="first_name" placeholder="Luna" required>
            </div>
            <div class="input-group">
                <label>Last Name</label>
                <input type="text" name="last_name" id="last_name" placeholder="Arrozcaldo" required>
            </div>
        </div>

        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" id="email" placeholder="lunas@example.com" required autocomplete="email">
        </div>

        <div class="input-group">
            <label>Assign Branch</label>
            <select name="branch" required>
                <option value="" disabled selected>Select a branch</option>
                <option value="festive">Festive Mall</option>
                <option value="sm_central">SM Central Market</option>
                <option value="gen_luna">General Luna</option>
                <option value="jaro">Jaro</option>
                <option value="molo">Molo</option>
                <option value="la_paz">La Paz</option>
                <option value="calumpang">Calumpang</option>
                <option value="tagbak">Tagbak</option>
            </select>
        </div>

        <div class="input-row">
            <div class="input-group">
                <label>Account Role</label>
                <select name="role" required>
                    <option value="staff">Staff / Cashier</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="input-group">
                <label>Employee ID</label>
                <input type="text" name="employee_id" placeholder="POS-001">
            </div>
        </div>

        <div class="input-group" id="passwordGroup">
            <label>Password <span id="passwordNote" style="color:#94a3b8;font-weight:400;font-size:.85rem;">(required)</span></label>
            <div style="position: relative;">
                <input type="password" name="password" id="password" placeholder="Create a strong password" required style="width: 100%; padding-right: 44px; box-sizing: border-box;">
                <button type="button" onclick="togglePass()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                    <i class="ti ti-eye" id="eyeIcon" style="font-size: 20px;"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="register-btn">Create Account</button>
    </form>

    <div class="footer-link">
        Already have an account? <a href="login.php">Sign In</a>
    </div>
</div>

<script src="js/api.js"></script>
<script>
    // ── REPLACE with your Google OAuth Client ID ──────────────────────────
    const GOOGLE_CLIENT_ID = '916893963118-1bu7l2rctucb87isdvv1h97n8athen0f.apps.googleusercontent.com';

    let googleData = null; // stores data from Google if used

    function togglePass() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'ti ti-eye-off';
        } else {
            input.type = 'password';
            icon.className = 'ti ti-eye';
        }
    }

    function signUpWithGoogle() {
        if (GOOGLE_CLIENT_ID.startsWith('YOUR_')) {
            alert('Google Client ID not configured yet.\nPlease read the GOOGLE_SETUP.md file included in this update.');
            return;
        }
        const client = google.accounts.oauth2.initTokenClient({
            client_id: GOOGLE_CLIENT_ID,
            scope: 'email profile openid',
            callback: prefillFromGoogle,
        });
        client.requestAccessToken();
    }

    async function prefillFromGoogle(tokenResponse) {
        if (tokenResponse.error) { alert('Google sign-in was cancelled.'); return; }
        document.getElementById('loadingOverlay').classList.add('active');
        try {
            const infoRes = await fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
                headers: { Authorization: 'Bearer ' + tokenResponse.access_token }
            });
            const user = await infoRes.json();
            googleData = user;

            // Pre-fill name + email
            setField('first_name', user.given_name  || '');
            setField('last_name',  user.family_name || '');
            setField('email',      user.email       || '');

            // Password not needed when using Google
            document.getElementById('password').required = true;
            document.getElementById('password').placeholder = 'Create a strong password';
            document.getElementById('passwordNote').textContent = '(required — used for email login too)';
        } catch (err) {
            alert('Could not fetch your Google info. Try again.');
        } finally {
            document.getElementById('loadingOverlay').classList.remove('active');
        }
    }

    function setField(id, value) {
        const el = document.getElementById(id);
        el.value = value;
        el.classList.add('google-filled');
    }

    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('.register-btn');
        btn.textContent = 'Creating…';
        btn.disabled = true;

        const payload = {
            first_name:  this.first_name.value,
            last_name:   this.last_name.value,
            email:       this.email.value,
            password:    this.password.value,
            role:        this.role.value,
            branch:      this.branch.value,
            employee_id: this.employee_id.value,
            // Pass Google data if the user used Google button
            google_id:   googleData ? googleData.sub     : null,
            picture:     googleData ? googleData.picture : null,
        };

        try {
            // If Google was used, route to google_auth.php; otherwise use normal register
            let res;
            if (googleData) {
                const r = await fetch('google_auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...payload, register_only: true })
                });
                res = await r.json();
            } else {
                res = await api.auth.register(payload);
            }

            if (res.success) {
                alert('Account created successfully! Redirecting to login…');
                window.location.href = 'login.php';
            } else {
                alert(res.error || 'Registration failed. Please try again.');
                btn.textContent = 'Create Account';
                btn.disabled = false;
            }
        } catch (err) {
            alert('Connection error: ' + (err.message || 'Could not reach the server.'));
            btn.textContent = 'Create Account';
            btn.disabled = false;
        }
    });
</script>
<script src="js/pwa.js"></script>
</body>
</html>
