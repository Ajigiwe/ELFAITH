<?php
/**
 * Detailed Loan Schedule & Repayment Waterfall View - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$loanId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT l.*, c.full_name AS customer_name, c.customer_code, c.phone AS customer_phone,
           c.residential_address, c.location_landmark, u.name AS agent_name, u.staff_code AS agent_code
    FROM loans l
    JOIN customers c ON l.customer_id = c.id
    LEFT JOIN users u ON l.agent_id = u.id
    WHERE l.id = ?
");
$stmt->execute([$loanId]);
$loan = $stmt->fetch();

if (!$loan) {
    setFlash('danger', 'Loan facility not found.');
    header('Location: ' . BASE_URL . '/admin/loans.php');
    exit;
}

$page_title = "Loan " . $loan['loan_code'];

// Fetch repayment schedule
$stmtSched = $pdo->prepare("
    SELECT * FROM repayment_schedule 
    WHERE loan_id = ? 
    ORDER BY installment_number ASC
");
$stmtSched->execute([$loanId]);
$schedule = $stmtSched->fetchAll();

// Fetch payments for this loan
$stmtPayments = $pdo->prepare("
    SELECT p.*, u.name AS agent_name
    FROM payments p
    JOIN users u ON p.agent_id = u.id
    WHERE p.loan_id = ?
    ORDER BY p.collection_timestamp DESC
");
$stmtPayments->execute([$loanId]);
$payments = $stmtPayments->fetchAll();

// Percentage Paid Progress
$pctPaid = $loan['total_payable_pesewas'] > 0 ? round(($loan['total_paid_pesewas'] / $loan['total_payable_pesewas']) * 100, 1) : 0;

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="font-mono text-xs font-semibold text-zinc-900 bg-zinc-100 px-2 py-0.5 rounded uppercase">
                    <?= htmlspecialchars($loan['loan_code']) ?>
                </span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $loan['status'] === 'active' ? 'bg-zinc-100 text-zinc-800' : ($loan['status'] === 'overdue' ? 'bg-zinc-200 text-zinc-900 font-semibold' : 'bg-zinc-100 text-zinc-500') ?>">
                    <?= htmlspecialchars($loan['status']) ?>
                </span>
            </div>
            <h1 class="text-lg font-bold text-zinc-900 tracking-tight mt-1"><?= htmlspecialchars($loan['customer_name']) ?></h1>
            <p class="text-xs text-zinc-400">
                Borrower: <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $loan['customer_id'] ?>" class="text-zinc-700 font-medium hover:underline"><?= htmlspecialchars($loan['customer_code']) ?></a> 
                &bull; Agent: <?= htmlspecialchars($loan['agent_name'] ?? 'Unassigned') ?>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/agent/collect.php?loan_id=<?= $loan['id'] ?>&customer_id=<?= $loan['customer_id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Collect Payment
            </a>
            <a href="<?= BASE_URL ?>/admin/loans.php" class="inline-flex items-center gap-1 text-xs text-zinc-600 bg-white border border-zinc-200 px-2.5 py-1.5 rounded-lg hover:bg-zinc-50">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> All Loans
            </a>
        </div>
    </div>

    <!-- Loan Financial Summary Card (Minimal) -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-zinc-400 block text-[10px] uppercase">Principal Facility</span>
                <span class="text-lg font-bold text-zinc-900"><?= formatMoney((int)$loan['principal_pesewas']) ?></span>
                <span class="text-[10px] text-zinc-400 block"><?= $loan['interest_rate'] ?>% <?= ucfirst($loan['interest_method']) ?></span>
            </div>
            <div>
                <span class="text-zinc-400 block text-[10px] uppercase">Total Payable</span>
                <span class="text-lg font-bold text-zinc-900"><?= formatMoney((int)$loan['total_payable_pesewas']) ?></span>
                <span class="text-[10px] text-zinc-400 block">Interest: <?= formatMoney((int)$loan['interest_pesewas']) ?></span>
            </div>
            <div>
                <span class="text-zinc-400 block text-[10px] uppercase">Total Paid</span>
                <span class="text-lg font-bold text-zinc-900"><?= formatMoney((int)$loan['total_paid_pesewas']) ?></span>
                <span class="text-[10px] text-zinc-400 block"><?= $pctPaid ?>% Realized</span>
            </div>
            <div>
                <span class="text-zinc-400 block text-[10px] uppercase">Remaining Balance</span>
                <span class="text-lg font-bold text-zinc-900"><?= formatMoney((int)$loan['remaining_balance_pesewas']) ?></span>
                <span class="text-[10px] text-zinc-400 block"><?= count($schedule) ?> installments</span>
            </div>
        </div>

        <!-- Progress Bar (Minimal) -->
        <div class="pt-2">
            <div class="w-full bg-zinc-100 rounded-full h-1.5 overflow-hidden">
                <div class="bg-zinc-900 h-1.5 rounded-full" style="width: <?= min(100, $pctPaid) ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Repayment Schedule Amortization Table -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-700">Amortization Schedule (<?= count($schedule) ?> Installments)</h2>
            <span class="text-xs text-zinc-400">Daily breakdown</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 text-zinc-400 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                    <tr>
                        <th class="py-2.5 px-4 font-normal">#</th>
                        <th class="py-2.5 px-4 font-normal">Due Date</th>
                        <th class="py-2.5 px-4 text-right font-normal">Principal</th>
                        <th class="py-2.5 px-4 text-right font-normal">Interest</th>
                        <th class="py-2.5 px-4 text-right font-normal">Expected</th>
                        <th class="py-2.5 px-4 text-right font-normal">Paid</th>
                        <th class="py-2.5 px-4 font-normal">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($schedule as $row): ?>
                        <?php 
                            $isOverdue = ($row['status'] !== 'paid' && strtotime($row['due_date']) < strtotime(date('Y-m-d')));
                        ?>
                        <tr class="hover:bg-zinc-50/50 transition">
                            <td class="py-2.5 px-4 font-mono text-zinc-400 text-[11px]">
                                <?= $row['installment_number'] ?>
                            </td>
                            <td class="py-2.5 px-4 font-mono text-zinc-700">
                                <?= formatDate($row['due_date']) ?>
                            </td>
                            <td class="py-2.5 px-4 text-right text-zinc-500">
                                <?= formatMoney((int)$row['principal_component_pesewas']) ?>
                            </td>
                            <td class="py-2.5 px-4 text-right text-zinc-500">
                                <?= formatMoney((int)$row['interest_component_pesewas']) ?>
                            </td>
                            <td class="py-2.5 px-4 text-right font-medium text-zinc-900">
                                <?= formatMoney((int)$row['expected_amount_pesewas']) ?>
                            </td>
                            <td class="py-2.5 px-4 text-right font-semibold text-zinc-900">
                                <?= formatMoney((int)$row['paid_amount_pesewas']) ?>
                            </td>
                            <td class="py-2.5 px-4">
                                <?php if ($row['status'] === 'paid'): ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase bg-zinc-100 text-zinc-800">
                                        Paid
                                    </span>
                                <?php elseif ($isOverdue): ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-zinc-200 text-zinc-900">
                                        Overdue
                                    </span>
                                <?php elseif ($row['status'] === 'partially_paid'): ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase bg-zinc-100 text-zinc-700">
                                        Partial
                                    </span>
                                <?php else: ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-normal uppercase text-zinc-400">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payments Ledger Table -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-700">Payments Recorded (<?= count($payments) ?>)</h2>
        </div>

        <?php if (empty($payments)): ?>
            <div class="p-8 text-center text-zinc-400 text-xs">
                No repayments have been recorded for this facility yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-400 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Date Time</th>
                            <th class="py-2.5 px-4 font-normal">Receipt #</th>
                            <th class="py-2.5 px-4 font-normal">Agent</th>
                            <th class="py-2.5 px-4 font-normal">Method</th>
                            <th class="py-2.5 px-4 text-right font-normal">Amount</th>
                            <th class="py-2.5 px-4 font-normal">Status</th>
                            <th class="py-2.5 px-4 text-center font-normal">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($payments as $p): ?>
                            <tr class="hover:bg-zinc-50/50 transition">
                                <td class="py-2.5 px-4 text-zinc-500 text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($p['collection_timestamp'])) ?>
                                </td>
                                <td class="py-2.5 px-4 font-mono font-medium text-zinc-900">
                                    <?= htmlspecialchars($p['receipt_number']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-zinc-700">
                                    <?= htmlspecialchars($p['agent_name']) ?>
                                </td>
                                <td class="py-2.5 px-4 uppercase text-[10px] font-medium text-zinc-500">
                                    <?= htmlspecialchars($p['payment_method']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-right font-semibold text-zinc-900">
                                    <?= formatMoney((int)$p['amount_pesewas']) ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $p['status'] === 'recorded' ? 'bg-zinc-100 text-zinc-800' : 'bg-zinc-200 text-zinc-500 line-through' ?>">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/receipt.php?id=<?= $p['id'] ?>" class="text-zinc-600 hover:text-zinc-900 underline text-xs">
                                        View
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
