<?php
/**
 * Shared Application Header - Minimalist Theme with Collapsible Side Menu
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$user = currentUser();
$role = currentUserRole();
$flash = getFlash();
$page_title = $page_title ?? COMPANY_NAME;
$currSelf = $_SERVER['PHP_SELF'] ?? '';
$isCalculator = strpos($currSelf, 'calculator.php') !== false;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?> — <?= htmlspecialchars(COMPANY_NAME) ?></title>
    
    <!-- PWA & Mobile Web App Meta -->
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="EL-FAITH">
    <link rel="manifest" href="<?= BASE_URL ?>/assets/manifest.json">
    <?php $companyLogoUrl = getCompanyLogoUrl(); $faviconUrl = getFaviconUrl(); ?>
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($companyLogoUrl ?: (BASE_URL . '/assets/icons/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($companyLogoUrl ?: (BASE_URL . '/assets/icons/icon-192.png')) ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= htmlspecialchars($faviconUrl) ?>">
    
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

    <!-- Prevent sidebar width flicker on initial load -->
    <script>
        try {
            if (localStorage.getItem('elfaith_sidebar_collapsed') === '1') {
                document.documentElement.classList.add('sidebar-is-collapsed');
            }
        } catch (e) {}
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Core Application Library (Offline Storage, GPS, UUID) -->
    <script>
        window.COMPANY_LOGO_URL = <?= json_encode($companyLogoUrl) ?>;
        window.COMPANY_DISPLAY_NAME = <?= json_encode(COMPANY_NAME) ?>;
    </script>
    <script src="<?= BASE_URL ?>/assets/app.js"></script>
    
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; }
            #app-main-shell { margin-left: 0 !important; }
        }
        .print-only { display: none; }
        body { -webkit-overflow-scrolling: touch; }

        /* Collapsible Desktop Sidebar States */
        @media (min-width: 768px) {
            html.sidebar-is-collapsed #app-sidebar {
                width: 4.25rem;
            }
            html.sidebar-is-collapsed #app-sidebar .sidebar-label,
            html.sidebar-is-collapsed #app-sidebar .sidebar-section-title,
            html.sidebar-is-collapsed #app-sidebar .sidebar-brand-text,
            html.sidebar-is-collapsed #app-sidebar .sidebar-user-meta {
                display: none;
            }
            html.sidebar-is-collapsed #app-sidebar .sidebar-nav-link {
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            html.sidebar-is-collapsed #app-sidebar .sidebar-header-row {
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            html.sidebar-is-collapsed #app-sidebar .sidebar-brand-link {
                display: none;
            }
            html.sidebar-is-collapsed #app-sidebar .sidebar-footer-user {
                justify-content: center;
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col text-black bg-zinc-50 font-sans antialiased pb-16 md:pb-0">

    <!-- Offline Notification Banner -->
    <div id="offline-banner" class="hidden no-print bg-black text-white text-xs px-4 py-2 text-center sticky top-0 z-50 transition-all">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2 flex-wrap">
            <i data-lucide="wifi-off" class="w-3.5 h-3.5 text-blue-400"></i>
            <span id="offline-banner-text">Offline Mode &bull; Collections saved locally on device</span>
            <span id="offline-count-badge" class="ml-1 bg-zinc-900 border border-zinc-700 text-white px-2 py-0.5 rounded text-[10px] font-mono">0 Pending</span>
            <a href="<?= BASE_URL ?>/agent/offline_queue.php" class="ml-2 text-blue-300 hover:text-white underline text-[11px] font-medium">View Queue</a>
        </div>
    </div>

