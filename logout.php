<?php
/**
 * Sign Out Handler
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('info', 'You have been signed out safely.');
header('Location: ' . BASE_URL . '/login.php');
exit;

