# Lesson 8: System Settings and Multi-Branch Management

## Overview
The Settings module lets admins configure the system, manage user accounts, and handle multi-branch operations across all 8 Luna's Arrozcaldo locations.

## What You Will Learn
- How to manage user accounts and roles
- How to configure system preferences
- How multi-branch support works
- How to use the PWA (Progressive Web App) feature

---

## 8.1 Opening Settings
Click **Settings** in the sidebar. This opens `settings.php`.

Only **Admins** have access to the Settings page.

## 8.2 Managing User Accounts
Admins can:
- **Add new users** (Staff or Admin)
- **Edit user details** (name, email, role)
- **Reset passwords** for any user
- **Deactivate accounts** for former employees

### Adding a New User
1. Go to **Settings → Users**.
2. Click **Add User**.
3. Enter name, email, and assign a role (Admin or Staff).
4. Click **Save** — the user receives a welcome email with login instructions.

## 8.3 Branch Management
Luna's POS supports **8 branches** across Iloilo City. Admins can:
- View and switch between branches
- Assign staff to specific branches
- View branch-specific sales data in Reports and Analytics

### Switching Branches
- Use the **Branch Selector** dropdown in the top navigation bar.
- All data (orders, inventory, reports) filters to the selected branch.

## 8.4 System Preferences
From Settings, Admins can configure:

| Setting | Description |
|---|---|
| Restaurant Name | Display name shown on receipts |
| Tax Rate | VAT or service charge percentage |
| Currency | Default currency (PHP) |
| Receipt Footer | Custom message printed on receipts |
| Low Stock Threshold | Minimum stock before alert is triggered |

## 8.5 PWA (Progressive Web App)
Luna's POS is installable as a PWA, meaning:
- Staff can **install it on their phone or desktop** like a native app.
- It works **offline** using the service worker (`sw.js`).
- The `manifest.json` file controls the app name, icon, and colors.

### Installing the PWA
1. Open the system in Chrome or Edge.
2. Click the **Install** icon in the browser address bar.
3. The app is added to your home screen or desktop.

## 8.6 Google Sign-In Setup
Refer to `GOOGLE_SETUP.md` for detailed steps on:
- Creating a Google Cloud project
- Enabling OAuth credentials
- Adding the Client ID to the system

---

## Summary
The Settings module gives admins complete control over users, branches, receipts, and system behavior — keeping Luna's POS tailored to each restaurant's needs.

---

## 🎉 Congratulations!
You have completed all 8 lessons. You are now ready to fully use and manage **Luna's Arrozcaldo POS System**.

For further reference, check the `README.md` file in the root of the project.
