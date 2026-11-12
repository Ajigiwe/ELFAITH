<?php
/**
 * Sign In Portal - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (currentUserRole() === 'admin') {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } else {
        header('Location: ' . BASE_URL . '/agent/index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Session security expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (attemptLogin($pdo, $username, $password)) {
            if (currentUserRole() === 'admin') {
                header('Location: ' . BASE_URL . '/admin/index.php');
            } else {
                header('Location: ' . BASE_URL . '/agent/index.php');
            }
            exit;
        } else {
            $error = $_SESSION['login_error'] ?? 'Invalid credentials or inactive account.';
            unset($_SESSION['login_error']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In — <?= htmlspecialchars(COMPANY_NAME) ?></title>
    <meta name="theme-color" content="#000000">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(getFaviconUrl()) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(getCompanyLogoUrl() ?: (BASE_URL . '/assets/icons/apple-touch-icon.png')) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-black bg-zinc-50 font-sans">
    <div class="max-w-sm w-full bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden my-auto">
        <div class="h-1.5 bg-gradient-to-r from-black via-blue-600 to-black"></div>
        <div class="p-6 sm:p-7">
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <?= renderCompanyLogo('auth') ?>
            <h1 class="text-sm font-bold tracking-tight uppercase text-black"><?= htmlspecialchars(COMPANY_NAME) ?></h1>
            <p class="text-[11px] text-blue-600 font-medium mt-0.5"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_REGION) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-[11px] font-medium flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0 text-rose-600"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label for="username" class="block text-[11px] font-medium text-zinc-800 mb-1">
                    Username / Staff Code
                </label>
                <input type="text" id="username" name="username" required autofocus 
                    class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg text-xs text-black placeholder-zinc-400 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition"
                    placeholder="Enter username or staff email">
            </div>

            <div>
                <label for="password" class="block text-[11px] font-medium text-zinc-800 mb-1">
                    Password
                </label>
                <input type="password" id="password" name="password" required 
                    class="w-full px-3 py-2 bg-white border border-zinc-300 rounded-lg text-xs text-black placeholder-zinc-400 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition"
                    placeholder="Enter password">
            </div>

            <button type="submit" 
                class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5 mt-2 shadow-sm">
                Sign In
            </button>
        </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
