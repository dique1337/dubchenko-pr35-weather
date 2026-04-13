-- ============================================
-- WEATHER APP - Полная схема базы данных
-- ============================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Удаляем старые таблицы
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS security_logs;
DROP TABLE IF EXISTS user_weather_settings;
DROP TABLE IF EXISTS weather_display_settings;
DROP TABLE IF EXISTS city_reviews;
DROP TABLE IF EXISTS favorite_cities;
DROP TABLE IF EXISTS weather_cache;
DROP TABLE IF EXISTS weather_cities;
DROP TABLE IF EXISTS users;

-- ============================================
-- ПОЛЬЗОВАТЕЛИ
-- ============================================
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    login           VARCHAR(100) NOT NULL UNIQUE,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    avatar          VARCHAR(255) DEFAULT 'default_avatar.png',
    role            ENUM('user','admin') DEFAULT 'user',
    is_active       TINYINT(1) DEFAULT 0,
    confirm_code    VARCHAR(10) DEFAULT NULL,
    unit_system     ENUM('metric','imperial') DEFAULT 'metric',
    theme           ENUM('auto','light','dark') DEFAULT 'auto',
    language        VARCHAR(10) DEFAULT 'ru',
    created_at      DATETIME DEFAULT NOW(),
    updated_at      DATETIME DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ВОССТАНОВЛЕНИЕ ПАРОЛЯ
-- ============================================
CREATE TABLE password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ИЗБРАННЫЕ ГОРОДА
-- ============================================
CREATE TABLE favorite_cities (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    city_name   VARCHAR(150) NOT NULL,
    alias       VARCHAR(150) DEFAULT NULL,
    lat         DECIMAL(9,6) DEFAULT NULL,
    lon         DECIMAL(9,6) DEFAULT NULL,
    timezone    VARCHAR(100) DEFAULT NULL,
    sort_order  INT DEFAULT 0,
    use_count   INT DEFAULT 1,
    created_at  DATETIME DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_city (user_id, city_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- КЭШ ПОГОДЫ
-- ============================================
CREATE TABLE weather_cache (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    city_key    VARCHAR(200) NOT NULL UNIQUE,
    city_name   VARCHAR(150) NOT NULL,
    lat         DECIMAL(9,6) NOT NULL,
    lon         DECIMAL(9,6) NOT NULL,
    data        LONGTEXT NOT NULL,
    fetched_at  DATETIME DEFAULT NOW(),
    expires_at  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- СПИСОК ГОРОДОВ (для админа)
-- ============================================
CREATE TABLE weather_cities (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    city_name   VARCHAR(150) NOT NULL UNIQUE,
    lat         DECIMAL(9,6) DEFAULT NULL,
    lon         DECIMAL(9,6) DEFAULT NULL,
    added_by    INT DEFAULT NULL,
    created_at  DATETIME DEFAULT NOW(),
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- НАСТРОЙКИ ОТОБРАЖЕНИЯ (АДМИН)
-- ============================================
CREATE TABLE weather_display_settings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    param_key   VARCHAR(50) NOT NULL UNIQUE,
    label_ru    VARCHAR(100) NOT NULL,
    is_enabled  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO weather_display_settings (param_key, label_ru, is_enabled) VALUES
('temp',        'Температура',           1),
('feels_like',  'Ощущается как',         1),
('humidity',    'Влажность',             1),
('pressure',    'Давление',              1),
('wind',        'Скорость ветра',        1),
('uv_index',    'УФ-индекс',             1),
('precip',      'Осадки',                1),
('visibility',  'Видимость',             1),
('sunrise',     'Восход / Закат',        1),
('aqi',         'Качество воздуха (AQI)',1);

-- ============================================
-- НАСТРОЙКИ ПОЛЬЗОВАТЕЛЯ
-- ============================================
CREATE TABLE user_weather_settings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    param_key   VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_param (user_id, param_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ОТЗЫВЫ О ГОРОДАХ
-- ============================================
CREATE TABLE city_reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    city_name   VARCHAR(150) NOT NULL,
    review_text TEXT NOT NULL,
    created_at  DATETIME DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЛОГИ БЕЗОПАСНОСТИ
-- ============================================
CREATE TABLE security_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT DEFAULT NULL,
    event_type  VARCHAR(50) NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    user_agent  TEXT DEFAULT NULL,
    details     TEXT DEFAULT NULL,
    created_at  DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ДЕМО ADMIN
-- ============================================
INSERT INTO users (login, email, password_hash, role, is_active)
VALUES ('admin', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);
-- пароль: password
