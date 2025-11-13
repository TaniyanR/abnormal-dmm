-- Initialize database and schema for Video Store (DMM/FANZA integration)
CREATE DATABASE IF NOT EXISTS `video_store` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `video_store`;

-- items: main table for video/product items
CREATE TABLE IF NOT EXISTS `items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `content_id` VARCHAR(64) NOT NULL,
  `product_id` VARCHAR(64) NULL,
  `title` TEXT NULL,
  `description` TEXT NULL,
  `url` VARCHAR(512) NULL,
  `affiliate_url` VARCHAR(512) NULL,
  `image_list` VARCHAR(512) NULL,
  `image_small` VARCHAR(512) NULL,
  `image_large` VARCHAR(512) NULL,
  `sample_images` JSON NULL,
  `sample_movies` JSON NULL,
  `price_min` INT NULL,
  `price_list_min` INT NULL,
  `volume` VARCHAR(100) NULL,
  `stock` VARCHAR(64) NULL,
  `release_date` DATETIME NULL,
  `duration_minutes` INT NULL,
  `review_count` INT DEFAULT 0,
  `review_average` FLOAT DEFAULT 0,
  `iteminfo` JSON NULL,
  `campaign` JSON NULL,
  `deliveries` JSON NULL,
  `last_fetched_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_items_content_id` (`content_id`),
  INDEX `idx_release_date` (`release_date`),
  INDEX `idx_price_min` (`price_min`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- genres: genre master (store external DMM id if available)
CREATE TABLE IF NOT EXISTS `genres` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `dmm_id` BIGINT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_genres_dmm_id` (`dmm_id`),
  INDEX `idx_genre_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- actresses: performer master
CREATE TABLE IF NOT EXISTS `actresses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `dmm_id` BIGINT NULL,
  `name` VARCHAR(255) NOT NULL,
  `ruby` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_actresses_dmm_id` (`dmm_id`),
  INDEX `idx_actress_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- makers: studio / maker master
CREATE TABLE IF NOT EXISTS `makers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `dmm_id` BIGINT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_makers_dmm_id` (`dmm_id`),
  INDEX `idx_maker_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- item_genres: junction table (many-to-many)
CREATE TABLE IF NOT EXISTS `item_genres` (
  `item_id` INT NOT NULL,
  `genre_id` INT NOT NULL,
  PRIMARY KEY (`item_id`, `genre_id`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`genre_id`) REFERENCES `genres`(`id`) ON DELETE CASCADE,
  INDEX `idx_item_genre_item` (`item_id`),
  INDEX `idx_item_genre_genre` (`genre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- item_actresses: junction table (many-to-many)
CREATE TABLE IF NOT EXISTS `item_actresses` (
  `item_id` INT NOT NULL,
  `actress_id` INT NOT NULL,
  PRIMARY KEY (`item_id`, `actress_id`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`actress_id`) REFERENCES `actresses`(`id`) ON DELETE CASCADE,
  INDEX `idx_item_actress_item` (`item_id`),
  INDEX `idx_item_actress_actress` (`actress_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- item_makers: junction table (many-to-many)
CREATE TABLE IF NOT EXISTS `item_makers` (
  `item_id` INT NOT NULL,
  `maker_id` INT NOT NULL,
  PRIMARY KEY (`item_id`, `maker_id`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`maker_id`) REFERENCES `makers`(`id`) ON DELETE CASCADE,
  INDEX `idx_item_maker_item` (`item_id`),
  INDEX `idx_item_maker_maker` (`maker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- campaigns: optional table for campaign/special offers
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `campaign_dmm_id` BIGINT NULL,
  `item_id` INT NULL,
  `title` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `date_begin` DATETIME NULL,
  `date_end` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_campaign_dmm_id` (`campaign_dmm_id`),
  INDEX `idx_campaign_dates` (`date_begin`, `date_end`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fetch_logs: simple logging of API fetch runs
CREATE TABLE IF NOT EXISTS `fetch_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `operation` VARCHAR(64) NOT NULL,
  `status` VARCHAR(64) DEFAULT 'success',
  `items_fetched` INT DEFAULT 0,
  `message` TEXT NULL,
  `execution_ms` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_fetch_operation` (`operation`),
  INDEX `idx_fetch_status` (`status`),
  INDEX `idx_fetch_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;