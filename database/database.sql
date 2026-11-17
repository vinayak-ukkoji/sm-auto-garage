-- =====================================================================
--  S M AUTO GARAGE – Car Repair and Service
--  Garage Management System : database.sql  (schema + demo seed data)
--  Target  : MySQL 8.x / MariaDB 10.4+ (XAMPP, WAMP, cPanel)
--  Import  : phpMyAdmin → select your database → Import → this file
--            (or)  mysql -u root -p garage_db < database.sql
--
--  Demo logins (mobile / password):
--     Admin            9999999999 / admin123
--     Service Advisor  9888888888 / advisor123
--     Technician       9777777777 / tech123
--     Technician       9666666666 / tech123
--  Change these passwords after first login.
-- =====================================================================

-- Local XAMPP/WAMP only: uncomment the next 2 lines to create the DB.
-- On cPanel create the database from "MySQL Databases" and import into it.
-- CREATE DATABASE IF NOT EXISTS garage_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE garage_db;

SET NAMES utf8mb4;
SET time_zone = '+05:30';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS vendor_payments, feedbacks, reminders, expenses, expense_categories, payments,
  invoice_items, invoices, purchase_order_items, purchase_orders, vendors,
  stock_movements, job_card_status_log, job_card_parts, job_card_labour,
  job_card_photos, job_card_inspections, job_card_complaints, job_cards,
  parts, vehicles, customers, checklist_items, labour_rates, tax_rates,
  login_attempts, users, settings;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  1. SETTINGS / USERS / MASTERS
-- =====================================================================

