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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">Overview</h1>
            <p class="text-xs text-blue-600 font-medium mt-0.5"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= date('l, d F Y') ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= BASE_URL ?>/calculator.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-zinc-300 hover:bg-zinc-100 text-black font-medium text-xs rounded-lg transition">
                <i data-lucide="calculator" class="w-3.5 h-3.5 text-zinc-700"></i> Loan Calculator
            </a>
            <a href="<?= BASE_URL ?>/admin/loans.php?action=new" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition shadow-sm">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Issue Loan
            </a>
            <a href="<?= BASE_URL ?>/admin/reconciliation.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-blue-200 hover:bg-blue-50 text-blue-700 font-medium text-xs rounded-lg transition">
                Reconciliation
            </a>
        </div>
    </div>

    <!-- 4 Blue & Deep Black KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Collected Today -->
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-xl p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Collected Today</span>
                <span class="w-7 h-7 rounded-lg bg-white/15 text-white flex items-center justify-center"><i data-lucide="wallet" class="w-3.5 h-3.5"></i></span>
            </div>
            <div class="text-2xl font-bold text-white tracking-tight">
                <?= formatMoney((int)$todayStats['total_today']) ?>
            </div>
            <div class="text-[11px] text-blue-100 flex justify-between pt-2.5 border-t border-white/15">
                <span>Cash: <strong class="text-white"><?= formatMoney((int)$todayStats['cash_today']) ?></strong></span>
                <span>MoMo: <strong class="text-white"><?= formatMoney((int)$todayStats['momo_today']) ?></strong></span>
            </div>
        </div>

        <!-- Expected Today -->
        <div class="bg-gradient-to-br from-zinc-900 via-zinc-950 to-black text-white rounded-xl p-4 space-y-3 shadow-sm border border-zinc-800">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-zinc-300 uppercase tracking-wider">Expected Today</span>
                <span class="w-7 h-7 rounded-lg bg-white/10 text-blue-400 flex items-center justify-center"><i data-lucide="calendar-check" class="w-3.5 h-3.5"></i></span>
            </div>
            <div class="text-2xl font-bold text-white tracking-tight">
                <?= formatMoney($expectedToday) ?>
            </div>
            <div class="text-[11px] text-zinc-300 pt-2.5 border-t border-white/10 flex items-center justify-between">
                <span>Realized:</span>
                <span class="font-semibold text-blue-400">
                    <?= $expectedToday > 0 ? round(($todayStats['total_today'] / $expectedToday) * 100, 1) : 0 ?>%
                </span>
            </div>
        </div>

        <!-- Active Portfolio -->
        <div class="bg-gradient-to-br from-blue-700 to-blue-900 text-white rounded-xl p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-blue-100 uppercase tracking-wider">Active Portfolio</span>
                <span class="w-7 h-7 rounded-lg bg-white/15 text-white flex items-center justify-center"><i data-lucide="briefcase" class="w-3.5 h-3.5"></i></span>
            </div>
            <div class="text-2xl font-bold text-white tracking-tight">
                <?= formatMoney((int)$portfolioStats['total_outstanding']) ?>
            </div>
            <div class="text-[11px] text-blue-100 pt-2.5 border-t border-white/15 flex items-center justify-between">
                <span>Active Loans:</span>
                <span class="font-semibold text-white"><?= (int)$portfolioStats['active_loans_count'] ?></span>
            </div>
        </div>

        <!-- Total Overdue -->
        <div class="bg-gradient-to-br from-neutral-900 via-zinc-950 to-black text-white rounded-xl p-4 space-y-3 shadow-sm border border-zinc-800">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-zinc-300 uppercase tracking-wider">Total Overdue</span>
                <span class="w-7 h-7 rounded-lg bg-white/10 text-blue-400 flex items-center justify-center"><i data-lucide="alert-circle" class="w-3.5 h-3.5"></i></span>
            </div>
            <div class="text-2xl font-bold text-white tracking-tight">
                <?= formatMoney((int)$overdueStats['total_overdue']) ?>
            </div>
            <div class="text-[11px] text-zinc-300 pt-2.5 border-t border-white/10 flex items-center justify-between">
                <span>PAR 30+: <strong class="text-white"><?= $parPercentage ?>%</strong></span>
                <span class="text-blue-400"><?= (int)$overdueStats['overdue_installments_count'] ?> items</span>
            </div>
        </div>
    </div>

    <!-- Agent Daily Collection Leaderboard -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50 flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-black">Agent Performance Today</h2>
            <a href="<?= BASE_URL ?>/admin/reconciliation.php" class="text-xs font-medium text-blue-600 hover:text-blue-700 transition">
                Reconciliation &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-100/70 text-zinc-600 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-200">
                    <tr>
                        <th class="py-2.5 px-4 font-normal">Agent</th>
                        <th class="py-2.5 px-4 font-normal">Staff Code</th>
                        <th class="py-2.5 px-4 text-center font-normal">Count</th>
                        <th class="py-2.5 px-4 text-right font-normal">Cash</th>
                        <th class="py-2.5 px-4 text-right font-normal">MoMo</th>
                        <th class="py-2.5 px-4 text-right font-normal">Total</th>
                        <th class="py-2.5 px-4 text-center font-normal">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($agentPerformance as $ag): ?>
                        <tr class="hover:bg-blue-50/30 transition">
                            <td class="py-3 px-4 font-medium text-black">
                                <?= htmlspecialchars($ag['name']) ?>
                            </td>
                            <td class="py-3 px-4 font-mono text-zinc-700 text-[11px]">
                                <?= htmlspecialchars($ag['staff_code']) ?>
                            </td>
                            <td class="py-3 px-4 text-center font-medium text-zinc-800">
                                <?= (int)$ag['collections_count'] ?>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-zinc-800">
                                <?= formatMoney((int)$ag['cash_today']) ?>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-zinc-800">
                                <?= formatMoney((int)$ag['momo_today']) ?>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-blue-700">
                                <?= formatMoney((int)$ag['collected_today']) ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="<?= BASE_URL ?>/admin/reconciliation.php?agent_id=<?= $ag['id'] ?>" class="text-[11px] font-medium text-blue-600 hover:text-blue-700 underline">
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
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50 flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-black">Recent Collections</h2>
            <a href="<?= BASE_URL ?>/admin/collections.php" class="text-xs font-medium text-blue-600 hover:text-blue-700 transition">
                All Transactions &rarr;
            </a>
        </div>

        <div class="divide-y divide-zinc-100 text-xs">
            <?php foreach ($recentPayments as $rp): ?>
                <div class="p-3.5 px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-blue-50/30 transition">
                    <div>
                        <span class="font-medium text-black block"><?= htmlspecialchars($rp['customer_name']) ?></span>
                        <span class="text-zinc-500 text-[11px]">
                            <?= htmlspecialchars($rp['receipt_number']) ?> &bull; <?= htmlspecialchars($rp['loan_code']) ?> &bull; <?= htmlspecialchars($rp['agent_name']) ?> &bull; <span class="uppercase font-mono text-[10px] text-blue-600"><?= htmlspecialchars($rp['payment_method']) ?></span>
                        </span>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-3">
                        <div class="text-left sm:text-right">
                            <span class="font-semibold block text-blue-700">
                                <?= formatMoney((int)$rp['amount_pesewas']) ?>
                            </span>
                            <span class="text-[10px] text-zinc-500"><?= formatDateTime($rp['collection_timestamp']) ?></span>
                        </div>
                        <a href="<?= BASE_URL ?>/receipt.php?id=<?= $rp['id'] ?>" class="p-1.5 rounded-lg border border-blue-200 text-blue-600 hover:text-white hover:bg-blue-600 transition" title="Receipt">
                            <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
