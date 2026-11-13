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
      AND c.deleted_at IS NULL
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
      AND c.deleted_at IS NULL
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
    <!-- Header Card -->
    <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-4 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold text-slate-950 tracking-tight">Today's Field Route</h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active Route
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1"><?= htmlspecialchars(currentUser()['name']) ?> &bull; <?= date('l, d F Y') ?></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?= BASE_URL ?>/calculator.php" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-800 font-semibold text-xs rounded-xl shadow-xs transition">
                    <i data-lucide="calculator" class="w-3.5 h-3.5 text-slate-500"></i> Loan Calculator
                </a>
                <a href="<?= BASE_URL ?>/agent/collect.php" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs rounded-xl shadow-xs transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Record Collection
                </a>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-slate-100 text-xs">
            <div class="bg-slate-50/70 border border-slate-200/70 rounded-xl p-3.5 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Collected Today</span>
                    <i data-lucide="wallet" class="w-3.5 h-3.5 text-blue-600"></i>
                </div>
                <span class="text-lg font-extrabold text-slate-950 block tabular-nums"><?= formatMoney((int)$todayStats['total_today']) ?></span>
                <span class="text-[10px] text-slate-500"><?= (int)$todayStats['count_today'] ?> receipts</span>
            </div>
            <div class="bg-slate-50/70 border border-slate-200/70 rounded-xl p-3.5 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Cash in Hand</span>
                    <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-600"></i>
                </div>
                <span class="text-lg font-extrabold text-slate-950 block tabular-nums"><?= formatMoney((int)$todayStats['cash_today']) ?></span>
                <span class="text-[10px] text-emerald-700 font-medium">Office Hand-in</span>
            </div>
            <div class="bg-slate-50/70 border border-slate-200/70 rounded-xl p-3.5 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Mobile Money</span>
                    <i data-lucide="smartphone" class="w-3.5 h-3.5 text-indigo-600"></i>
                </div>
                <span class="text-lg font-extrabold text-slate-950 block tabular-nums"><?= formatMoney((int)$todayStats['momo_today']) ?></span>
                <span class="text-[10px] text-indigo-700 font-medium">Electronic MoMo</span>
            </div>
            <div class="bg-slate-50/70 border border-slate-200/70 rounded-xl p-3.5 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Overdue Items</span>
                    <i data-lucide="alert-circle" class="w-3.5 h-3.5 text-rose-600"></i>
                </div>
                <span class="text-lg font-extrabold text-slate-950 block tabular-nums"><?= count($overdueList) ?></span>
                <span class="text-[10px] text-rose-600 font-medium">Pending recovery</span>
            </div>
        </div>
    </div>

    <!-- OVERDUE LIST -->
    <?php if (!empty($overdueList)): ?>
        <div class="bg-white rounded-2xl border border-slate-200/90 overflow-hidden shadow-xs">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i> Overdue Installments (<?= count($overdueList) ?>)
                </span>
                <span class="text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200/60 px-2 py-0.5 rounded-full">High Priority</span>
            </div>

            <div class="divide-y divide-slate-100">
                <?php foreach ($overdueList as $item): ?>
                    <div class="p-4 px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs hover:bg-slate-50/70 transition-colors">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-950 text-xs"><?= htmlspecialchars($item['full_name']) ?></h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-800 font-mono font-bold">
                                    <?= $item['days_overdue'] ?>d late
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-slate-500 text-[11px]">
                                <span class="font-mono text-slate-700 font-semibold"><?= htmlspecialchars($item['loan_code']) ?></span>
                                <span>&bull;</span>
                                <span><?= htmlspecialchars($item['location_landmark']) ?></span>
                                <span>&bull;</span>
                                <a href="tel:<?= htmlspecialchars($item['phone']) ?>" class="font-mono text-blue-600 hover:text-blue-700 font-semibold">
                                    <?= htmlspecialchars($item['phone']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                            <div class="text-left sm:text-right">
                                <span class="text-sm font-bold text-slate-950 block tabular-nums"><?= formatMoney((int)$item['balance_due_pesewas']) ?></span>
                                <span class="text-[10px] text-slate-400 block tabular-nums">Bal: <?= formatMoney((int)$item['remaining_balance_pesewas']) ?></span>
                            </div>
                            <a href="<?= BASE_URL ?>/agent/collect.php?loan_id=<?= $item['loan_id'] ?>&customer_id=<?= $item['customer_id'] ?>&amount=<?= pesewasToGHS($item['balance_due_pesewas']) ?>" 
                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs transition shadow-xs">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Collect
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- DUE TODAY LIST -->
    <div class="bg-white rounded-2xl border border-slate-200/90 overflow-hidden shadow-xs">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i> Scheduled Today (<?= count($dueTodayList) ?>)
            </span>
            <span class="text-xs text-slate-600">
                Target: <strong class="text-blue-700 font-bold tabular-nums"><?= formatMoney($totalExpectedToday) ?></strong>
            </span>
        </div>

        <?php if (empty($dueTodayList)): ?>
            <div class="p-8 text-center text-slate-400 text-xs font-medium">
                All scheduled route collections completed for today.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($dueTodayList as $item): ?>
                    <div class="p-4 px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs hover:bg-slate-50/70 transition-colors">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-950 text-xs"><?= htmlspecialchars($item['full_name']) ?></h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-50 text-blue-700 border border-blue-200/60 font-mono font-semibold">
                                    Inst #<?= $item['installment_number'] ?>
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-slate-500 text-[11px]">
                                <span class="font-mono text-slate-700 font-semibold"><?= htmlspecialchars($item['loan_code']) ?></span>
                                <span>&bull;</span>
                                <span><?= htmlspecialchars($item['location_landmark']) ?></span>
                                <span>&bull;</span>
                                <a href="tel:<?= htmlspecialchars($item['phone']) ?>" class="font-mono text-blue-600 hover:text-blue-700 font-semibold">
                                    <?= htmlspecialchars($item['phone']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                            <div class="text-left sm:text-right">
                                <span class="text-sm font-bold text-slate-950 block tabular-nums"><?= formatMoney((int)$item['balance_due_pesewas']) ?></span>
                                <span class="text-[10px] text-slate-400 block tabular-nums">Bal: <?= formatMoney((int)$item['remaining_balance_pesewas']) ?></span>
                            </div>
                            <a href="<?= BASE_URL ?>/agent/collect.php?loan_id=<?= $item['loan_id'] ?>&customer_id=<?= $item['customer_id'] ?>&amount=<?= pesewasToGHS($item['balance_due_pesewas']) ?>" 
                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs transition shadow-xs">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Collect
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
