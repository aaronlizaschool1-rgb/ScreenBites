SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM audit_logs;
DELETE FROM utility_expenses;
DELETE FROM payments;
DELETE FROM order_combos;
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
ALTER TABLE order_combos     AUTO_INCREMENT = 1;
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

INSERT INTO movies (movie_id, title, duration_minutes, rating, poster_path, trailer_url, genre, synopsis, is_active) VALUES
(1, 'Avatar: The Way of Water', 192, 'PG-13', 'uploads/posters/avatar_twow.jpg', 'd9MyW72ELq0', 'Sci-Fi / Adventure', 'Jake Sully lives with his newfound family formed on the extrasolar moon Pandora. Once a familiar threat returns to finish what was previously started, Jake must work with Neytiri and the army of the Na\'vi race to protect their home.', 1),
(2, 'Spider-Man: Across the Spider-Verse', 140, 'PG', 'uploads/posters/spiderman_atsv.jpg', 'cqGjhVJWtEg', 'Animation / Action', 'Miles Morales catapults across the Multiverse, where he encounters a team of Spider-People charged with protecting its very existence. When the heroes clash on how to handle a new threat, Miles must redefine what it means to be a hero.', 1),
(3, 'Oppenheimer', 180, 'R-16', 'uploads/posters/oppenheimer.jpg', 'uYPbbksJxIg', 'Biography / Drama', 'The story of American scientist J. Robert Oppenheimer and his role in the development of the atomic bomb during World War II, exploring the moral and scientific dilemmas of the Manhattan Project.', 1),
(4, 'The Super Mario Bros. Movie', 92, 'G', 'uploads/posters/mario_bros.jpg', 'TnGl01FkMMo', 'Family / Comedy', 'A Brooklyn plumber named Mario travels through the Mushroom Kingdom with a princess named Peach and an anthropomorphic mushroom named Toad to find Mario\'s brother, Luigi, and to save the world from Bowser.', 1),
(5, 'Spider-Man: Brand New Day', 135, 'PG-13', 'uploads/posters/spiderman_bnd.jpg', 'U4MFvAL02Vg', 'Action / Superhero', 'Peter Parker navigates a fresh start in New York City with no one remembering his identity, facing emerging street-level threats and new allies in a brand new era.', 1),
(6, 'Avengers: Endgame', 181, 'PG-13', 'uploads/posters/endgame.jpg', 'TcMBFSGVi1c', 'Action / Sci-Fi / Adventure', 'After the devastating events of Infinity War, the universe is in ruins. With the help of remaining allies, the Avengers assemble once more to reverse Thanos\' actions and restore balance.', 1),
(7, 'Spider-Man: No Way Home', 148, 'PG-13', 'uploads/posters/nowayhome.jpg', 'JfVOs4VSpmA', 'Action / Sci-Fi / Adventure', 'With Spider-Man\'s identity revealed, Peter asks Doctor Strange for help. When a spell goes wrong, dangerous foes from other worlds appear, forcing Peter to discover what being Spider-Man truly means.', 1),
(8, 'Spider-Man: Far From Home', 129, 'PG-13', 'uploads/posters/farfromhome.jpg', 'Nt9L1jCKGnE', 'Action / Sci-Fi / Comedy', 'Following the events of Avengers: Endgame, Spider-Man must step up to take on new threats in a world that has changed forever during a European school trip.', 1),
(9, 'Inside Out', 95, 'PG', 'uploads/posters/insideout.jpg', 'yRUAzGQ3nSY', 'Animation / Family / Comedy', 'After young Riley is uprooted from her Midwest life and moved to San Francisco, her emotions - Joy, Fear, Anger, Disgust and Sadness - conflict on how best to navigate a new city, house, and school.', 1),
(10, 'A Quiet Place', 90, 'PG-13', 'uploads/posters/quietplace.jpg', 'WR7cc5t7tv8', 'Horror / Sci-Fi / Drama', 'In a desolate world overrun by blind monsters with ultra-sensitive hearing, a family must navigate their lives in absolute silence to survive.', 1),
(11, 'The Odyssey', 155, 'PG-13', 'uploads/posters/odyssey.jpg', 'rdu1q0TxFG4', 'Epic / Adventure / Myth', 'Homer\'s legendary epic brought to the big screen: Odysseus embarks on a perilous ten-year journey home from the Trojan War, facing mythical beasts, wrathful gods, and treacherous seas.', 1),
(12, 'Hoppers', 100, 'PG', 'uploads/posters/hoppers.jpg', 'PJmX4vFEEQc', 'Animation / Adventure / Comedy', 'A young animal lover uses a scientific breakthrough to \'hop\' her consciousness into an adorable robotic beaver, uncovering high-stakes secrets in the animal kingdom.', 1),
(13, 'Obsession', 108, 'R-16', 'uploads/posters/obsession.jpg', 'TaaDkbG3I7g', 'Horror / Psychological Thriller', 'Bear, a hopeless romantic shop clerk, breaks a supernatural \'One Wish Willow\' artifact to win his crush Nikki\'s heart, only to unleash an obsessive, violent nightmare.', 1),
(14, 'Toy Story 5', 102, 'G', 'uploads/posters/toystory5.jpg', 'wmiIUN-7qhE', 'Animation / Family / Adventure', 'Woody, Buzz Lightyear, and their beloved toy family reunite to confront their biggest modern challenge yet: winning kids\' attention back from electronic screens and smart gadgets.', 1),
(15, 'Peaky Blinders', 130, 'R-16', 'uploads/posters/peakyblinders.jpg', 'oVzVdvGIC7U', 'Crime / Drama / Period', 'Tommy Shelby and the notorious Birmingham razor gang face their definitive confrontation on the eve of World War II, battling rival syndicates and shifting political powers.', 1),
(16, 'BUDDY', 98, 'R-16', 'uploads/posters/buddy.jpg', 'ZFy8ZgLd574', 'Horror / Comedy / Analog Horror', 'A bright orange 1990s television unicorn mascot named Buddy returns from the analog airwaves in this unsettling horror-comedy about vintage children\'s programming harboring a malevolent secret.', 1),
(17, 'The Backrooms', 105, 'PG-13', 'uploads/posters/backrooms.jpg', 'H4dGpz6cnHo', 'Horror / Mystery / Sci-Fi', 'A young videographer accidentally noclipped out of reality into an endless labyrinth of yellow-wallpapered, fluorescent-lit empty rooms where terrifying entities lurk.', 1),
(18, 'GOAT', 180, 'PG-13', 'uploads/posters/goat.jpg', 'iO2Br5WWCII', 'Action / Sci-Fi / Thriller', 'An elite field operative and special forces agent returns from retirement to confront an international syndicate in this high-octane spectacle.', 1),
(19, 'Colony', 112, 'R-13', 'uploads/posters/colony.jpg', 'kRfXdS2q08k', 'Sci-Fi / Survival / Action', 'In an ice-age subterranean biosphere, survivors of a global cataclysm must venture into the frozen wasteland when an enigmatic transmission signals a thriving sanctuary.', 1),
(20, 'Fast and Furious', 135, 'PG-13', 'uploads/posters/fastandfurious.jpg', '2TAOizOnNPo', 'Action / Crime / High-Octane', 'Dominic Toretto and Brian O\'Conner reunite behind the wheel to infiltrate a ruthless heroin cartel across the Los Angeles streets and Mexican desert.', 1),
(21, 'Jujutsu Kaisen the Movie', 105, 'PG-13', 'uploads/posters/jjk0.png', 'eGJWEVnvqO4', 'Anime / Supernatural / Action', 'Yuta Okkotsu is haunted by the cursed spirit of his childhood friend Rika. Mentored by Satoru Gojo at Tokyo Jujutsu High, he learns to wield his curse to protect humanity.', 1),
(22, 'Send Help', 104, 'R-16', 'uploads/posters/sendhelp.jpg', 'R4wiXj9NmEE', 'Horror / Survival / Dark Comedy', 'Sam Raimi directs this razor-sharp survival black comedy where corporate coworkers stranded on a remote tropical island after a plane crash engage in a twisted battle of wits and survival.', 1),
(23, '500 Days of Summer', 95, 'PG-13', 'uploads/posters/500daysofsummer.jpg', 'PsD0NpFSADM', 'Romance / Comedy / Indie Drama', 'An offbeat romantic comedy about a woman who doesn\'t believe in true love, and the hopeless romantic greeting card writer who falls head over heels for her over 500 days.', 1),
(24, 'Coco', 105, 'PG', 'uploads/posters/coco.jpg', 'Rvr68u6k5sI', 'Animation / Music / Fantasy', 'Aspiring young musician Miguel confronts his family\'s ancestral ban on music and enters the vibrant, colorful Land of the Dead to uncover his great-great-grandfather\'s legacy.', 1),
(25, 'Eternal Sunshine of the Spotless Mind', 108, 'R-13', 'uploads/posters/eternalsunshine.jpg', '07-QBnEkgXU', 'Romance / Sci-Fi / Drama', 'When their relationship turns sour, a heartbroken couple undergoes a radical medical procedure to have each other permanently erased from their memories.', 1),
(26, 'Atonement', 123, 'R-16', 'uploads/posters/atonement.jpg', '-J1VUwagte4', 'Romance / War / Drama', 'Thirteen-year-old fledgling writer Briony Tallis irrevocably changes the course of several lives when she accuses her older sister\'s lover of a crime he did not commit.', 1),
(27, 'The Green Mile', 189, 'R-16', 'uploads/posters/greenmile.jpg', 'Ki4haFrqSrw', 'Crime / Drama / Fantasy', 'A death row corrections officer during the Great Depression discovers that an imposing, gentle inmate convicted of a horrific crime possesses a miraculous healing power.', 1),
(28, 'Forrest Gump', 142, 'PG-13', 'uploads/posters/forrestgump.jpg', 'bLvqoHBptjg', 'Drama / Romance / Comedy', 'The presidencies of Kennedy and Johnson, the Vietnam War, and other historical events unfold through the perspective of an Alabama man with an IQ of 75.', 1),
(29, 'Room', 118, 'R-16', 'uploads/posters/room.jpg', 'E_Ci-pAL4eE', 'Drama / Thriller', 'Held captive for years in an enclosed shed, a young woman and her 5-year-old son gain their freedom, allowing the boy to experience the outside world for the first time.', 1),
(30, 'Titanic', 195, 'PG-13', 'uploads/posters/titanic.jpg', '2e-eXJ6HgkQ', 'Romance / Drama / Historical', 'A seventeen-year-old aristocrat falls in love with a kind but poor artist aboard the luxurious, ill-fated R.M.S. Titanic on its maiden voyage across the Atlantic in 1912.', 1);

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

