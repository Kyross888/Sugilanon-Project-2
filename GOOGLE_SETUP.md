# Google Sign-In Setup Guide

Follow these steps to enable "Continue with Google" on your login and register pages.

---

## Step 1 — Add columns to your database

Run this SQL in your Supabase SQL editor:

```sql
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) UNIQUE,
  ADD COLUMN IF NOT EXISTS picture   TEXT;
```

---

## Step 2 — Get your Google OAuth Client ID

1. Go to https://console.cloud.google.com
2. Create a project (or select your existing one)
3. Navigate to **APIs & Services → Credentials**
4. Click **Create Credentials → OAuth 2.0 Client ID**
5. Choose **Web application**
6. Under **Authorized JavaScript origins**, add:
   - `http://localhost` (for local testing)
   - `https://yourdomain.com` (your live site URL)
7. Click **Create** — copy the **Client ID** (looks like `xxxxx.apps.googleusercontent.com`)

---

## Step 3 — Paste the Client ID into the files

Open **login.php** and **register.php** and replace:

```javascript
const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
```

with your actual Client ID:

```javascript
const GOOGLE_CLIENT_ID = '123456789-abcdef.apps.googleusercontent.com';
```

---

## How it works

### Login page
- User clicks **Continue with Google** → Google popup appears
- On success, `google_auth.php` is called
- If the email exists in your DB → they are logged in immediately
- If not → they see an error prompting them to register first

### Register page
- User clicks **Continue with Google** → name & email are pre-filled automatically (highlighted in green)
- User still picks their Branch, Role, and Employee ID
- On submit, the account is created without requiring a password

### Existing users
If a staff member already has a regular account, they can still use **Continue with Google** to log in — the system links the accounts by email automatically on first Google sign-in.

---

## Files changed / added

| File | What changed |
|------|-------------|
| `login.php` | Added Google button + `signInWithGoogle()` JS |
| `register.php` | Added Google button + pre-fill logic |
| `google_auth.php` | **NEW** — backend that handles Google sign-in/up |
| `GOOGLE_SETUP.md` | **NEW** — this file |

