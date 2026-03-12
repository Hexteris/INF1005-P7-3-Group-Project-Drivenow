# DriveNow – Car Rental Web App
### INF1005 Web Systems & Technologies | SIT

---
# INF1005-P7-3-Group-Project-Drivenow
# DriveNow Car Rental System

**Module:** INF1005 - Web Systems and Technologies  
**Group:** P7-03

## Overview
Car rental web application built with LAMP stack.

## Tech Stack
- **Frontend:** HTML5, Bootstrap 5, Custom CSS/JS
- **Backend:** PHP, MySQL
- **Server:** Google Cloud Platform (GCP) Ubuntu VM

## Features
- User Registration/Login
- Admin Dashboard
- Car Management
- Booking System
- Reviews System

## Security Measures
- Password hashing (password_hash())
- Prepared statements (SQL injection prevention)
- Input sanitization
- Secure configuration files (/var/www/private/)

## Setup
1. Clone repository
2. Configure /var/www/private/db-config.ini
3. Deploy to server via SFTP


## Quick Start Deployment

### Step 1: Database Setup
SSH into your GCP VM and run:
```bash
mysql -u root -p < /var/www/html/setup.sql
```

### Step 2: Config File (CRITICAL)
```bash
sudo mkdir -p /var/www/private
sudo nano /var/www/private/db-config.ini
```
Paste the contents from `db-config.ini.template`, fill in your real password, then:
```bash
sudo chown www-data:www-data /var/www/private/db-config.ini
sudo chmod 640 /var/www/private/db-config.ini
```

### Step 3: Generate Real Admin Password Hash
```bash
php /var/www/html/admin/gen-hash.php
```
Copy the UPDATE query from the output and run it in MySQL.
Then **delete gen-hash.php from the server**:
```bash
rm /var/www/html/admin/gen-hash.php
```

### Step 4: Upload Files
Use VS Code SFTP extension to sync everything in this folder to `/var/www/html/`.
The `db-config.ini.template` can stay in html (it's just a template, not the real config).

### Step 5: Permissions
```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

---

## File Structure
```
/var/www/html/
├── index.php              ← Homepage
├── register.php           ← User registration
├── login.php              ← User login
├── logout.php             ← Logout
├── cars.php               ← Browse & filter cars
├── book.php               ← Book a car
├── my-bookings.php        ← User dashboard
├── review.php             ← Leave a review
├── setup.sql              ← Run once to set up DB
├── db-config.ini.template ← Template only (not real config)
├── includes/
│   ├── db-connect.php     ← DB connection (reads from /var/www/private/)
│   ├── auth.php           ← Session helpers
│   ├── header.php         ← Nav + HTML head
│   └── footer.php         ← Footer + scripts
├── css/style.css          ← All custom styles
├── js/main.js             ← Client-side validation + interactivity
└── admin/
    ├── login.php          ← Admin login
    ├── logout.php         ← Admin logout
    ├── index.php          ← Admin dashboard
    ├── manage-cars.php    ← Cars CRUD
    ├── manage-bookings.php← Bookings management
    ├── manage-users.php   ← Members management
    ├── manage-reviews.php ← Reviews management
    └── gen-hash.php       ← Password hash tool (DELETE after use)

/var/www/private/
└── db-config.ini          ← Real DB credentials (OUTSIDE web root)
```

---

## Default Credentials
| Role   | Username/Email       | Password    |
|--------|---------------------|-------------|
| Admin  | admin               | Admin@123   |
| Member | test@example.com    | (see setup.sql note — hash is placeholder, register a new account) |

> ⚠️ Change all passwords in production!

---

## Security Features
- Passwords hashed with `password_hash()` / `password_verify()`
- All DB queries use **Prepared Statements** (prevents SQL Injection)
- All output uses `htmlspecialchars()` (prevents XSS)
- DB credentials stored **outside web root**
- Session regeneration on login (prevents session fixation)
- Server-side validation on all forms
- Admin panel fully separated from member area

