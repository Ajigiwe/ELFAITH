<?php
/**
 * Executive Admin Dashboard - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Executive Dashboard";

// 1. Collections Today
$stmtToday = $pdo->query("
    SELECT 
        COALESCE(SUM(amount_pesewas), 0) AS total_today,
        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_pesewas ELSE 0 END), 0) AS cash_today,
        COALESCE(SUM(CASE WHEN payment_method = 'momo' THEN amount_pesewas ELSE 0 END), 0) AS momo_today,
        COUNT(id) AS count_today
    FROM payments 
    WHERE status = 'recorded' AND DATE(collection_timestamp) = CURDATE()
");
$todayStats = $stmtToday->fetch();

// 2. Expected Collections Today (from repayment_schedule)
$stmtExpected = $pdo->query("
    SELECT COALESCE(SUM(expected_amount_pesewas), 0) AS expected_today
    FROM repayment_schedule
    WHERE due_date = CURDATE()
");
$expectedToday = (int)$stmtExpected->fetchColumn();

// 3. Outstanding Portfolio Total
$stmtPort = $pdo->query("
    SELECT 
        COALESCE(SUM(remaining_balance_pesewas), 0) AS total_outstanding,
        COALESCE(SUM(principal_pesewas), 0) AS total_principal_disbursed,
        COUNT(id) AS active_loans_count
    FROM loans
    WHERE status IN ('active', 'overdue')
");
$portfolioStats = $stmtPort->fetch();

// 4. Overdue Amount & Count
$stmtOverdue = $pdo->query("
    SELECT 
        COALESCE(SUM(expected_amount_pesewas - paid_amount_pesewas), 0) AS total_overdue,
        COUNT(id) AS overdue_installments_count
    FROM repayment_schedule
    WHERE due_date < CURDATE() AND status IN ('pending', 'partially_paid', 'overdue')
");
$overdueStats = $stmtOverdue->fetch();

// 5. Portfolio at Risk (PAR 30+ days)
$stmtPar = $pdo->query("
    SELECT COALESCE(SUM(DISTINCT l.remaining_balance_pesewas), 0) AS par_amount
    FROM loans l
    JOIN repayment_schedule rs ON l.id = rs.loan_id
    WHERE rs.due_date < (CURDATE() - INTERVAL 30 DAY)
      AND rs.status IN ('pending', 'partially_paid', 'overdue')
      AND l.status IN ('active', 'overdue')
");
$parAmount = (int)$stmtPar->fetchColumn();
$totalOutstanding = (int)$portfolioStats['total_outstanding'];
$parPercentage = $totalOutstanding > 0 ? round(($parAmount / $totalOutstanding) * 100, 1) : 0.0;

// 6. Recent Payments Feed
$recentPayments = $pdo->query("
    SELECT p.*, c.full_name AS customer_name, l.loan_code, u.name AS agent_name
    FROM payments p
    JOIN customers c ON p.customer_id = c.id
    JOIN loans l ON p.loan_id = l.id
    JOIN users u ON p.agent_id = u.id
    ORDER BY p.collection_timestamp DESC
    LIMIT 6
")->fetchAll();

// 7. Per-Agent Performance Today
$agentPerformance = $pdo->query("
    SELECT u.id, u.name, u.staff_code,
           COALESCE(SUM(p.amount_pesewas), 0) AS collected_today,
           COALESCE(SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount_pesewas ELSE 0 END), 0) AS cash_today,
           COALESCE(SUM(CASE WHEN p.payment_method = 'momo' THEN p.amount_pesewas ELSE 0 END), 0) AS momo_today,
           COUNT(p.id) AS collections_count
    FROM users u
    LEFT JOIN payments p ON u.id = p.agent_id AND p.status = 'recorded' AND DATE(p.collection_timestamp) = CURDATE()
    WHERE u.role = 'agent' AND u.status = 'active'
    GROUP BY u.id, u.name, u.staff_code
    ORDER BY collected_today DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-extrabold text-slate-950 tracking-tight">Executive Dashboard</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
                </span>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-1"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= date('l, d F Y') ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= BASE_URL ?>/calculator.php" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-800 font-semibold text-xs rounded-xl shadow-xs transition">
                <i data-lucide="calculator" class="w-3.5 h-3.5 text-slate-500"></i> Loan Calculator
            </a>
            <a href="<?= BASE_URL ?>/admin/loans.php?action=new" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-semibold text-xs rounded-xl shadow-xs transition">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Issue Loan
            </a>
            <a href="<?= BASE_URL ?>/admin/reconciliation.php" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-900 hover:bg-black text-white font-semibold text-xs rounded-xl shadow-xs transition">
                <i data-lucide="scale" class="w-3.5 h-3.5 text-slate-400"></i> Reconciliation
            </a>
        </div>
    </div>

    <!-- 4 Modern Fintech KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Collected Today -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-3.5 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Collected Today</span>
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center ring-1 ring-blue-600/10">
                    <i data-lucide="wallet" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums">
                    <?= formatMoney((int)$todayStats['total_today']) ?>
                </div>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between pt-3 border-t border-slate-100">
                <span>Cash: <strong class="text-slate-900 font-semibold"><?= formatMoney((int)$todayStats['cash_today']) ?></strong></span>
                <span class="text-slate-300">&bull;</span>
                <span>MoMo: <strong class="text-slate-900 font-semibold"><?= formatMoney((int)$todayStats['momo_today']) ?></strong></span>
            </div>
        </div>

        <!-- Expected Today -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-3.5 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Expected Today</span>
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center ring-1 ring-indigo-600/10">
                    <i data-lucide="calendar-check" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums">
                    <?= formatMoney($expectedToday) ?>
                </div>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between pt-3 border-t border-slate-100">
                <span>Realization Rate:</span>
                <?php $realizedRate = $expectedToday > 0 ? round(($todayStats['total_today'] / $expectedToday) * 100, 1) : 0; ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold text-[10px] <?= $realizedRate >= 80 ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' ?>">
                    <?= $realizedRate ?>%
                </span>
            </div>
        </div>

        <!-- Active Portfolio -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-3.5 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Active Portfolio</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center ring-1 ring-emerald-600/10">
                    <i data-lucide="briefcase" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums">
                    <?= formatMoney((int)$portfolioStats['total_outstanding']) ?>
                </div>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between pt-3 border-t border-slate-100">
                <span>Active Loans:</span>
                <span class="font-bold text-slate-900"><?= (int)$portfolioStats['active_loans_count'] ?> active</span>
            </div>
        </div>

        <!-- Total Overdue -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-3.5 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Overdue</span>
                <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center ring-1 ring-rose-600/10">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                </div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums">
                    <?= formatMoney((int)$overdueStats['total_overdue']) ?>
                </div>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between pt-3 border-t border-slate-100">
                <span>PAR 30+: <strong class="text-rose-600 font-semibold"><?= $parPercentage ?>%</strong></span>
                <span class="text-slate-400"><?= (int)$overdueStats['overdue_installments_count'] ?> items</span>
            </div>
        </div>
    </div>

    <!-- Agent Daily Collection Leaderboard -->
    <div class="bg-white rounded-2xl border border-slate-200/90 overflow-hidden shadow-xs">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <i data-lucide="trophy" class="w-4 h-4 text-blue-600"></i> Field Agent Collections Today
            </h2>
            <a href="<?= BASE_URL ?>/admin/reconciliation.php" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition">
                Reconciliation &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4 font-semibold">Agent</th>
                        <th class="py-3 px-4 font-semibold">Staff Code</th>
                        <th class="py-3 px-4 text-center font-semibold">Collections</th>
                        <th class="py-3 px-4 text-right font-semibold">Cash</th>
                        <th class="py-3 px-4 text-right font-semibold">MoMo</th>
                        <th class="py-3 px-4 text-right font-semibold">Total Amount</th>
                        <th class="py-3 px-4 text-center font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($agentPerformance as $ag): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-4 font-semibold text-slate-900">
                                <?= htmlspecialchars($ag['name']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-600 text-[11px]">
                                <?= htmlspecialchars($ag['staff_code']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-center font-medium text-slate-800">
                                <span class="inline-block px-2 py-0.5 rounded-full bg-slate-100 font-mono text-[11px]"><?= (int)$ag['collections_count'] ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-slate-800 tabular-nums">
                                <?= formatMoney((int)$ag['cash_today']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-slate-800 tabular-nums">
                                <?= formatMoney((int)$ag['momo_today']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-950 tabular-nums">
                                <?= formatMoney((int)$ag['collected_today']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a href="<?= BASE_URL ?>/admin/reconciliation.php?agent_id=<?= $ag['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                    Reconcile
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Collections Feed -->
    <div class="bg-white rounded-2xl border border-slate-200/90 overflow-hidden shadow-xs">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <i data-lucide="clock" class="w-4 h-4 text-blue-600"></i> Recent Payment Transactions
            </h2>
            <a href="<?= BASE_URL ?>/admin/collections.php" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition">
                All Transactions &rarr;
            </a>
        </div>

        <div class="divide-y divide-slate-100 text-xs">
            <?php foreach ($recentPayments as $rp): ?>
                <div class="p-4 px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors">
                    <div>
                        <span class="font-bold text-slate-950 block text-xs"><?= htmlspecialchars($rp['customer_name']) ?></span>
                        <span class="text-slate-500 text-[11px] flex items-center gap-2 mt-0.5">
                            <span class="font-mono text-slate-700"><?= htmlspecialchars($rp['receipt_number']) ?></span>
                            <span>&bull;</span>
                            <span class="font-mono text-slate-700"><?= htmlspecialchars($rp['loan_code']) ?></span>
                            <span>&bull;</span>
                            <span><?= htmlspecialchars($rp['agent_name']) ?></span>
                            <span>&bull;</span>
                            <span class="uppercase font-mono text-[10px] font-bold px-1.5 py-0.2 rounded <?= $rp['payment_method'] === 'cash' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' ?>">
                                <?= htmlspecialchars($rp['payment_method']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-3">
                        <div class="text-left sm:text-right">
                            <span class="font-bold block text-slate-950 text-sm tabular-nums">
                                <?= formatMoney((int)$rp['amount_pesewas']) ?>
                            </span>
                            <span class="text-[10px] text-slate-400 block"><?= formatDateTime($rp['collection_timestamp']) ?></span>
                        </div>
                        <a href="<?= BASE_URL ?>/receipt.php?id=<?= $rp['id'] ?>" class="p-2 rounded-xl border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50/50 transition shadow-xs" title="View Receipt">
                            <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
