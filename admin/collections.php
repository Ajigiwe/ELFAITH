<?php
/**
 * Master Payments Ledger & Reversal Desk - Minimalist Theme
 * Append-Only Financial Records
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Collections Ledger";

$error = '';
$success = '';

// Handle Reversal Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reverse') {
    if (!verifyCsrf()) {
        $error = 'Security session expired. Please retry.';
    } else {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $reason = trim($_POST['reversal_reason'] ?? '');

        if ($paymentId <= 0 || empty($reason)) {
            $error = 'Please provide a valid payment ID and an audit reason for the reversal.';
        } else {
            try {
                $revId = reversePayment($pdo, $paymentId, currentUserId(), $reason);
                setFlash('success', "Transaction reversed successfully! Reversal entry #{$revId} recorded.");
                header('Location: ' . BASE_URL . '/admin/collections.php');
                exit;
            } catch (Exception $e) {
                $error = 'Reversal failed: ' . $e->getMessage();
            }
        }
    }
}

// Filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$agentFilter = !empty($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$methodFilter = trim($_GET['method'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=elfaith_collections_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Receipt #', 'Date Time', 'Borrower', 'Phone', 'Loan Code', 'Agent', 'Method', 'MoMo Ref', 'Amount (GHS)', 'Status', 'GPS Lat', 'GPS Lng', 'Note']);

    $sqlExp = "
        SELECT p.*, c.full_name AS customer_name, c.phone AS customer_phone, l.loan_code, u.name AS agent_name
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        JOIN loans l ON p.loan_id = l.id
        JOIN users u ON p.agent_id = u.id
        WHERE DATE(p.collection_timestamp) BETWEEN ? AND ?
        ORDER BY p.collection_timestamp DESC
    ";
    $stmtExp = $pdo->prepare($sqlExp);
    $stmtExp->execute([$startDate, $endDate]);
    while ($row = $stmtExp->fetch()) {
        fputcsv($output, [
            $row['receipt_number'],
            $row['collection_timestamp'],
            $row['customer_name'],
            $row['customer_phone'],
            $row['loan_code'],
            $row['agent_name'],
            strtoupper($row['payment_method']),
            $row['momo_reference'] ?? '',
            pesewasToGHS($row['amount_pesewas']),
            $row['status'],
            $row['gps_lat'] ?? '',
            $row['gps_lng'] ?? '',
            $row['note'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Fetch ledger
$sql = "
    SELECT p.*, c.full_name AS customer_name, c.customer_code, c.phone AS customer_phone,
           l.loan_code, u.name AS agent_name, u.staff_code AS agent_code
    FROM payments p
    JOIN customers c ON p.customer_id = c.id
    JOIN loans l ON p.loan_id = l.id
    JOIN users u ON p.agent_id = u.id
    WHERE DATE(p.collection_timestamp) BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if ($agentFilter > 0) {
    $sql .= " AND p.agent_id = ?";
    $params[] = $agentFilter;
}

if (!empty($methodFilter)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $methodFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (p.receipt_number LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ? OR l.loan_code LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
}

$sql .= " ORDER BY p.collection_timestamp DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Aggregates for active view
$totalSum = 0;
$cashSum = 0;
$momoSum = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'recorded') {
        $totalSum += (int)$p['amount_pesewas'];
        if ($p['payment_method'] === 'cash') $cashSum += (int)$p['amount_pesewas'];
        if ($p['payment_method'] === 'momo') $momoSum += (int)$p['amount_pesewas'];
    }
}

// Fetch agents for filter dropdown
$agents = $pdo->query("SELECT id, name, staff_code FROM users WHERE role = 'agent' AND status = 'active' ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Payments Ledger</h1>
            <p class="text-xs text-slate-400 mt-0.5">Append-only collection records & reversal audit log</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/admin/collections.php?export=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium text-xs rounded-lg transition">
                <i data-lucide="download" class="w-3.5 h-3.5"></i> Export CSV
            </a>
            <a href="<?= BASE_URL ?>/admin/reports.php" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Reports
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-slate-50 border border-purple-200 text-slate-900 text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-slate-700"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics (Minimal Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-1">
            <span class="text-[11px] font-medium text-slate-400 uppercase tracking-wider block">Filtered Collections</span>
            <div class="text-2xl font-bold text-slate-900 tracking-tight"><?= formatMoney($totalSum) ?></div>
            <span class="text-[11px] text-slate-400 block"><?= count($payments) ?> transactions in view</span>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-1">
            <span class="text-[11px] font-medium text-slate-400 uppercase tracking-wider block">Physical Cash</span>
            <div class="text-2xl font-bold text-slate-900 tracking-tight"><?= formatMoney($cashSum) ?></div>
            <span class="text-[11px] text-slate-400 block">Handed in at office</span>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-1">
            <span class="text-[11px] font-medium text-slate-400 uppercase tracking-wider block">Mobile Money</span>
            <div class="text-2xl font-bold text-slate-900 tracking-tight"><?= formatMoney($momoSum) ?></div>
            <span class="text-[11px] text-slate-400 block">Electronic MoMo</span>
        </div>
    </div>

    <!-- Filters Panel (Minimal) -->
    <div class="bg-white rounded-xl border border-slate-200 p-3.5">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-5 gap-2.5 text-xs">
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-wider mb-1">From Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" 
                    class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
            </div>

            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-wider mb-1">To Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" 
                    class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
            </div>

            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-wider mb-1">Field Agent</label>
                <select name="agent_id" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">All Agents</option>
                    <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $agentFilter === (int)$ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-wider mb-1">Method</label>
                <select name="method" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash Only</option>
                    <option value="momo" <?= $methodFilter === 'momo' ? 'selected' : '' ?>>Mobile Money</option>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Payments Ledger Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-900">Ledger Records</h2>
            <span class="text-xs text-slate-400"><?= count($payments) ?> Entries</span>
        </div>

        <?php if (empty($payments)): ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                No payment transactions match the filter criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-400 uppercase font-medium text-[10px] tracking-wider border-b border-slate-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Date Time</th>
                            <th class="py-2.5 px-4 font-normal">Receipt #</th>
                            <th class="py-2.5 px-4 font-normal">Borrower</th>
                            <th class="py-2.5 px-4 font-normal">Loan</th>
                            <th class="py-2.5 px-4 font-normal">Agent</th>
                            <th class="py-2.5 px-4 font-normal">Method</th>
                            <th class="py-2.5 px-4 text-right font-normal">Amount</th>
                            <th class="py-2.5 px-4 font-normal">Status</th>
                            <th class="py-2.5 px-4 text-center font-normal">Actions</th>
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
                                <td class="py-2.5 px-4">
                                    <span class="font-medium text-slate-900 block"><?= htmlspecialchars($p['customer_name']) ?></span>
                                    <span class="text-[11px] font-mono text-slate-400"><?= htmlspecialchars($p['customer_phone']) ?></span>
                                </td>
                                <td class="py-2.5 px-4 font-mono text-slate-500">
                                    <?= htmlspecialchars($p['loan_code']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-slate-700">
                                    <?= htmlspecialchars($p['agent_name']) ?>
                                </td>
                                <td class="py-2.5 px-4 uppercase text-[10px] font-medium text-slate-500">
                                    <?= htmlspecialchars($p['payment_method']) ?>
                                    <?php if ($p['momo_reference']): ?>
                                        <span class="font-mono text-slate-400 block text-[9px]"><?= htmlspecialchars($p['momo_reference']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 text-right font-semibold text-slate-900">
                                    <?= formatMoney((int)$p['amount_pesewas']) ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <?php if ($p['status'] === 'recorded'): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase bg-purple-50 text-slate-800">
                                            Recorded
                                        </span>
                                    <?php elseif ($p['status'] === 'reversed'): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase bg-slate-200 text-slate-500 line-through">
                                            Reversed
                                        </span>
                                    <?php else: ?>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono uppercase bg-purple-50 text-slate-700">
                                            Correction
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="<?= BASE_URL ?>/receipt.php?id=<?= $p['id'] ?>" class="text-slate-600 hover:text-slate-900 underline text-xs">
                                            Receipt
                                        </a>
                                        <?php if ($p['status'] === 'recorded' && !$p['is_reversal']): ?>
                                            <button onclick="openReversalModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['receipt_number']) ?>', '<?= formatMoney((int)$p['amount_pesewas']) ?>', '<?= htmlspecialchars(addslashes($p['customer_name'])) ?>')" 
                                                class="text-slate-400 hover:text-slate-900 text-xs">
                                                Reverse
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reversal Modal (Minimal) -->
<div id="reversal-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 max-w-md w-full p-6 space-y-4 shadow-sm">
        <div>
            <h3 class="text-sm font-bold text-slate-900">Reverse Payment Entry</h3>
            <p class="text-xs text-slate-400 mt-0.5">Creates an immutable negative ledger reversal entry.</p>
        </div>

        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1">
            <div><span class="text-slate-400 text-[10px] uppercase">Receipt:</span> <strong id="modal-receipt" class="font-mono text-slate-900"></strong></div>
            <div><span class="text-slate-400 text-[10px] uppercase">Borrower:</span> <strong id="modal-customer" class="text-slate-900"></strong></div>
            <div><span class="text-slate-400 text-[10px] uppercase">Amount:</span> <strong id="modal-amount" class="text-slate-900"></strong></div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/collections.php" class="space-y-4 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reverse">
            <input type="hidden" id="modal-payment-id" name="payment_id" value="">

            <div>
                <label class="block text-[11px] font-medium text-slate-700 mb-1">
                    Audit Reason for Reversal *
                </label>
                <textarea name="reversal_reason" rows="3" required
                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                    placeholder="e.g. Agent mistakenly entered GH₵ 100 instead of GH₵ 50; customer was double-charged"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeReversalModal()" class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-slate-700 rounded-lg text-xs font-medium">
                    Cancel
                </button>
                <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium">
                    Confirm Reversal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReversalModal(id, receipt, amount, customer) {
        document.getElementById('modal-payment-id').value = id;
        document.getElementById('modal-receipt').textContent = receipt;
        document.getElementById('modal-amount').textContent = amount;
        document.getElementById('modal-customer').textContent = customer;
        document.getElementById('reversal-modal').classList.remove('hidden');
    }

    function closeReversalModal() {
        document.getElementById('reversal-modal').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