INSERT INTO order_items (order_item_id, order_id, product_id, quantity, unit_price_charged) VALUES
(1, 1, 1, 1, 160.00);

INSERT INTO payments (payment_id, order_id, payment_method, amount_paid, change_returned, reference_number, payment_date) VALUES
(1, 1, 'Cash', 1000.00, 200.00, NULL, '2026-10-10 12:31:02');

INSERT INTO orders (order_id, order_number, user_id, subtotal_amount, tax_amount, order_status, created_at) VALUES
(2, 'ORD-20261010-0002', 4, 187.50, 22.50, 'Completed', '2026-10-10 12:45:20');

INSERT INTO order_items (order_item_id, order_id, product_id, quantity, unit_price_charged) VALUES
(2, 2, 7, 1, 150.00),
(3, 2, 4, 1, 60.00);

INSERT INTO payments (payment_id, order_id, payment_method, amount_paid, change_returned, reference_number, payment_date) VALUES
(2, 2, 'E-Wallet', 210.00, 0.00, 'GCASH-REF-89472190', '2026-10-10 12:46:11');

INSERT INTO seat_holds (screening_id, seat_id, hall_id, session_token, hold_expires_at, created_at) VALUES
(2, 11, 1, 'sess_9f83a21b44c84d7890e0c1f516a', DATE_ADD(NOW(), INTERVAL 8 MINUTE), NOW());