<?php if ($user): ?>
<?php
    if ($role === 'admin') {
        $navSections = [
            'Overview' => [
                ['label' => 'Dashboard', 'url' => BASE_URL . '/admin/index.php', 'icon' => 'layout-dashboard', 'active' => strpos($currSelf, 'admin/index.php') !== false],
            ],
            'Operations' => [
                ['label' => 'Customers', 'url' => BASE_URL . '/admin/customers.php', 'icon' => 'users', 'active' => strpos($currSelf, 'admin/customers.php') !== false || strpos($currSelf, 'admin/customer_view.php') !== false],
                ['label' => 'Loans', 'url' => BASE_URL . '/admin/loans.php', 'icon' => 'briefcase', 'active' => strpos($currSelf, 'admin/loans.php') !== false || strpos($currSelf, 'admin/loan_view.php') !== false],
                ['label' => 'Loan Calculator', 'url' => BASE_URL . '/calculator.php', 'icon' => 'calculator', 'active' => $isCalculator],
                ['label' => 'Payments', 'url' => BASE_URL . '/admin/collections.php', 'icon' => 'wallet', 'active' => strpos($currSelf, 'admin/collections.php') !== false],
                ['label' => 'Overdue', 'url' => BASE_URL . '/admin/overdue.php', 'icon' => 'alert-circle', 'active' => strpos($currSelf, 'admin/overdue.php') !== false],
                ['label' => 'Reconciliation', 'url' => BASE_URL . '/admin/reconciliation.php', 'icon' => 'scale', 'active' => strpos($currSelf, 'admin/reconciliation.php') !== false],
            ],
            'Management' => [
                ['label' => 'Staff & Agents', 'url' => BASE_URL . '/admin/agents.php', 'icon' => 'user-cog', 'active' => strpos($currSelf, 'admin/agents.php') !== false],
                ['label' => 'Reports', 'url' => BASE_URL . '/admin/reports.php', 'icon' => 'bar-chart-3', 'active' => strpos($currSelf, 'admin/reports.php') !== false],
                ['label' => 'SMS Logs', 'url' => BASE_URL . '/admin/notifications.php', 'icon' => 'message-square', 'active' => strpos($currSelf, 'admin/notifications.php') !== false],
            ],
            'System' => [
                ['label' => 'Audit Trail', 'url' => BASE_URL . '/admin/audit_logs.php', 'icon' => 'shield-check', 'active' => strpos($currSelf, 'admin/audit') !== false],
                ['label' => 'Backup & Wipe', 'url' => BASE_URL . '/admin/backup.php', 'icon' => 'database', 'active' => strpos($currSelf, 'admin/backup') !== false],
                ['label' => 'Settings', 'url' => BASE_URL . '/admin/settings.php', 'icon' => 'settings', 'active' => strpos($currSelf, 'admin/settings.php') !== false],
            ],
        ];
    } else {
        $navSections = [
            'Field Work' => [
                ['label' => "Today's Route", 'url' => BASE_URL . '/agent/index.php', 'icon' => 'map-pin', 'active' => strpos($currSelf, 'agent/index.php') !== false],
                ['label' => 'Collect Payment', 'url' => BASE_URL . '/agent/collect.php', 'icon' => 'plus-circle', 'active' => strpos($currSelf, 'agent/collect.php') !== false],
                ['label' => 'Offline Queue', 'url' => BASE_URL . '/agent/offline_queue.php', 'icon' => 'cloud-sync', 'active' => strpos($currSelf, 'agent/offline_queue.php') !== false],
            ],
            'Clients & Tools' => [
                ['label' => 'My Clients', 'url' => BASE_URL . '/agent/my_customers.php', 'icon' => 'users', 'active' => strpos($currSelf, 'agent/my_customers.php') !== false],
                ['label' => 'Register Client', 'url' => BASE_URL . '/agent/customer_new.php', 'icon' => 'user-plus', 'active' => strpos($currSelf, 'agent/customer_new.php') !== false],
                ['label' => 'Loan Calculator', 'url' => BASE_URL . '/calculator.php', 'icon' => 'calculator', 'active' => $isCalculator],
                ['label' => 'My History', 'url' => BASE_URL . '/agent/history.php', 'icon' => 'receipt', 'active' => strpos($currSelf, 'agent/history.php') !== false],
            ],
        ];
    }
    $initials = strtoupper(substr(trim($user['name'] ?? 'EF'), 0, 2));
