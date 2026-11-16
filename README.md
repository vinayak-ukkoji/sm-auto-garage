# S M AUTO GARAGE – Garage Management System

Mobile-first garage / workshop management web app.
Built with core PHP 8, MySQL/MariaDB (PDO), Bootstrap 5, jQuery and Bootstrap Icons. All libraries are bundled locally, so it works offline on XAMPP.

## 1. Install on XAMPP / WAMP (local)

1. Copy the `garage` folder into `C:\xampp\htdocs\` (WAMP: `C:\wamp64\www\`).
2. Start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin`:
   - Create a database `garage_db` with collation `utf8mb4_unicode_ci`.
   - Select it → **Import** → choose `garage/database/database.sql` → **Go**.
4. Open `config.php` and check `DB_USER` / `DB_PASS` (XAMPP default: `root` with an empty password).
5. Visit **http://localhost/garage/**.

## 2. Install on shared hosting (cPanel)

1. Use **MySQL Databases** to create a database and a user, and give the user ALL privileges on the database.
2. Import `database/database.sql` with phpMyAdmin.
3. Upload the folder contents (File Manager or FTP) to `public_html/` or a sub-folder.
4. Edit `config.php`:
   - Set `DB_NAME`, `DB_USER` and `DB_PASS`.
   - Set `APP_DEBUG` to `false`.
   - Set `APP_KEY` to a long random text.
5. Make sure `uploads/` is writable (755).
6. Use **PHP 8.0+** (cPanel → *Select PHP Version*), with the `pdo_mysql` and `mbstring` extensions on.

If links break in a sub-folder, set `BASE_URL` in `config.php`, e.g. `define('BASE_URL', '/garage');`.

## 3. Demo logins (mobile / password)

| Role | Mobile | Password |
|---|---|---|
| Admin | 9999999999 | admin123 |
| Service Advisor | 9888888888 | advisor123 |
| Technician | 9777777777 | tech123 |

Change these after the first login: **More → Change Password**. Admins can manage staff in **More → Staff / Users**.

Then go to **Settings → Garage Profile** and enter your address, GSTIN and state, and upload your logo.

## 4. Main flow

1. **Service → Create Repair Order.**
   - Search for the registration number. If it is not found, create the customer and vehicle on the spot.
   - Enter the odometer and fuel level → **Next**.
   - Add complaints, services/labour and parts → **Create Repair Order**.
2. Move the order along: **Approve Estimate → Start Work → Mark Vehicle Ready → Create Invoice**. Creating the invoice makes the GST invoice and deducts the parts from stock.
3. **Add Payment.** Part payments are allowed, by Cash, UPI, Card, Bank or Cheque.
4. **Deliver Vehicle.** This sets the next service date and creates a service reminder. It also adds a **Send Feedback Link** button, which opens WhatsApp.
5. Follow-ups:
   - **More → Service Reminders / Insurance Due** to send WhatsApp or SMS reminders.
   - **More → Service Feedbacks** to see ratings.

## 5. Folder structure

```
garage/
├── config.php                 DB + app settings
├── index.php, login.php, logout.php
├── service.php, orders.php, jobcard.php, jobcard_new.php, jobcard_edit.php
├── invoice_new.php, invoice.php, invoices.php
├── parts.php, part.php, stock_in.php, purchase_orders.php, po_form.php, po.php
├── vendors.php, vendor.php
├── accounts.php, daybook.php
├── more.php, customers.php, customer.php, vehicle.php, vehicle_search.php, order_search.php
├── reminders.php, insurance_due.php, feedbacks.php, feedback.php (public)
├── reports.php, settings.php, users.php
├── print/        invoice.php, jobcard.php (estimate / job card / gate pass), po.php
├── api/          JSON endpoints for AJAX (search, jobcards, customers, parts, purchases, invoices, payments, expenses, reminders, vendors, settings, users)
├── includes/     bootstrap, db (PDO), helpers, csrf, auth (roles), lang (en/hi), business rules, views, header/footer
├── assets/       css/app.css, js/app.js, vendor/ (Bootstrap, icons, jQuery, font)
├── uploads/      logos + job card photos (PHP execution blocked)
└── database/     database.sql (schema + demo data)
```

## 6. Security

- Passwords are stored with `password_hash`. Logins are rate-limited (5 tries per 15 minutes).
- Every POST checks a CSRF token. All queries are PDO prepared statements, and all output is escaped.
- Role checks run on both the pages and the API:
  - **Admin:** full access.
  - **Service Advisor:** everything except settings, staff and profit.
  - **Technician:** only their assigned job cards, with no prices shown.
- Invoice and estimate links shared on WhatsApp are signed with `APP_KEY`, so customers can open them without logging in.
- `.htaccess` blocks access to `includes/`, `database/` and `config.php`, and stops PHP from running in `uploads/`.

## 7. Languages

English and Hindi are included. Users switch language from the header dropdown. To add or change a translation, edit `includes/lang/en.php` and `includes/lang/hi.php`.
