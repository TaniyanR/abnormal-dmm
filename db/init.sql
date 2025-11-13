-- Create database if not exists
CREATE DATABASE IF NOT EXISTS video_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE video_store;

-- Items table: stores video/product information from DMM API
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    url TEXT,
    affiliate_url TEXT,
    image_url TEXT,
    sample_image_urls JSON,
    sample_movie_url TEXT,
    description TEXT,
    release_date DATE,
    price INT,
    list_price INT,
    volume VARCHAR(100),
    review_count INT DEFAULT 0,
    review_average DECIMAL(3,2) DEFAULT 0.00,
    stock VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_id (content_id),
    INDEX idx_release_date (release_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genres table: stores genre information
CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    genre_id INT NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_genre_id (genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actresses table: stores actress/performer information
CREATE TABLE IF NOT EXISTS actresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actress_id INT NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    ruby VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actress_id (actress_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Makers table: stores maker/studio information
CREATE TABLE IF NOT EXISTS makers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maker_id INT NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_maker_id (maker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Genre relationship table
CREATE TABLE IF NOT EXISTS item_genres (
    item_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (item_id, genre_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE,
    INDEX idx_item_id (item_id),
    INDEX idx_genre_id (genre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Actress relationship table
CREATE TABLE IF NOT EXISTS item_actresses (
    item_id INT NOT NULL,
    actress_id INT NOT NULL,
    PRIMARY KEY (item_id, actress_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE,
    INDEX idx_item_id (item_id),
    INDEX idx_actress_id (actress_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Maker relationship table
CREATE TABLE IF NOT EXISTS item_makers (
    item_id INT NOT NULL,
    maker_id INT NOT NULL,
    PRIMARY KEY (item_id, maker_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (maker_id) REFERENCES makers(id) ON DELETE CASCADE,
    INDEX idx_item_id (item_id),
    INDEX idx_maker_id (maker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaigns table: stores campaign/special offer information
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL UNIQUE,
    title VARCHAR(500),
    description TEXT,
    start_date DATETIME,
    end_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fetch logs table: tracks API fetch operations
CREATE TABLE IF NOT EXISTS fetch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fetch_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    items_fetched INT DEFAULT 0,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fetch_type (fetch_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