?>
    <!-- App Shell with Collapsible Side Menu -->
    <div class="flex-1 flex min-h-0">
        <!-- Mobile Sidebar Backdrop -->
        <div id="sidebar-backdrop" onclick="toggleMobileSidebar(false)" class="no-print fixed inset-0 bg-black/60 z-40 hidden md:hidden transition-opacity"></div>

        <!-- Collapsible Sidebar -->
        <aside id="app-sidebar" class="no-print fixed inset-y-0 left-0 z-50 w-60 bg-white border-r border-zinc-200 flex flex-col transform -translate-x-full md:translate-x-0 md:sticky md:top-0 md:h-screen transition-all duration-200 ease-in-out shrink-0 shadow-sm">
            <!-- Deep Black & Blue Brand Top Accent -->
            <div class="h-1 bg-gradient-to-r from-black via-blue-600 to-black shrink-0"></div>

            <!-- Brand & Collapse Toggle -->
            <div class="sidebar-header-row h-14 px-4 border-b border-zinc-200 flex items-center justify-between gap-2 shrink-0">
                <a href="<?= BASE_URL ?>" class="sidebar-brand-link flex items-center gap-2.5 min-w-0">
                    <?= renderCompanyLogo('navbar') ?>
                    <div class="sidebar-brand-text leading-none truncate">
                        <span class="text-xs font-bold tracking-wide uppercase text-black block truncate">EL-FAITH</span>
                        <span class="text-[10px] block text-blue-600 font-medium uppercase tracking-wider mt-0.5 truncate"><?= htmlspecialchars(BRANCH_NAME) ?></span>
                    </div>
                </a>

                <!-- Desktop Collapse Button -->
                <button type="button" onclick="toggleDesktopSidebar()" title="Collapse / Expand Menu"
                    class="hidden md:inline-flex items-center justify-center p-1.5 rounded-lg text-zinc-500 hover:text-black hover:bg-zinc-100 transition shrink-0">
                    <i data-lucide="panel-left" class="w-4 h-4"></i>
                </button>

                <!-- Mobile Close Button -->
                <button type="button" onclick="toggleMobileSidebar(false)" title="Close Menu"
                    class="md:hidden inline-flex items-center justify-center p-1.5 rounded-lg text-zinc-500 hover:text-black hover:bg-zinc-100 transition shrink-0">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Scrollable Side Navigation -->
            <nav class="flex-1 overflow-y-auto px-2.5 py-3 space-y-4">
                <?php foreach ($navSections as $sectionName => $items): ?>
                    <div>
                        <div class="sidebar-section-title px-2.5 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-500">
                            <?= htmlspecialchars($sectionName) ?>
                        </div>
                        <div class="space-y-0.5">
                            <?php foreach ($items as $item): ?>
                                <a href="<?= htmlspecialchars($item['url']) ?>" title="<?= htmlspecialchars($item['label']) ?>"
                                   class="sidebar-nav-link flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-xs transition <?= $item['active'] ? 'bg-black text-white font-semibold shadow-sm' : 'text-zinc-700 hover:text-black hover:bg-zinc-100' ?>">
                                    <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-4 h-4 shrink-0 <?= $item['active'] ? 'text-blue-400' : 'text-zinc-500' ?>"></i>
                                    <span class="sidebar-label truncate"><?= htmlspecialchars($item['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </nav>

            <!-- Sidebar Footer User Info -->
            <div class="p-3 border-t border-zinc-200 shrink-0 bg-zinc-50">
                <div class="sidebar-footer-user flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-black text-white flex items-center justify-center text-[11px] font-bold shrink-0 shadow-sm">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="sidebar-user-meta min-w-0 leading-tight">
                            <span class="block text-xs font-semibold text-black truncate"><?= htmlspecialchars($user['name']) ?></span>
                            <span class="block text-[10px] text-blue-600 font-mono font-medium uppercase"><?= htmlspecialchars(strtoupper($user['role'])) ?></span>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/logout.php" title="Sign Out" class="sidebar-label p-1.5 rounded-lg text-zinc-500 hover:text-black hover:bg-zinc-200/70 transition shrink-0">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Right Content Column -->
        <div id="app-main-shell" class="flex-1 flex flex-col min-w-0">
            <!-- Slim White Top Utility Bar -->
            <header class="no-print bg-white border-b border-zinc-200 sticky top-0 z-30 h-14 flex items-center shrink-0">
                <div class="w-full px-4 sm:px-6 flex items-center justify-between gap-3">
                    <!-- Left: Menu Triggers + Page Title -->
                    <div class="flex items-center gap-2.5 min-w-0">
                        <button type="button" onclick="toggleMobileSidebar(true)" title="Open Menu"
                            class="md:hidden inline-flex items-center justify-center p-1.5 -ml-1 rounded-lg text-zinc-700 hover:text-black hover:bg-zinc-100 transition">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <button type="button" onclick="toggleDesktopSidebar()" title="Toggle Side Menu"
                            class="hidden md:inline-flex items-center justify-center p-1.5 -ml-1 rounded-lg text-zinc-600 hover:text-black hover:bg-zinc-100 transition">
                            <i data-lucide="menu" class="w-4 h-4"></i>
                        </button>
                        <a href="<?= BASE_URL ?>" class="md:hidden flex items-center gap-2 shrink-0">
                            <?= renderCompanyLogo('navbar') ?>
                        </a>
                        <div class="min-w-0 truncate flex items-center gap-2">
                            <span class="hidden sm:inline-block w-1.5 h-1.5 rounded-full bg-blue-600 shrink-0"></span>
                            <span class="text-xs font-bold text-black truncate block"><?= htmlspecialchars($page_title) ?></span>
                        </div>
                    </div>

                    <!-- Right: Quick Actions & Account -->
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="<?= BASE_URL ?>/calculator.php" title="Loan Calculator"
                            class="p-1.5 rounded-lg transition <?= $isCalculator ? 'bg-black text-white' : 'text-zinc-600 hover:text-black hover:bg-zinc-100' ?>">
                            <i data-lucide="calculator" class="w-4 h-4"></i>
                        </a>

                        <button type="button" onclick="promptPwaInstall()"
                            class="pwa-install-trigger hidden inline-flex items-center gap-1 px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-medium rounded-lg transition shadow-sm whitespace-nowrap">
                            <i data-lucide="download" class="w-3 h-3"></i> Install App
                        </button>

                        <div class="text-right hidden sm:block leading-tight pl-2 border-l border-zinc-200">
                            <span class="block text-xs font-semibold text-black"><?= htmlspecialchars($user['name']) ?></span>
                            <span class="inline-block text-[10px] text-white bg-black px-1.5 py-0.2 rounded font-mono uppercase"><?= htmlspecialchars(strtoupper($user['role'])) ?></span>
                        </div>
                        <a href="<?= BASE_URL ?>/logout.php" title="Sign Out" class="p-1.5 rounded-lg text-zinc-500 hover:text-black hover:bg-zinc-100 transition">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-5">
<?php else: ?>
    <!-- Guest Minimal Header -->
    <header class="no-print bg-white border-b border-zinc-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
            <a href="<?= BASE_URL ?>" class="flex items-center gap-2.5">
                <?= renderCompanyLogo('navbar') ?>
                <div class="leading-none">
                    <span class="text-xs font-bold tracking-wide uppercase text-black">EL-FAITH</span>
                    <span class="text-[10px] block text-blue-600 font-medium uppercase tracking-wider mt-0.5"><?= htmlspecialchars(BRANCH_NAME) ?></span>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/login.php" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition">Sign In</a>
        </div>
    </header>
    <div id="app-main-shell" class="flex-1 flex flex-col min-w-0">
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-5">
<?php endif; ?>

                <!-- Flash Alert Messages -->
                <?php if ($flash): ?>
                    <?php
                        $bgColor = 'bg-blue-50 border-blue-200 text-blue-900';
                        $iconColor = 'text-blue-600';
                        $icon = 'info';
                        if ($flash['type'] === 'success') {
                            $bgColor = 'bg-blue-50 border-blue-200 text-blue-900';
                            $iconColor = 'text-blue-600';
                            $icon = 'check-circle';
                        } elseif ($flash['type'] === 'danger' || $flash['type'] === 'error') {
                            $bgColor = 'bg-rose-50 border-rose-200 text-rose-900';
                            $iconColor = 'text-rose-600';
                            $icon = 'alert-triangle';
                        } elseif ($flash['type'] === 'warning') {
                            $bgColor = 'bg-zinc-900 border-black text-white';
                            $iconColor = 'text-blue-400';
                            $icon = 'alert-circle';
                        }
                    ?>
                    <div class="no-print mb-5 rounded-lg border p-3.5 <?= $bgColor ?> flex items-start gap-2.5 text-xs shadow-sm">
                        <i data-lucide="<?= $icon ?>" class="w-4 h-4 shrink-0 <?= $iconColor ?> mt-0.5"></i>
                        <div class="font-medium flex-1">
                            <?= htmlspecialchars($flash['message']) ?>
                        </div>
                    </div>
                <?php endif; ?>
