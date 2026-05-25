-- ============================================
-- SkillSwap - База данных платформы обмена навыками
-- ============================================

CREATE DATABASE IF NOT EXISTS skillswap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE skillswap;

-- Пользователи
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    bio TEXT,
    avatar VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    rating DECIMAL(3,2) DEFAULT 0.00,
    rating_count INT DEFAULT 0,
    balance DECIMAL(10,2) DEFAULT 0.00,
    is_verified TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    role ENUM('user','moderator','admin') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT NULL
) ENGINE=InnoDB;

-- Категории навыков
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT 'ti-star',
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Объявления об услугах/навыках
CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('offer','request') NOT NULL DEFAULT 'offer',
    exchange_type ENUM('skill_swap','paid','both') DEFAULT 'both',
    price DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT 'RUB',
    skill_wanted TEXT DEFAULT NULL,
    status ENUM('active','paused','completed','deleted') DEFAULT 'active',
    views INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FULLTEXT INDEX ft_search (title, description)
) ENGINE=InnoDB;

-- Теги
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE listing_tags (
    listing_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (listing_id, tag_id),
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Изображения к объявлениям
CREATE TABLE listing_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Заявки на обмен
CREATE TABLE exchange_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    message TEXT NOT NULL,
    offer_description TEXT DEFAULT NULL,
    status ENUM('pending','accepted','rejected','completed','cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Отзывы
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exchange_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_review (exchange_id, from_user_id),
    FOREIGN KEY (exchange_id) REFERENCES exchange_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Сообщения чата
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exchange_id INT DEFAULT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exchange_id) REFERENCES exchange_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Избранное
CREATE TABLE favorites (
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Уведомления
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Транзакции (для платных услуг)
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    exchange_id INT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    fee DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending','completed','refunded','failed') DEFAULT 'pending',
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id),
    FOREIGN KEY (exchange_id) REFERENCES exchange_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Жалобы
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    listing_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    reason VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('open','resolved','dismissed') DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- Начальные данные
-- ============================================

INSERT INTO categories (name, slug, icon, sort_order) VALUES
('Программирование', 'programming', 'ti-code', 1),
('Дизайн', 'design', 'ti-palette', 2),
('Маркетинг', 'marketing', 'ti-speakerphone', 3),
('Языки', 'languages', 'ti-world', 4),
('Музыка', 'music', 'ti-music', 5),
('Репетиторство', 'tutoring', 'ti-school', 6),
('Фото и видео', 'photo-video', 'ti-camera', 7),
('Юридические', 'legal', 'ti-scale', 8),
('Здоровье', 'health', 'ti-heart', 9),
('Рукоделие', 'crafts', 'ti-scissors', 10),
('Кулинария', 'cooking', 'ti-chef-hat', 11),
('Спорт', 'sports', 'ti-run', 12);

INSERT INTO categories (name, slug, icon, parent_id, sort_order) VALUES
('Веб-разработка', 'web-dev', 'ti-brand-html5', 1, 1),
('Мобильная разработка', 'mobile-dev', 'ti-device-mobile', 1, 2),
('Data Science', 'data-science', 'ti-chart-dots', 1, 3),
('Графический дизайн', 'graphic-design', 'ti-vector', 2, 1),
('UX/UI', 'ux-ui', 'ti-layers', 2, 2),
('SEO', 'seo', 'ti-search', 3, 1),
('SMM', 'smm', 'ti-brand-instagram', 3, 2),
('Английский', 'english', 'ti-flag', 4, 1),
('Китайский', 'chinese', 'ti-flag', 4, 2);

-- Администратор (пароль: admin123)
INSERT INTO users (username, email, password_hash, full_name, role, is_verified) VALUES
('admin', 'admin@skillswap.ru', '$2y$12$YourHashedPasswordHere', 'Администратор', 'admin', 1);

-- Тестовые теги
INSERT INTO tags (name, slug) VALUES
('PHP', 'php'), ('Python', 'python'), ('JavaScript', 'javascript'),
('React', 'react'), ('Laravel', 'laravel'), ('MySQL', 'mysql'),
('Photoshop', 'photoshop'), ('Figma', 'figma'), ('WordPress', 'wordpress'),
('SEO', 'seo'), ('English', 'english'), ('Онлайн', 'online');