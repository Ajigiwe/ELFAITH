<?php
/**
 * Agent Collection History & Daily Totals
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAgent();

$agentId = currentUserId();
$page_title = "My Collections History";

$dateFilter = $_GET['date'] ?? date('Y-m-d');

// Fetch summary for selected date
$stmtSummary = $pdo->prepare("
    SELECT 
        COALESCE(SUM(amount_pesewas), 0) AS total_amount,
        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_pesewas ELSE 0 END), 0) AS cash_amount,
        COALESCE(SUM(CASE WHEN payment_method = 'momo' THEN amount_pesewas ELSE 0 END), 0) AS momo_amount,
        COUNT(id) AS total_count
    FROM payments 
    WHERE agent_id = ? AND status = 'recorded' AND DATE(collection_timestamp) = ?
");
$stmtSummary->execute([$agentId, $dateFilter]);
$summary = $stmtSummary->fetch();

// Fetch transactions list
$stmtList = $pdo->prepare("
    SELECT p.*, c.full_name AS customer_name, c.phone AS customer_phone, l.loan_code
    FROM payments p
    JOIN customers c ON p.customer_id = c.id
    JOIN loans l ON p.loan_id = l.id
    WHERE p.agent_id = ? AND DATE(p.collection_timestamp) = ?
    ORDER BY p.collection_timestamp DESC
");
$stmtList->execute([$agentId, $dateFilter]);
$payments = $stmtList->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">My Collections</h1>
            <p class="text-xs text-zinc-500">Daily collections log & cash summary</p>
        </div>
        <div>
            <!-- Date Filter Form -->
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" 
                    class="px-2.5 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition">
                    Filter
                </button>
            </form>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-zinc-200 p-4 space-y-2">
            <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider block">Total Collected</span>
            <div class="text-2xl font-bold text-black"><?= formatMoney((int)$summary['total_amount']) ?></div>
            <span class="text-[11px] text-zinc-500 block"><?= (int)$summary['total_count'] ?> collections</span>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4 space-y-2">
            <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider block">Cash in Hand</span>
            <div class="text-2xl font-bold text-black"><?= formatMoney((int)$summary['cash_amount']) ?></div>
            <span class="text-[11px] text-zinc-500 block">Deliver to office</span>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4 space-y-2">
            <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider block">Mobile Money</span>
            <div class="text-2xl font-bold text-black"><?= formatMoney((int)$summary['momo_amount']) ?></div>
            <span class="text-[11px] text-zinc-500 block">Electronic payments</span>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black">Receipts Issued</h2>
            <span class="text-xs text-zinc-500"><?= count($payments) ?> Entries</span>
        </div>

        <?php if (empty($payments)): ?>
            <div class="p-8 text-center text-zinc-500 text-xs">
                No collections logged for <?= formatDate($dateFilter) ?>.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-500 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Time</th>
                            <th class="py-2.5 px-4 font-normal">Receipt #</th>
                            <th class="py-2.5 px-4 font-normal">Borrower</th>
                            <th class="py-2.5 px-4 font-normal">Loan</th>
                            <th class="py-2.5 px-4 font-normal">Method</th>
                            <th class="py-2.5 px-4 text-right font-normal">Amount</th>
                            <th class="py-2.5 px-4 text-center font-normal">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($payments as $p): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="py-3 px-4 text-zinc-500 font-mono text-[11px]">
                                    <?= date('h:i A', strtotime($p['collection_timestamp'])) ?>
                                </td>
                                <td class="py-3 px-4 font-mono font-medium text-black">
                                    <?= htmlspecialchars($p['receipt_number']) ?>
                                    <?php if ($p['status'] === 'reversed'): ?>
                                        <span class="ml-1 text-[9px] text-zinc-500 uppercase font-mono">(Reversed)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 font-medium text-black">
                                    <?= htmlspecialchars($p['customer_name']) ?>
                                </td>
                                <td class="py-3 px-4 font-mono text-zinc-700">
                                    <?= htmlspecialchars($p['loan_code']) ?>
                                </td>
                                <td class="py-3 px-4 uppercase text-[10px] font-mono text-zinc-700">
                                    <?= htmlspecialchars($p['payment_method']) ?>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-black">
                                    <?= formatMoney((int)$p['amount_pesewas']) ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="<?= BASE_URL ?>/receipt.php?id=<?= $p['id'] ?>" class="text-[11px] text-zinc-700 hover:text-black underline">
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

