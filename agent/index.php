<?php
/**
 * Agent Daily Route & Collection List - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAgent();

$agentId = currentUserId();
$page_title = "Today's Route";

// 1. Fetch collections summary for this agent today
$stmtToday = $pdo->prepare("
    SELECT 
        COALESCE(SUM(amount_pesewas), 0) AS total_today,
        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_pesewas ELSE 0 END), 0) AS cash_today,
        COALESCE(SUM(CASE WHEN payment_method = 'momo' THEN amount_pesewas ELSE 0 END), 0) AS momo_today,
        COUNT(id) AS count_today
    FROM payments 
    WHERE agent_id = ? AND status = 'recorded' AND DATE(collection_timestamp) = CURDATE()
");
$stmtToday->execute([$agentId]);
$todayStats = $stmtToday->fetch();

// 2. Fetch Overdue Installments for assigned customers
$stmtOverdue = $pdo->prepare("
    SELECT 
        rs.id AS schedule_id, rs.installment_number, rs.due_date, 
        rs.expected_amount_pesewas, rs.paid_amount_pesewas, rs.status AS schedule_status,
        (rs.expected_amount_pesewas - rs.paid_amount_pesewas) AS balance_due_pesewas,
        DATEDIFF(CURDATE(), rs.due_date) AS days_overdue,
        l.id AS loan_id, l.loan_code, l.installment_amount_pesewas, l.remaining_balance_pesewas,
        c.id AS customer_id, c.customer_code, c.full_name, c.phone, c.location_landmark, c.residential_address
    FROM repayment_schedule rs
    JOIN loans l ON rs.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    WHERE (l.agent_id = ? OR c.assigned_agent_id = ?)
      AND rs.due_date < CURDATE()
      AND rs.status IN ('pending', 'partially_paid', 'overdue')
      AND l.status IN ('active', 'overdue')
    ORDER BY rs.due_date ASC, c.full_name ASC
");
$stmtOverdue->execute([$agentId, $agentId]);
$overdueList = $stmtOverdue->fetchAll();

// 3. Fetch Due Today Installments for assigned customers
$stmtDueToday = $pdo->prepare("
    SELECT 
        rs.id AS schedule_id, rs.installment_number, rs.due_date, 
        rs.expected_amount_pesewas, rs.paid_amount_pesewas, rs.status AS schedule_status,
        (rs.expected_amount_pesewas - rs.paid_amount_pesewas) AS balance_due_pesewas,
        l.id AS loan_id, l.loan_code, l.installment_amount_pesewas, l.remaining_balance_pesewas,
        c.id AS customer_id, c.customer_code, c.full_name, c.phone, c.location_landmark, c.residential_address
    FROM repayment_schedule rs
    JOIN loans l ON rs.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    WHERE (l.agent_id = ? OR c.assigned_agent_id = ?)
      AND rs.due_date = CURDATE()
      AND rs.status IN ('pending', 'partially_paid')
      AND l.status IN ('active', 'overdue')
    ORDER BY c.full_name ASC
");
$stmtDueToday->execute([$agentId, $agentId]);
$dueTodayList = $stmtDueToday->fetchAll();

// 4. Calculate total expected collections for today
$totalExpectedToday = 0;
foreach ($dueTodayList as $row) {
    $totalExpectedToday += $row['balance_due_pesewas'];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Minimal Header Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-zinc-900 tracking-tight">Today's Route</h1>
                <p class="text-xs text-zinc-400 mt-0.5"><?= htmlspecialchars(currentUser()['name']) ?> &bull; <?= date('l, d F Y') ?></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= BASE_URL ?>/calculator.php" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-white border border-zinc-200 hover:bg-zinc-50 text-zinc-700 font-medium text-xs rounded-lg transition">
                    <i data-lucide="calculator" class="w-3.5 h-3.5 text-zinc-400"></i> Loan Calculator
                </a>
                <a href="<?= BASE_URL ?>/agent/collect.php" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Record Collection
                </a>
            </div>
        </div>

        <!-- Metric Grid (Clean Monochrome) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-zinc-100 text-xs">
            <div>
                <span class="text-[10px] font-medium text-zinc-400 uppercase tracking-wider block">Collected Today</span>
                <span class="text-lg font-bold text-zinc-900 mt-0.5 block"><?= formatMoney((int)$todayStats['total_today']) ?></span>
                <span class="text-[10px] text-zinc-400"><?= (int)$todayStats['count_today'] ?> receipts</span>
            </div>
            <div>
                <span class="text-[10px] font-medium text-zinc-400 uppercase tracking-wider block">Cash in Hand</span>
                <span class="text-lg font-bold text-zinc-900 mt-0.5 block"><?= formatMoney((int)$todayStats['cash_today']) ?></span>
                <span class="text-[10px] text-zinc-400">Office Hand-in</span>
            </div>
            <div>
                <span class="text-[10px] font-medium text-zinc-400 uppercase tracking-wider block">Mobile Money</span>
                <span class="text-lg font-bold text-zinc-900 mt-0.5 block"><?= formatMoney((int)$todayStats['momo_today']) ?></span>
                <span class="text-[10px] text-zinc-400">Electronic MoMo</span>
            </div>
            <div>
                <span class="text-[10px] font-medium text-zinc-400 uppercase tracking-wider block">Overdue</span>
                <span class="text-lg font-bold text-zinc-900 mt-0.5 block"><?= count($overdueList) ?></span>
                <span class="text-[10px] text-zinc-400">Pending recovery</span>
            </div>
        </div>
    </div>

    <!-- OVERDUE LIST (Minimalist) -->
    <?php if (!empty($overdueList)): ?>
        <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
                <span class="text-xs font-semibold text-zinc-900 uppercase tracking-wider">Overdue (<?= count($overdueList) ?>)</span>
                <span class="text-[11px] text-zinc-400 font-medium">Priority</span>
            </div>

            <div class="divide-y divide-zinc-100">
                <?php foreach ($overdueList as $item): ?>
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs hover:bg-zinc-50/50 transition">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-zinc-900"><?= htmlspecialchars($item['full_name']) ?></h3>
                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-zinc-100 text-zinc-700 font-mono">
                                    <?= $item['days_overdue'] ?>d late
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-zinc-400 text-[11px]">
                                <span class="font-mono text-zinc-700"><?= htmlspecialchars($item['loan_code']) ?></span>
                                <span>&bull;</span>
                                <span><?= htmlspecialchars($item['location_landmark']) ?></span>
                                <span>&bull;</span>
                                <a href="tel:<?= htmlspecialchars($item['phone']) ?>" class="font-mono text-zinc-600 hover:text-zinc-900">
                                    <?= htmlspecialchars($item['phone']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-zinc-100">
                            <div class="text-left sm:text-right">
                                <span class="text-sm font-bold text-zinc-900"><?= formatMoney((int)$item['balance_due_pesewas']) ?></span>
                                <span class="text-[10px] text-zinc-400 block">Bal: <?= formatMoney((int)$item['remaining_balance_pesewas']) ?></span>
                            </div>
                            <a href="<?= BASE_URL ?>/agent/collect.php?loan_id=<?= $item['loan_id'] ?>&customer_id=<?= $item['customer_id'] ?>&amount=<?= pesewasToGHS($item['balance_due_pesewas']) ?>" 
                               class="px-3 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs transition">
                                Collect
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- DUE TODAY LIST (Minimalist) -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <span class="text-xs font-semibold text-zinc-900 uppercase tracking-wider">Due Today (<?= count($dueTodayList) ?>)</span>
            <span class="text-xs text-zinc-500">
                Target: <strong><?= formatMoney($totalExpectedToday) ?></strong>
            </span>
        </div>

        <?php if (empty($dueTodayList)): ?>
            <div class="p-8 text-center text-zinc-400 text-xs">
                All scheduled route collections completed.
            </div>
        <?php else: ?>
            <div class="divide-y divide-zinc-100">
                <?php foreach ($dueTodayList as $item): ?>
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs hover:bg-zinc-50/50 transition">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-zinc-900"><?= htmlspecialchars($item['full_name']) ?></h3>
                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-zinc-100 text-zinc-600 font-mono">
                                    Inst #<?= $item['installment_number'] ?>
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-zinc-400 text-[11px]">
                                <span class="font-mono text-zinc-700"><?= htmlspecialchars($item['loan_code']) ?></span>
                                <span>&bull;</span>
                                <span><?= htmlspecialchars($item['location_landmark']) ?></span>
                                <span>&bull;</span>
                                <a href="tel:<?= htmlspecialchars($item['phone']) ?>" class="font-mono text-zinc-600 hover:text-zinc-900">
                                    <?= htmlspecialchars($item['phone']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-zinc-100">
                            <div class="text-left sm:text-right">
                                <span class="text-sm font-bold text-zinc-900"><?= formatMoney((int)$item['balance_due_pesewas']) ?></span>
                                <span class="text-[10px] text-zinc-400 block">Bal: <?= formatMoney((int)$item['remaining_balance_pesewas']) ?></span>
                            </div>
                            <a href="<?= BASE_URL ?>/agent/collect.php?loan_id=<?= $item['loan_id'] ?>&customer_id=<?= $item['customer_id'] ?>&amount=<?= pesewasToGHS($item['balance_due_pesewas']) ?>" 
                               class="px-3 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs transition">
                                Collect
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Hydrate offline cache with today's assigned route borrowers
    const routeLoans = [
        <?php foreach (array_merge($dueTodayList, $overdueList) as $r): ?>
        {
            id: <?= (int)$r['loan_id'] ?>,
            loan_code: <?= json_encode($r['loan_code']) ?>,
            customer_id: <?= (int)$r['customer_id'] ?>,
            customer_name: <?= json_encode($r['full_name']) ?>,
            customer_phone: <?= json_encode($r['phone']) ?>,
            landmark: <?= json_encode($r['location_landmark']) ?>,
            installment: <?= pesewasToGHS($r['installment_amount_pesewas']) ?>,
            balance: <?= pesewasToGHS($r['remaining_balance_pesewas']) ?>,
            balance_pesewas: <?= (int)$r['remaining_balance_pesewas'] ?>
        },
        <?php endforeach; ?>
    ];

    if (routeLoans.length > 0 && typeof OfflineStorage !== 'undefined') {
        OfflineStorage.cacheLoans(routeLoans);
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
