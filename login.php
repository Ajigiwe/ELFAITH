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
    <meta name="theme-color" content="#ffffff">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-zinc-900 bg-zinc-50 font-sans">
    <div class="max-w-sm w-full bg-white rounded-xl border border-zinc-200 shadow-sm p-6 sm:p-7 my-auto">
        <!-- Minimal Brand Header -->
        <div class="text-center mb-6">
            <div class="w-8 h-8 bg-zinc-900 rounded-lg flex items-center justify-center mx-auto mb-2.5 text-white font-bold text-xs tracking-tight">
                EF
            </div>
            <h1 class="text-sm font-bold tracking-tight uppercase text-zinc-900"><?= htmlspecialchars(COMPANY_NAME) ?></h1>
            <p class="text-[11px] text-zinc-400 font-normal mt-0.5"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_REGION) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-2.5 rounded-lg bg-zinc-50 border border-zinc-300 text-zinc-900 text-[11px] font-medium flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0 text-zinc-700"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <?= csrfField() ?>

            <div>
                <label for="username" class="block text-[11px] font-medium text-zinc-700 mb-1">
                    Username / Staff Code
                </label>
                <input type="text" id="username" name="username" required autofocus 
                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900 transition"
                    placeholder="e.g. ELIJAH or agent_kwame">
            </div>

            <div>
                <label for="password" class="block text-[11px] font-medium text-zinc-700 mb-1">
                    Password
                </label>
                <input type="password" id="password" name="password" required 
                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900 transition"
                    placeholder="Enter password">
            </div>

            <button type="submit" 
                class="w-full py-2 px-4 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5 mt-2">
                Sign In
            </button>
        </form>

        <!-- Access Credentials (Minimal) -->
        <div class="mt-6 pt-4 border-t border-zinc-100">
            <p class="text-[10px] font-medium text-zinc-400 mb-2 text-center">Quick Access Credentials</p>
            <div class="grid grid-cols-2 gap-2 text-[10px]">
                <button type="button" onclick="fillCreds('ELIJAH', 'APPIAH')" class="p-2 text-left rounded-lg bg-zinc-50 border border-zinc-200 hover:bg-zinc-100 transition">
                    <span class="block font-semibold text-zinc-800">Super Admin</span>
                    <span class="text-zinc-500 font-mono text-[9px]">ELIJAH / APPIAH</span>
                </button>
                <button type="button" onclick="fillCreds('agent_kwame', 'agent123')" class="p-2 text-left rounded-lg bg-zinc-50 border border-zinc-200 hover:bg-zinc-100 transition">
                    <span class="block font-semibold text-zinc-800">Field Agent</span>
                    <span class="text-zinc-500 font-mono text-[9px]">agent_kwame / agent123</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function fillCreds(u, p) {
            document.getElementById('username').value = u;
            document.getElementById('password').value = p;
        }
    </script>
</body>
</html>
