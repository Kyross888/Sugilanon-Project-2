-- ============================================================
--  Luna's POS — Database Schema
--  PostgreSQL / Supabase compatible
--  Paste this into Supabase → SQL Editor and click Run
-- ============================================================

-- ── BRANCHES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS branches (
  id         SERIAL PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  address    VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO branches (id, name, address) VALUES
  (1, 'Festive Mall',       'Festive Walk Mall, Iloilo City'),
  (2, 'SM Central Market',  'SM City Iloilo, Mandurriao'),
  (3, 'General Luna',       'General Luna St., Iloilo City'),
  (4, 'Jaro',               'Jaro, Iloilo City'),
  (5, 'Molo',               'Molo, Iloilo City'),
  (6, 'La Paz',             'La Paz, Iloilo City'),
  (7, 'Calumpang',          'Calumpang, Iloilo City'),
  (8, 'Tagbak',             'Tagbak, Jaro, Iloilo City')
ON CONFLICT (id) DO NOTHING;

-- Keep SERIAL in sync after manual inserts
SELECT setval('branches_id_seq', (SELECT MAX(id) FROM branches));

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id          SERIAL PRIMARY KEY,
  first_name  VARCHAR(80)  NOT NULL,
  last_name   VARCHAR(80)  NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  role        VARCHAR(10)  NOT NULL DEFAULT 'staff' CHECK (role IN ('admin','staff')),
  employee_id VARCHAR(50),
  phone       VARCHAR(20),
  branch_id   INT REFERENCES branches(id) ON DELETE SET NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account (password: admin123 — CHANGE IN PRODUCTION)
INSERT INTO users (id, first_name, last_name, email, password, role, branch_id)
VALUES (1, 'Luna', 'Admin', 'admin@lunas.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin', NULL)
ON CONFLICT (id) DO NOTHING;

SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- ── PRODUCTS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
  id          SERIAL PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  category    VARCHAR(50)  NOT NULL DEFAULT 'Rice Meal'
                CHECK (category IN (
                  'Breakfast','Merienda','Burgers And Sandwiches',
                  'Rice Meal','Native','Dessert','Drinks'
                )),
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock       INT NOT NULL DEFAULT 0,
  image_path  VARCHAR(255),
  icon        VARCHAR(10),
  branch_id   INT DEFAULT NULL REFERENCES branches(id) ON DELETE SET NULL,
  is_active   BOOLEAN NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── CUSTOMERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customers (
  id          SERIAL PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  email       VARCHAR(150),
  phone       VARCHAR(20),
  branch_id   INT REFERENCES branches(id) ON DELETE SET NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── TRANSACTIONS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
  id              SERIAL PRIMARY KEY,
  reference_no    VARCHAR(30) NOT NULL UNIQUE,
  branch_id       INT REFERENCES branches(id) ON DELETE SET NULL,
  user_id         INT REFERENCES users(id) ON DELETE SET NULL,
  customer_id     INT DEFAULT NULL REFERENCES customers(id) ON DELETE SET NULL,
  order_type      VARCHAR(10) NOT NULL DEFAULT 'Dine-in'
                    CHECK (order_type IN ('Dine-in','Take-out','Coupon')),
  payment_method  VARCHAR(10) NOT NULL DEFAULT 'Cash'
                    CHECK (payment_method IN ('Cash','GCash','Card','Others')),
  subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  coupon_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status          VARCHAR(10) NOT NULL DEFAULT 'completed'
                    CHECK (status IN ('completed','voided','pending')),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── TRANSACTION ITEMS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transaction_items (
  id             SERIAL PRIMARY KEY,
  transaction_id INT NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
  product_id     INT DEFAULT NULL REFERENCES products(id) ON DELETE SET NULL,
  product_name   VARCHAR(150) NOT NULL,
  unit_price     DECIMAL(10,2) NOT NULL,
  quantity       INT NOT NULL DEFAULT 1,
  line_total     DECIMAL(10,2) NOT NULL
);

-- ── PASSWORD RESETS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
  id         SERIAL PRIMARY KEY,
  user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  code       VARCHAR(6)  NOT NULL,
  token      VARCHAR(64),
  expires_at TIMESTAMP   NOT NULL,
  used       BOOLEAN     DEFAULT FALSE,
  created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

-- ── INDEXES ──────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_transactions_date   ON transactions  (created_at);
CREATE INDEX IF NOT EXISTS idx_transactions_branch ON transactions  (branch_id);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions  (status);
CREATE INDEX IF NOT EXISTS idx_items_transaction   ON transaction_items (transaction_id);
CREATE INDEX IF NOT EXISTS idx_products_category   ON products      (category);
CREATE INDEX IF NOT EXISTS idx_products_active     ON products      (is_active);


SELECT current_user;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) UNIQUE,
  ADD COLUMN IF NOT EXISTS picture   TEXT;

CREATE TABLE IF NOT EXISTS password_resets (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL,
    code       VARCHAR(6) NOT NULL,
    token      VARCHAR(64),
    expires_at TIMESTAMP NOT NULL,
    used       BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE products ALTER COLUMN image_path TYPE TEXT;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) UNIQUE,
  ADD COLUMN IF NOT EXISTS picture   TEXT;

CREATE TABLE IF NOT EXISTS password_resets (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL,
    code       VARCHAR(6) NOT NULL,
    token      VARCHAR(64),
    expires_at TIMESTAMP NOT NULL,
    used       BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
