-- XAMPP MySQL setup for MediaHub
CREATE DATABASE IF NOT EXISTS mediahub
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE mediahub;

-- === USERS ===
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30),
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- === RESOURCES (equipment & rooms) ===
CREATE TABLE IF NOT EXISTS resources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type ENUM('equipment','room') NOT NULL,
  rate DECIMAL(10,2) DEFAULT NULL,
  capacity INT DEFAULT NULL,
  inventory INT DEFAULT 1,
  rate_hour DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed items
INSERT INTO resources (slug, name, type, rate, capacity, inventory, rate_hour) VALUES
('kamera', 'Kamera', 'equipment', 80.00, NULL, 2, 15.00),
('loud-speaker', 'Loud Speaker', 'equipment', 120.00, NULL, 4, 20.00),
('walkie-talkie', 'Walkie Talkie', 'equipment', 30.00, NULL, 10, 8.00),
('mikrofon', 'Mikrofon', 'equipment', 25.00, NULL, 5, 6.00),
('tecc1', 'TECC1', 'room', 0.00, 12, 1, 0.00),
('tecc2', 'TECC2', 'room', 0.00, 8, 1, 0.00),
('tecc3', 'TECC3', 'room', 0.00, 20, 1, 0.00)
ON DUPLICATE KEY UPDATE name=VALUES(name), rate=VALUES(rate), capacity=VALUES(capacity),
  inventory=VALUES(inventory), rate_hour=VALUES(rate_hour);

-- === BOOKINGS ===
CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  resource_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  contact VARCHAR(60),
  status ENUM('pending','approved','rejected','checked_in','returned','cancelled') DEFAULT 'pending',
  total_amount DECIMAL(10,2) DEFAULT 0.00,
  approved_at DATETIME NULL,
  rejected_at DATETIME NULL,
  checked_in_at DATETIME NULL,
  returned_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_book_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_book_resource FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
  INDEX idx_resource_date (resource_id, date),
  INDEX idx_bookings_overlap (resource_id, date, start_time, end_time, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- === DEFAULT ADMIN (admin1 / admin123) ===
-- bcrypt hash for "admin123"
-- $2y$10$UuFrw5Rf2rBQCIjRUKZbE.kmkPzZCjQfXxKQceBoVjJAzgZVimZcW
INSERT INTO users (name, email, password_hash, phone, role)
SELECT 'Admin One',
       'admin1@mediahub.com',
       '$2y$10$UuFrw5Rf2rBQCIjRUKZbE.kmkPzZCjQfXxKQceBoVjJAzgZVimZcW',
       '000-0000000',
       'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE email = 'admin1@mediahub.com'
);
