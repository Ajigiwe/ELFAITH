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
    <nav class="md:hidden no-print fixed bottom-0 left-0 right-0 bg-white border-t border-zinc-200 z-40 py-1.5 shadow-sm">
        <div class="flex items-center justify-around text-center max-w-md mx-auto">
            <?php if ($role === 'agent'): ?>
                <!-- Route Tab -->
                <a href="<?= BASE_URL ?>/agent/index.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isRoute ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="map-pin" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isRoute ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Route</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isRoute ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Collect Tab -->
                <a href="<?= BASE_URL ?>/agent/collect.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isCollect ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="plus-circle" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isCollect ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Collect</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isCollect ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Clients Tab -->
                <a href="<?= BASE_URL ?>/agent/my_customers.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isClients ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isClients ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Clients</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isClients ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- History Tab -->
                <a href="<?= BASE_URL ?>/agent/history.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isHistory ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="receipt" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isHistory ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">History</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isHistory ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Queue Tab -->
                <a href="<?= BASE_URL ?>/agent/offline_queue.php" class="flex flex-col items-center justify-center flex-1 py-1 relative transition <?= $isQueue ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <div class="relative">
                        <i data-lucide="cloud-sync" class="w-5 h-5"></i>
                        <span id="mobile-offline-badge" class="hidden absolute -top-1 -right-1 w-2 h-2 bg-zinc-900 rounded-full"></span>
                    </div>
                    <span class="text-[10px] mt-1 <?= $isQueue ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Queue</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isQueue ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>
            <?php else: ?>
                <!-- Admin Dashboard Tab -->
                <a href="<?= BASE_URL ?>/admin/index.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isAdminDash ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminDash ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Dashboard</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isAdminDash ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Payments Tab -->
                <a href="<?= BASE_URL ?>/admin/collections.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isAdminPay ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="wallet" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminPay ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Payments</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isAdminPay ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Clients Tab -->
                <a href="<?= BASE_URL ?>/admin/customers.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isAdminCust ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminCust ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Clients</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isAdminCust ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Reconcile Tab -->
                <a href="<?= BASE_URL ?>/admin/reconciliation.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isAdminRecon ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="scale" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isAdminRecon ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Reconcile</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isAdminRecon ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>

                <!-- Admin Calculator Tab -->
                <a href="<?= BASE_URL ?>/calculator.php" class="flex flex-col items-center justify-center flex-1 py-1 transition <?= $isCalculatorNav ? 'text-zinc-900' : 'text-zinc-400 hover:text-zinc-700' ?>">
                    <i data-lucide="calculator" class="w-5 h-5"></i>
                    <span class="text-[10px] mt-1 <?= $isCalculatorNav ? 'font-semibold text-zinc-900' : 'text-zinc-400' ?>">Calc</span>
                    <span class="w-1 h-1 rounded-full bg-zinc-900 mt-0.5 <?= $isCalculatorNav ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Standard Minimal Footer -->
    <footer class="no-print bg-white border-t border-zinc-200 mt-auto py-5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-zinc-400 gap-2">
            <div>
                &copy; <?= date('Y') ?> <?= htmlspecialchars(COMPANY_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_NAME) ?>
            </div>
            <div class="flex items-center gap-3 text-[11px]">
                <span>Currency: <strong><?= htmlspecialchars(CURRENCY_CODE) ?> (<?= htmlspecialchars(safeCurrencySymbol()) ?>)</strong></span>
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

