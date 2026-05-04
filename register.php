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

    <title>Create Account - POS System</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #4f46e5;
            --bg: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'SF Pro Display', sans-serif;
        }

        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-card {
            background: white;
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #1e293b;
            margin-bottom: 8px;
            text-align: center;
            font-size: 1.8rem;
        }

        p {
            color: #64748b;
            margin-bottom: 30px;
            text-align: center;
        }

        .input-row {
            display: flex;
            gap: 15px;
        }

        .input-group {
            flex: 1;
            text-align: left;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #475569;
            font-size: 0.95rem;
        }

        input,
        select {
            width: 100%;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            outline: none;
            background: white;
            transition: border-color 0.2s;
        }

        input:focus,
        select:focus {
            border-color: var(--primary);
        }

        .register-btn {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: opacity 0.2s;
        }

        .register-btn:hover {
            opacity: 0.9;
        }

        .footer-link {
            margin-top: 25px;
            color: #64748b;
            font-size: 0.9rem;
            text-align: center;
        }

        .footer-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="register-card">
        <h2>Create Profile</h2>
        <p>Join the team and start managing sales.</p>

        <form method="POST">

            <div class="input-row">
                <div class="input-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" placeholder="Luna" required>
                </div>
                <div class="input-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" placeholder="Arrozcaldo" required>
                </div>
            </div>

            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="lunas@example.com" required autocomplete="email">
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

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a strong password" required>
            </div>

            <button type="submit" class="register-btn">Create Account</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', async function(e) {
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
            };

            try {
                const res = await api.auth.register(payload);

                if (res.success) {
                    alert('Account created successfully! Redirecting to login…');
                    window.location.href = 'login.php';
                } else {
                    alert(res.error || 'Registration failed. Please try again.');
                    btn.textContent = 'Create Account';
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Connection error: ' + (err.message || 'Could not reach the server. Please try again.'));
                btn.textContent = 'Create Account';
                btn.disabled = false;
            }
        });
    </script>

    <!-- PWA Registration -->
    <script src="js/pwa.js"></script>
</body>

</html>
