<?php
/**
 * Customer / Borrower Directory (Admin) - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Borrowers";
$currentAdminId = (int)currentUserId();

// Handle Customer Delete & Restore Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Security session expired. Please reload and try again.');
        header('Location: ' . BASE_URL . '/admin/customers.php');
        exit;
    }

    $action = trim($_POST['action'] ?? '');
    $customerId = (int)($_POST['customer_id'] ?? 0);

    if ($customerId > 0) {
        $stmtC = $pdo->prepare("SELECT id, full_name, customer_code, deleted_at FROM customers WHERE id = ?");
        $stmtC->execute([$customerId]);
        $targetCustomer = $stmtC->fetch();

        if ($targetCustomer) {
            if ($action === 'delete_customer') {
                $stmtDel = $pdo->prepare("UPDATE customers SET deleted_at = NOW(), status = 'inactive' WHERE id = ?");
                $stmtDel->execute([$customerId]);

                logAudit($pdo, $currentAdminId, 'DELETE_CUSTOMER', 'customers', $customerId, [
                    'status' => 'active'
                ], [
                    'status' => 'inactive',
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'name' => $targetCustomer['full_name'],
                    'code' => $targetCustomer['customer_code']
                ]);

                setFlash('success', "Borrower '{$targetCustomer['full_name']}' ({$targetCustomer['customer_code']}) removed. All historical loans, payments, and financial ledgers remain completely intact.");
                header('Location: ' . BASE_URL . '/admin/customers.php');
                exit;
            } elseif ($action === 'restore_customer') {
                $stmtRes = $pdo->prepare("UPDATE customers SET deleted_at = NULL, status = 'active' WHERE id = ?");
                $stmtRes->execute([$customerId]);

                logAudit($pdo, $currentAdminId, 'RESTORE_CUSTOMER', 'customers', $customerId, null, [
                    'action' => 'restored',
                    'name' => $targetCustomer['full_name']
                ]);

                setFlash('success', "Borrower '{$targetCustomer['full_name']}' has been successfully restored to active records.");
                header('Location: ' . BASE_URL . '/admin/customers.php');
                exit;
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$agentFilter = !empty($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT c.*, u.name AS agent_name, u.staff_code AS agent_code,
           COUNT(l.id) AS total_loans_count,
           COALESCE(SUM(CASE WHEN l.status IN ('active', 'overdue') THEN l.remaining_balance_pesewas ELSE 0 END), 0) AS total_balance_pesewas
    FROM customers c
    LEFT JOIN users u ON c.assigned_agent_id = u.id
    LEFT JOIN loans l ON c.id = l.customer_id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (c.full_name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ? OR c.id_number LIKE ? OR c.location_landmark LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
}

if ($agentFilter > 0) {
    $sql .= " AND c.assigned_agent_id = ?";
    $params[] = $agentFilter;
}

if ($statusFilter === 'deleted') {
    $sql .= " AND c.deleted_at IS NOT NULL";
} elseif ($statusFilter === 'all') {
    // Show all records, including deleted
} elseif (!empty($statusFilter)) {
    $sql .= " AND c.deleted_at IS NULL AND c.status = ?";
    $params[] = $statusFilter;
} else {
    // Default: exclude deleted customers
    $sql .= " AND c.deleted_at IS NULL";
}

$sql .= " GROUP BY c.id ORDER BY (c.deleted_at IS NOT NULL) ASC, c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Fetch agents for filter dropdown
$agents = $pdo->query("SELECT id, name, staff_code FROM users WHERE role = 'agent' AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">Borrowers</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Directory, KYC records, locations & loan balances</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/agent/customer_new.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Register Borrower
            </a>
        </div>
    </div>

    <!-- Minimal Filters Bar -->
    <div class="bg-white rounded-xl border border-zinc-200 p-3.5">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
            <div class="sm:col-span-2 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-500">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                </span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                    class="w-full pl-9 pr-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs placeholder-zinc-400 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                    placeholder="Search name, phone, Ghana Card, or landmark...">
            </div>

            <div>
                <select name="agent_id" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-950 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">All Field Agents</option>
                    <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $agentFilter === (int)$ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['name']) ?> (<?= htmlspecialchars($ag['staff_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <select name="status" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-950 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Active Records</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Status</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Status</option>
                    <option value="blacklisted" <?= $statusFilter === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
                    <option value="deleted" <?= $statusFilter === 'deleted' ? 'selected' : '' ?>>Deleted / Archived</option>
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All (Including Deleted)</option>
                </select>
                <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-xs transition">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Customers Table (Minimal) -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-black">Borrower Accounts</h2>
            <span class="text-xs text-zinc-500"><?= count($customers) ?> Borrowers</span>
        </div>

        <?php if (empty($customers)): ?>
            <div class="p-8 text-center text-zinc-500 text-xs">
                No borrowers found matching criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-500 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Borrower</th>
                            <th class="py-2.5 px-4 font-normal">Ghana Card</th>
                            <th class="py-2.5 px-4 font-normal">Location Landmark</th>
                            <th class="py-2.5 px-4 font-normal">Guarantor</th>
                            <th class="py-2.5 px-4 font-normal">Agent</th>
                            <th class="py-2.5 px-4 text-right font-normal">Balance</th>
                            <th class="py-2.5 px-4 text-center font-normal">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($customers as $c): ?>
                            <?php $isDeleted = !empty($c['deleted_at']); ?>
                            <tr class="<?= $isDeleted ? 'bg-zinc-50/60 opacity-80' : 'hover:bg-blue-50/30' ?> transition">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono text-[10px] text-zinc-500 block uppercase"><?= htmlspecialchars($c['customer_code']) ?></span>
                                        <?php if ($isDeleted): ?>
                                            <span class="px-1 py-0.2 text-[9px] font-bold bg-red-100 text-red-700 rounded border border-red-200">DELETED</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="font-medium <?= $isDeleted ? 'text-zinc-600 line-through' : 'text-black' ?> text-xs block"><?= htmlspecialchars($c['full_name']) ?></span>
                                    <span class="text-zinc-600 font-mono text-[11px]"><?= htmlspecialchars($c['phone']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-mono text-zinc-950 block"><?= htmlspecialchars($c['id_number']) ?></span>
                                    <span class="text-[10px] text-zinc-500 block"><?= htmlspecialchars($c['occupation']) ?></span>
                                </td>
                                <td class="py-3 px-4 max-w-xs">
                                    <span class="text-zinc-950 block"><?= htmlspecialchars($c['location_landmark']) ?></span>
                                    <span class="text-[10px] text-zinc-500 block truncate"><?= htmlspecialchars($c['residential_address']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-zinc-950 font-medium block"><?= htmlspecialchars($c['guarantor_name']) ?></span>
                                    <span class="text-[10px] text-zinc-500 block"><?= htmlspecialchars($c['guarantor_relation']) ?> &bull; <?= htmlspecialchars($c['guarantor_phone']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($c['agent_name']): ?>
                                        <span class="text-zinc-950 block"><?= htmlspecialchars($c['agent_name']) ?></span>
                                        <span class="text-[10px] font-mono text-zinc-500 block"><?= htmlspecialchars($c['agent_code']) ?></span>
                                    <?php else: ?>
                                        <span class="text-zinc-500 italic">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-semibold block <?= $c['total_balance_pesewas'] > 0 ? 'text-black' : 'text-zinc-500' ?>">
                                        <?= formatMoney((int)$c['total_balance_pesewas']) ?>
                                    </span>
                                    <span class="text-[10px] text-zinc-500"><?= (int)$c['total_loans_count'] ?> loans</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $c['id'] ?>" class="px-2 py-1 bg-zinc-100 hover:bg-zinc-200 text-zinc-950 rounded text-[11px] font-medium transition">
                                            View
                                        </a>
                                        <?php if ($isDeleted): ?>
                                            <form method="POST" action="customers.php" class="inline" onsubmit="return confirm('Restore borrower <?= htmlspecialchars(addslashes($c['full_name'])) ?> to active records?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="restore_customer">
                                                <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[11px] font-medium transition">
                                                    Restore
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>/admin/loans.php?action=new&customer_id=<?= $c['id'] ?>" class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-[11px] font-medium transition">
                                                + Loan
                                            </a>
                                            <button type="button" 
                                                onclick="openDeleteCustomerModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars(addslashes($c['full_name'])) ?>', '<?= htmlspecialchars(addslashes($c['customer_code'])) ?>', <?= (int)$c['total_balance_pesewas'] ?>)"
                                                class="px-2 py-1 bg-zinc-100 hover:bg-red-50 text-zinc-500 hover:text-red-700 rounded text-[11px] font-medium border border-zinc-200 transition"
                                                title="Delete customer record">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
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

<!-- Modal: Delete Customer Record Confirmation -->
<div id="deleteCustomerModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border border-zinc-200">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                <i data-lucide="trash-2" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-black">Delete Customer Record?</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Non-destructive removal & archiving</p>
            </div>
        </div>

        <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-xs space-y-1.5 text-zinc-700">
            <div><strong>Borrower:</strong> <span id="delCustName" class="font-medium text-black"></span> (<span id="delCustCode" class="font-mono"></span>)</div>
            <div id="delCustBalanceWarning" class="hidden text-amber-700 font-medium">
                Outstanding Balance: <span id="delCustBalance"></span>
            </div>
        </div>

        <div class="bg-blue-50/60 border border-blue-200 rounded-lg p-3 text-[11px] text-blue-900 space-y-1 leading-relaxed">
            <div class="font-bold flex items-center gap-1.5 text-blue-950">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-blue-600"></i> Financial Records Protected
            </div>
            <div>All previous loan history, payment receipts, repayment schedules, and ledger balances are strictly preserved. This borrower will simply be archived and hidden from daily agent route collections.</div>
        </div>

        <form method="POST" action="customers.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_customer">
            <input type="hidden" name="customer_id" id="delCustomerId" value="">

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                <button type="button" onclick="closeDeleteCustomerModal()" class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 rounded-lg text-xs font-medium transition">
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
function openDeleteCustomerModal(id, name, code, balancePesewas) {
    document.getElementById('delCustomerId').value = id;
    document.getElementById('delCustName').textContent = name;
    document.getElementById('delCustCode').textContent = code;
    
    const balanceWarning = document.getElementById('delCustBalanceWarning');
    if (balancePesewas > 0) {
        document.getElementById('delCustBalance').textContent = 'GH₵ ' + (balancePesewas / 100).toFixed(2);
        balanceWarning.classList.remove('hidden');
    } else {
        balanceWarning.classList.add('hidden');
    }
    
    document.getElementById('deleteCustomerModal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeDeleteCustomerModal() {
    document.getElementById('deleteCustomerModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
