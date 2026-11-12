<?php
/**
 * Reports & Data Exports - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Reports";

$reportType = $_GET['type'] ?? 'daily_sheet';
$date = $_GET['date'] ?? date('Y-m-d');

// 1. Daily Route Sheet
$dailySheet = [];
if ($reportType === 'daily_sheet') {
    $stmt = $pdo->prepare("
        SELECT p.*, c.full_name AS customer_name, c.phone AS customer_phone, c.location_landmark,
               l.loan_code, l.remaining_balance_pesewas, u.name AS agent_name
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        JOIN loans l ON p.loan_id = l.id
        JOIN users u ON p.agent_id = u.id
        WHERE DATE(p.collection_timestamp) = ? AND p.status = 'recorded'
        ORDER BY p.collection_timestamp ASC
    ");
    $stmt->execute([$date]);
    $dailySheet = $stmt->fetchAll();
}

// 2. Defaulter Report
$defaulters = [];
if ($reportType === 'defaulters') {
    $stmt = $pdo->query("
        SELECT rs.installment_number, rs.due_date, (rs.expected_amount_pesewas - rs.paid_amount_pesewas) AS overdue_pesewas,
               DATEDIFF(CURDATE(), rs.due_date) AS days_overdue,
               l.loan_code, l.remaining_balance_pesewas,
               c.full_name, c.phone, c.location_landmark, c.guarantor_name, c.guarantor_phone,
               u.name AS agent_name
        FROM repayment_schedule rs
        JOIN loans l ON rs.loan_id = l.id
        JOIN customers c ON l.customer_id = c.id
        LEFT JOIN users u ON l.agent_id = u.id
        WHERE rs.due_date < CURDATE() AND rs.status IN ('pending', 'partially_paid', 'overdue')
        ORDER BY days_overdue DESC
    ");
    $defaulters = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header Toolbar -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-zinc-900 tracking-tight">Reports & Print Statements</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Route collection sheets & defaulter audits</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print Statement
            </button>
        </div>
    </div>

    <!-- Report Selector Tabs (Minimal) -->
    <div class="no-print bg-white rounded-xl border border-zinc-200 p-3 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/admin/reports.php?type=daily_sheet&date=<?= urlencode($date) ?>" 
               class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $reportType === 'daily_sheet' ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200' ?>">
                Daily Collection Sheet
            </a>
            <a href="<?= BASE_URL ?>/admin/reports.php?type=defaulters" 
               class="px-3 py-1.5 rounded-lg text-xs font-medium transition <?= $reportType === 'defaulters' ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200' ?>">
                Defaulter Audit
            </a>
        </div>

        <?php if ($reportType === 'daily_sheet'): ?>
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="type" value="daily_sheet">
                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" 
                    class="px-2.5 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                <button type="submit" class="px-3 py-1.5 bg-zinc-900 text-white rounded-lg text-xs font-medium hover:bg-zinc-800 transition">
                    Load
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Printable Report Container -->
    <div class="bg-white rounded-xl border border-zinc-200 p-6 space-y-6">
        <!-- Branded Report Header -->
        <div class="text-center pb-5 border-b border-zinc-200">
            <?= renderCompanyLogo('report') ?>
            <h1 class="text-base font-bold uppercase tracking-tight text-zinc-900"><?= htmlspecialchars(COMPANY_NAME) ?></h1>
            <p class="text-xs text-zinc-500 mt-0.5"><?= htmlspecialchars(BRANCH_NAME) ?> Branch &bull; <?= htmlspecialchars(BRANCH_REGION) ?></p>
            <p class="text-[11px] text-zinc-400 mt-0.5"><?= htmlspecialchars(COMPANY_PHONE) ?></p>
            <div class="mt-3 inline-block px-3 py-1 rounded bg-zinc-100 text-zinc-800 text-[11px] font-medium uppercase tracking-wider">
                <?= $reportType === 'daily_sheet' ? 'Daily Collection Sheet — ' . formatDate($date) : 'Delinquent Loan & Defaulter Audit Report' ?>
            </div>
        </div>

        <?php if ($reportType === 'daily_sheet'): ?>
            <?php
                $totCash = 0;
                $totMoMo = 0;
                $totAll = 0;
                foreach ($dailySheet as $r) {
                    $totAll += $r['amount_pesewas'];
                    if ($r['payment_method'] === 'cash') $totCash += $r['amount_pesewas'];
                    if ($r['payment_method'] === 'momo') $totMoMo += $r['amount_pesewas'];
                }
            ?>
            <!-- Totals Header (Minimal) -->
            <div class="grid grid-cols-3 gap-3 p-3.5 rounded-lg bg-zinc-50 border border-zinc-200 text-xs">
                <div>
                    <span class="text-zinc-400 block text-[10px] uppercase">Cash Collected</span>
                    <span class="text-base font-bold text-zinc-900"><?= formatMoney($totCash) ?></span>
                </div>
                <div>
                    <span class="text-zinc-400 block text-[10px] uppercase">MoMo Collected</span>
                    <span class="text-base font-bold text-zinc-900"><?= formatMoney($totMoMo) ?></span>
                </div>
                <div class="text-right">
                    <span class="text-zinc-400 block text-[10px] uppercase">Total Realized</span>
                    <span class="text-base font-bold text-zinc-900"><?= formatMoney($totAll) ?></span>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-400 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-200">
                        <tr>
                            <th class="py-2.5 px-3 font-normal">Time</th>
                            <th class="py-2.5 px-3 font-normal">Receipt #</th>
                            <th class="py-2.5 px-3 font-normal">Borrower</th>
                            <th class="py-2.5 px-3 font-normal">Landmark</th>
                            <th class="py-2.5 px-3 font-normal">Loan</th>
                            <th class="py-2.5 px-3 font-normal">Agent</th>
                            <th class="py-2.5 px-3 font-normal">Method</th>
                            <th class="py-2.5 px-3 text-right font-normal">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($dailySheet as $d): ?>
                            <tr>
                                <td class="py-2.5 px-3 text-zinc-500 font-mono text-[11px]"><?= date('h:i A', strtotime($d['collection_timestamp'])) ?></td>
                                <td class="py-2.5 px-3 font-mono font-medium text-zinc-900"><?= htmlspecialchars($d['receipt_number']) ?></td>
                                <td class="py-2.5 px-3 font-medium text-zinc-900"><?= htmlspecialchars($d['customer_name']) ?></td>
                                <td class="py-2.5 px-3 text-zinc-500 text-[11px]"><?= htmlspecialchars($d['location_landmark']) ?></td>
                                <td class="py-2.5 px-3 font-mono text-zinc-700"><?= htmlspecialchars($d['loan_code']) ?></td>
                                <td class="py-2.5 px-3 text-zinc-700"><?= htmlspecialchars($d['agent_name']) ?></td>
                                <td class="py-2.5 px-3 uppercase text-[10px] font-medium text-zinc-500"><?= htmlspecialchars($d['payment_method']) ?></td>
                                <td class="py-2.5 px-3 text-right font-semibold text-zinc-900"><?= formatMoney((int)$d['amount_pesewas']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Signatures Section for Reconciliation Printout -->
            <div class="pt-8 grid grid-cols-2 gap-8 text-xs text-zinc-600 border-t border-zinc-200 mt-6">
                <div>
                    <div class="border-b border-zinc-300 w-44 mb-2"></div>
                    <span class="font-medium text-zinc-900 block">Manager / Auditor</span>
                    <span class="text-[10px] text-zinc-400">Signature</span>
                </div>
                <div class="text-right flex flex-col items-end">
                    <div class="border-b border-zinc-300 w-44 mb-2"></div>
                    <span class="font-medium text-zinc-900 block">Field Supervisor</span>
                    <span class="text-[10px] text-zinc-400">Verified & Handed Over</span>
                </div>
            </div>

        <?php else: ?>
            <!-- DEFAULTER AUDIT REPORT -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-400 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-200">
                        <tr>
                            <th class="py-2.5 px-3 font-normal">Overdue</th>
                            <th class="py-2.5 px-3 font-normal">Borrower & Phone</th>
                            <th class="py-2.5 px-3 font-normal">Landmark</th>
                            <th class="py-2.5 px-3 font-normal">Guarantor</th>
                            <th class="py-2.5 px-3 font-normal">Loan</th>
                            <th class="py-2.5 px-3 font-normal">Agent</th>
                            <th class="py-2.5 px-3 text-right font-normal">Overdue Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($defaulters as $df): ?>
                            <tr>
                                <td class="py-2.5 px-3 font-mono font-medium text-zinc-900"><?= $df['days_overdue'] ?> days</td>
                                <td class="py-2.5 px-3">
                                    <span class="font-medium text-zinc-900 block"><?= htmlspecialchars($df['full_name']) ?></span>
                                    <span class="font-mono text-zinc-400 text-[10px]"><?= htmlspecialchars($df['phone']) ?></span>
                                </td>
                                <td class="py-2.5 px-3 text-zinc-500 text-[11px]"><?= htmlspecialchars($df['location_landmark']) ?></td>
                                <td class="py-2.5 px-3">
                                    <span class="block text-zinc-800"><?= htmlspecialchars($df['guarantor_name']) ?></span>
                                    <span class="font-mono text-[10px] text-zinc-400"><?= htmlspecialchars($df['guarantor_phone']) ?></span>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-zinc-700"><?= htmlspecialchars($df['loan_code']) ?></td>
                                <td class="py-2.5 px-3 text-zinc-700"><?= htmlspecialchars($df['agent_name'] ?? 'Unassigned') ?></td>
                                <td class="py-2.5 px-3 text-right font-semibold text-zinc-900"><?= formatMoney((int)$df['overdue_pesewas']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
