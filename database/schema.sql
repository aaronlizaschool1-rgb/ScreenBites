SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS stock_restocks;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS utility_expenses;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_combos;
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

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(150) NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    role ENUM('Customer', 'Administrator', 'Ticketing Staff', 'Snackbar Cashier') NOT NULL DEFAULT 'Customer',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_username UNIQUE (username),
    CONSTRAINT uq_users_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

    CONSTRAINT uq_seats_identity UNIQUE (seat_id, hall_id),
    CONSTRAINT fk_seats_halls FOREIGN KEY (hall_id)
        REFERENCES halls (hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_seats_tiers FOREIGN KEY (tier_id)
        REFERENCES seat_tiers (tier_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_seat_number CHECK (seat_number > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movies (
    movie_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    rating ENUM('G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18') NOT NULL DEFAULT 'PG',
    poster_path VARCHAR(255) NULL,
    trailer_url VARCHAR(255) NULL,
    genre VARCHAR(100) NULL,
    synopsis TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_movie_duration CHECK (duration_minutes > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movie_images (
    image_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movie_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movie_images_movie FOREIGN KEY (movie_id)
        REFERENCES movies (movie_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_movie_images_movie (movie_id)
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

    CONSTRAINT uq_screenings_identity UNIQUE (screening_id, hall_id),
    CONSTRAINT fk_screenings_movies FOREIGN KEY (movie_id)
        REFERENCES movies (movie_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_screenings_halls FOREIGN KEY (hall_id)
        REFERENCES halls (hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_screenings_price CHECK (base_price >= 0.00),
    INDEX idx_screenings_timeline (screening_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seat_holds (
    screening_id INT UNSIGNED NOT NULL,
    seat_id INT UNSIGNED NOT NULL,
    hall_id SMALLINT UNSIGNED NOT NULL,
    session_token VARCHAR(128) NOT NULL,
    hold_expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (screening_id, seat_id),
    CONSTRAINT fk_holds_screening FOREIGN KEY (screening_id, hall_id)
        REFERENCES screenings (screening_id, hall_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_holds_seat FOREIGN KEY (seat_id, hall_id)
        REFERENCES seats (seat_id, hall_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_holds_expiry (hold_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    category ENUM('Popcorn', 'Beverage', 'Snacks', 'Candy') NOT NULL,
    unit_cost DECIMAL(8, 2) NOT NULL,
    retail_price DECIMAL(8, 2) NOT NULL,
    current_stock INT NOT NULL DEFAULT 0,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 15,
    image_path VARCHAR(255) NULL,
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

CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(32) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    customer_name VARCHAR(100) NULL,
    customer_email VARCHAR(150) NULL,
    subtotal_amount DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    order_status ENUM('Pending', 'Paid', 'Completed', 'Cancelled', 'Refunded') NOT NULL DEFAULT 'Completed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_orders_number UNIQUE (order_number),
    CONSTRAINT fk_orders_users FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_order_subtotal CHECK (subtotal_amount >= 0.00),
    CONSTRAINT chk_order_tax CHECK (tax_amount >= 0.00),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_restocks (
    restock_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    quantity_added INT NOT NULL,
    purchase_cost DECIMAL(10, 2) NOT NULL,
    supplier_name VARCHAR(150) NOT NULL,
    invoice_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    received_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_restocks_products FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_restocks_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    ticket_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    screening_id INT UNSIGNED NOT NULL,
    seat_id INT UNSIGNED NOT NULL,
    hall_id SMALLINT UNSIGNED NOT NULL,
    price_charged DECIMAL(8, 2) NOT NULL,
    ticket_status ENUM('Valid', 'Cancelled') NOT NULL DEFAULT 'Valid',

    CONSTRAINT uq_screening_seat UNIQUE (screening_id, seat_id),
    CONSTRAINT fk_tickets_orders FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_tickets_screening FOREIGN KEY (screening_id, hall_id)
        REFERENCES screenings (screening_id, hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_tickets_seat FOREIGN KEY (seat_id, hall_id)
        REFERENCES seats (seat_id, hall_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_tickets_price CHECK (price_charged >= 0.00),
    INDEX idx_tickets_screening (screening_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price_charged DECIMAL(8, 2) NOT NULL,
    CONSTRAINT fk_oi_orders FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oi_products FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_oi_quantity CHECK (quantity > 0),
    CONSTRAINT chk_oi_price CHECK (unit_price_charged >= 0.00),
    INDEX idx_oi_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_combos (
    order_combo_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    combo_id INT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price_charged DECIMAL(8, 2) NOT NULL,
    CONSTRAINT fk_oc_orders FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oc_combos FOREIGN KEY (combo_id)
        REFERENCES combos (combo_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_oc_quantity CHECK (quantity > 0),
    CONSTRAINT chk_oc_price CHECK (unit_price_charged >= 0.00),
    INDEX idx_oc_order (order_id)
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

CREATE TABLE utility_expenses (
    expense_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utility_type ENUM('Electricity', 'Water') NOT NULL,
    billing_month TINYINT UNSIGNED NOT NULL,
    billing_year SMALLINT UNSIGNED NOT NULL,
    bill_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('Unpaid', 'Paid') NOT NULL DEFAULT 'Paid',
    due_date DATE NULL,
    payment_date DATE NULL,
    notes TEXT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_utility_period UNIQUE (utility_type, billing_year, billing_month),
    CONSTRAINT fk_utility_users FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_utility_month CHECK (billing_month BETWEEN 1 AND 12),
    CONSTRAINT chk_utility_year CHECK (billing_year >= 2020),
    CONSTRAINT chk_utility_amount CHECK (bill_amount > 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL,
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

CREATE TABLE reviews (
    review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_type ENUM('movie', 'concession') NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_users FOREIGN KEY (user_id) 
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
    INDEX idx_reviews_item (item_type, item_id),
    INDEX idx_reviews_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- RELATIONAL DATABASE VIEWS (Academic Assessment: Unit 1)
-- Consolidated order and transaction ledger aggregating tickets, concessions,
-- and payment status for operational reporting and administrative auditing.
-- ============================================================================
CREATE OR REPLACE VIEW view_order_transaction_details AS
SELECT 
    o.order_id,
    o.order_number,
    o.customer_name,
    o.customer_email,
    o.created_at AS order_date,
    o.order_status,
    o.subtotal_amount,
    o.tax_amount,
    (o.subtotal_amount + o.tax_amount) AS grand_total,
    u.user_id,
    u.username AS processed_by_username,
    u.full_name AS processed_by_name,
    u.role AS processed_by_role,
    p.payment_id,
    p.payment_method,
    p.amount_paid,
    p.change_returned,
    p.payment_date,
    COALESCE(tc.ticket_count, 0) AS total_tickets,
    COALESCE(tc.ticket_total, 0.00) AS ticket_subtotal,
    COALESCE(ic.concession_count, 0) AS total_concessions,
    COALESCE(ic.concession_total, 0.00) AS concession_subtotal,
    COALESCE(cc.combo_count, 0) AS total_combos,
    COALESCE(cc.combo_total, 0.00) AS combo_subtotal
FROM orders o
LEFT JOIN users u ON o.user_id = u.user_id
LEFT JOIN payments p ON o.order_id = p.order_id
LEFT JOIN (
    SELECT order_id, COUNT(*) AS ticket_count, SUM(price_charged) AS ticket_total 
    FROM tickets 
    GROUP BY order_id
) tc ON o.order_id = tc.order_id
LEFT JOIN (
    SELECT order_id, SUM(quantity) AS concession_count, SUM(quantity * unit_price_charged) AS concession_total 
    FROM order_items 
    GROUP BY order_id
) ic ON o.order_id = ic.order_id
LEFT JOIN (
    SELECT order_id, SUM(quantity) AS combo_count, SUM(quantity * unit_price_charged) AS combo_total 
    FROM order_combos 
    GROUP BY order_id
) cc ON o.order_id = cc.order_id;

