<?php
/**
 * Database Connection Configuration
 * PDO with UTF-8mb4 and Exception error handling
 */

defined('APP_INIT') or define('APP_INIT', true);

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'elfaith_microfinance';
$db_user = 'root';
$db_pass = '';

if (!defined('DB_HOST')) define('DB_HOST', $db_host);
if (!defined('DB_PORT')) define('DB_PORT', $db_port);
if (!defined('DB_NAME')) define('DB_NAME', $db_name);
if (!defined('DB_USER')) define('DB_USER', $db_user);
if (!defined('DB_PASS')) define('DB_PASS', $db_pass);
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// Function helper to get PDO instance
function getDb(): PDO {
    global $pdo;
    return $pdo;
}

