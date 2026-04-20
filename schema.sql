-- ============================================================
--  Luna's POS — Database Schema
--  Compatible with MySQL 5.7+ / MariaDB 10+
--  Run this file once to set up all tables.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── BRANCHES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `branches` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `address`    VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `branches` (`id`, `name`, `address`) VALUES
  (1, 'Festive Mall',       'Festive Walk Mall, Iloilo City'),
  (2, 'SM Central Market',  'SM City Iloilo, Mandurriao'),
  (3, 'General Luna',       'General Luna St., Iloilo City'),
  (4, 'Jaro',               'Jaro, Iloilo City'),
  (5, 'Molo',               'Molo, Iloilo City'),
  (6, 'La Paz',             'La Paz, Iloilo City'),
  (7, 'Calumpang',          'Calumpang, Iloilo City'),
  (8, 'Tagbak',             'Tagbak, Jaro, Iloilo City');

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `first_name`  VARCHAR(80)  NOT NULL,
  `last_name`   VARCHAR(80)  NOT NULL,
  `email`       VARCHAR(150) NOT NULL UNIQUE,
  `password`    VARCHAR(255) NOT NULL,        -- bcrypt hash
  `role`        ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `employee_id` VARCHAR(50),
  `phone`       VARCHAR(20),                  -- for SMS password reset
  `branch_id`   INT,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account (password: admin123 — CHANGE THIS IN PRODUCTION)
INSERT IGNORE INTO `users`
  (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `branch_id`)
VALUES
  (1, 'Luna', 'Admin', 'admin@lunas.com',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'admin', NULL);

-- ── PRODUCTS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `category`    ENUM(
                  'Breakfast',
                  'Merienda',
                  'Burgers And Sandwiches',
                  'Rice Meal',
                  'Native',
                  'Dessert',
                  'Drinks'
                ) NOT NULL DEFAULT 'Rice Meal',
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock`       INT NOT NULL DEFAULT 0,
  `image_path`  VARCHAR(255),                -- e.g. img/prod_abc123.jpg
  `icon`        VARCHAR(10),                 -- emoji fallback
  `branch_id`   INT DEFAULT NULL,            -- NULL = all branches
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CUSTOMERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customers` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `email`       VARCHAR(150),
  `phone`       VARCHAR(20),
  `branch_id`   INT,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── TRANSACTIONS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `reference_no`    VARCHAR(30) NOT NULL UNIQUE,
  `branch_id`       INT,
  `user_id`         INT,
  `customer_id`     INT DEFAULT NULL,
  `order_type`      ENUM('Dine-in','Take-out','Coupon') NOT NULL DEFAULT 'Dine-in',
  `payment_method`  ENUM('Cash','GCash','Card','Others') NOT NULL DEFAULT 'Cash',
  `subtotal`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `coupon_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`          ENUM('completed','voided','pending') NOT NULL DEFAULT 'completed',
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`)   ON DELETE SET NULL,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE SET NULL,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── TRANSACTION ITEMS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transaction_items` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` INT NOT NULL,
  `product_id`     INT DEFAULT NULL,
  `product_name`   VARCHAR(150) NOT NULL,
  `unit_price`     DECIMAL(10,2) NOT NULL,
  `quantity`       INT NOT NULL DEFAULT 1,
  `line_total`     DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)     REFERENCES `products`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PASSWORD RESETS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `code`       VARCHAR(6)  NOT NULL,
  `token`      VARCHAR(64),
  `expires_at` DATETIME    NOT NULL,
  `used`       TINYINT(1)  DEFAULT 0,
  `created_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── USEFUL INDEXES ───────────────────────────────────────────
CREATE INDEX IF NOT EXISTS `idx_transactions_date`   ON `transactions`  (`created_at`);
CREATE INDEX IF NOT EXISTS `idx_transactions_branch` ON `transactions`  (`branch_id`);
CREATE INDEX IF NOT EXISTS `idx_transactions_status` ON `transactions`  (`status`);
CREATE INDEX IF NOT EXISTS `idx_items_transaction`   ON `transaction_items` (`transaction_id`);
CREATE INDEX IF NOT EXISTS `idx_products_category`   ON `products`      (`category`);
CREATE INDEX IF NOT EXISTS `idx_products_active`     ON `products`      (`is_active`);

SET FOREIGN_KEY_CHECKS = 1;
