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
    if (isAdmin()) {
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
            if (isAdmin()) {
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
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-slate-900 bg-slate-50 font-sans relative overflow-hidden selection:bg-blue-600 selection:text-white">
    <?php $uiBgLogoUrl = getUiBackgroundLogoUrl(); ?>
    <!-- Premium Fintech Ambient UI Background Logo Watermark -->
    <div class="fixed inset-0 pointer-events-none z-0 flex items-center justify-center overflow-hidden select-none" aria-hidden="true">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_35%,rgba(37,99,235,0.04)_0%,transparent_75%)]"></div>
        <img src="<?= htmlspecialchars($uiBgLogoUrl) ?>" 
             alt="" 
             class="w-[520px] max-w-[85vw] max-h-[85vh] object-contain opacity-[0.06] filter contrast-125">
    </div>

    <div class="max-w-sm w-full bg-white/95 backdrop-blur-sm rounded-2xl border border-slate-200/90 shadow-[0_8px_30px_rgb(0,0,0,0.06)] overflow-hidden my-auto relative z-10">
        <div class="p-7 sm:p-8">
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="inline-flex p-2.5 rounded-2xl bg-slate-50 border border-slate-200/70 shadow-xs mb-3">
                <?= renderCompanyLogo('auth') ?>
            </div>
            <h1 class="text-base font-extrabold tracking-tight uppercase text-slate-950"><?= htmlspecialchars(COMPANY_NAME) ?></h1>
            <p class="text-[11px] text-blue-600 font-bold uppercase tracking-wider mt-1"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_REGION) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-medium flex items-center gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-600"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label for="username" class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Username / Staff Email
                </label>
                <input type="text" id="username" name="username" required autofocus 
                    class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-950 placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 shadow-xs transition"
                    placeholder="Enter username or staff email">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-slate-700">
                        Password
                    </label>
                </div>
                <input type="password" id="password" name="password" required 
                    class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-950 placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 shadow-xs transition"
                    placeholder="Enter account password">
            </div>

            <button type="submit" 
                class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs rounded-xl transition shadow-xs flex items-center justify-center gap-1.5 mt-2">
                <span>Sign In to Portal</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </button>
        </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
