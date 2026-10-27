USE screenbites_db;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM audit_logs;
DELETE FROM utility_expenses;
DELETE FROM payments;
DELETE FROM order_items;
DELETE FROM tickets;
DELETE FROM orders;
DELETE FROM seat_holds;
DELETE FROM combo_items;
DELETE FROM combos;
DELETE FROM products;
DELETE FROM seats;
DELETE FROM seat_tiers;
DELETE FROM screenings;
DELETE FROM halls;
DELETE FROM movies;
DELETE FROM users;

ALTER TABLE audit_logs       AUTO_INCREMENT = 1;
ALTER TABLE utility_expenses AUTO_INCREMENT = 1;
ALTER TABLE payments         AUTO_INCREMENT = 1;
ALTER TABLE order_items      AUTO_INCREMENT = 1;
ALTER TABLE tickets          AUTO_INCREMENT = 1;
ALTER TABLE orders           AUTO_INCREMENT = 1;
ALTER TABLE combos           AUTO_INCREMENT = 1;
ALTER TABLE products         AUTO_INCREMENT = 1;
ALTER TABLE seats            AUTO_INCREMENT = 1;
ALTER TABLE seat_tiers       AUTO_INCREMENT = 1;
ALTER TABLE screenings       AUTO_INCREMENT = 1;
ALTER TABLE halls            AUTO_INCREMENT = 1;
ALTER TABLE movies           AUTO_INCREMENT = 1;
ALTER TABLE users            AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (user_id, full_name, username, password_hash, role, status) VALUES
(1, 'Luzada, John Rey D.', 'admin_john', '$2y$10$QcuXYsoJREs5OSFs9ISeH.9uDAlq9dHaq0SHIRYRMwp6rD1/ILmsW', 'Administrator', 'Active'),
(2, 'Macapagal, Aaron Louis F.', 'admin_aaron', '$2y$10$QcuXYsoJREs5OSFs9ISeH.9uDAlq9dHaq0SHIRYRMwp6rD1/ILmsW', 'Administrator', 'Active'),
(3, 'Maria Santos', 'ticketing_maria', '$2y$10$QcuXYsoJREs5OSFs9ISeH.9uDAlq9dHaq0SHIRYRMwp6rD1/ILmsW', 'Ticketing Staff', 'Active'),
(4, 'Carlos Reyes', 'cashier_carlos', '$2y$10$QcuXYsoJREs5OSFs9ISeH.9uDAlq9dHaq0SHIRYRMwp6rD1/ILmsW', 'Snackbar Cashier', 'Active');

INSERT INTO halls (hall_id, hall_name, total_rows, total_columns, is_active) VALUES
(1, 'Cinema 1 - Main Auditorium', 5, 8, 1),
(2, 'Cinema 2 - VIP Lounge', 4, 6, 1);

INSERT INTO seat_tiers (tier_id, tier_name, tier_surcharge) VALUES
(1, 'Standard', 0.00),
(2, 'Deluxe Couple Seats', 80.00),
(3, 'VIP Recliner', 150.00);

