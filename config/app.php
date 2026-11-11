<?php
/**
 * Application Core Configuration & Environment Settings
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/database.php';

// Dynamically determine Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$app_root = preg_replace('/(\/(admin|agent|api|includes|config).*)$/', '', $script_name);
$app_root = rtrim($app_root, '/');

define('BASE_URL', $protocol . $host . $app_root);
define('APP_NAME', 'EL-FAITH MICROFINANCE');
define('APP_VERSION', '2.0.0-PROD');

// Load dynamic system settings from database
$system_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $system_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Fallback if settings table query fails
}

define('COMPANY_NAME', $system_settings['company_name'] ?? 'EL-FAITH MICROFINANCE');
define('BRANCH_NAME', $system_settings['branch_name'] ?? 'Wassa Powuako');
define('BRANCH_REGION', $system_settings['branch_region'] ?? 'Western Region, Ghana');
define('COMPANY_PHONE', $system_settings['company_phone'] ?? '+233 24 556 7890');
define('COMPANY_EMAIL', $system_settings['company_email'] ?? 'admin@elfaithmicrofinance.com');
define('CURRENCY_CODE', $system_settings['currency_code'] ?? 'GHS');
define('CURRENCY_SYMBOL', $system_settings['currency_symbol'] ?? 'GH₵');
define('DEFAULT_INTEREST_RATE', (float)($system_settings['default_interest_rate'] ?? 10.00));
define('SMS_PROVIDER', $system_settings['sms_provider'] ?? 'simulated');
define('SMS_SENDER_ID', $system_settings['sms_sender_id'] ?? 'ELFAITH');

