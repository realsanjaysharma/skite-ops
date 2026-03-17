<?php

class Database
{
    private static $connection = null;

    private function __construct() {}

    public static function getConnection()
    {
        if (self::$connection === null) {

            $env = self::loadEnv();

            $host = $env['DB_HOST'] ?? 'localhost';
            $db   = $env['DB_NAME'] ?? '';
            $user = $env['DB_USER'] ?? '';
            $pass = $env['DB_PASS'] ?? '';

            $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

            $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,

    // Ensures DB timezone matches India (important for logs, compliance, reports)
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'"
];

            self::$connection = new PDO($dsn, $user, $pass, $options);
        }

        return self::$connection;
    }

    private static function loadEnv()
    {
        $envFile = dirname(__DIR__) . '/.env';

        if (!file_exists($envFile)) {
            return [];
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $env = [];

        foreach ($lines as $line) {

            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);

            $env[trim($name)] = trim($value);
        }

        return $env;
    }
}