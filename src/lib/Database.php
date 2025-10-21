<?php
// src/lib/Database.php
declare(strict_types=1);

/**
 * Lightweight PDO singleton wrapper.
 * Expects a config.php returning an array with 'db' => [host, port, name, user, pass, charset]
 *
 * Copy config.php.sample -> config.php and edit DB credentials for the real domain.
 */

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo !== null) return self::$pdo;

        $cfgFile = __DIR__ . '/../../config.php';
        if (!file_exists($cfgFile)) {
            throw new RuntimeException('Missing config.php. Copy config.php.sample and set database credentials.');
        }
        $cfg = require $cfgFile;
        if (!isset($cfg['db'])) throw new RuntimeException('Invalid config.php: db settings missing');

        $db = $cfg['db'];
        $host = $db['host'] ?? '127.0.0.1';
        $port = $db['port'] ?? 3306;
        $name = $db['name'] ?? '';
        $user = $db['user'] ?? '';
        $pass = $db['pass'] ?? '';
        $charset = $db['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        self::$pdo = new PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }
}