CREATE TABLE settings (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  garage_name            VARCHAR(150) NOT NULL,
  tagline                VARCHAR(150) NULL,
  address                TEXT NULL,
  city                   VARCHAR(60)  NULL,
  pincode                VARCHAR(10)  NULL,
  phone                  VARCHAR(20)  NULL,
  email                  VARCHAR(100) NULL,
  gstin                  VARCHAR(15)  NULL,
  gst_enabled            TINYINT(1)   NOT NULL DEFAULT 1,   -- 1 = charge GST on new bills
  state_code             CHAR(2)      NOT NULL DEFAULT '27',
  state_name             VARCHAR(50)  NOT NULL DEFAULT 'Maharashtra',
  logo                   VARCHAR(255) NULL,
  invoice_prefix         VARCHAR(10)  NOT NULL DEFAULT 'INV-',
  invoice_next           INT UNSIGNED NOT NULL DEFAULT 1,
  jobcard_prefix         VARCHAR(10)  NOT NULL DEFAULT 'JC-',
  jobcard_next           INT UNSIGNED NOT NULL DEFAULT 1,
  po_prefix              VARCHAR(10)  NOT NULL DEFAULT 'PO-',
  po_next                INT UNSIGNED NOT NULL DEFAULT 1,
  service_interval_days  INT UNSIGNED NOT NULL DEFAULT 180,
  service_interval_km    INT UNSIGNED NOT NULL DEFAULT 10000,
  default_language       CHAR(2)      NOT NULL DEFAULT 'en',
  invoice_terms          TEXT NULL,
  bank_details           TEXT NULL,
  upi_id                 VARCHAR(60)  NULL,
  wa_template_reminder   TEXT NULL,
  wa_template_invoice    TEXT NULL,
  wa_template_ready      TEXT NULL,
  wa_template_feedback   TEXT NULL,
  updated_at             DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  mobile         VARCHAR(15)  NOT NULL,
  email          VARCHAR(100) NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('admin','advisor','technician') NOT NULL DEFAULT 'advisor',
  language       CHAR(2)      NOT NULL DEFAULT 'en',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  last_login     DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_mobile (mobile),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mobile        VARCHAR(15)  NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,
  attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts (mobile, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tax_rates (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(50)  NOT NULL,
  rate       DECIMAL(5,2) NOT NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE labour_rates (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(150)  NOT NULL,
  sac        VARCHAR(10)   NOT NULL DEFAULT '998714',
  rate       DECIMAL(10,2) NOT NULL DEFAULT 0,
  gst_rate   DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_labour_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150) NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  2. CUSTOMERS & VEHICLES
-- =====================================================================

CREATE TABLE customers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  mobile      VARCHAR(15)  NOT NULL,
  alt_mobile  VARCHAR(15)  NULL,
  email       VARCHAR(100) NULL,
  address     TEXT         NULL,
  city        VARCHAR(60)  NULL,
  gstin       VARCHAR(15)  NULL,
  notes       TEXT         NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_mobile (mobile),
  KEY idx_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicles (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id        INT UNSIGNED NOT NULL,
  reg_no             VARCHAR(20)  NOT NULL,
  make               VARCHAR(50)  NOT NULL,
  model              VARCHAR(50)  NOT NULL,
  variant            VARCHAR(50)  NULL,
  fuel_type          ENUM('petrol','diesel','cng','ev','other') NOT NULL DEFAULT 'petrol',
  year               SMALLINT UNSIGNED NULL,
  color              VARCHAR(30)  NULL,
  chassis_no         VARCHAR(40)  NULL,
  engine_no          VARCHAR(40)  NULL,
  odometer           INT UNSIGNED NULL,
  insurance_company  VARCHAR(100) NULL,
  policy_no          VARCHAR(50)  NULL,
  insurance_expiry   DATE         NULL,
  puc_expiry         DATE         NULL,
  next_service_date  DATE         NULL,
  next_service_km    INT UNSIGNED NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vehicles_reg (reg_no),
  KEY idx_vehicles_customer (customer_id),
  KEY idx_vehicles_chassis (chassis_no),
  KEY idx_vehicles_insurance (insurance_expiry),
  KEY idx_vehicles_puc (puc_expiry),
  KEY idx_vehicles_service (next_service_date),
  CONSTRAINT fk_vehicles_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  3. PARTS / VENDORS / PURCHASE ORDERS
-- =====================================================================

CREATE TABLE parts (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  part_no         VARCHAR(50)   NULL,
  name            VARCHAR(150)  NOT NULL,
  brand           VARCHAR(60)   NULL,
  category        VARCHAR(60)   NULL,
  hsn             VARCHAR(10)   NULL,
  unit            VARCHAR(10)   NOT NULL DEFAULT 'pcs',
  purchase_price  DECIMAL(10,2) NOT NULL DEFAULT 0,
  mrp             DECIMAL(10,2) NOT NULL DEFAULT 0,
  selling_price   DECIMAL(10,2) NOT NULL DEFAULT 0,
  gst_rate        DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
  stock           DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_stock       DECIMAL(10,2) NOT NULL DEFAULT 0,
  rack_no         VARCHAR(30)   NULL,
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_parts_no (part_no),
  KEY idx_parts_name (name),
  KEY idx_parts_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendors (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(150) NOT NULL,
  contact_person  VARCHAR(100) NULL,
  mobile          VARCHAR(15)  NULL,
  email           VARCHAR(100) NULL,
  address         TEXT         NULL,
  gstin           VARCHAR(15)  NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vendors_name (name),
  KEY idx_vendors_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_orders (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_no          VARCHAR(20)   NOT NULL,
  vendor_id      INT UNSIGNED  NOT NULL,
  po_date        DATE          NOT NULL,
  expected_date  DATE          NULL,
  bill_no        VARCHAR(50)   NULL,
  status         ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
  subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_total      DECIMAL(12,2) NOT NULL DEFAULT 0,
  total          DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid           DECIMAL(12,2) NOT NULL DEFAULT 0,
  received_at    DATETIME      NULL,
  notes          TEXT          NULL,
  created_by     INT UNSIGNED  NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_no (po_no),
  KEY idx_po_vendor (vendor_id),
  KEY idx_po_status (status),
  KEY idx_po_date (po_date),
  CONSTRAINT fk_po_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
  CONSTRAINT fk_po_user   FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_id     INT UNSIGNED  NOT NULL,
  part_id   INT UNSIGNED  NOT NULL,
  qty       DECIMAL(10,2) NOT NULL DEFAULT 1,
  rate      DECIMAL(10,2) NOT NULL DEFAULT 0,
  gst_rate  DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
  amount    DECIMAL(12,2) NOT NULL DEFAULT 0,   -- taxable = qty*rate
  PRIMARY KEY (id),
  KEY idx_poi_po (po_id),
  KEY idx_poi_part (part_id),
  CONSTRAINT fk_poi_po   FOREIGN KEY (po_id)   REFERENCES purchase_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_poi_part FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_payments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_id         INT UNSIGNED  NOT NULL,
  vendor_id     INT UNSIGNED  NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  mode          ENUM('cash','upi','card','bank','cheque') NOT NULL DEFAULT 'cash',
  reference_no  VARCHAR(60)   NULL,
  payment_date  DATE          NOT NULL,
  note          VARCHAR(255)  NULL,
  user_id       INT UNSIGNED  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vp_po (po_id),
  KEY idx_vp_date (payment_date),
  CONSTRAINT fk_vp_po     FOREIGN KEY (po_id)     REFERENCES purchase_orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_vp_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id)         ON DELETE RESTRICT,
  CONSTRAINT fk_vp_user   FOREIGN KEY (user_id)   REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  4. JOB CARDS (SERVICE)
-- =====================================================================

CREATE TABLE job_cards (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jc_no              VARCHAR(20)   NOT NULL,
  customer_id        INT UNSIGNED  NOT NULL,
  vehicle_id         INT UNSIGNED  NOT NULL,
  advisor_id         INT UNSIGNED  NULL,
  technician_id      INT UNSIGNED  NULL,
  status             ENUM('estimate','approved','in_progress','ready','invoiced','delivered','cancelled')
                     NOT NULL DEFAULT 'estimate',
  odometer_in        INT UNSIGNED  NULL,
  fuel_level         TINYINT UNSIGNED NULL,          -- 0..100 %
  belongings         TEXT          NULL,
  damage_notes       TEXT          NULL,
  expected_delivery  DATETIME      NULL,
  delivered_at       DATETIME      NULL,
  labour_total       DECIMAL(12,2) NOT NULL DEFAULT 0,  -- taxable
  parts_total        DECIMAL(12,2) NOT NULL DEFAULT 0,  -- taxable
  tax_total          DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount           DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total        DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes              TEXT          NULL,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jc_no (jc_no),
  KEY idx_jc_status (status),
  KEY idx_jc_created (created_at),
  KEY idx_jc_customer (customer_id),
  KEY idx_jc_vehicle (vehicle_id),
  KEY idx_jc_technician (technician_id),
  CONSTRAINT fk_jc_customer   FOREIGN KEY (customer_id)   REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_jc_vehicle    FOREIGN KEY (vehicle_id)    REFERENCES vehicles(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_jc_advisor    FOREIGN KEY (advisor_id)    REFERENCES users(id)     ON DELETE SET NULL,
  CONSTRAINT fk_jc_technician FOREIGN KEY (technician_id) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_card_complaints (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_card_id  INT UNSIGNED NOT NULL,
  complaint    TEXT NOT NULL,
  resolution   TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_jcc_jc (job_card_id),
  CONSTRAINT fk_jcc_jc FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_card_inspections (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_card_id        INT UNSIGNED NOT NULL,
  checklist_item_id  INT UNSIGNED NOT NULL,
  status             ENUM('ok','attention','replace','na') NOT NULL DEFAULT 'ok',
  remarks            VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jci (job_card_id, checklist_item_id),
  KEY idx_jci_item (checklist_item_id),
  CONSTRAINT fk_jci_jc   FOREIGN KEY (job_card_id)       REFERENCES job_cards(id)       ON DELETE CASCADE,
  CONSTRAINT fk_jci_item FOREIGN KEY (checklist_item_id) REFERENCES checklist_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_card_photos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_card_id  INT UNSIGNED NOT NULL,
  file_path    VARCHAR(255) NOT NULL,
  caption      VARCHAR(150) NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jcp_jc (job_card_id),
  CONSTRAINT fk_jcp_jc FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_card_labour (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_card_id     INT UNSIGNED  NOT NULL,
  labour_rate_id  INT UNSIGNED  NULL,
  description     VARCHAR(200)  NOT NULL,
  qty             DECIMAL(8,2)  NOT NULL DEFAULT 1,
  rate            DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount        DECIMAL(10,2) NOT NULL DEFAULT 0,
  gst_rate        DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
  amount          DECIMAL(12,2) NOT NULL DEFAULT 0,   -- taxable = qty*rate - discount
  is_done         TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_jcl_jc (job_card_id),
  CONSTRAINT fk_jcl_jc   FOREIGN KEY (job_card_id)    REFERENCES job_cards(id)    ON DELETE CASCADE,
  CONSTRAINT fk_jcl_rate FOREIGN KEY (labour_rate_id) REFERENCES labour_rates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_card_parts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_card_id  INT UNSIGNED  NOT NULL,
  part_id      INT UNSIGNED  NOT NULL,
  description  VARCHAR(200)  NOT NULL,
  qty          DECIMAL(10,2) NOT NULL DEFAULT 1,
  rate         DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount     DECIMAL(10,2) NOT NULL DEFAULT 0,
  gst_rate     DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
  amount       DECIMAL(12,2) NOT NULL DEFAULT 0,      -- taxable = qty*rate - discount
  is_done      TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_jcpt_jc (job_card_id),
  KEY idx_jcpt_part (part_id),
  CONSTRAINT fk_jcpt_jc   FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
  CONSTRAINT fk_jcpt_part FOREIGN KEY (part_id)     REFERENCES parts(id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_card_status_log (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_card_id  INT UNSIGNED NOT NULL,
  status       VARCHAR(20)  NOT NULL,
  user_id      INT UNSIGNED NULL,
  note         VARCHAR(255) NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jcsl_jc (job_card_id, created_at),
  CONSTRAINT fk_jcsl_jc   FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
  CONSTRAINT fk_jcsl_user FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  5. STOCK LEDGER
-- =====================================================================

CREATE TABLE stock_movements (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  part_id     INT UNSIGNED  NOT NULL,
  type        ENUM('opening','in','out','adjust') NOT NULL,
  qty         DECIMAL(10,2) NOT NULL,     -- always positive for in/out/opening; signed for adjust
  rate        DECIMAL(10,2) NOT NULL DEFAULT 0,
  ref_type    ENUM('opening','purchase','po','invoice','counter','manual') NOT NULL DEFAULT 'manual',
  ref_id      INT UNSIGNED  NULL,
  note        VARCHAR(255)  NULL,
  user_id     INT UNSIGNED  NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sm_part_date (part_id, created_at),
  KEY idx_sm_ref (ref_type, ref_id),
  CONSTRAINT fk_sm_part FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  6. ACCOUNTS
-- =====================================================================

CREATE TABLE invoices (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_no       VARCHAR(20)   NOT NULL,
  type             ENUM('job','counter') NOT NULL DEFAULT 'job',
  job_card_id      INT UNSIGNED  NULL,
  customer_id      INT UNSIGNED  NULL,
  customer_name    VARCHAR(100)  NULL,   -- walk-in name for counter sales
  customer_mobile  VARCHAR(15)   NULL,
  invoice_date     DATE          NOT NULL,
  place_of_supply  CHAR(2)       NOT NULL DEFAULT '27',
  is_igst          TINYINT(1)    NOT NULL DEFAULT 0,
  gst_enabled      TINYINT(1)    NOT NULL DEFAULT 1,   -- snapshot of the GST setting when the invoice was made
  subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,   -- total taxable
  discount         DECIMAL(12,2) NOT NULL DEFAULT 0,
  cgst             DECIMAL(12,2) NOT NULL DEFAULT 0,
  sgst             DECIMAL(12,2) NOT NULL DEFAULT 0,
  igst             DECIMAL(12,2) NOT NULL DEFAULT 0,
  round_off        DECIMAL(6,2)  NOT NULL DEFAULT 0,
  total            DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid             DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance          DECIMAL(12,2) NOT NULL DEFAULT 0,
  status           ENUM('unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  notes            TEXT          NULL,
  created_by       INT UNSIGNED  NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoice_no (invoice_no),
  UNIQUE KEY uq_invoice_jc (job_card_id),
  KEY idx_inv_date (invoice_date),
  KEY idx_inv_status (status),
  KEY idx_inv_customer (customer_id),
  CONSTRAINT fk_inv_jc       FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_user     FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id   INT UNSIGNED  NOT NULL,
  item_type    ENUM('part','labour') NOT NULL,
  part_id      INT UNSIGNED  NULL,
  description  VARCHAR(200)  NOT NULL,
  hsn_sac      VARCHAR(10)   NULL,
  qty          DECIMAL(10,2) NOT NULL DEFAULT 1,
  rate         DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount     DECIMAL(10,2) NOT NULL DEFAULT 0,
  gst_rate     DECIMAL(5,2)  NOT NULL DEFAULT 18.00,
  taxable      DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax          DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount       DECIMAL(12,2) NOT NULL DEFAULT 0,   -- taxable + tax
  PRIMARY KEY (id),
  KEY idx_ii_invoice (invoice_id),
  KEY idx_ii_part (part_id),
  CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_ii_part    FOREIGN KEY (part_id)    REFERENCES parts(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id    INT UNSIGNED  NOT NULL,
  customer_id   INT UNSIGNED  NULL,
  amount        DECIMAL(12,2) NOT NULL,
  mode          ENUM('cash','upi','card','bank','cheque') NOT NULL DEFAULT 'cash',
  reference_no  VARCHAR(60)   NULL,
  payment_date  DATE          NOT NULL,
  note          VARCHAR(255)  NULL,
  user_id       INT UNSIGNED  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pay_invoice (invoice_id),
  KEY idx_pay_date (payment_date),
  KEY idx_pay_customer (customer_id),
  CONSTRAINT fk_pay_invoice  FOREIGN KEY (invoice_id)  REFERENCES invoices(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_pay_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pay_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_categories (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(60)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expcat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id   INT UNSIGNED  NOT NULL,
  expense_date  DATE          NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  mode          ENUM('cash','upi','card','bank','cheque') NOT NULL DEFAULT 'cash',
  paid_to       VARCHAR(100)  NULL,
  note          VARCHAR(255)  NULL,
  job_card_id   INT UNSIGNED  NULL,
  user_id       INT UNSIGNED  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exp_date (expense_date),
  KEY idx_exp_cat (category_id),
  KEY idx_exp_jc (job_card_id),
  CONSTRAINT fk_exp_jc   FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_cat  FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_exp_user FOREIGN KEY (user_id)     REFERENCES users(id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  7. REMINDERS & FEEDBACK
-- =====================================================================

CREATE TABLE reminders (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id    INT UNSIGNED NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  type          ENUM('service','insurance','puc') NOT NULL,
  due_date      DATE         NOT NULL,
  due_km        INT UNSIGNED NULL,
  status        ENUM('pending','sent','done','snoozed') NOT NULL DEFAULT 'pending',
  snooze_until  DATE         NULL,
  last_sent_at  DATETIME     NULL,
  job_card_id   INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reminder (vehicle_id, type, due_date),
  KEY idx_rem_lookup (type, status, due_date),
  KEY idx_rem_customer (customer_id),
  CONSTRAINT fk_rem_vehicle  FOREIGN KEY (vehicle_id)  REFERENCES vehicles(id)  ON DELETE CASCADE,
  CONSTRAINT fk_rem_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_rem_jc       FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedbacks (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_card_id   INT UNSIGNED NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  token         CHAR(32)     NOT NULL,
  rating        TINYINT UNSIGNED NULL,     -- 1..5, NULL = not yet submitted
  comment       TEXT         NULL,
  submitted_at  DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fb_jc (job_card_id),
  UNIQUE KEY uq_fb_token (token),
  KEY idx_fb_customer (customer_id),
  KEY idx_fb_rating (rating),
  CONSTRAINT fk_fb_jc       FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
  CONSTRAINT fk_fb_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  SEED DATA
--  Dates are relative to the import date so the demo always looks current.
-- =====================================================================

INSERT INTO settings (id, garage_name, tagline, address, city, pincode, phone, email, gstin,
  state_code, state_name, invoice_prefix, invoice_next, jobcard_prefix, jobcard_next,
  po_prefix, po_next, service_interval_days, service_interval_km, default_language,
  invoice_terms, upi_id,
  wa_template_reminder, wa_template_invoice, wa_template_ready, wa_template_feedback)
VALUES (1, 'S M AUTO GARAGE', 'CAR REPAIR AND SERVICE',
  'Shop No. 1, Main Road', 'Your City', '000000', '9999999999', NULL, NULL,
  '27', 'Maharashtra', 'INV-', 6, 'JC-', 11, 'PO-', 3, 180, 10000, 'en',
  '1. Goods once sold will not be taken back.\n2. Warranty on parts as per manufacturer.\n3. Subject to local jurisdiction.',
  NULL,
  'Dear {customer}, your vehicle {reg_no} is due for {type} on {due_date}. Please call {garage_phone} to book. - {garage_name}',
  'Dear {customer}, invoice {invoice_no} for {reg_no}: Rs. {total}. Balance due: Rs. {balance}. Thank you! - {garage_name}',
  'Dear {customer}, your vehicle {reg_no} is ready for delivery. Amount: Rs. {total}. - {garage_name}',
  'Dear {customer}, thank you for choosing {garage_name}. Please rate our service: {link}');

-- Passwords: admin123 / advisor123 / tech123 (bcrypt via password_hash)
INSERT INTO users (id, name, mobile, email, password_hash, role, language) VALUES
(1, 'Garage Admin',   '9999999999', 'admin@example.com', '$2y$10$9Nu029uhjYCwnFL5a1g/y.c189uVxJgACHhpAMDzCPkbb45oVBeOm', 'admin',      'en'),
(2, 'Sunil Advisor',  '9888888888', NULL,                '$2y$10$HHnksT72vjTJXHoNfupsuO/Lbswb1hlOA2JfjcmPSbzoLMMZYuEGS', 'advisor',    'en'),
(3, 'Ramesh Mechanic','9777777777', NULL,                '$2y$10$ng99xU9chDoaAOkFDLqlqOva5W.b4kqVSmUsqnzesnf3bvmtuyRHy', 'technician', 'hi'),
(4, 'Imran Mechanic', '9666666666', NULL,                '$2y$10$ng99xU9chDoaAOkFDLqlqOva5W.b4kqVSmUsqnzesnf3bvmtuyRHy', 'technician', 'en');

INSERT INTO tax_rates (name, rate) VALUES
('GST 0%', 0.00), ('GST 5%', 5.00), ('GST 12%', 12.00), ('GST 18%', 18.00), ('GST 28%', 28.00);

INSERT INTO labour_rates (id, name, rate, gst_rate) VALUES
(1,  'General Service',               1200.00, 18.00),
(2,  'Wheel Alignment',                600.00, 18.00),
(3,  'Wheel Balancing',                400.00, 18.00),
(4,  'AC Service',                    1500.00, 18.00),
(5,  'Brake Service',                  800.00, 18.00),
(6,  'Clutch Overhaul',               2500.00, 18.00),
(7,  'Battery Replacement',            200.00, 18.00),
(8,  'Denting & Painting (per panel)',2500.00, 18.00),
(9,  'Car Wash & Vacuum',              400.00, 18.00),
(10, 'Engine Scanning (OBD)',          500.00, 18.00);

INSERT INTO checklist_items (id, name, sort_order) VALUES
(1,  'Engine oil level',        1),
(2,  'Coolant level',           2),
(3,  'Brake fluid level',       3),
(4,  'Brake pads / shoes',      4),
(5,  'Battery condition',       5),
(6,  'Tyre pressure & tread',   6),
(7,  'Headlights & indicators', 7),
(8,  'Wiper blades',            8),
(9,  'AC cooling',              9),
(10, 'Suspension noise',       10),
(11, 'Clutch play',            11),
(12, 'Horn',                   12);

INSERT INTO expense_categories (id, name) VALUES
(1,'Rent'), (2,'Electricity'), (3,'Salary'), (4,'Tea & Snacks'),
(5,'Tools & Equipment'), (6,'Transport'), (7,'Miscellaneous');

-- ---------- Customers (5) ----------
INSERT INTO customers (id, name, mobile, alt_mobile, email, address, city, gstin, notes, created_at) VALUES
(1, 'Rahul Sharma',   '9820011001', NULL,         'rahul.s@example.com', 'Flat 12, Green Park Society', 'Pune',   NULL, 'Prefers weekend pickup',      NOW() - INTERVAL 120 DAY),
(2, 'Priya Deshmukh', '9820011002', '9820011012', NULL,                  'Plot 7, Shivaji Nagar',       'Pune',   NULL, NULL,                          NOW() - INTERVAL 95 DAY),
(3, 'Amit Patel',     '9820011003', NULL,         'amit.p@example.com',  '22 MG Road',                  'Mumbai', NULL, 'Fleet owner – 2 cars',        NOW() - INTERVAL 80 DAY),
(4, 'Sneha Kulkarni', '9820011004', NULL,         NULL,                  'B-4, Lake View Apartments',   'Pune',   NULL, NULL,                          NOW() - INTERVAL 30 DAY),
(5, 'Vikram Singh',   '9820011005', NULL,         NULL,                  'House 45, Sector 9',          'Nashik', NULL, NULL,                          NOW() - INTERVAL 10 DAY);

-- ---------- Vehicles (8) ----------
INSERT INTO vehicles (id, customer_id, reg_no, make, model, variant, fuel_type, year, color,
  chassis_no, engine_no, odometer, insurance_company, policy_no, insurance_expiry, puc_expiry,
  next_service_date, next_service_km, created_at) VALUES
(1, 1, 'MH12AB1234', 'Maruti Suzuki', 'Swift',         'VXI',    'petrol', 2019, 'Red',
  'MA3EWDE1S00123401', 'K12MN1234501', 48200, 'ICICI Lombard', 'POL-1001', CURDATE() + INTERVAL 18 DAY, CURDATE() + INTERVAL 60 DAY,
  CURDATE() + INTERVAL 140 DAY, 58200, NOW() - INTERVAL 120 DAY),
(2, 1, 'MH12CD5678', 'Hyundai',       'Creta',         'SX',     'diesel', 2021, 'White',
  'MALPC81CLMM123402', 'D4FBM123402',  36500, 'HDFC Ergo',     'POL-1002', CURDATE() + INTERVAL 200 DAY, CURDATE() - INTERVAL 5 DAY,
  NULL, NULL, NOW() - INTERVAL 118 DAY),
(3, 2, 'MH14EF9012', 'Honda',         'City',          'V CVT',  'petrol', 2020, 'Silver',
  'MAKGM65EXLN123403', 'L15ZF123403',  41000, 'Bajaj Allianz', 'POL-1003', CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 25 DAY,
  CURDATE() + INTERVAL 5 DAY, 51000, NOW() - INTERVAL 95 DAY),
(4, 2, 'MH14GH3456', 'Tata',          'Nexon',         'XZ+',    'petrol', 2022, 'Blue',
  'MAT627110NKP23404', 'RV1200123404', 22800, 'Tata AIG',      'POL-1004', CURDATE() + INTERVAL 150 DAY, CURDATE() + INTERVAL 90 DAY,
  NULL, NULL, NOW() - INTERVAL 94 DAY),
(5, 3, 'MH01JK7890', 'Mahindra',      'XUV700',        'AX7',    'diesel', 2023, 'Black',
  'MA1NF2XU1P6123405', 'MHAWK123405',  18900, 'New India',     'POL-1005', CURDATE() + INTERVAL 9 DAY, CURDATE() + INTERVAL 12 DAY,
  CURDATE() - INTERVAL 3 DAY, 28900, NOW() - INTERVAL 80 DAY),
(6, 3, 'MH01LM2345', 'Toyota',        'Innova Crysta', 'GX',     'diesel', 2018, 'Grey',
  'MBJGA3GS10012340', '2GD1234056',  112400, 'ICICI Lombard', 'POL-1006', CURDATE() + INTERVAL 28 DAY, CURDATE() - INTERVAL 15 DAY,
  NULL, NULL, NOW() - INTERVAL 79 DAY),
(7, 4, 'MH12NP6789', 'Maruti Suzuki', 'Baleno',        'Zeta',   'petrol', 2020, 'Blue',
  'MA3NYFB1S00123407', 'K12NN123407',  39700, 'Reliance General','POL-1007', CURDATE() + INTERVAL 75 DAY, CURDATE() + INTERVAL 40 DAY,
  NULL, NULL, NOW() - INTERVAL 30 DAY),
(8, 5, 'MH15QR0123', 'Kia',           'Seltos',        'HTX',    'petrol', 2022, 'White',
  'MZBEN813LNN123408', 'G4FL123408',   27300, 'HDFC Ergo',     'POL-1008', CURDATE() + INTERVAL 300 DAY, CURDATE() + INTERVAL 7 DAY,
  NULL, NULL, NOW() - INTERVAL 10 DAY);

-- ---------- Parts (20) ---------- (stock is recalculated from the ledger below)
INSERT INTO parts (id, part_no, name, brand, category, hsn, unit, purchase_price, mrp, selling_price, gst_rate, stock, min_stock, rack_no) VALUES
(1,  'EO-C530',  'Castrol 5W-30 FS Engine Oil (4L)', 'Castrol', 'Lubricants',  '27101980', 'can', 1800.00, 2250.00, 2250.00, 18.00, 0, 4, 'A1'),
(2,  'EO-S530',  'Shell 5W-30 Engine Oil (4L)',      'Shell',   'Lubricants',  '27101980', 'can', 1950.00, 2500.00, 2500.00, 18.00, 0, 4, 'A1'),
(3,  'FL-OIL01', 'Oil Filter',                       'Bosch',   'Filters',     '84212300', 'pcs',   80.00,  150.00,  150.00, 18.00, 0, 6, 'B1'),
(4,  'FL-AIR01', 'Air Filter',                       'Bosch',   'Filters',     '84213100', 'pcs',  180.00,  350.00,  350.00, 18.00, 0, 4, 'B1'),
(5,  'FL-CAB01', 'Cabin AC Filter',                  'Bosch',   'Filters',     '84213100', 'pcs',  220.00,  450.00,  450.00, 18.00, 0, 3, 'B2'),
(6,  'FL-FUE01', 'Fuel Filter',                      'Bosch',   'Filters',     '84212300', 'pcs',  250.00,  480.00,  480.00, 18.00, 0, 2, 'B2'),
(7,  'SP-OEM01', 'OEM Spark Plug',                   'NGK',     'Ignition',    '85111000', 'pcs',   95.00,  150.00,  150.00, 18.00, 0, 8, 'C1'),
(8,  'BR-PAD01', 'Brake Pad Set (Front)',            'Brembo',  'Brakes',      '87083000', 'set', 1100.00, 1800.00, 1800.00, 18.00, 0, 2, 'C2'),
(9,  'BR-SHO01', 'Brake Shoe Set (Rear)',            'Brembo',  'Brakes',      '87083000', 'set',  750.00, 1250.00, 1250.00, 18.00, 0, 2, 'C2'),
(10, 'CL-COO01', 'Coolant (1L)',                     'Prestone','Lubricants',  '38200000', 'ltr',  180.00,  275.00,  275.00, 18.00, 0, 5, 'A2'),
(11, 'BR-FLU01', 'Brake Fluid DOT4 (500ml)',         'Bosch',   'Brakes',      '38190010', 'btl',  160.00,  260.00,  260.00, 18.00, 0, 3, 'A2'),
(12, 'WP-BLD01', 'Wiper Blade',                      'Bosch',   'Accessories', '85129000', 'pcs',  120.00,  200.00,  200.00, 18.00, 0, 4, 'D1'),
(13, 'BT-35AH',  'Battery 35Ah',                     'Exide',   'Electrical',  '85071000', 'pcs', 3600.00, 4800.00, 4800.00, 28.00, 0, 1, 'E1'),
(14, 'CL-PLT01', 'Clutch Plate',                     'Valeo',   'Transmission','87089300', 'pcs', 1800.00, 2650.00, 2650.00, 18.00, 0, 2, 'F1'),
(15, 'BL-H4',    'Headlight Bulb H4',                'Philips', 'Electrical',  '85392120', 'pcs',  140.00,  250.00,  250.00, 18.00, 0, 4, 'E2'),
(16, 'EL-FUSE',  'Fuses and Relays',                 'Minda',   'Electrical',  '85362010', 'pcs',  120.00,  200.00,  200.00, 18.00, 0, 5, 'E2'),
(17, 'AC-R134',  'A/C Refrigerant R134a',            'Chemours','AC',          '29033920', 'can',  600.00,  800.00,  800.00, 18.00, 0, 2, 'G1'),
(18, 'CH-WD40',  'WD 40',                            'WD-40',   'Consumables', '34031900', 'can',  170.00,  250.00,  250.00, 18.00, 0, 3, 'G2'),
(19, 'WH-BOLT',  'Stepney / Wheel Bolt',             'OEM',     'Hardware',    '73181500', 'pcs',  120.00,  200.00,  200.00, 18.00, 0, 4, 'H1'),
(20, 'BT-FAN01', 'Fan Belt',                         'Gates',   'Engine',      '40103999', 'pcs',  280.00,  450.00,  450.00, 18.00, 0, 2, 'F2');

-- ---------- Vendors ----------
INSERT INTO vendors (id, name, contact_person, mobile, email, address, gstin, created_at) VALUES
(1, 'Shree Auto Spares',      'Mahesh Jain',  '9822033001', 'shreeauto@example.com', 'Spare Parts Market, Pune', NULL, NOW() - INTERVAL 150 DAY),
(2, 'Ganesh Lubricants',      'Ganesh Pawar', '9822033002', NULL,                    'Industrial Area, Pune',    NULL, NOW() - INTERVAL 140 DAY),
(3, 'National Battery House', 'Farhan Shaikh','9822033003', NULL,                    'Station Road, Pune',       NULL, NOW() - INTERVAL 100 DAY);

-- ---------- Opening stock ----------
INSERT INTO stock_movements (part_id, type, qty, rate, ref_type, note, user_id, created_at) VALUES
(1,'opening',10,1800,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(2,'opening', 8,1950,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(3,'opening',10,  80,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(4,'opening', 6, 180,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(5,'opening', 4, 220,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(6,'opening', 3, 250,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(7,'opening',12,  95,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(8,'opening', 4,1100,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(9,'opening', 3, 750,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(10,'opening',8, 180,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(11,'opening',5, 160,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(12,'opening',6, 120,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(13,'opening',2,3600,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(14,'opening',1,1800,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(15,'opening',0, 140,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(16,'opening',10,120,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(17,'opening',3, 600,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(18,'opening',3, 170,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(19,'opening',8, 120,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY),
(20,'opening',4, 280,'opening','Opening stock',1,NOW() - INTERVAL 60 DAY);

-- ---------- Purchase orders ----------
INSERT INTO purchase_orders (id, po_no, vendor_id, po_date, expected_date, bill_no, status, received_at, notes, created_by, created_at) VALUES
(1, 'PO-0001', 2, CURDATE() - INTERVAL 20 DAY, CURDATE() - INTERVAL 18 DAY, 'GL/2231', 'received', NOW() - INTERVAL 18 DAY, 'Monthly oil & filter stock', 1, NOW() - INTERVAL 20 DAY),
(2, 'PO-0002', 1, CURDATE() - INTERVAL 1 DAY,  CURDATE() + INTERVAL 2 DAY,  NULL,      'ordered',  NULL,                     'Clutch plates and bulbs low', 2, NOW() - INTERVAL 1 DAY);

INSERT INTO purchase_order_items (po_id, part_id, qty, rate, gst_rate) VALUES
(1, 1, 6, 1800, 18), (1, 3, 10, 80, 18), (1, 7, 8, 95, 18),
(2, 14, 3, 1800, 18), (2, 15, 6, 140, 18);

UPDATE purchase_order_items SET amount = ROUND(qty * rate, 2);
UPDATE purchase_orders po
  JOIN (SELECT po_id, SUM(amount) st, SUM(ROUND(amount * gst_rate / 100, 2)) tx
        FROM purchase_order_items GROUP BY po_id) x ON x.po_id = po.id
   SET po.subtotal = x.st, po.tax_total = x.tx, po.total = ROUND(x.st + x.tx, 0);
INSERT INTO vendor_payments (po_id, vendor_id, amount, mode, reference_no, payment_date, note, user_id, created_at)
SELECT id, vendor_id, total, 'bank', 'NEFT-99812', po_date + INTERVAL 2 DAY, 'Paid in full', 1, NOW() - INTERVAL 18 DAY FROM purchase_orders WHERE id = 1;
UPDATE purchase_orders SET paid = total WHERE id = 1;

-- Stock-in for received PO
INSERT INTO stock_movements (part_id, type, qty, rate, ref_type, ref_id, note, user_id, created_at)
SELECT poi.part_id, 'in', poi.qty, poi.rate, 'po', poi.po_id, 'PO-0001 received', 1, NOW() - INTERVAL 18 DAY
  FROM purchase_order_items poi WHERE poi.po_id = 1;

-- ---------- Job cards (10) ----------
INSERT INTO job_cards (id, jc_no, customer_id, vehicle_id, advisor_id, technician_id, status, odometer_in, fuel_level,
  belongings, damage_notes, expected_delivery, delivered_at, notes, created_at) VALUES
(1,  'JC-0001', 1, 1, 2, 3, 'delivered',   47950,  50, 'Stepney, jack, tool kit', 'Minor scratch on rear bumper', NOW() - INTERVAL 39 DAY, NOW() - INTERVAL 39 DAY, NULL,                          NOW() - INTERVAL 40 DAY),
(2,  'JC-0002', 2, 3, 2, 4, 'delivered',   40800,  25, 'Stepney, umbrella',       NULL,                           NOW() - INTERVAL 24 DAY, NOW() - INTERVAL 24 DAY, NULL,                          NOW() - INTERVAL 25 DAY),
(3,  'JC-0003', 3, 5, 2, 3, 'delivered',   18750,  75, 'Stepney, dash cam',       'Dent on left front door',      NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 11 DAY, 'Customer will pay balance later', NOW() - INTERVAL 12 DAY),
(4,  'JC-0004', 4, 7, 2, 4, 'invoiced',    39650,  50, 'Stepney',                 NULL,                           NOW() - INTERVAL 2 DAY,  NULL,                    NULL,                          NOW() - INTERVAL 3 DAY),
(5,  'JC-0005', 1, 2, 2, 3, 'ready',       36500,  50, 'Stepney, tool kit',       NULL,                           NOW(),                   NULL,                    NULL,                          NOW() - INTERVAL 1 DAY),
(6,  'JC-0006', 2, 4, 2, 4, 'in_progress', 22800,  75, 'Phone holder',            NULL,                           NOW() + INTERVAL 1 DAY,  NULL,                    NULL,                          NOW() - INTERVAL 1 DAY),
(7,  'JC-0007', 3, 6, 2, 3, 'in_progress',112400,  25, 'Stepney, first-aid kit',  'Rear bumper cracked',          NOW() + INTERVAL 1 DAY,  NULL,                    NULL,                          NOW() - INTERVAL 4 HOUR),
(8,  'JC-0008', 5, 8, 2, 4, 'approved',    27300, 100, 'Stepney',                 NULL,                           NOW() + INTERVAL 1 DAY,  NULL,                    NULL,                          NOW() - INTERVAL 3 HOUR),
(9,  'JC-0009', 2, 3, 2, NULL, 'estimate', 41900,  50, NULL,                      'Scratches on both right doors', NOW() + INTERVAL 3 DAY,  NULL,                    'Insurance claim possible',    NOW() - INTERVAL 2 HOUR),
(10, 'JC-0010', 1, 1, 2, NULL, 'estimate', 48200,  50, NULL,                      NULL,                           NOW() + INTERVAL 1 DAY,  NULL,                    NULL,                          NOW() - INTERVAL 1 HOUR);

INSERT INTO job_card_complaints (job_card_id, complaint, resolution) VALUES
(1,  'Periodic service due',                   'General service done'),
(1,  'Car pulling to the left',                'Wheel alignment done'),
(2,  'AC not cooling',                         'AC gas refilled, cabin filter replaced'),
(3,  'Squeaking noise while braking',          'Front brake pads replaced'),
(4,  'Periodic service + engine misfire',      'Spark plugs replaced, service done'),
(4,  'Steering vibration at high speed',       'Wheel balancing done'),
(5,  'Clutch slipping, hard gear shift',       'Clutch plate replaced'),
(6,  'Periodic service due',                   NULL),
(6,  'Coolant warning light',                  NULL),
(7,  'Car not starting in morning',            NULL),
(8,  'Squealing noise from engine bay',        NULL),
(8,  'Steering not centred',                   NULL),
(9,  'Denting & painting – right side doors',  NULL),
(10, 'Periodic service, wipers not cleaning',  NULL);

INSERT INTO job_card_inspections (job_card_id, checklist_item_id, status)
SELECT jc.id, ci.id, 'ok' FROM job_cards jc CROSS JOIN checklist_items ci WHERE jc.id <= 8;
UPDATE job_card_inspections SET status = 'attention', remarks = 'Low – topped up'  WHERE job_card_id = 6 AND checklist_item_id = 2;
UPDATE job_card_inspections SET status = 'replace',   remarks = 'Pads worn out'    WHERE job_card_id = 3 AND checklist_item_id = 4;
UPDATE job_card_inspections SET status = 'replace',   remarks = 'Battery dead'     WHERE job_card_id = 7 AND checklist_item_id = 5;
UPDATE job_card_inspections SET status = 'attention', remarks = 'Weak cooling'     WHERE job_card_id = 2 AND checklist_item_id = 9;
UPDATE job_card_inspections SET status = 'attention', remarks = 'Excess play'      WHERE job_card_id = 5 AND checklist_item_id = 11;

INSERT INTO job_card_labour (job_card_id, labour_rate_id, description, qty, rate, discount, gst_rate) VALUES
(1, 1, 'General Service', 1, 1200, 100, 18),
(1, 2, 'Wheel Alignment', 1,  600,   0, 18),
(2, 4, 'AC Service',      1, 1500,   0, 18),
(3, 5, 'Brake Service',   1,  800,   0, 18),
(4, 1, 'General Service', 1, 1200,   0, 18),
(4, 3, 'Wheel Balancing', 1,  400,   0, 18),
(5, 6, 'Clutch Overhaul', 1, 2500,   0, 18),
(6, 1, 'General Service', 1, 1200,   0, 18),
(7, 7, 'Battery Replacement', 1, 200, 0, 18),
(8, 2, 'Wheel Alignment', 1,  600,   0, 18),
(8, 3, 'Wheel Balancing', 1,  400,   0, 18),
(9, 8, 'Denting & Painting (per panel)', 2, 2500, 0, 18),
(10,1, 'General Service', 1, 1200,   0, 18);
UPDATE job_card_labour SET amount = ROUND(qty * rate - discount, 2);

INSERT INTO job_card_parts (job_card_id, part_id, description, qty, rate, discount, gst_rate) VALUES
(1, 1,  'Castrol 5W-30 FS Engine Oil (4L)', 1, 2250, 0, 18),
(1, 3,  'Oil Filter',                       1,  150, 0, 18),
(1, 4,  'Air Filter',                       1,  350, 0, 18),
(2, 5,  'Cabin AC Filter',                  1,  450, 0, 18),
(2, 17, 'A/C Refrigerant R134a',            1,  800, 0, 18),
(3, 8,  'Brake Pad Set (Front)',            1, 1800, 0, 18),
(3, 11, 'Brake Fluid DOT4 (500ml)',         1,  260, 0, 18),
(4, 2,  'Shell 5W-30 Engine Oil (4L)',      1, 2500, 0, 18),
(4, 3,  'Oil Filter',                       1,  150, 0, 18),
(4, 7,  'OEM Spark Plug',                   4,  150, 0, 18),
(5, 14, 'Clutch Plate',                     1, 2650, 0, 18),
(6, 1,  'Castrol 5W-30 FS Engine Oil (4L)', 1, 2250, 0, 18),
(6, 3,  'Oil Filter',                       1,  150, 0, 18),
(6, 10, 'Coolant (1L)',                     2,  275, 0, 18),
(7, 13, 'Battery 35Ah',                     1, 4800, 0, 28),
(8, 20, 'Fan Belt',                         1,  450, 0, 18),
(10,12, 'Wiper Blade',                      2,  200, 0, 18);
UPDATE job_card_parts SET amount = ROUND(qty * rate - discount, 2);
UPDATE job_card_labour SET is_done = 1 WHERE job_card_id <= 5;
UPDATE job_card_parts  SET is_done = 1 WHERE job_card_id <= 5;

-- Job card totals
UPDATE job_cards jc
  LEFT JOIN (SELECT job_card_id, SUM(amount) amt, SUM(ROUND(amount * gst_rate / 100, 2)) tx
               FROM job_card_labour GROUP BY job_card_id) l ON l.job_card_id = jc.id
  LEFT JOIN (SELECT job_card_id, SUM(amount) amt, SUM(ROUND(amount * gst_rate / 100, 2)) tx
               FROM job_card_parts GROUP BY job_card_id) p ON p.job_card_id = jc.id
   SET jc.labour_total = COALESCE(l.amt, 0),
       jc.parts_total  = COALESCE(p.amt, 0),
       jc.tax_total    = COALESCE(l.tx, 0) + COALESCE(p.tx, 0),
       jc.grand_total  = ROUND(COALESCE(l.amt,0) + COALESCE(p.amt,0) + COALESCE(l.tx,0) + COALESCE(p.tx,0) - jc.discount, 0);

-- Status history
INSERT INTO job_card_status_log (job_card_id, status, user_id, created_at)
SELECT id, 'estimate', 2, created_at FROM job_cards;
INSERT INTO job_card_status_log (job_card_id, status, user_id, created_at)
SELECT id, status, 2, created_at + INTERVAL 1 HOUR FROM job_cards WHERE status <> 'estimate';

-- ---------- Invoices (4 job + 1 counter sale) ----------
INSERT INTO invoices (id, invoice_no, type, job_card_id, customer_id, customer_name, customer_mobile, invoice_date, place_of_supply, created_by, created_at) VALUES
(1, 'INV-0001', 'job',     1,    1,    NULL,          NULL,         CURDATE() - INTERVAL 39 DAY, '27', 2, NOW() - INTERVAL 39 DAY),
(2, 'INV-0002', 'job',     2,    2,    NULL,          NULL,         CURDATE() - INTERVAL 24 DAY, '27', 2, NOW() - INTERVAL 24 DAY),
(3, 'INV-0003', 'job',     3,    3,    NULL,          NULL,         CURDATE() - INTERVAL 11 DAY, '27', 2, NOW() - INTERVAL 11 DAY),
(4, 'INV-0004', 'job',     4,    4,    NULL,          NULL,         CURDATE() - INTERVAL 2 DAY,  '27', 2, NOW() - INTERVAL 2 DAY),
(5, 'INV-0005', 'counter', NULL, NULL, 'Walk-in Customer', '9820099999', CURDATE() - INTERVAL 6 DAY, '27', 2, NOW() - INTERVAL 6 DAY);

-- Copy job card lines into invoice lines
INSERT INTO invoice_items (invoice_id, item_type, part_id, description, hsn_sac, qty, rate, discount, gst_rate, taxable)
SELECT i.id, 'labour', NULL, l.description, '998714', l.qty, l.rate, l.discount, l.gst_rate, l.amount
  FROM invoices i JOIN job_card_labour l ON l.job_card_id = i.job_card_id;
INSERT INTO invoice_items (invoice_id, item_type, part_id, description, hsn_sac, qty, rate, discount, gst_rate, taxable)
SELECT i.id, 'part', p.part_id, p.description, pt.hsn, p.qty, p.rate, p.discount, p.gst_rate, p.amount
  FROM invoices i JOIN job_card_parts p ON p.job_card_id = i.job_card_id JOIN parts pt ON pt.id = p.part_id;
-- Counter sale lines
INSERT INTO invoice_items (invoice_id, item_type, part_id, description, hsn_sac, qty, rate, discount, gst_rate, taxable) VALUES
(5, 'part', 18, 'WD 40',       '34031900', 1, 250, 0, 18, 250),
(5, 'part', 12, 'Wiper Blade', '85129000', 2, 200, 0, 18, 400);

UPDATE invoice_items SET tax = ROUND(taxable * gst_rate / 100, 2), amount = taxable + ROUND(taxable * gst_rate / 100, 2);

UPDATE invoices i
  JOIN (SELECT invoice_id, SUM(taxable) st, SUM(tax) tx FROM invoice_items GROUP BY invoice_id) x ON x.invoice_id = i.id
   SET i.subtotal  = x.st,
       i.cgst      = ROUND(x.tx / 2, 2),
       i.sgst      = x.tx - ROUND(x.tx / 2, 2),
       i.igst      = 0,
       i.total     = ROUND(x.st + x.tx, 0),
       i.round_off = ROUND(x.st + x.tx, 0) - (x.st + x.tx);

-- Stock out for invoiced parts
INSERT INTO stock_movements (part_id, type, qty, rate, ref_type, ref_id, note, user_id, created_at)
SELECT ii.part_id, 'out', ii.qty, ii.rate, IF(i.type = 'counter', 'counter', 'invoice'), i.id,
       CONCAT('Sold on ', i.invoice_no), 2, i.created_at
  FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
 WHERE ii.item_type = 'part';

-- ---------- Payments ----------
INSERT INTO payments (invoice_id, customer_id, amount, mode, reference_no, payment_date, note, user_id, created_at)
SELECT id, customer_id, total, 'cash', NULL, invoice_date, 'Full payment', 2, created_at FROM invoices WHERE id = 1;
INSERT INTO payments (invoice_id, customer_id, amount, mode, reference_no, payment_date, note, user_id, created_at)
SELECT id, customer_id, total, 'upi', 'UPI-58201934', invoice_date, 'Full payment', 2, created_at FROM invoices WHERE id = 2;
INSERT INTO payments (invoice_id, customer_id, amount, mode, reference_no, payment_date, note, user_id, created_at)
SELECT id, customer_id, 2000.00, 'card', 'CARD-4471', invoice_date, 'Advance / part payment', 2, created_at FROM invoices WHERE id = 3;
INSERT INTO payments (invoice_id, customer_id, amount, mode, reference_no, payment_date, note, user_id, created_at)
SELECT id, NULL, total, 'cash', NULL, invoice_date, 'Counter sale', 2, created_at FROM invoices WHERE id = 5;

UPDATE invoices i
  LEFT JOIN (SELECT invoice_id, SUM(amount) paid FROM payments GROUP BY invoice_id) p ON p.invoice_id = i.id
   SET i.paid    = COALESCE(p.paid, 0),
       i.balance = i.total - COALESCE(p.paid, 0),
       i.status  = CASE WHEN COALESCE(p.paid,0) >= i.total THEN 'paid'
                        WHEN COALESCE(p.paid,0) > 0        THEN 'partial'
                        ELSE 'unpaid' END;

-- ---------- Recalculate part stock from ledger ----------
UPDATE parts pt
  LEFT JOIN (SELECT part_id,
                    SUM(CASE WHEN type IN ('opening','in') THEN qty
                             WHEN type = 'out' THEN -qty
                             ELSE qty END) bal
               FROM stock_movements GROUP BY part_id) s ON s.part_id = pt.id
   SET pt.stock = COALESCE(s.bal, 0);

-- ---------- Expenses ----------
INSERT INTO expenses (category_id, expense_date, amount, mode, paid_to, note, user_id) VALUES
(1, CURDATE() - INTERVAL 28 DAY, 15000.00, 'bank', 'Shop owner',        'Monthly rent',          1),
(2, CURDATE() - INTERVAL 20 DAY,  3240.00, 'upi',  'MSEDCL',            'Electricity bill',      1),
(3, CURDATE() - INTERVAL 27 DAY, 18000.00, 'bank', 'Ramesh Mechanic',   'Salary',                1),
(3, CURDATE() - INTERVAL 27 DAY, 16000.00, 'bank', 'Imran Mechanic',    'Salary',                1),
(4, CURDATE() - INTERVAL 2 DAY,    180.00, 'cash', 'Tea stall',         'Tea for staff',         2),
(4, CURDATE(),                     150.00, 'cash', 'Tea stall',         'Tea for staff',         2),
(5, CURDATE() - INTERVAL 9 DAY,   2450.00, 'cash', 'Hardware shop',     'Socket set',            1),
(6, CURDATE() - INTERVAL 5 DAY,    300.00, 'cash', 'Auto rickshaw',     'Parts pickup',          2);

-- ---------- Reminders ----------
INSERT INTO reminders (vehicle_id, customer_id, type, due_date, due_km, status, job_card_id)
SELECT v.id, v.customer_id, 'service', v.next_service_date, v.next_service_km, 'pending',
       (SELECT MAX(jc.id) FROM job_cards jc WHERE jc.vehicle_id = v.id AND jc.status = 'delivered')
  FROM vehicles v WHERE v.next_service_date IS NOT NULL;
INSERT INTO reminders (vehicle_id, customer_id, type, due_date, status)
SELECT id, customer_id, 'insurance', insurance_expiry, 'pending' FROM vehicles WHERE insurance_expiry IS NOT NULL;
INSERT INTO reminders (vehicle_id, customer_id, type, due_date, status)
SELECT id, customer_id, 'puc', puc_expiry, 'pending' FROM vehicles WHERE puc_expiry IS NOT NULL;
UPDATE reminders SET status = 'sent', last_sent_at = NOW() - INTERVAL 1 DAY WHERE vehicle_id = 3 AND type = 'insurance';

-- ---------- Feedbacks ----------
INSERT INTO feedbacks (job_card_id, customer_id, token, rating, comment, submitted_at, created_at) VALUES
(1, 1, 'a3f1c9e2b7d04e8f9a6b1c2d3e4f5a61', 5,    'Very good service, car delivered on time.', NOW() - INTERVAL 38 DAY, NOW() - INTERVAL 39 DAY),
(2, 2, 'b4e2d0f3c8e15f9a0b7c2d3e4f5a6b72', 4,    'AC is cooling well now. Slightly delayed.', NOW() - INTERVAL 23 DAY, NOW() - INTERVAL 24 DAY),
(3, 3, 'c5f3e1a4d9f26a0b1c8d3e4f5a6b7c83', NULL, NULL,                                         NULL,                    NOW() - INTERVAL 11 DAY);

-- =====================================================================
--  END
-- =====================================================================
