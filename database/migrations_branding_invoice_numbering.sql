-- Invoice numbering standard: PREFIX/FY/0001  (e.g. INV/26-27/0001), restarting each financial year (1 April).
-- Run ONCE on an existing database. Fresh installs of database.sql already include this column.
-- Existing invoices keep their old numbers; the counter simply continues in the new format.

ALTER TABLE settings ADD COLUMN invoice_fy VARCHAR(7) NULL AFTER invoice_next;

-- Mark the counter as belonging to the current financial year so it does NOT restart mid-year.
UPDATE settings
   SET invoice_fy = CONCAT(LPAD(MOD(YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)), 100), 2, '0'), '-',
                           LPAD(MOD(YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) + 1, 100), 2, '0'))
 WHERE id = 1;
