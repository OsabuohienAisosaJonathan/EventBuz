
-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'owner', 'crew') DEFAULT 'owner',
    `reset_token` VARCHAR(64) NULL DEFAULT NULL,
    `reset_expires` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB;

-- 2. Events Table
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_uuid` VARCHAR(36) NOT NULL UNIQUE,
    `owner_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `venue` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `banner_path` VARCHAR(255) NULL,
    `is_public_gallery` TINYINT(1) DEFAULT 0,
    `watermark_enabled` TINYINT(1) DEFAULT 0,
    `watermark_text` VARCHAR(50) DEFAULT 'EventSnap',
    `expires_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_event_uuid` (`event_uuid`)
) ENGINE=InnoDB;

-- 3. QR Codes Table
CREATE TABLE IF NOT EXISTS `qr_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `qr_path` VARCHAR(255) NOT NULL,
    `redirect_url` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Uploads (Photos/Videos Metadata) Table
CREATE TABLE IF NOT EXISTS `uploads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `uploader_name` VARCHAR(100) DEFAULT 'Anonymous Guest',
    `uploader_role` ENUM('guest', 'crew', 'owner') DEFAULT 'guest',
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` INT NOT NULL,
    `caption` VARCHAR(255) NULL DEFAULT NULL,
    `is_approved` TINYINT(1) DEFAULT 1,
    `file_hash` VARCHAR(32) NOT NULL, -- Duplicate detection
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    INDEX `idx_event_uploads` (`event_id`, `is_approved`),
    INDEX `idx_file_hash` (`file_hash`)
) ENGINE=InnoDB;

-- 5. Media Crew Table (Invitations & Event Staff Link)
CREATE TABLE IF NOT EXISTS `media_crew` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `invite_token` VARCHAR(64) NOT NULL UNIQUE,
    `is_accepted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    INDEX `idx_invite` (`invite_token`)
) ENGINE=InnoDB;

-- 6. Guest Sessions Table
CREATE TABLE IF NOT EXISTS `guest_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `session_token` VARCHAR(64) NOT NULL UNIQUE,
    `guest_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    INDEX `idx_guest_session` (`session_token`)
) ENGINE=InnoDB;

-- 7. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Subscriptions Table
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `plan_name` ENUM('Free', 'Pro', 'Premium') DEFAULT 'Free',
    `status` VARCHAR(50) DEFAULT 'active',
    `amount` DECIMAL(10,2) DEFAULT 0.00,
    `starts_at` DATETIME NOT NULL,
    `ends_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default Administrator account (Password: admin123)
-- Hash generated using PASSWORD_BCRYPT
INSERT INTO `users` (`name`, `email`, `password`, `role`)
VALUES ('System Admin', 'admin@eventsnap.com', '$2y$10$vOpeO4HlQ.HlDkR4V112Z.T4v61kX1v5lXy4aZ3l1Q5e5Jg.U2GfG', 'admin')
ON DUPLICATE KEY UPDATE `email` = `email`;
