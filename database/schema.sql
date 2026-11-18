-- Jinal · MySQL 8 / MariaDB 10.4 · UTF-8
-- Неразрушающая установка: существующие таблицы и записи не удаляются.
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    bio VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(40) NOT NULL,
    UNIQUE KEY categories_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    city VARCHAR(80) NOT NULL,
    venue VARCHAR(180) NOT NULL,
    starts_at DATETIME NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    poster VARCHAR(40) NOT NULL DEFAULT 'meetup',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT events_organizer_fk FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT events_category_fk FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT events_capacity_positive CHECK (capacity > 0),
    CONSTRAINT events_price_nonnegative CHECK (price >= 0),
    KEY events_starts_at_index (starts_at),
    KEY events_city_starts_index (city, starts_at),
    KEY events_category_starts_index (category_id, starts_at),
    KEY events_organizer_index (organizer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY bookings_user_event_unique (user_id, event_id),
    KEY bookings_event_index (event_id),
    CONSTRAINT bookings_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT bookings_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY comments_event_created_index (event_id, created_at),
    CONSTRAINT comments_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT comments_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY login_attempts_identifier_time_index (identifier, attempted_at),
    KEY login_attempts_time_index (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
