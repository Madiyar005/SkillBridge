<?php
// config/database.php — Подключение к БД (PDO Singleton)

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]);
            } catch (PDOException $e) {
                if (DEBUG) {
                    die('<pre>DB Error: ' . htmlspecialchars($e->getMessage()) . '</pre>');
                }
                die('Ошибка подключения к базе данных.');
            }
        }
        return self::$instance;
    }

    // Запрет клонирования и сериализации
    private function __construct() {}
    private function __clone() {}
}

// Хелпер-функция
function db(): PDO {
    return Database::getInstance();
}