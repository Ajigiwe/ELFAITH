<?php
/**
 * Root Entry Point - Role Based Routing
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (currentUserRole() === 'admin') {
    header('Location: ' . BASE_URL . '/admin/index.php');
} else {
    header('Location: ' . BASE_URL . '/agent/index.php');
}
exit;

