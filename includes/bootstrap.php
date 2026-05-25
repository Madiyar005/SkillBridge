<?php
// includes/bootstrap.php — Инициализация приложения

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

startSession();

// Обновление last_seen для авторизованного пользователя
if (isLoggedIn()) {
    static $lastSeenUpdated = false;
    if (!$lastSeenUpdated) {
        $uid = currentUser()['id'];
        db()->prepare("UPDATE users SET last_seen=NOW() WHERE id=?")->execute([$uid]);
        $lastSeenUpdated = true;
    }
}