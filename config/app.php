<?php
/**
 * Application Core Configuration & Environment Settings
 */

date_default_timezone_set('Africa/Accra');

$hostHeader = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostOnly = strtolower(preg_replace('/:\d+$/', '', $hostHeader));
$isLocalHost = in_array($hostOnly, ['localhost', '127.0.0.1', '::1', '[::1]'], true);

ini_set('log_errors', '1');
ini_set('display_errors', $isLocalHost ? '1' : '0');

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/database.php';

// Dynamically determine Base URL (works on root domain, subdomain, or subfolder)
$protocol = $isHttps ? 'https://' : 'http://';
$host = $hostHeader;
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$app_root = preg_replace('/(\/(admin|agent|api|includes|config).*)$/', '', $script_name);
$app_root = rtrim($app_root, '/');

define('BASE_URL', $protocol . $host . $app_root);
define('APP_NAME', 'EL-FAITH MICROFINANCE');
define('APP_VERSION', '2.7.1-PROD');

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
define('COMPANY_LOGO', $system_settings['company_logo'] ?? '');
define('BRANCH_NAME', $system_settings['branch_name'] ?? 'Wassa Powuako');
define('BRANCH_REGION', $system_settings['branch_region'] ?? 'Western Region, Ghana');
define('COMPANY_PHONE', $system_settings['company_phone'] ?? '+233 24 556 7890');
define('COMPANY_EMAIL', $system_settings['company_email'] ?? 'admin@el-faithgroupofcompanies.com');
define('CURRENCY_CODE', $system_settings['currency_code'] ?? 'GHS');
$rawCurrencySymbol = trim((string)($system_settings['currency_symbol'] ?? 'GH₵'));
if ($rawCurrencySymbol === '' || strpos($rawCurrencySymbol, '262145') !== false || preg_match('/^[\d\s\x00-\x1F]+$/', $rawCurrencySymbol)) {
    $rawCurrencySymbol = 'GH₵';
    if (isset($pdo)) {
        try {
            $pdo->prepare("UPDATE system_settings SET setting_value = 'GH₵', updated_at = NOW() WHERE setting_key = 'currency_symbol'")->execute();
        } catch (Exception $e) {
            // Non-blocking fallback
        }
    }
}
define('CURRENCY_SYMBOL', $rawCurrencySymbol);
define('DEFAULT_INTEREST_RATE', (float)($system_settings['default_interest_rate'] ?? 10.00));
define('SMS_PROVIDER', $system_settings['sms_provider'] ?? 'simulated');
define('SMS_SENDER_ID', $system_settings['sms_sender_id'] ?? 'ELFAITH');

