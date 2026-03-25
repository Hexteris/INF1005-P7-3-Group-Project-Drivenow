# DriveNow – Car Rental Web App
### INF1005 Web Systems & Technologies | SIT

---

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

## Email Verification (Registration)

When a new user registers, a verification email is automatically sent to their address.
The email contains a unique link (valid for 24 hours) that activates their account.

### Setup: SMTP credentials in `db-config.ini`

Add these lines to your existing `db-config.ini` file (the same one used for DB credentials):

```ini
; ── Email / SMTP ──────────────────────────────
smtp_host = smtp.gmail.com
smtp_user = your-email@gmail.com
smtp_pass = your-app-password
smtp_port = 587
```

> For Gmail, generate an **App Password** at https://myaccount.google.com/apppasswords
> (requires 2FA to be enabled on your Google account).

### Optional: PHPMailer (recommended for production)

For reliable SMTP delivery, install PHPMailer via Composer in the project root:

```bash
composer require phpmailer/phpmailer
```

If PHPMailer is not installed, the system falls back to PHP's built-in `mail()` function
(works on most shared hosting; SMTP above is ignored in this case).

### Database migration (existing installs)

If you already have the database set up, run the migration to add the new columns:

```bash
mysql -u root -p car_rental < migration_email_verification.sql
```

Fresh installs using `setup.sql` already include the new columns.

---

## Security Features
- Passwords hashed with `password_hash()` / `password_verify()`
- All DB queries use **Prepared Statements** (prevents SQL Injection)
- All output uses `htmlspecialchars()` (prevents XSS)
- DB credentials stored **outside web root**
- Session regeneration on login (prevents session fixation)
- Server-side validation on all forms
- Admin panel fully separated from member area
