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
$currentAdminId = (int)currentUserId();

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

// Handle Customer Delete & Restore Actions from 360 View
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Security session expired. Please reload and try again.');
        header('Location: ' . BASE_URL . '/admin/customer_view.php?id=' . $customerId);
        exit;
    }

    $action = trim($_POST['action'] ?? '');
    if ($action === 'delete_customer') {
        $stmtDel = $pdo->prepare("UPDATE customers SET deleted_at = NOW(), status = 'inactive' WHERE id = ?");
        $stmtDel->execute([$customerId]);

        logAudit($pdo, $currentAdminId, 'DELETE_CUSTOMER', 'customers', $customerId, [
            'status' => 'active'
        ], [
            'status' => 'inactive',
            'deleted_at' => date('Y-m-d H:i:s'),
            'name' => $customer['full_name'],
            'code' => $customer['customer_code']
        ]);

        setFlash('success', "Borrower '{$customer['full_name']}' ({$customer['customer_code']}) removed. All historical loans, payments, and financial ledgers remain completely intact.");
        header('Location: ' . BASE_URL . '/admin/customers.php');
        exit;
    } elseif ($action === 'restore_customer') {
        $stmtRes = $pdo->prepare("UPDATE customers SET deleted_at = NULL, status = 'active' WHERE id = ?");
        $stmtRes->execute([$customerId]);

        logAudit($pdo, $currentAdminId, 'RESTORE_CUSTOMER', 'customers', $customerId, null, [
            'action' => 'restored',
            'name' => $customer['full_name']
        ]);

        setFlash('success', "Borrower '{$customer['full_name']}' has been successfully restored to active records.");
        header('Location: ' . BASE_URL . '/admin/customer_view.php?id=' . $customerId);
        exit;
    }
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
    <div class="print-only pb-4 border-b border-zinc-200 mb-4">
        <div class="flex items-center gap-3">
            <?= renderCompanyLogo('print') ?>
            <div>
                <h1 class="text-base font-bold uppercase text-black"><?= htmlspecialchars(COMPANY_NAME) ?> — Borrower Profile</h1>
                <p class="text-xs text-zinc-700"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_REGION) ?> &bull; Generated <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-medium uppercase bg-zinc-100 text-zinc-800">
                    <?= htmlspecialchars($customer['customer_code']) ?>
                </span>
                <?php if (!empty($customer['deleted_at'])): ?>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-red-100 text-red-700 border border-red-200">
                        DELETED / ARCHIVED
                    </span>
                <?php else: ?>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $customer['status'] === 'active' ? 'bg-zinc-100 text-zinc-950' : 'bg-zinc-200 text-zinc-700' ?>">
                        <?= htmlspecialchars($customer['status']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-lg font-bold text-black tracking-tight mt-1 <?= !empty($customer['deleted_at']) ? 'text-zinc-600 line-through' : '' ?>"><?= htmlspecialchars($customer['full_name']) ?></h1>
            <p class="text-xs text-zinc-500"><?= htmlspecialchars($customer['occupation']) ?> &bull; Joined <?= formatDate($customer['created_at']) ?></p>
        </div>
        <div class="flex items-center gap-2">
            <?php if (empty($customer['deleted_at'])): ?>
                <a href="<?= BASE_URL ?>/admin/loans.php?action=new&customer_id=<?= $customer['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Issue Loan
                </a>
                <button type="button" onclick="openDeleteCustomerViewModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-red-200 hover:bg-red-50 text-red-700 font-medium text-xs rounded-lg transition">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete Borrower
                </button>
            <?php else: ?>
                <form method="POST" action="customer_view.php?id=<?= $customerId ?>" class="inline" onsubmit="return confirm('Restore borrower <?= htmlspecialchars(addslashes($customer['full_name'])) ?> to active records?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="restore_customer">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs rounded-lg transition shadow-sm">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Restore Borrower
                    </button>
                </form>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/customers.php" class="inline-flex items-center gap-1 text-xs text-zinc-700 bg-white border border-zinc-200 px-2.5 py-1.5 rounded-lg hover:bg-zinc-100">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> All Borrowers
            </a>
        </div>
    </div>

    <?php if (!empty($customer['deleted_at'])): ?>
        <!-- Preserved Historical Banner -->
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-red-900">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-red-100 text-red-700 flex items-center justify-center shrink-0">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                </div>
                <div>
                    <strong class="font-bold text-red-950">Borrower Account Deleted / Archived</strong>
                    <p class="text-[11px] text-red-800 mt-0.5">Removed on <?= formatDateTime($customer['deleted_at']) ?>. All previous loan ledgers, repayments, and audit records below remain strictly preserved.</p>
                </div>
            </div>
            <form method="POST" action="customer_view.php?id=<?= $customerId ?>" class="shrink-0" onsubmit="return confirm('Restore this borrower account?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="restore_customer">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs rounded-lg transition shadow-sm">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Restore Borrower
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- KYC Profile Cards (Minimal) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Borrower Info -->
        <div class="bg-white rounded-xl border border-zinc-200 p-4 space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black pb-2 border-b border-zinc-100">
                Identity & Contact
            </h2>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Ghana Card / ID</span>
                    <span class="font-mono font-medium text-black"><?= htmlspecialchars($customer['id_number']) ?></span>
                </div>
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Primary Phone</span>
                    <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" class="font-mono text-black hover:underline">
                        <?= htmlspecialchars($customer['phone']) ?>
                    </a>
                </div>
                <?php if ($customer['alt_phone']): ?>
                    <div>
                        <span class="text-zinc-500 block text-[10px] uppercase">Alternative Phone</span>
                        <span class="font-mono text-zinc-700"><?= htmlspecialchars($customer['alt_phone']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Location Landmark -->
        <div class="bg-white rounded-xl border border-zinc-200 p-4 space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black pb-2 border-b border-zinc-100">
                Location & Route
            </h2>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Landmark</span>
                    <span class="text-black font-medium"><?= htmlspecialchars($customer['location_landmark']) ?></span>
                </div>
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Residential Address</span>
                    <span class="text-zinc-700"><?= htmlspecialchars($customer['residential_address']) ?></span>
                </div>
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Assigned Field Agent</span>
                    <span class="text-zinc-950 font-medium">
                        <?= htmlspecialchars($customer['agent_name'] ?? 'Unassigned') ?>
                        <?= $customer['agent_code'] ? '(' . htmlspecialchars($customer['agent_code']) . ')' : '' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Guarantor Details -->
        <div class="bg-white rounded-xl border border-zinc-200 p-4 space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black pb-2 border-b border-zinc-100">
                Guarantor
            </h2>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Full Name</span>
                    <span class="font-medium text-black"><?= htmlspecialchars($customer['guarantor_name']) ?></span>
                </div>
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Relationship</span>
                    <span class="text-zinc-800"><?= htmlspecialchars($customer['guarantor_relation']) ?></span>
                </div>
                <div>
                    <span class="text-zinc-500 block text-[10px] uppercase">Guarantor Phone</span>
                    <a href="tel:<?= htmlspecialchars($customer['guarantor_phone']) ?>" class="font-mono text-black hover:underline">
                        <?= htmlspecialchars($customer['guarantor_phone']) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan Facilities Portfolio -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black">Loan Facilities (<?= count($loans) ?>)</h2>
            <a href="<?= BASE_URL ?>/admin/loans.php?action=new&customer_id=<?= $customer['id'] ?>" class="text-xs text-zinc-700 hover:text-black font-medium">
                + New Loan
            </a>
        </div>

        <?php if (empty($loans)): ?>
            <div class="p-8 text-center text-zinc-500 text-xs">
                No loans originated for this borrower yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-500 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
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
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($loans as $l): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="py-3 px-4 font-mono font-medium text-black">
                                    <?= htmlspecialchars($l['loan_code']) ?>
                                </td>
                                <td class="py-3 px-4 text-zinc-800">
                                    <?= formatMoney((int)$l['principal_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 font-medium text-black">
                                    <?= formatMoney((int)$l['total_payable_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 text-zinc-800">
                                    <?= formatMoney((int)$l['total_paid_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 font-semibold text-black">
                                    <?= formatMoney((int)$l['remaining_balance_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 text-zinc-500 text-[11px]">
                                    <?= $l['term_duration'] ?> <?= $l['term_period'] ?> (<?= ucfirst($l['repayment_frequency']) ?>)
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $l['status'] === 'active' ? 'bg-zinc-100 text-zinc-950' : ($l['status'] === 'overdue' ? 'bg-zinc-200 text-black font-semibold' : 'bg-zinc-100 text-zinc-600') ?>">
                                        <?= htmlspecialchars($l['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/admin/loan_view.php?id=<?= $l['id'] ?>" class="px-2 py-1 bg-zinc-100 hover:bg-zinc-200 text-zinc-950 rounded text-[11px] font-medium transition">
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
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black">Repayment Ledger (<?= count($payments) ?>)</h2>
        </div>

        <?php if (empty($payments)): ?>
            <div class="p-8 text-center text-zinc-500 text-xs">
                No payment transactions recorded for this borrower yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-500 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
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
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($payments as $p): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="py-2.5 px-4 text-zinc-600 text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($p['collection_timestamp'])) ?>
                                </td>
                                <td class="py-2.5 px-4 font-mono font-medium text-black">
                                    <?= htmlspecialchars($p['receipt_number']) ?>
                                </td>
                                <td class="py-2.5 px-4 font-mono text-zinc-600">
                                    <?= htmlspecialchars($p['loan_code']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-zinc-800">
                                    <?= htmlspecialchars($p['agent_name']) ?>
                                </td>
                                <td class="py-2.5 px-4 uppercase text-[10px] font-medium text-zinc-600">
                                    <?= htmlspecialchars($p['payment_method']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-right font-semibold text-black">
                                    <?= formatMoney((int)$p['amount_pesewas']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/receipt.php?id=<?= $p['id'] ?>" class="text-zinc-700 hover:text-black underline text-xs">
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

<!-- Modal: Delete Customer Record Confirmation -->
<div id="deleteCustomerViewModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border border-zinc-200">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                <i data-lucide="trash-2" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-black">Delete Borrower Account?</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Non-destructive removal & archiving</p>
            </div>
        </div>

        <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-xs space-y-1.5 text-zinc-700">
            <div><strong>Borrower:</strong> <span class="font-medium text-black"><?= htmlspecialchars($customer['full_name']) ?></span> (<span class="font-mono"><?= htmlspecialchars($customer['customer_code']) ?></span>)</div>
            <div><strong>Total Loans Recorded:</strong> <?= count($loans) ?></div>
            <div><strong>Total Repayments Recorded:</strong> <?= count($payments) ?></div>
        </div>

        <div class="bg-blue-50/60 border border-blue-200 rounded-lg p-3 text-[11px] text-blue-900 space-y-1 leading-relaxed">
            <div class="font-bold flex items-center gap-1.5 text-blue-950">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-blue-600"></i> Financial Records Protected
            </div>
            <div>All previous loan history, payment receipts, repayment schedules, and ledger balances are strictly preserved. This borrower will simply be archived and hidden from daily agent route collections.</div>
        </div>

        <form method="POST" action="customer_view.php?id=<?= $customerId ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_customer">

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                <button type="button" onclick="closeDeleteCustomerViewModal()" class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 rounded-lg text-xs font-medium transition">
                    Cancel
                </button>
                <button type="submit" class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-medium transition">
                    Confirm Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteCustomerViewModal() {
    document.getElementById('deleteCustomerViewModal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}
function closeDeleteCustomerViewModal() {
    document.getElementById('deleteCustomerViewModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
