-- Video Store Database Initialization Script
-- Character set: utf8mb4, Engine: InnoDB

CREATE DATABASE IF NOT EXISTS video_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE video_store;

-- Items (main video/product table)
CREATE TABLE IF NOT EXISTS items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_id VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    sample_video_url VARCHAR(1000),
    price INT UNSIGNED,
    release_date DATE,
    affiliate_url VARCHAR(1000),
    thumbnail_url VARCHAR(1000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_id (content_id),
    INDEX idx_release_date (release_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genres
CREATE TABLE IF NOT EXISTS genres (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    genre_id VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_genre_id (genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actresses
CREATE TABLE IF NOT EXISTS actresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actress_id VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actress_id (actress_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Genre relationship (many-to-many)
CREATE TABLE IF NOT EXISTS item_genres (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    genre_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_genre (item_id, genre_id),
    INDEX idx_item_id (item_id),
    INDEX idx_genre_id (genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Actress relationship (many-to-many)
CREATE TABLE IF NOT EXISTS item_actresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    actress_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_actress (item_id, actress_id),
    INDEX idx_item_id (item_id),
    INDEX idx_actress_id (actress_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Makers (production companies)
CREATE TABLE IF NOT EXISTS makers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    maker_id VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_maker_id (maker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Maker relationship (many-to-many)
CREATE TABLE IF NOT EXISTS item_makers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    maker_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (maker_id) REFERENCES makers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_maker (item_id, maker_id),
    INDEX idx_item_id (item_id),
    INDEX idx_maker_id (maker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaigns
CREATE TABLE IF NOT EXISTS campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    campaign_name VARCHAR(255),
    start_date DATE,
    end_date DATE,
    discount_rate INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_item_id (item_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fetch logs (API call tracking)
CREATE TABLE IF NOT EXISTS fetch_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fetch_type VARCHAR(50) NOT NULL,
    status ENUM('success', 'failure') NOT NULL,
    items_fetched INT UNSIGNED DEFAULT 0,
    error_message TEXT,
    fetch_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fetch_date (fetch_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
