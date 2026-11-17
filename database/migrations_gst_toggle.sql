-- GST enable/disable flag. Run ONCE on an existing database (fresh installs of database.sql already include it).
-- Existing invoices become gst_enabled = 1, so they keep their GST exactly as issued.

ALTER TABLE settings ADD COLUMN gst_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER gstin;
ALTER TABLE invoices ADD COLUMN gst_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER is_igst;
