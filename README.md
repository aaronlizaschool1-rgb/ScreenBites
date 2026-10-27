# ScreenBites - Cinema & Concessions POS System

A clean, procedural PHP & MySQL system designed for independent cinema reservations and snack counter sales. Patterned directly after the procedural college shop reference (`itim211-2b-shop`).

## System Architecture

The codebase has been refactored from complex OOP/PDO into clean, direct procedural PHP using MySQLi:
- **No Heavy Frameworks or OOP Overhead**: Direct procedural scripts (`mysqli_query`, `mysqli_fetch_assoc`).
- **Standard PHP Session Cart**: Centralized in `cart_update.php` and `view_cart.php` with direct session storage (`$_SESSION['cart']`).
- **Interactive Seat Matrix**: Visual cinema seat selection with checkboxes and real-time occupied/available status.
- **Transactional Checkout**: Order finalization inserting `orders`, `tickets`, `order_items`, `payments`, deducting snack inventory, and generating a printable receipt.
- **Direct CRUD Modules**: Patterned directly after `itim211-2b-shop/item/`:
  - `movies/`: `index.php`, `create.php`, `store.php`, `edit.php`, `update.php`, `delete.php`
  - `concessions/`: `index.php`, `create.php`, `store.php`, `edit.php`, `update.php`, `delete.php`
  - `schedules/`: `index.php`, `create.php`, `store.php`, `delete.php`
  - `utilities/`: `create.php`, `store.php`, `edit.php`, `update.php`, `delete.php`
  - `dashboard.php`: Admin operational dashboard & utility overhead auditing

## Directory Structure

```text
ScreenBites/
├── assets/
│   ├── css/                    # Master cinema stylesheet (style.css, seat-matrix.css)
│   ├── img/                    # Static brand logos
│   └── js/                     # Responsive drawer navigation (main.js)
├── audit/
│   └── index.php               # Security & compliance audit log viewer with filters
├── concessions/                # Snack inventory CRUD
├── database/                   # Database schema & initial seeds
├── includes/
│   ├── config.php              # MySQLi connection, session, audit_log(), auth helpers
│   ├── header.php              # Global sidebar navigation (Overview, Box Office, Concessions, Finance, System)
│   ├── footer.php              # Global footer & scripts
│   └── style.css               # Clean cinema dark theme styling
├── movies/                     # Movie catalog CRUD
├── reports/
│   ├── sales.php               # Monthly sales report (ticket rev, snacks rev, daily ledger)
│   ├── financial.php           # Net income report & formal P&L statement
│   └── occupancy.php           # Cinema occupancy analytics & peak showtimes
├── schedules/                  # Screening schedule CRUD
├── ticketing/
│   └── seat_matrix.php         # Checkbox-driven cinema seating grid
├── utilities/                  # Operational utility expenses CRUD & ledger
├── uploads/
│   ├── posters/                # Movie posters
│   └── products/               # Concession snack pictures
├── cart_update.php             # Session cart update handler
├── checkout.php                # Order finalization & printable receipt
├── dashboard.php               # Admin operational KPIs & utilities overview
├── index.php                   # Customer frontpage (Now Showing & Concessions)
├── login.php                   # Staff/Admin login
├── logout.php                  # Session logout
└── view_cart.php               # Shopping cart summary & checkout entry
```

## Default Accounts

| Username | Password | Role |
| :--- | :--- | :--- |
| `admin_john` | `Password123` | Administrator |
| `ticketing_maria` | `Password123` | Ticketing Staff |
| `cashier_carlos` | `Password123` | Cashier |

## Local Setup
1. Place the folder into your local web root: `C:\xampp\htdocs\project\ScreenBites`.
2. Start Apache and MySQL from XAMPP.
3. Import `database/schema.sql` and `database/seeds.sql` into MySQL database `screenbites_db`.
4. Access the system at: `http://localhost/project/ScreenBites/` (or port configured in Apache, e.g. `http://localhost:8080/project/ScreenBites/`).
