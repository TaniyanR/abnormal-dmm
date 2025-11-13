-- Database initialization script for Video Store
-- Creates the database and tables for DMM/FANZA integration

CREATE DATABASE IF NOT EXISTS video_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE video_store;

-- Items table: stores video/product information from DMM API
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id VARCHAR(255) UNIQUE NOT NULL COMMENT 'DMM content ID',
    title VARCHAR(500) NOT NULL COMMENT 'Product title',
    description TEXT COMMENT 'Product description',
    product_url VARCHAR(500) COMMENT 'Affiliate product URL',
    affiliate_url VARCHAR(500) COMMENT 'Affiliate link URL',
    image_url_small VARCHAR(500) COMMENT 'Small image URL',
    image_url_large VARCHAR(500) COMMENT 'Large image URL',
    sample_video_url VARCHAR(500) COMMENT 'Sample video URL',
    price INT COMMENT 'Product price',
    release_date DATE COMMENT 'Release date',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_id (content_id),
    INDEX idx_release_date (release_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genres table: stores genre information
CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    genre_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'DMM genre ID',
    name VARCHAR(200) NOT NULL COMMENT 'Genre name',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_genre_id (genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actresses table: stores actress/performer information
CREATE TABLE IF NOT EXISTS actresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actress_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'DMM actress ID',
    name VARCHAR(200) NOT NULL COMMENT 'Actress name',
    ruby VARCHAR(200) COMMENT 'Name in ruby/kana',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actress_id (actress_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Genre relationship table (many-to-many)
CREATE TABLE IF NOT EXISTS item_genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    genre_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_genre (item_id, genre_id),
    INDEX idx_item_id (item_id),
    INDEX idx_genre_id (genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Actress relationship table (many-to-many)
CREATE TABLE IF NOT EXISTS item_actresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    actress_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_actress (item_id, actress_id),
    INDEX idx_item_id (item_id),
    INDEX idx_actress_id (actress_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Makers table: stores maker/studio information
CREATE TABLE IF NOT EXISTS makers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maker_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'DMM maker ID',
    name VARCHAR(200) NOT NULL COMMENT 'Maker name',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_maker_id (maker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Maker relationship table (many-to-many)
CREATE TABLE IF NOT EXISTS item_makers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    maker_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (maker_id) REFERENCES makers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_maker (item_id, maker_id),
    INDEX idx_item_id (item_id),
    INDEX idx_maker_id (maker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaigns table: stores campaign information
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'DMM campaign ID',
    title VARCHAR(300) NOT NULL COMMENT 'Campaign title',
    description TEXT COMMENT 'Campaign description',
    start_date DATETIME COMMENT 'Campaign start date',
    end_date DATETIME COMMENT 'Campaign end date',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fetch logs table: tracks API fetch operations
CREATE TABLE IF NOT EXISTS fetch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fetch_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the fetch occurred',
    items_fetched INT DEFAULT 0 COMMENT 'Number of items fetched',
    status VARCHAR(50) DEFAULT 'success' COMMENT 'Status: success, error, partial',
    error_message TEXT COMMENT 'Error message if any',
    execution_time_ms INT COMMENT 'Execution time in milliseconds',
    INDEX idx_fetch_date (fetch_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
