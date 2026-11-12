<?php
/**
 * Agent Assigned Customers View - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAgent();

$agentId = currentUserId();
$page_title = "My Borrowers";

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT c.*, 
           l.id AS active_loan_id, l.loan_code, l.principal_pesewas, 
           l.remaining_balance_pesewas, l.status AS loan_status
    FROM customers c
    LEFT JOIN loans l ON c.id = l.customer_id AND l.status IN ('active', 'overdue')
    WHERE (c.assigned_agent_id = ? OR c.created_by = ?)
";

$params = [$agentId, $agentId];

if (!empty($search)) {
    $sql .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ? OR c.location_landmark LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY c.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">My Borrowers</h1>
            <p class="text-xs text-slate-400 mt-0.5">Assigned route directory & balances</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/agent/customer_new.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs transition">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Register Borrower
            </a>
        </div>
    </div>

    <!-- Search Bar (Minimal) -->
    <div class="bg-white rounded-xl border border-slate-200 p-3">
        <form method="GET" class="flex items-center gap-2">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                </span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                    class="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                    placeholder="Search by name, phone, landmark, or customer ID...">
            </div>
            <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="<?= BASE_URL ?>/agent/my_customers.php" class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-slate-600 rounded-lg text-xs font-medium">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Customer Cards Grid (Minimal) -->
    <?php if (empty($customers)): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-400 text-xs">
            No borrowers found matching your criteria.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($customers as $c): ?>
                <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-mono text-slate-400 block uppercase"><?= htmlspecialchars($c['customer_code']) ?></span>
                            <h2 class="text-sm font-bold text-slate-900 mt-0.5"><?= htmlspecialchars($c['full_name']) ?></h2>
                            <p class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                <i data-lucide="map-pin" class="w-3 h-3 text-slate-400"></i>
                                <?= htmlspecialchars($c['location_landmark']) ?>
                            </p>
                        </div>
                        <div>
                            <a href="tel:<?= htmlspecialchars($c['phone']) ?>" class="w-7 h-7 rounded-lg bg-purple-50 text-slate-700 flex items-center justify-center hover:bg-purple-100 transition" title="Call Borrower">
                                <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Phone</span>
                            <span class="font-mono text-slate-800 text-[11px]"><?= htmlspecialchars($c['phone']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase">Ghana Card</span>
                            <span class="font-mono text-slate-700 text-[11px]"><?= htmlspecialchars($c['id_number']) ?></span>
                        </div>
                        <div class="col-span-2 pt-1 border-t border-slate-200/60">
                            <span class="text-slate-400 block text-[10px] uppercase">Guarantor</span>
                            <span class="text-slate-700"><?= htmlspecialchars($c['guarantor_name']) ?> (<?= htmlspecialchars($c['guarantor_relation']) ?> &bull; <?= htmlspecialchars($c['guarantor_phone']) ?>)</span>
                        </div>
                    </div>

                    <!-- Active Loan Status -->
                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                        <div>
                            <?php if ($c['active_loan_id']): ?>
                                <span class="text-[10px] text-slate-400 uppercase block">Active: <?= htmlspecialchars($c['loan_code']) ?></span>
                                <span class="font-semibold text-slate-900">
                                    Bal: <?= formatMoney((int)$c['remaining_balance_pesewas']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400 text-xs italic">No active loan</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($c['active_loan_id']): ?>
                            <a href="<?= BASE_URL ?>/agent/collect.php?loan_id=<?= $c['active_loan_id'] ?>&customer_id=<?= $c['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs transition">
                                Collect
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