INSERT INTO utility_expenses (expense_id, utility_type, billing_month, billing_year, bill_amount, payment_date, notes, user_id, created_at) VALUES
(1, 'Electricity', 8, 2026, 38500.00, '2026-09-05', 'Meralco Electric Draw - Dual laser projectors, audio amps & central HVAC', 1, '2026-09-05 10:14:00'),
(2, 'Water', 8, 2026, 4200.00, '2026-09-06', 'Manila Water - Restrooms sanitation & snack preparation sinks', 1, '2026-09-06 11:20:00'),
(3, 'Electricity', 9, 2026, 41200.00, '2026-10-04', 'Meralco Electric Draw - Full month theater operations and HVAC run', 2, '2026-10-04 09:30:00'),
(4, 'Water', 9, 2026, 4650.00, '2026-10-05', 'Manila Water - Concessions station wash down & facility maintenance', 2, '2026-10-05 14:05:00');

INSERT INTO audit_logs (log_id, user_id, action_type, target_table, record_id, details, ip_address, created_at) VALUES
(1, 1, 'EXPENSE_MODIFIED', 'utility_expenses', 3, 'Recorded initial monthly Meralco billing statement for September 2026', '127.0.0.1', '2026-10-04 09:30:00'),
(2, 2, 'INVENTORY_OVERRIDE', 'products', 9, 'Restocked Milk Chocolate Bars by 50 units following distributor shipment', '127.0.0.1', '2026-10-08 15:45:12');