INSERT INTO seats (seat_id, hall_id, tier_id, seat_row, seat_number)
SELECT (ASCII(r.r_label) - 65) * 8 + c.c_num, 1, 1, r.r_label, c.c_num
FROM (SELECT 'A' AS r_label UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D') r
CROSS JOIN (SELECT 1 AS c_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) c;

INSERT INTO seats (seat_id, hall_id, tier_id, seat_row, seat_number)
SELECT 32 + c.c_num, 1, 2, 'E', c.c_num
FROM (SELECT 1 AS c_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) c;

INSERT INTO seats (seat_id, hall_id, tier_id, seat_row, seat_number)
SELECT 40 + (ASCII(r.r_label) - 65) * 6 + c.c_num, 2, 3, r.r_label, c.c_num
FROM (SELECT 'A' AS r_label UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D') r
CROSS JOIN (SELECT 1 AS c_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) c;

INSERT INTO movies (movie_id, title, duration_minutes, rating, poster_path, is_active) VALUES
(1, 'Avatar: The Way of Water', 192, 'PG-13', 'uploads/posters/avatar_twow.jpg', 1),
(2, 'Spider-Man: Across the Spider-Verse', 140, 'PG', 'uploads/posters/spiderman_atsv.jpg', 1),
(3, 'Oppenheimer', 180, 'R-16', 'uploads/posters/oppenheimer.jpg', 1),
(4, 'The Super Mario Bros. Movie', 92, 'G', 'uploads/posters/mario_bros.jpg', 1),
(5, 'Spider-Man: Brand New Day', 135, 'PG-13', 'uploads/posters/spiderman_bnd.jpg', 1),
(6, 'Avengers: Endgame', 181, 'PG-13', 'uploads/posters/endgame.jpg', 1),
(7, 'Spider-Man: No Way Home', 148, 'PG-13', 'uploads/posters/nowayhome.jpg', 1),
(8, 'Spider-Man: Far From Home', 129, 'PG-13', 'uploads/posters/farfromhome.jpg', 1),
(9, 'Inside Out', 95, 'PG', 'uploads/posters/insideout.jpg', 1),
(10, 'A Quiet Place', 90, 'PG-13', 'uploads/posters/quietplace.jpg', 1),
(11, 'The Odyssey', 155, 'PG-13', 'uploads/posters/odyssey.jpg', 1),
(12, 'Hoppers', 100, 'PG', 'uploads/posters/hoppers.jpg', 1),
(13, 'Obsession', 108, 'R-16', 'uploads/posters/obsession.jpg', 1),
(14, 'Toy Story 5', 102, 'G', 'uploads/posters/toystory5.jpg', 1),
(15, 'Peaky Blinders', 130, 'R-16', 'uploads/posters/peakyblinders.jpg', 1),
(16, 'BUDDY', 98, 'R-16', 'uploads/posters/buddy.jpg', 1),
(17, 'The Backrooms', 105, 'PG-13', 'uploads/posters/backrooms.jpg', 1),
(18, 'GOAT', 180, 'PG-13', 'uploads/posters/goat.jpg', 1),
(19, 'Colony', 112, 'R-13', 'uploads/posters/colony.jpg', 1),
(20, 'Fast and Furious', 135, 'PG-13', 'uploads/posters/fastandfurious.jpg', 1),
(21, 'Jujutsu Kaisen the Movie', 105, 'PG-13', 'uploads/posters/jjk0.png', 1),
(22, 'Send Help', 104, 'R-16', 'uploads/posters/sendhelp.jpg', 1),
(23, '500 Days of Summer', 95, 'PG-13', 'uploads/posters/500daysofsummer.jpg', 1),
(24, 'Coco', 105, 'PG', 'uploads/posters/coco.jpg', 1),
(25, 'Eternal Sunshine of the Spotless Mind', 108, 'R-13', 'uploads/posters/eternalsunshine.jpg', 1),
(26, 'Atonement', 123, 'R-16', 'uploads/posters/atonement.jpg', 1),
(27, 'The Green Mile', 189, 'R-16', 'uploads/posters/greenmile.jpg', 1),
(28, 'Forrest Gump', 142, 'PG-13', 'uploads/posters/forrestgump.jpg', 1),
(29, 'Room', 118, 'R-16', 'uploads/posters/room.jpg', 1),
(30, 'Titanic', 195, 'PG-13', 'uploads/posters/titanic.jpg', 1);

INSERT INTO screenings (screening_id, movie_id, hall_id, screening_date, start_time, end_time, base_price) VALUES

(1, 1, 1, '2026-10-10', '13:00:00', '16:15:00', 320.00),
(2, 2, 1, '2026-10-10', '17:00:00', '19:25:00', 300.00),
(3, 3, 1, '2026-10-10', '20:00:00', '23:00:00', 340.00),

(4, 3, 2, '2026-10-10', '14:00:00', '17:00:00', 450.00),
(5, 4, 2, '2026-10-10', '18:00:00', '19:35:00', 400.00);

INSERT INTO products (product_id, product_name, category, unit_cost, retail_price, current_stock, reorder_level, image_path, is_active) VALUES
(1, 'Classic Butter Popcorn (Large)', 'Popcorn', 35.00, 120.00, 85, 20, 'uploads/products/Butter_Popcorn.jpg', 1),
(2, 'Caramel Glazed Popcorn (Large)', 'Popcorn', 42.00, 140.00, 50, 15, 'uploads/products/Caramel_Popcorn.jpg', 1),
(3, 'Cheese Blast Popcorn (Large)', 'Popcorn', 40.00, 135.00, 65, 15, 'uploads/products/Cheese_Popcorn.jpg', 1),
(4, 'Fountain Soda - Cola (22oz)', 'Beverage', 15.00, 65.00, 120, 30, 'uploads/products/Soda_Cola.jpg', 1),
(5, 'Fountain Soda - Lemon Lime (22oz)', 'Beverage', 15.00, 65.00, 110, 30, 'uploads/products/Soda_Lemon.jpg', 1),
(6, 'Bottled Mineral Water (500ml)', 'Beverage', 10.00, 40.00, 95, 25, 'uploads/products/Water.jpg', 1),
(7, 'Nacho Platter with Warm Cheese', 'Snacks', 45.00, 150.00, 35, 10, 'uploads/products/Cheese_Nachos.jpg', 1),
(8, 'Hotdog on a Stick with Bun', 'Snacks', 30.00, 95.00, 28, 10, 'uploads/products/Hotdog.jpg', 1),
(9, 'Milk Chocolate Bar (50g)', 'Candy', 25.00, 75.00, 12, 15, 'uploads/products/White_Chocolate.jpg', 1),
(10, 'Gummy Bears Bag (100g)', 'Candy', 20.00, 60.00, 8, 15, 'uploads/products/Gummybear.jpg', 1);

INSERT INTO combos (combo_id, combo_name, description, combo_price, is_active) VALUES
(1, 'Solo Movie Treat', '1 Large Butter Popcorn + 1 22oz Soda', 160.00, 1),
(2, 'Duo Blockbuster Feast', '2 Large Butter Popcorn + 2 22oz Sodas + 1 Nachos', 420.00, 1),
(3, 'Sweet & Savory Pack', '1 Caramel Popcorn + 1 Mineral Water + 1 Chocolate Bar', 230.00, 1);

INSERT INTO combo_items (combo_id, product_id, quantity) VALUES
(1, 1, 1),
(1, 4, 1),
(2, 1, 2),
(2, 4, 2),
(2, 7, 1),
(3, 2, 1),
(3, 6, 1),
(3, 9, 1);

INSERT INTO orders (order_id, order_number, user_id, subtotal_amount, tax_amount, order_status, created_at) VALUES
(1, 'ORD-20261010-0001', 3, 714.29, 85.71, 'Completed', '2026-10-10 12:30:15');

INSERT INTO tickets (ticket_id, order_id, screening_id, seat_id, hall_id, price_charged, ticket_status) VALUES
(1, 1, 1, 20, 1, 320.00, 'Valid'),
(2, 1, 1, 21, 1, 320.00, 'Valid');

INSERT INTO order_items (order_item_id, order_id, product_id, combo_id, quantity, unit_price_charged) VALUES
(1, 1, NULL, 1, 1, 160.00);

INSERT INTO payments (payment_id, order_id, payment_method, amount_paid, change_returned, reference_number, payment_date) VALUES
(1, 1, 'Cash', 1000.00, 200.00, NULL, '2026-10-10 12:31:02');

INSERT INTO orders (order_id, order_number, user_id, subtotal_amount, tax_amount, order_status, created_at) VALUES
(2, 'ORD-20261010-0002', 4, 187.50, 22.50, 'Completed', '2026-10-10 12:45:20');

INSERT INTO order_items (order_item_id, order_id, product_id, combo_id, quantity, unit_price_charged) VALUES
(2, 2, 7, NULL, 1, 150.00),
(3, 2, 4, NULL, 1, 60.00);

INSERT INTO payments (payment_id, order_id, payment_method, amount_paid, change_returned, reference_number, payment_date) VALUES
(2, 2, 'E-Wallet', 210.00, 0.00, 'GCASH-REF-89472190', '2026-10-10 12:46:11');

INSERT INTO seat_holds (screening_id, seat_id, hall_id, session_token, hold_expires_at, created_at) VALUES
(2, 11, 1, 'sess_9f83a21b44c84d7890e0c1f516a', DATE_ADD(NOW(), INTERVAL 8 MINUTE), NOW());

INSERT INTO utility_expenses (expense_id, utility_type, billing_month, billing_year, bill_amount, payment_date, notes, recorded_by, created_at) VALUES
(1, 'Electricity', 8, 2026, 38500.00, '2026-09-05', 'Meralco Electric Draw - Dual laser projectors, audio amps & central HVAC', 1, '2026-09-05 10:14:00'),
(2, 'Water', 8, 2026, 4200.00, '2026-09-06', 'Manila Water - Restrooms sanitation & snack preparation sinks', 1, '2026-09-06 11:20:00'),
(3, 'Electricity', 9, 2026, 41200.00, '2026-10-04', 'Meralco Electric Draw - Full month theater operations and HVAC run', 2, '2026-10-04 09:30:00'),
(4, 'Water', 9, 2026, 4650.00, '2026-10-05', 'Manila Water - Concessions station wash down & facility maintenance', 2, '2026-10-05 14:05:00');

INSERT INTO audit_logs (log_id, user_id, action_type, target_table, record_id, details, ip_address, created_at) VALUES
(1, 1, 'EXPENSE_MODIFIED', 'utility_expenses', 3, 'Recorded initial monthly Meralco billing statement for September 2026', '127.0.0.1', '2026-10-04 09:30:00'),
(2, 2, 'INVENTORY_OVERRIDE', 'products', 9, 'Restocked Milk Chocolate Bars by 50 units following distributor shipment', '127.0.0.1', '2026-10-08 15:45:12');
