# Lesson 2: Setting Up the System

## Overview
This lesson walks you through how to set up and run Luna's POS on your local machine or server.

## What You Will Learn
- Requirements needed to run the system
- How to set up the database
- How to configure the environment
- How to run the project

---

## 2.1 Requirements
Before setting up, make sure you have:

- **PHP** 8.0 or higher
- **PostgreSQL** database (or a Supabase account)
- A web server (Apache recommended) or Docker
- A web browser (Chrome, Firefox, Edge)

## 2.2 Project Structure
```
Sugilanon-Project-2/
├── index.php           # Entry point (redirects to login)
├── login.php           # Login page
├── dashboard.php       # Admin/staff dashboard
├── pos_terminal.php    # POS checkout interface
├── inventory.php       # Inventory management
├── orders.php          # Order history
├── products.php        # Product management
├── customer.php        # Customer records
├── salesreport.php     # Sales reports
├── analytics.php       # Analytics dashboard
├── settings.php        # System settings
├── db.php              # Database connection
├── auth.php            # Authentication logic
├── schema.sql          # Database schema
└── manifest.json       # PWA configuration
```

## 2.3 Database Setup
1. Open your PostgreSQL client or Supabase dashboard.
2. Create a new database (e.g., `lunas_pos`).
3. Run the `schema.sql` file to create all required tables:
   ```bash
   psql -U your_user -d lunas_pos -f schema.sql
   ```

## 2.4 Configuration
Open `db.php` and update your database credentials:
```php
$host = 'your_host';
$dbname = 'lunas_pos';
$user = 'your_user';
$password = 'your_password';
```

## 2.5 Running with Docker
If you prefer Docker, a `Dockerfile` is included:
```bash
docker build -t lunas-pos .
docker run -p 8080:80 lunas-pos
```
Then open `http://localhost:8080` in your browser.

## 2.6 Running with Apache
1. Copy the project folder to your Apache `htdocs` or `www` directory.
2. Start Apache and navigate to `http://localhost/Sugilanon-Project-2/`.

## 2.7 First Login
- Default admin credentials are set during database setup.
- You can also enable **Google Sign-In** by following the instructions in `GOOGLE_SETUP.md`.

---

## Summary
You now know how to set up the database, configure the connection, and run Luna's POS using either Docker or Apache.

## Next Lesson
➡️ [Lesson 3: User Authentication and Roles](Lesson_3_Guide.md)
