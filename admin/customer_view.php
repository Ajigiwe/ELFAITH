<?php
/**
 * Customer 360 Profile View - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$customerId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, u.name AS agent_name, u.staff_code AS agent_code, u.phone AS agent_phone
    FROM customers c
    LEFT JOIN users u ON c.assigned_agent_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    setFlash('danger', 'Customer not found.');
    header('Location: ' . BASE_URL . '/admin/customers.php');
    exit;
}

$page_title = $customer['full_name'];

// Fetch customer's loans
$stmtLoans = $pdo->prepare("
    SELECT * FROM loans 
    WHERE customer_id = ? 
    ORDER BY created_at DESC
");
$stmtLoans->execute([$customerId]);
$loans = $stmtLoans->fetchAll();

// Fetch customer's repayments
$stmtPayments = $pdo->prepare("
    SELECT p.*, l.loan_code, u.name AS agent_name
    FROM payments p
    JOIN loans l ON p.loan_id = l.id
    JOIN users u ON p.agent_id = u.id
    WHERE p.customer_id = ?
    ORDER BY p.collection_timestamp DESC
");
$stmtPayments->execute([$customerId]);
$payments = $stmtPayments->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Print-only Brand Header -->
    <div class="print-only pb-4 border-b border-purple-200 mb-4">
        <div class="flex items-center gap-3">
            <?= renderCompanyLogo('print') ?>
            <div>
                <h1 class="text-base font-bold uppercase text-slate-900"><?= htmlspecialchars(COMPANY_NAME) ?> — Borrower Profile</h1>
                <p class="text-xs text-slate-600"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_REGION) ?> &bull; Generated <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-medium uppercase bg-purple-50 text-slate-700">
                    <?= htmlspecialchars($customer['customer_code']) ?>
                </span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $customer['status'] === 'active' ? 'bg-purple-50 text-slate-800' : 'bg-slate-200 text-slate-600' ?>">
                    <?= htmlspecialchars($customer['status']) ?>
                </span>
            </div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight mt-1"><?= htmlspecialchars($customer['full_name']) ?></h1>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($customer['occupation']) ?> &bull; Joined <?= formatDate($customer['created_at']) ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/admin/loans.php?action=new&customer_id=<?= $customer['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Issue Loan
            </a>
            <a href="<?= BASE_URL ?>/admin/customers.php" class="inline-flex items-center gap-1 text-xs text-slate-600 bg-white border border-slate-200 px-2.5 py-1.5 rounded-lg hover:bg-slate-50">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> All Borrowers
            </a>
        </div>
    </div>

    <!-- KYC Profile Cards (Minimal) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Borrower Info -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-700 pb-2 border-b border-slate-100">
                Identity & Contact
            </h2>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Ghana Card / ID</span>
                    <span class="font-mono font-medium text-slate-900"><?= htmlspecialchars($customer['id_number']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Primary Phone</span>
                    <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" class="font-mono text-slate-900 hover:underline">
                        <?= htmlspecialchars($customer['phone']) ?>
                    </a>
                </div>
                <?php if ($customer['alt_phone']): ?>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase">Alternative Phone</span>
                        <span class="font-mono text-slate-600"><?= htmlspecialchars($customer['alt_phone']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Location Landmark -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-700 pb-2 border-b border-slate-100">
                Location & Route
            </h2>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Landmark</span>
                    <span class="text-slate-900 font-medium"><?= htmlspecialchars($customer['location_landmark']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Residential Address</span>
                    <span class="text-slate-600"><?= htmlspecialchars($customer['residential_address']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Assigned Field Agent</span>
                    <span class="text-slate-800 font-medium">
                        <?= htmlspecialchars($customer['agent_name'] ?? 'Unassigned') ?>
                        <?= $customer['agent_code'] ? '(' . htmlspecialchars($customer['agent_code']) . ')' : '' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Guarantor Details -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-700 pb-2 border-b border-slate-100">
                Guarantor
            </h2>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Full Name</span>
                    <span class="font-medium text-slate-900"><?= htmlspecialchars($customer['guarantor_name']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Relationship</span>
                    <span class="text-slate-700"><?= htmlspecialchars($customer['guarantor_relation']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Guarantor Phone</span>
                    <a href="tel:<?= htmlspecialchars($customer['guarantor_phone']) ?>" class="font-mono text-slate-900 hover:underline">
                        <?= htmlspecialchars($customer['guarantor_phone']) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan Facilities Portfolio -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-900">Loan Facilities (<?= count($loans) ?>)</h2>
            <a href="<?= BASE_URL ?>/admin/loans.php?action=new&customer_id=<?= $customer['id'] ?>" class="text-xs text-slate-600 hover:text-slate-900 font-medium">
                + New Loan
            </a>
        </div>

        <?php if (empty($loans)): ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                No loans originated for this borrower yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-400 uppercase font-medium text-[10px] tracking-wider border-b border-slate-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Loan Code</th>
                            <th class="py-2.5 px-4 font-normal">Principal</th>
                            <th class="py-2.5 px-4 font-normal">Total Payable</th>
                            <th class="py-2.5 px-4 font-normal">Total Paid</th>
                            <th class="py-2.5 px-4 font-normal">Remaining Balance</th>
                            <th class="py-2.5 px-4 font-normal">Terms</th>
                            <th class="py-2.5 px-4 font-normal">Status</th>
                            <th class="py-2.5 px-4 text-center font-normal">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($loans as $l): ?>
                            <tr class="hover:bg-purple-50/30 transition">
                                <td class="py-3 px-4 font-mono font-medium text-slate-900">
                                    <?= htmlspecialchars($l['loan_code']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-700">
                                    <?= formatMoney((int)$l['principal_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-900">
                                    <?= formatMoney((int)$l['total_payable_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-700">
                                    <?= formatMoney((int)$l['total_paid_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    <?= formatMoney((int)$l['remaining_balance_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-400 text-[11px]">
                                    <?= $l['term_duration'] ?> <?= $l['term_period'] ?> (<?= ucfirst($l['repayment_frequency']) ?>)
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $l['status'] === 'active' ? 'bg-purple-50 text-slate-800' : ($l['status'] === 'overdue' ? 'bg-slate-200 text-slate-900 font-semibold' : 'bg-purple-50 text-slate-500') ?>">
                                        <?= htmlspecialchars($l['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/admin/loan_view.php?id=<?= $l['id'] ?>" class="px-2 py-1 bg-purple-50 hover:bg-purple-100 text-slate-800 rounded text-[11px] font-medium transition">
                                        Schedule
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Ledger for this Borrower -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-900">Repayment Ledger (<?= count($payments) ?>)</h2>
        </div>

        <?php if (empty($payments)): ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                No payment transactions recorded for this borrower yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-400 uppercase font-medium text-[10px] tracking-wider border-b border-slate-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Date Time</th>
                            <th class="py-2.5 px-4 font-normal">Receipt #</th>
                            <th class="py-2.5 px-4 font-normal">Loan</th>
                            <th class="py-2.5 px-4 font-normal">Agent</th>
                            <th class="py-2.5 px-4 font-normal">Method</th>
                            <th class="py-2.5 px-4 text-right font-normal">Amount</th>
                            <th class="py-2.5 px-4 text-center font-normal">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($payments as $p): ?>
                            <tr class="hover:bg-purple-50/30 transition">
                                <td class="py-2.5 px-4 text-slate-500 text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($p['collection_timestamp'])) ?>
                                </td>
                                <td class="py-2.5 px-4 font-mono font-medium text-slate-900">
                                    <?= htmlspecialchars($p['receipt_number']) ?>
                                </td>
                                <td class="py-2.5 px-4 font-mono text-slate-500">
                                    <?= htmlspecialchars($p['loan_code']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-slate-700">
                                    <?= htmlspecialchars($p['agent_name']) ?>
                                </td>
                                <td class="py-2.5 px-4 uppercase text-[10px] font-medium text-slate-500">
                                    <?= htmlspecialchars($p['payment_method']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-right font-semibold text-slate-900">
                                    <?= formatMoney((int)$p['amount_pesewas']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/receipt.php?id=<?= $p['id'] ?>" class="text-slate-600 hover:text-slate-900 underline text-xs">
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
