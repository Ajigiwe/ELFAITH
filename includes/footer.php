    </main>

    <!-- Mobile Bottom Navigation Bar (Minimalist) -->
    <?php if ($user): ?>
    <?php
        $currPage = $_SERVER['PHP_SELF'];
        $isRoute = strpos($currPage, 'agent/index.php') !== false;
        $isCollect = strpos($currPage, 'collect.php') !== false;
        $isClients = strpos($currPage, 'customer') !== false;
        $isHistory = strpos($currPage, 'history.php') !== false;
        $isQueue = strpos($currPage, 'offline_queue.php') !== false;

        $isAdminDash = strpos($currPage, 'admin/index.php') !== false;
        $isAdminPay = strpos($currPage, 'collections.php') !== false;
        $isAdminCust = strpos($currPage, 'customer') !== false;
        $isAdminRecon = strpos($currPage, 'reconciliation.php') !== false;
        $isCalculatorNav = strpos($currPage, 'calculator.php') !== false;
    ?>
    <nav class="md:hidden no-print fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200/90 z-40 py-2 shadow-[0_-4px_20px_rgba(15,23,42,0.06)]">
        <div class="flex items-center justify-around text-center max-w-md mx-auto px-2">
            <?php if ($role === 'agent'): ?>
                <!-- Route Tab -->
                <a href="<?= BASE_URL ?>/agent/index.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isRoute ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="map-pin" class="w-5 h-5 <?= $isRoute ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isRoute ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Route</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isRoute ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Collect Tab -->
                <a href="<?= BASE_URL ?>/agent/collect.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isCollect ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="plus-circle" class="w-5 h-5 <?= $isCollect ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isCollect ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Collect</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isCollect ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Clients Tab -->
                <a href="<?= BASE_URL ?>/agent/my_customers.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isClients ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="users" class="w-5 h-5 <?= $isClients ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isClients ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Clients</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isClients ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- History Tab -->
                <a href="<?= BASE_URL ?>/agent/history.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isHistory ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="receipt" class="w-5 h-5 <?= $isHistory ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isHistory ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">History</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isHistory ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Queue Tab -->
                <a href="<?= BASE_URL ?>/agent/offline_queue.php" class="flex flex-col items-center justify-center flex-1 py-1 relative transition-all duration-150 <?= $isQueue ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <div class="relative">
                        <i data-lucide="cloud-sync" class="w-5 h-5 <?= $isQueue ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                        <span id="mobile-offline-badge" class="hidden absolute -top-1 -right-1 w-2.5 h-2.5 bg-blue-600 rounded-full ring-2 ring-white"></span>
                    </div>
                    <span class="text-[10px] mt-1 <?= $isQueue ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Queue</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isQueue ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>
            <?php else: ?>
                <!-- Admin Dashboard Tab -->
                <a href="<?= BASE_URL ?>/admin/index.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isAdminDash ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 <?= $isAdminDash ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminDash ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Overview</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isAdminDash ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Payments Tab -->
                <a href="<?= BASE_URL ?>/admin/collections.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isAdminPay ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="wallet" class="w-5 h-5 <?= $isAdminPay ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminPay ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Ledger</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isAdminPay ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Clients Tab -->
                <a href="<?= BASE_URL ?>/admin/customers.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isAdminCust ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="users" class="w-5 h-5 <?= $isAdminCust ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminCust ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Clients</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isAdminCust ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Reconcile Tab -->
                <a href="<?= BASE_URL ?>/admin/reconciliation.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isAdminRecon ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="scale" class="w-5 h-5 <?= $isAdminRecon ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminRecon ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Reconcile</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isAdminRecon ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Calculator Tab -->
                <a href="<?= BASE_URL ?>/calculator.php" class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-150 <?= $isCalculatorNav ? 'text-blue-600' : 'text-slate-400 hover:text-slate-800' ?>">
                    <i data-lucide="calculator" class="w-5 h-5 <?= $isCalculatorNav ? 'stroke-[2.2]' : 'stroke-[1.75]' ?>"></i>
                    <span class="text-[10px] mt-1 <?= $isCalculatorNav ? 'font-bold text-blue-600' : 'font-medium text-slate-500' ?>">Calc</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-0.5 <?= $isCalculatorNav ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Standard Footer -->
    <footer class="no-print bg-white border-t border-zinc-200 mt-auto py-5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-zinc-500 gap-2">
            <div>
                &copy; <?= date('Y') ?> <span class="text-black font-semibold"><?= htmlspecialchars(COMPANY_NAME) ?></span> &bull; <span class="text-blue-600 font-medium"><?= htmlspecialchars(BRANCH_NAME) ?></span>
            </div>
            <div class="flex items-center gap-3 text-[11px]">
                <span>Currency: <strong class="text-blue-700"><?= htmlspecialchars(CURRENCY_CODE) ?> (<?= htmlspecialchars(safeCurrencySymbol()) ?>)</strong></span>
                <span>&bull;</span>
                <span>Integer-safe Ledger</span>
            </div>
        </div>
    </footer>
    <!-- sys: <?= htmlspecialchars(APP_VERSION) ?> raw=<?= defined('CURRENCY_SYMBOL') ? bin2hex((string)CURRENCY_SYMBOL) : 'none' ?> sym=<?= bin2hex(safeCurrencySymbol()) ?> -->
        </div><!-- /#app-main-shell -->
<?php if ($user): ?>
    </div><!-- /.flex-1 flex min-h-0 -->
<?php endif; ?>

    <!-- App JavaScript, Sidebar Controls & Offline Sync Manager -->
    <script>
        function toggleDesktopSidebar() {
            const root = document.documentElement;
            const isCollapsed = root.classList.toggle('sidebar-is-collapsed');
            try {
                localStorage.setItem('elfaith_sidebar_collapsed', isCollapsed ? '1' : '0');
            } catch (e) {}
        }

        function toggleMobileSidebar(forceOpen) {
            const sidebar = document.getElementById('app-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            if (!sidebar || !backdrop) return;

            const shouldOpen = typeof forceOpen === 'boolean'
                ? forceOpen
                : sidebar.classList.contains('-translate-x-full');

            if (shouldOpen) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        // Close mobile drawer on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                toggleMobileSidebar(false);
            }
        });

        // Initialize Lucide Icons
        if (window.lucide) {
            lucide.createIcons();
        }

        // Initialize Network Monitor & Offline UI
        initNetworkMonitor('<?= BASE_URL ?>');
    </script>
</body>
</html>

