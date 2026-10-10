-- ============================================================================
-- ScreenBites: Cinema Reservation and Snackbar System
-- Target RDBMS: MySQL 8.0+ / MariaDB 10.4+ (XAMPP Default)
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Normalized strictly to Third Normal Form (3NF)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS utility_expenses;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS combo_items;
DROP TABLE IF EXISTS combos;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS seat_holds;
DROP TABLE IF EXISTS seats;
DROP TABLE IF EXISTS seat_tiers;
DROP TABLE IF EXISTS screenings;
DROP TABLE IF EXISTS halls;
DROP TABLE IF EXISTS movies;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. USERS & ACCESS CONTROL (FR13)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Administrator', 'Ticketing Staff', 'Snackbar Cashier') NOT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_username UNIQUE (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. CINEMA HALLS, TIERS & SEATING CONFIGURATION (FR1, FR2, FR3)
-- ----------------------------------------------------------------------------
CREATE TABLE halls (
    hall_id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hall_name VARCHAR(50) NOT NULL,
    total_rows TINYINT UNSIGNED NOT NULL,
    total_columns TINYINT UNSIGNED NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_halls_name UNIQUE (hall_name),
    CONSTRAINT chk_halls_grid CHECK (total_rows > 0 AND total_columns > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seat_tiers (
    tier_id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier_name VARCHAR(50) NOT NULL,
    tier_surcharge DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_seat_tiers_name UNIQUE (tier_name),
    CONSTRAINT chk_tier_surcharge CHECK (tier_surcharge >= 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seats (
    seat_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hall_id SMALLINT UNSIGNED NOT NULL,
    tier_id TINYINT UNSIGNED NOT NULL,
    seat_row CHAR(2) NOT NULL,
    seat_number TINYINT UNSIGNED NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_seats_hall_position UNIQUE (hall_id, seat_row, seat_number),
    -- Supporting key for multi-column foreign key validation in screenings/tickets
    CONSTRAINT uq_seats_identity UNIQUE (seat_id, hall_id),
    CONSTRAINT fk_seats_halls FOREIGN KEY (hall_id) 
        REFERENCES halls (hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_seats_tiers FOREIGN KEY (tier_id) 
        REFERENCES seat_tiers (tier_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_seat_number CHECK (seat_number > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. MOVIES & SCREENING SCHEDULES (FR1, FR15)
-- ----------------------------------------------------------------------------
CREATE TABLE movies (
    movie_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    rating ENUM('G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18') NOT NULL DEFAULT 'PG',
    poster_path VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_movie_duration CHECK (duration_minutes > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE screenings (
    screening_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movie_id INT UNSIGNED NOT NULL,
    hall_id SMALLINT UNSIGNED NOT NULL,
    screening_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    base_price DECIMAL(8, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Composite unique to prevent hall double-booking and provide FK target
    CONSTRAINT uq_screenings_identity UNIQUE (screening_id, hall_id),
    CONSTRAINT fk_screenings_movies FOREIGN KEY (movie_id) 
        REFERENCES movies (movie_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_screenings_halls FOREIGN KEY (hall_id) 
        REFERENCES halls (hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_screenings_time CHECK (end_time > start_time),
    CONSTRAINT chk_screenings_price CHECK (base_price >= 0.00),
    INDEX idx_screenings_timeline (screening_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. REAL-TIME SEAT HOLD MECHANISM (FR4)
-- ----------------------------------------------------------------------------
CREATE TABLE seat_holds (
    screening_id INT UNSIGNED NOT NULL,
    seat_id INT UNSIGNED NOT NULL,
    hall_id SMALLINT UNSIGNED NOT NULL,
    session_token VARCHAR(128) NOT NULL,
    hold_expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Natural composite key ensures a seat cannot be locked twice concurrently
    PRIMARY KEY (screening_id, seat_id),
    CONSTRAINT fk_holds_screening FOREIGN KEY (screening_id, hall_id) 
        REFERENCES screenings (screening_id, hall_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_holds_seat FOREIGN KEY (seat_id, hall_id) 
        REFERENCES seats (seat_id, hall_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_holds_expiry (hold_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. CONCESSIONS, INVENTORY & PACKAGES (FR5, FR6, FR7)
-- ----------------------------------------------------------------------------
CREATE TABLE products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    category ENUM('Popcorn', 'Beverage', 'Snacks', 'Candy') NOT NULL,
    unit_cost DECIMAL(8, 2) NOT NULL,
    retail_price DECIMAL(8, 2) NOT NULL,
    current_stock INT NOT NULL DEFAULT 0,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 15,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_products_name UNIQUE (product_name),
    CONSTRAINT chk_products_cost CHECK (unit_cost >= 0.00),
    CONSTRAINT chk_products_price CHECK (retail_price >= unit_cost),
    CONSTRAINT chk_products_stock CHECK (current_stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE combos (
    combo_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combo_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    combo_price DECIMAL(8, 2) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_combos_name UNIQUE (combo_name),
    CONSTRAINT chk_combo_price CHECK (combo_price >= 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pure junction table: composite primary key, no artificial surrogate PK
CREATE TABLE combo_items (
    combo_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (combo_id, product_id),
    CONSTRAINT fk_ci_combos FOREIGN KEY (combo_id) 
        REFERENCES combos (combo_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ci_products FOREIGN KEY (product_id) 
        REFERENCES products (product_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_ci_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. TRANSACTIONS, TICKETING & POINT OF SALE (FR8, FR9, FR10)
-- ----------------------------------------------------------------------------
CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(32) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    subtotal_amount DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    order_status ENUM('Completed', 'Cancelled', 'Refunded') NOT NULL DEFAULT 'Completed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_orders_number UNIQUE (order_number),
    CONSTRAINT fk_orders_users FOREIGN KEY (user_id) 
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_order_subtotal CHECK (subtotal_amount >= 0.00),
    CONSTRAINT chk_order_tax CHECK (tax_amount >= 0.00),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    ticket_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    screening_id INT UNSIGNED NOT NULL,
    seat_id INT UNSIGNED NOT NULL,
    hall_id SMALLINT UNSIGNED NOT NULL,
    price_charged DECIMAL(8, 2) NOT NULL,
    ticket_status ENUM('Valid', 'Cancelled') NOT NULL DEFAULT 'Valid',
    -- Enforces that a seat can only be sold once per screening
    CONSTRAINT uq_tickets_seat_screening UNIQUE (screening_id, seat_id),
    CONSTRAINT fk_tickets_orders FOREIGN KEY (order_id) 
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    -- Multi-column FK enforces that ticket hall matches screening hall
    CONSTRAINT fk_tickets_screening FOREIGN KEY (screening_id, hall_id) 
        REFERENCES screenings (screening_id, hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    -- Multi-column FK enforces that ticket hall matches seat hall
    CONSTRAINT fk_tickets_seat FOREIGN KEY (seat_id, hall_id) 
        REFERENCES seats (seat_id, hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_tickets_price CHECK (price_charged >= 0.00),
    INDEX idx_tickets_screening (screening_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Concessions sale items (supports standalone products and bundled combos)
CREATE TABLE order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    combo_id INT UNSIGNED NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price_charged DECIMAL(8, 2) NOT NULL,
    CONSTRAINT fk_oi_orders FOREIGN KEY (order_id) 
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oi_products FOREIGN KEY (product_id) 
        REFERENCES products (product_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_oi_combos FOREIGN KEY (combo_id) 
        REFERENCES combos (combo_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_oi_quantity CHECK (quantity > 0),
    CONSTRAINT chk_oi_price CHECK (unit_price_charged >= 0.00),
    -- Must reference either a single product or a combo, never both, never neither
    CONSTRAINT chk_oi_target_item CHECK (
        (product_id IS NOT NULL AND combo_id IS NULL) OR 
        (product_id IS NULL AND combo_id IS NOT NULL)
    ),
    INDEX idx_oi_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    payment_method ENUM('Cash', 'E-Wallet', 'Card') NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    change_returned DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    reference_number VARCHAR(100) NULL,
    payment_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_orders FOREIGN KEY (order_id) 
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_payments_amount CHECK (amount_paid >= 0.00),
    CONSTRAINT chk_payments_change CHECK (change_returned >= 0.00),
    INDEX idx_payments_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. OVERHEAD UTILITY EXPENSE LOGGING (FR11, FR12)
-- ----------------------------------------------------------------------------
CREATE TABLE utility_expenses (
    expense_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utility_type ENUM('Electricity', 'Water') NOT NULL,
    billing_month TINYINT UNSIGNED NOT NULL,
    billing_year SMALLINT UNSIGNED NOT NULL,
    bill_amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    notes TEXT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- 3NF: Only one logged bill per utility type per billing period
    CONSTRAINT uq_utility_period UNIQUE (utility_type, billing_year, billing_month),
    CONSTRAINT fk_utility_users FOREIGN KEY (recorded_by) 
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_utility_month CHECK (billing_month BETWEEN 1 AND 12),
    CONSTRAINT chk_utility_year CHECK (billing_year >= 2020),
    CONSTRAINT chk_utility_amount CHECK (bill_amount > 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. SYSTEM AUDIT LOGGING (FR16)
-- ----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action_type ENUM(
        'CANCEL_TICKET', 
        'REFUND_ORDER', 
        'INVENTORY_OVERRIDE', 
        'EXPENSE_MODIFIED', 
        'LOGIN_FAILURE'
    ) NOT NULL,
    target_table VARCHAR(50) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    details TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_users FOREIGN KEY (user_id) 
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_audit_timeline (created_at),
    INDEX idx_audit_user_action (user_id, action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
