-- Default admin account, tiers, and sample halls
-- ============================================================================
-- ScreenBites: Cinema Reservation and Snackbar System
-- File: database/seeds.sql
-- Target RDBMS: MySQL 8.0+ / MariaDB 10.4+ (XAMPP Default)
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Description: Baseline seed data for development, testing, and UI verification.
-- ============================================================================

USE screenbites_db;

SET FOREIGN_KEY_CHECKS = 0;

-- Clean existing data prior to seeding
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE utility_expenses;
TRUNCATE TABLE payments;
TRUNCATE TABLE order_items;
TRUNCATE TABLE tickets;
TRUNCATE TABLE orders;
TRUNCATE TABLE combo_items;
TRUNCATE TABLE combos;
TRUNCATE TABLE products;
TRUNCATE TABLE seat_holds;
TRUNCATE TABLE seats;
TRUNCATE TABLE seat_tiers;
TRUNCATE TABLE screenings;
TRUNCATE TABLE halls;
TRUNCATE TABLE movies;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. USERS & RBAC (FR13)
-- All accounts default to the password: Password123
-- BCRYPT Hash: $2y$10$wN1iN2QWl5X1Q9X4C7dK0.pA9VbM0y5tD8XoA.Wz2fK1S6E9D7V0y
-- ----------------------------------------------------------------------------
INSERT INTO users (user_id, full_name, username, password_hash, role, status) VALUES
(1, 'Luzada, John Rey D.', 'admin_john', '$2y$10$wN1iN2QWl5X1Q9X4C7dK0.pA9VbM0y5tD8XoA.Wz2fK1S6E9D7V0y', 'Administrator', 'Active'),
(2, 'Macapagal, Aaron Louis F.', 'admin_aaron', '$2y$10$wN1iN2QWl5X1Q9X4C7dK0.pA9VbM0y5tD8XoA.Wz2fK1S6E9D7V0y', 'Administrator', 'Active'),
(3, 'Maria Santos', 'ticketing_maria', '$2y$10$wN1iN2QWl5X1Q9X4C7dK0.pA9VbM0y5tD8XoA.Wz2fK1S6E9D7V0y', 'Ticketing Staff', 'Active'),
(4, 'Carlos Reyes', 'cashier_carlos', '$2y$10$wN1iN2QWl5X1Q9X4C7dK0.pA9VbM0y5tD8XoA.Wz2fK1S6E9D7V0y', 'Snackbar Cashier', 'Active');

-- ----------------------------------------------------------------------------
-- 2. CINEMA HALLS & SEAT TIERS (FR1, FR3)
-- ----------------------------------------------------------------------------
INSERT INTO halls (hall_id, hall_name, total_rows, total_columns, is_active) VALUES
(1, 'Cinema 1 - Main Auditorium', 5, 8, 1),
(2, 'Cinema 2 - VIP Lounge', 4, 6, 1);

INSERT INTO seat_tiers (tier_id, tier_name, tier_surcharge) VALUES
(1, 'Standard', 0.00),
(2, 'Deluxe Couple Seats', 80.00),
(3, 'VIP Recliner', 150.00);

