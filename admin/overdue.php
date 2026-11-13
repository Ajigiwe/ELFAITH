<?php
/**
 * Overdue & Defaulter Tracking & Portfolio at Risk (PAR) - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Overdue & Risk";

// 1. Aging Buckets Aggregates
$stmtAging = $pdo->query("
    SELECT 
        SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 7 THEN (expected_amount_pesewas - paid_amount_pesewas) ELSE 0 END) AS bucket_1_7,
        SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 8 AND 30 THEN (expected_amount_pesewas - paid_amount_pesewas) ELSE 0 END) AS bucket_8_30,
        SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 30 THEN (expected_amount_pesewas - paid_amount_pesewas) ELSE 0 END) AS bucket_30_plus,
        COUNT(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 7 THEN 1 END) AS count_1_7,
        COUNT(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 8 AND 30 THEN 1 END) AS count_8_30,
        COUNT(CASE WHEN DATEDIFF(CURDATE(), due_date) > 30 THEN 1 END) AS count_30_plus
    FROM repayment_schedule
    WHERE due_date < CURDATE() AND status IN ('pending', 'partially_paid', 'overdue')
");
$aging = $stmtAging->fetch();

// 2. Overdue Installments List
$stmtList = $pdo->query("
    SELECT rs.id AS schedule_id, rs.installment_number, rs.due_date, 
           rs.expected_amount_pesewas, rs.paid_amount_pesewas,
           (rs.expected_amount_pesewas - rs.paid_amount_pesewas) AS overdue_pesewas,
           DATEDIFF(CURDATE(), rs.due_date) AS days_overdue,
           l.id AS loan_id, l.loan_code, l.remaining_balance_pesewas,
           c.id AS customer_id, c.customer_code, c.full_name, c.phone, c.location_landmark,
           c.guarantor_name, c.guarantor_phone,
           u.name AS agent_name, u.staff_code AS agent_code
    FROM repayment_schedule rs
    JOIN loans l ON rs.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    LEFT JOIN users u ON l.agent_id = u.id
    WHERE rs.due_date < CURDATE() AND rs.status IN ('pending', 'partially_paid', 'overdue')
    ORDER BY days_overdue DESC, rs.due_date ASC
");
$overdueList = $stmtList->fetchAll();

// 3. Per-Agent Overdue Performance
$agentOverdue = $pdo->query("
    SELECT u.id, u.name, u.staff_code,
           COUNT(rs.id) AS overdue_count,
           COALESCE(SUM(rs.expected_amount_pesewas - rs.paid_amount_pesewas), 0) AS overdue_total_pesewas
    FROM users u
    JOIN loans l ON u.id = l.agent_id
    JOIN repayment_schedule rs ON l.id = rs.loan_id
    WHERE rs.due_date < CURDATE() AND rs.status IN ('pending', 'partially_paid', 'overdue')
      AND u.role = 'agent'
    GROUP BY u.id, u.name, u.staff_code
    ORDER BY overdue_total_pesewas DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">Overdue Tracking & PAR</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Aging analysis & defaulter recovery monitoring</p>
        </div>
    </div>

    <!-- Modern Fintech Aging Analysis Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- 1 - 7 Days -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-2.5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span> 1 — 7 Days Late
                </span>
                <span class="text-xs font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full"><?= (int)$aging['count_1_7'] ?> loans</span>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums">
                <?= formatMoney((int)$aging['bucket_1_7']) ?>
            </div>
            <div class="text-[11px] text-slate-500 pt-2 border-t border-slate-100">
                Early warning arrears bucket
            </div>
        </div>

        <!-- 8 - 30 Days -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-2.5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-orange-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-orange-500"></span> 8 — 30 Days Late
                </span>
                <span class="text-xs font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full"><?= (int)$aging['count_8_30'] ?> loans</span>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums">
                <?= formatMoney((int)$aging['bucket_8_30']) ?>
            </div>
            <div class="text-[11px] text-slate-500 pt-2 border-t border-slate-100">
                Moderate risk overdue bucket
            </div>
        </div>

        <!-- 30+ Days (PAR) -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-2.5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-rose-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span> 30+ Days (PAR 30+)
                </span>
                <span class="text-xs font-mono font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200/60"><?= (int)$aging['count_30_plus'] ?> loans</span>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums">
                <?= formatMoney((int)$aging['bucket_30_plus']) ?>
            </div>
            <div class="text-[11px] text-rose-600 font-medium pt-2 border-t border-slate-100">
                Critical portfolio at risk (PAR)
            </div>
        </div>
    </div>
    </div>

    <!-- Overdue Installments Detail List -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black">Delinquent Installments (<?= count($overdueList) ?>)</h2>
            <a href="<?= BASE_URL ?>/admin/reports.php?type=defaulters" class="text-xs text-zinc-600 hover:text-black font-medium">
                Print Defaulters Report &rarr;
            </a>
        </div>

        <?php if (empty($overdueList)): ?>
            <div class="p-8 text-center text-zinc-500 text-xs">
                Zero delinquent installments. Portfolio is completely current.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-500 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Borrower</th>
                            <th class="py-2.5 px-4 font-normal">Loan Code</th>
                            <th class="py-2.5 px-4 font-normal">Due Date</th>
                            <th class="py-2.5 px-4 font-normal">Overdue Days</th>
                            <th class="py-2.5 px-4 text-right font-normal">Overdue Amount</th>
                            <th class="py-2.5 px-4 font-normal">Agent</th>
                            <th class="py-2.5 px-4 font-normal">Guarantor</th>
                            <th class="py-2.5 px-4 text-center font-normal">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($overdueList as $od): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="py-3 px-4">
                                    <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $od['customer_id'] ?>" class="font-medium text-black hover:underline block">
                                        <?= htmlspecialchars($od['full_name']) ?>
                                    </a>
                                    <span class="text-[11px] font-mono text-zinc-500"><?= htmlspecialchars($od['phone']) ?></span>
                                </td>
                                <td class="py-3 px-4 font-mono font-medium text-black">
                                    <?= htmlspecialchars($od['loan_code']) ?>
                                    <span class="text-[10px] text-zinc-500 block">Inst #<?= $od['installment_number'] ?></span>
                                </td>
                                <td class="py-3 px-4 font-mono text-zinc-800">
                                    <?= formatDate($od['due_date']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-medium uppercase <?= $od['days_overdue'] > 30 ? 'bg-zinc-200 text-black font-bold' : 'bg-zinc-100 text-zinc-800' ?>">
                                        <?= $od['days_overdue'] ?> days
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-bold text-black block"><?= formatMoney((int)$od['overdue_pesewas']) ?></span>
                                    <span class="text-[10px] text-zinc-500">Total: <?= formatMoney((int)$od['remaining_balance_pesewas']) ?></span>
                                </td>
                                <td class="py-3 px-4 text-zinc-800">
                                    <?= htmlspecialchars($od['agent_name'] ?? 'Unassigned') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-zinc-950 block"><?= htmlspecialchars($od['guarantor_name']) ?></span>
                                    <a href="tel:<?= htmlspecialchars($od['guarantor_phone']) ?>" class="text-[11px] font-mono text-zinc-500 hover:text-black">
                                        <?= htmlspecialchars($od['guarantor_phone']) ?>
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/agent/collect.php?loan_id=<?= $od['loan_id'] ?>&customer_id=<?= $od['customer_id'] ?>&amount=<?= pesewasToGHS((int)$od['overdue_pesewas']) ?>" 
                                       class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-[11px] font-medium transition">
                                        Collect
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
