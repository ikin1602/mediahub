-- Upgrade to v6 (MediaHub)
CREATE DATABASE IF NOT EXISTS mediahub
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE mediahub;

-- Resources: inventory & hourly rate
ALTER TABLE resources ADD COLUMN IF NOT EXISTS inventory INT DEFAULT 1 AFTER capacity;
ALTER TABLE resources ADD COLUMN IF NOT EXISTS rate_hour DECIMAL(10,2) DEFAULT 0.00 AFTER inventory;

-- Seed sensible inventory & rates
UPDATE resources SET inventory=2,  rate_hour=15.00 WHERE slug='kamera';
UPDATE resources SET inventory=4,  rate_hour=20.00 WHERE slug='loud-speaker';
UPDATE resources SET inventory=10, rate_hour=8.00  WHERE slug='walkie-talkie';
UPDATE resources SET inventory=5,  rate_hour=6.00  WHERE slug='mikrofon';

-- Rooms as single-inventory with free rate by default
UPDATE resources SET inventory=1, rate_hour=0.00 WHERE slug IN ('tecc1','tecc2','tecc3');

-- Bookings: status workflow, totals, timestamps
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected','checked_in','returned','cancelled') DEFAULT 'pending';
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS rejected_at DATETIME NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS returned_at DATETIME NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Helpful index for overlap & status
CREATE INDEX IF NOT EXISTS idx_bookings_overlap ON bookings (resource_id, date, start_time, end_time, status);
