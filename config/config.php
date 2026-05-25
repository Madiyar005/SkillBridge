<?php
// config/config.php — Основные настройки приложения

define('DB_HOST', 'localhost');
define('DB_NAME', 'skillswap');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'SkillBridge');
define('APP_URL', 'http://localhost/');
define('APP_VERSION', '1.0.0');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

define('PLATFORM_FEE', 0.05); // 5% комиссия
define('MIN_WITHDRAWAL', 500); // минимальная сумма вывода

define('ITEMS_PER_PAGE', 12);
define('MESSAGES_PER_PAGE', 30);

define('SESSION_LIFETIME', 86400 * 7); // 7 дней

define('MAIL_FROM', 'noreply@skillswap.ru');
define('MAIL_FROM_NAME', 'SkillSwap');

// Режим разработки
define('DEBUG', true);
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}