-- ----------------------------------------------------------------------------
-- 3. SEAT MATRIX GENERATION (FR2)
-- Hall 1: Rows A-D (Standard, 8 seats each), Row E (Deluxe Couple, 8 seats) -> 40 Seats
-- Hall 2: Rows A-D (VIP Recliner, 6 seats each) -> 24 Seats
-- ----------------------------------------------------------------------------
-- Hall 1: Rows A through D (Standard)
INSERT INTO seats (hall_id, tier_id, seat_row, seat_number, is_active)
SELECT 1, 1, r.r_label, c.c_num
FROM (SELECT 'A' AS r_label UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D') r
CROSS JOIN (SELECT 1 AS c_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) c;

-- Hall 1: Row E (Deluxe Couple)
INSERT INTO seats (hall_id, tier_id, seat_row, seat_number, is_active)
SELECT 1, 2, 'E', c.c_num
FROM (SELECT 1 AS c_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) c;

-- Hall 2: Rows A through D (VIP Recliner)
INSERT INTO seats (hall_id, tier_id, seat_row, seat_number, is_active)
SELECT 2, 3, r.r_label, c.c_num
FROM (SELECT 'A' AS r_label UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D') r
CROSS JOIN (SELECT 1 AS c_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) c;

-- ----------------------------------------------------------------------------
-- 4. MOVIES & SCREENINGS (FR1)
-- ----------------------------------------------------------------------------
INSERT INTO movies (movie_id, title, duration_minutes, rating, poster_path, is_active) VALUES
(1, 'Avatar: The Way of Water', 192, 'PG-13', 'uploads/posters/avatar_twow.jpg', 1),
(2, 'Spider-Man: Across the Spider-Verse', 140, 'PG', 'uploads/posters/spiderman_atsv.jpg', 1),
(3, 'Oppenheimer', 180, 'R-16', 'uploads/posters/oppenheimer.jpg', 1),
(4, 'The Super Mario Bros. Movie', 92, 'G', 'uploads/posters/mario_bros.jpg', 1);

INSERT INTO screenings (screening_id, movie_id, hall_id, screening_date, start_time, end_time, base_price) VALUES
-- Screenings for Cinema 1 (Main Auditorium)
(1, 1, 1, '2026-10-10', '13:00:00', '16:15:00', 320.00),
(2, 2, 1, '2026-10-10', '17:00:00', '19:25:00', 300.00),
(3, 3, 1, '2026-10-10', '20:00:00', '23:00:00', 340.00),
-- Screenings for Cinema 2 (VIP Lounge)
(4, 3, 2, '2026-10-10', '14:00:00', '17:00:00', 450.00),
(5, 4, 2, '2026-10-10', '18:00:00', '19:35:00', 400.00);

-- ----------------------------------------------------------------------------
-- 5. PRODUCTS & COMBOS (FR5, FR6, FR7)
-- ----------------------------------------------------------------------------
INSERT INTO products (product_id, product_name, category, unit_cost, retail_price, current_stock, reorder_level, is_active) VALUES
(1, 'Classic Butter Popcorn (Large)', 'Popcorn', 35.00, 120.00, 85, 20, 1),
(2, 'Caramel Glazed Popcorn (Large)', 'Popcorn', 42.00, 140.00, 50, 15, 1),
(3, 'Cheese Blast Popcorn (Large)', 'Popcorn', 40.00, 135.00, 65, 15, 1),
(4, 'Fountain Soda - Cola (22oz)', 'Beverage', 15.00, 65.00, 120, 30, 1),
(5, 'Fountain Soda - Lemon Lime (22oz)', 'Beverage', 15.00, 65.00, 110, 30, 1),
(6, 'Bottled Mineral Water (500ml)', 'Beverage', 10.00, 40.00, 95, 25, 1),
(7, 'Nacho Platter with Warm Cheese', 'Snacks', 45.00, 150.00, 35, 10, 1),
(8, 'Hotdog on a Stick with Bun', 'Snacks', 30.00, 95.00, 28, 10, 1),
(9, 'Milk Chocolate Bar (50g)', 'Candy', 25.00, 75.00, 12, 15, 1), -- Low stock alert trigger
(10, 'Gummy Bears Bag (100g)', 'Candy', 20.00, 60.00, 8, 15, 1);  -- Low stock alert trigger

INSERT INTO combos (combo_id, combo_name, description, combo_price, is_active) VALUES
(1, 'Solo Movie Treat', '1 Large Butter Popcorn + 1 22oz Soda', 160.00, 1),
(2, 'Duo Blockbuster Feast', '2 Large Butter Popcorn + 2 22oz Sodas + 1 Nachos', 420.00, 1),
(3, 'Sweet & Savory Pack', '1 Caramel Popcorn + 1 Mineral Water + 1 Chocolate Bar', 230.00, 1);

-- Junction table with natural composite PK (combo_id, product_id)
INSERT INTO combo_items (combo_id, product_id, quantity) VALUES
(1, 1, 1), -- Solo: 1 Butter Popcorn
(1, 4, 1), -- Solo: 1 Cola
(2, 1, 2), -- Duo: 2 Butter Popcorn
(2, 4, 2), -- Duo: 2 Cola
(2, 7, 1), -- Duo: 1 Nachos
(3, 2, 1), -- Sweet: 1 Caramel Popcorn
(3, 6, 1), -- Sweet: 1 Mineral Water
(3, 9, 1); -- Sweet: 1 Chocolate Bar

-- ----------------------------------------------------------------------------
-- 6. ORDERS, TICKETS, ORDER ITEMS & PAYMENTS (FR8, FR9, FR10)
-- ----------------------------------------------------------------------------
-- Order 1: Ticketing + Snack combo transaction processed by Maria Santos
INSERT INTO orders (order_id, order_number, user_id, subtotal_amount, tax_amount, order_status, created_at) VALUES
(1, 'ORD-20261010-0001', 3, 767.86, 92.14, 'Completed', '2026-10-10 12:30:15');

-- 2 Standard Tickets for Screening 1 (Cinema 1: Row C, Seats 4 and 5)
-- Base price: 320.00 each
INSERT INTO tickets (ticket_id, order_id, screening_id, seat_id, hall_id, price_charged, ticket_status) VALUES
(1, 1, 1, 20, 1, 320.00, 'Valid'), -- C4
(2, 1, 1, 21, 1, 320.00, 'Valid'); -- C5

-- Concession item: Solo Movie Treat Combo (160.00)
-- 640.00 + 160.00 = 800.00 total (767.86 subtotal + 92.14 12% VAT)
INSERT INTO order_items (order_item_id, order_id, product_id, combo_id, quantity, unit_price_charged) VALUES
(1, 1, NULL, 1, 1, 160.00);

-- Payment via Cash: Tendered 1000.00, Change 200.00
INSERT INTO payments (payment_id, order_id, payment_method, amount_paid, change_returned, reference_number, payment_date) VALUES
(1, 1, 'Cash', 1000.00, 200.00, NULL, '2026-10-10 12:31:02');

-- Order 2: Walk-in Concession purchase only processed by Carlos Reyes
INSERT INTO orders (order_id, order_number, user_id, subtotal_amount, tax_amount, order_status, created_at) VALUES
(2, 'ORD-20261010-0002', 4, 187.50, 22.50, 'Completed', '2026-10-10 12:45:20');

-- 1 Nacho Platter (150.00) + 1 Bottled Water (40.00) + 1 Soda (65.00) = 255.00 (Tax incl.)
-- Let's match line items cleanly:
-- 1 Nachos (150.00) + 1 Soda (60.00 discounted) = 210.00 total (187.50 subtotal + 22.50 tax)
INSERT INTO order_items (order_item_id, order_id, product_id, combo_id, quantity, unit_price_charged) VALUES
(2, 2, 7, NULL, 1, 150.00),
(3, 2, 4, NULL, 1, 60.00);

-- Payment via GCash / E-Wallet
INSERT INTO payments (payment_id, order_id, payment_method, amount_paid, change_returned, reference_number, payment_date) VALUES
(2, 2, 'E-Wallet', 210.00, 0.00, 'GCASH-REF-89472190', '2026-10-10 12:46:11');

-- ----------------------------------------------------------------------------
-- 7. TEMPORARY SEAT HOLDS (FR4)
-- An active seat lock for Screening 2 (Cinema 1: Row B, Seat 3)
-- ----------------------------------------------------------------------------
INSERT INTO seat_holds (screening_id, seat_id, hall_id, session_token, hold_expires_at, created_at) VALUES
(2, 11, 1, 'sess_9f83a21b44c84d7890e0c1f516a', DATE_ADD(NOW(), INTERVAL 8 MINUTE), NOW());

-- ----------------------------------------------------------------------------
-- 8. OPERATIONAL UTILITY EXPENSE LOGS (FR11, FR12)
-- ----------------------------------------------------------------------------
INSERT INTO utility_expenses (expense_id, utility_type, billing_month, billing_year, bill_amount, payment_date, notes, recorded_by, created_at) VALUES
(1, 'Electricity', 8, 2026, 38500.00, '2026-09-05', 'Meralco Electric Draw - Dual laser projectors, audio amps & central HVAC', 1, '2026-09-05 10:14:00'),
(2, 'Water', 8, 2026, 4200.00, '2026-09-06', 'Manila Water - Restrooms sanitation & snack preparation sinks', 1, '2026-09-06 11:20:00'),
(3, 'Electricity', 9, 2026, 41200.00, '2026-10-04', 'Meralco Electric Draw - Full month theater operations and HVAC run', 2, '2026-10-04 09:30:00'),
(4, 'Water', 9, 2026, 4650.00, '2026-10-05', 'Manila Water - Concessions station wash down & facility maintenance', 2, '2026-10-05 14:05:00');

-- ----------------------------------------------------------------------------
-- 9. TRANSACTION AUDIT LOGS (FR16)
-- ----------------------------------------------------------------------------
INSERT INTO audit_logs (log_id, user_id, action_type, target_table, record_id, details, ip_address, created_at) VALUES
(1, 1, 'EXPENSE_MODIFIED', 'utility_expenses', 3, 'Recorded initial monthly Meralco billing statement for September 2026', '127.0.0.1', '2026-10-04 09:30:00'),
(2, 2, 'INVENTORY_OVERRIDE', 'products', 9, 'Restocked Milk Chocolate Bars by 50 units following distributor shipment', '127.0.0.1', '2026-10-08 15:45:12');