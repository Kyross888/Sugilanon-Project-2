# Lesson 3: User Authentication and Roles

## Overview
Learn how Luna's POS handles user login, session management, and role-based access for admins and staff.

## What You Will Learn
- How the login system works
- The difference between Admin and Staff roles
- How Google Sign-In is integrated
- How sessions and security are managed

---

## 3.1 Login Page
When you open the system, you are redirected to `login.php`. Users enter their:
- **Email address**
- **Password**

The system then checks credentials against the database and creates a session.

## 3.2 User Roles
Luna's POS supports two roles:

| Role | Access Level |
|---|---|
| **Admin** | Full access — manage products, inventory, staff, reports, settings |
| **Staff** | Limited access — POS terminal, view orders, basic dashboard |

The role is assigned when the user account is created.

## 3.3 Google Sign-In
The system supports **Google OAuth** for easier login. To enable it:
1. Follow the steps in `GOOGLE_SETUP.md`.
2. Create a Google Cloud project and obtain OAuth credentials.
3. Add your **Client ID** and **Client Secret** to the configuration.

Google Sign-In is handled by `google_auth.php`.

## 3.4 Forgot Password
If a user forgets their password:
1. Click **Forgot Password** on the login page.
2. Enter your registered email address.
3. A reset link will be sent to your email (handled by `forgot_password.php`).

## 3.5 Session Management
- Sessions are started upon successful login using PHP `$_SESSION`.
- Users are automatically redirected if they try to access a page without logging in.
- Logging out clears the session and redirects back to `login.php`.

## 3.6 Security Notes
- Passwords are stored as hashed values (never plain text).
- All pages check for an active session before displaying content.
- `.htaccess` is configured to protect sensitive files.

---

## Summary
Luna's POS uses a secure, role-based login system with support for traditional credentials and Google Sign-In. Admin and Staff roles control what each user can see and do.

## Next Lesson
➡️ [Lesson 4: Using the POS Terminal](Lesson_4_Guide.md)
