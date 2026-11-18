<?php

declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/RegistrationInput.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function has_valid_csrf_token($value): bool
{
    return is_string($value)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $value);
}

function database_connection(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $configPath = dirname(__DIR__) . '/config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException(
            'Database configuration is missing. Copy config/config.example.php to config/config.php and set your database credentials.'
        );
    }

    $config = require $configPath;
    if (!is_array($config) || !isset($config['dsn'], $config['username'], $config['password'])) {
        throw new RuntimeException('Database configuration must define dsn, username, and password.');
    }

    $connection = new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
