<?php
/**
 * Shared Application Header - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$user = currentUser();
$role = currentUserRole();
$flash = getFlash();
$page_title = $page_title ?? COMPANY_NAME;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?> — <?= htmlspecialchars(COMPANY_NAME) ?></title>
    
    <!-- PWA & Mobile Web App Meta -->
    <meta name="theme-color" content="#18181b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="EL-FAITH">
    <link rel="manifest" href="<?= BASE_URL ?>/assets/manifest.json">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= BASE_URL ?>/assets/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL ?>/assets/icons/favicon.png">
    
    <!-- Tailwind CSS (CDN for zero-build standalone portability) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Core Application Library (Offline Storage, GPS, UUID) -->
    <script src="<?= BASE_URL ?>/assets/app.js"></script>
    
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; }
        }
        .print-only { display: none; }
        body { -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="flex flex-col min-h-screen text-zinc-900 bg-zinc-50 font-sans antialiased pb-16 md:pb-0">

    <!-- Offline Notification Banner (Minimal) -->
    <div id="offline-banner" class="hidden no-print bg-zinc-900 text-zinc-100 text-xs px-4 py-2 text-center sticky top-0 z-50 transition-all">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2 flex-wrap">
            <i data-lucide="wifi-off" class="w-3.5 h-3.5 text-zinc-400"></i>
            <span id="offline-banner-text">Offline Mode &bull; Collections saved locally on device</span>
            <span id="offline-count-badge" class="ml-1 bg-zinc-800 text-zinc-200 px-2 py-0.5 rounded text-[10px] font-mono">0 Pending</span>
            <a href="<?= BASE_URL ?>/agent/offline_queue.php" class="ml-2 text-zinc-300 hover:text-white underline text-[11px] font-medium">View Queue</a>
        </div>
    </div>

    <!-- Minimal Clean Header -->
    <header class="no-print bg-white border-b border-zinc-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                <!-- Brand -->
                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>" class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-zinc-900 flex items-center justify-center text-white text-xs font-bold tracking-tight">
                            EF
                        </div>
                        <div class="leading-none">
                            <span class="text-xs font-bold tracking-wide uppercase text-zinc-900">EL-FAITH</span>
                            <span class="text-[10px] block text-zinc-400 font-normal uppercase tracking-wider mt-0.5"><?= htmlspecialchars(BRANCH_NAME) ?></span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Role Navigation (Minimal) -->
                <?php if ($user): ?>
                    <nav class="hidden md:flex items-center gap-1">
                        <?php if ($role === 'admin'): ?>
                            <a href="<?= BASE_URL ?>/admin/index.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'admin/index.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Dashboard</a>
                            <a href="<?= BASE_URL ?>/admin/customers.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'customer') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Customers</a>
                            <a href="<?= BASE_URL ?>/admin/loans.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'loan') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Loans</a>
                            <a href="<?= BASE_URL ?>/admin/collections.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'collections.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Payments</a>
                            <a href="<?= BASE_URL ?>/admin/reconciliation.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'reconciliation.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Reconciliation</a>
                            <a href="<?= BASE_URL ?>/admin/overdue.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'overdue.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Overdue</a>
                            <a href="<?= BASE_URL ?>/admin/agents.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'agents.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Staff</a>
                            <a href="<?= BASE_URL ?>/admin/reports.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'reports.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Reports</a>
                            <a href="<?= BASE_URL ?>/admin/notifications.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'notifications.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">SMS Logs</a>
                            <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'audit') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Audit</a>
                            <a href="<?= BASE_URL ?>/admin/backup.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'backup') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Backup</a>
                            <a href="<?= BASE_URL ?>/admin/settings.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Settings</a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/agent/index.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'agent/index.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Route</a>
                            <a href="<?= BASE_URL ?>/agent/collect.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'collect.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Collect</a>
                            <a href="<?= BASE_URL ?>/agent/my_customers.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'my_customers.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Clients</a>
                            <a href="<?= BASE_URL ?>/agent/customer_new.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'customer_new.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">New Client</a>
                            <a href="<?= BASE_URL ?>/agent/history.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'history.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">History</a>
                            <a href="<?= BASE_URL ?>/agent/offline_queue.php" class="px-2.5 py-1.5 rounded-lg text-xs <?= strpos($_SERVER['PHP_SELF'], 'offline_queue.php') !== false ? 'bg-zinc-100 font-semibold text-zinc-900' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">Queue</a>
                        <?php endif; ?>
                    </nav>

                    <!-- User Profile & Sign Out -->
                    <div class="flex items-center gap-2.5">
                        <!-- PWA Install Button (reveals on mobile/desktop beforeinstallprompt) -->
                        <button type="button" onclick="promptPwaInstall()" 
                            class="pwa-install-trigger hidden inline-flex items-center gap-1 px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 text-white text-[11px] font-medium rounded-lg transition shadow-sm">
                            <i data-lucide="download" class="w-3 h-3"></i> Install App
                        </button>

                        <div class="text-right hidden sm:block">
                            <span class="block text-xs font-medium text-zinc-800 leading-tight"><?= htmlspecialchars($user['name']) ?></span>
                            <span class="text-[10px] text-zinc-400 font-mono uppercase">
                                <?= htmlspecialchars(strtoupper($user['role'])) ?>
                            </span>
                        </div>
                        <a href="<?= BASE_URL ?>/logout.php" title="Sign Out" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 transition">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div>
                        <a href="<?= BASE_URL ?>/login.php" class="text-xs font-medium text-zinc-900 hover:text-zinc-600 transition">
                            Sign In
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <!-- Minimal Flash Alert Messages -->
        <?php if ($flash): ?>
            <?php
                $bgColor = 'bg-zinc-100 border-zinc-200 text-zinc-800';
                $icon = 'info';
                if ($flash['type'] === 'success') {
                    $bgColor = 'bg-zinc-50 border-zinc-300 text-zinc-800';
                    $icon = 'check-circle';
                } elseif ($flash['type'] === 'danger' || $flash['type'] === 'error') {
                    $bgColor = 'bg-zinc-50 border-zinc-300 text-zinc-900';
                    $icon = 'alert-triangle';
                } elseif ($flash['type'] === 'warning') {
                    $bgColor = 'bg-zinc-50 border-zinc-300 text-zinc-900';
                    $icon = 'alert-circle';
                }
            ?>
            <div class="no-print mb-5 rounded-lg border p-3.5 <?= $bgColor ?> flex items-start gap-2.5 text-xs">
                <i data-lucide="<?= $icon ?>" class="w-4 h-4 shrink-0 text-zinc-600 mt-0.5"></i>
                <div class="font-medium flex-1">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            </div>
        <?php endif; ?>
