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

if (!empty($statusFilter)) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY c.id ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Fetch agents for filter dropdown
$agents = $pdo->query("SELECT id, name, staff_code FROM users WHERE role = 'agent' AND status = 'active' ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Borrowers</h1>
            <p class="text-xs text-slate-400 mt-0.5">Directory, KYC records, locations & loan balances</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/agent/customer_new.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Register Borrower
            </a>
        </div>
    </div>

    <!-- Minimal Filters Bar -->
    <div class="bg-white rounded-xl border border-slate-200 p-3.5">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
            <div class="sm:col-span-2 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                </span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                    class="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                    placeholder="Search name, phone, Ghana Card, or landmark...">
            </div>

            <div>
                <select name="agent_id" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">All Field Agents</option>
                    <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $agentFilter === (int)$ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['name']) ?> (<?= htmlspecialchars($ag['staff_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <select name="status" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="blacklisted" <?= $statusFilter === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
                </select>
                <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-xs transition">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Customers Table (Minimal) -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-900">Borrower Accounts</h2>
            <span class="text-xs text-slate-400"><?= count($customers) ?> Borrowers</span>
        </div>

        <?php if (empty($customers)): ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                No borrowers found matching criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-400 uppercase font-medium text-[10px] tracking-wider border-b border-slate-100">
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
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($customers as $c): ?>
                            <tr class="hover:bg-purple-50/30 transition">
                                <td class="py-3 px-4">
                                    <span class="font-mono text-[10px] text-slate-400 block uppercase"><?= htmlspecialchars($c['customer_code']) ?></span>
                                    <span class="font-medium text-slate-900 text-xs block"><?= htmlspecialchars($c['full_name']) ?></span>
                                    <span class="text-slate-500 font-mono text-[11px]"><?= htmlspecialchars($c['phone']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-mono text-slate-800 block"><?= htmlspecialchars($c['id_number']) ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($c['occupation']) ?></span>
                                </td>
                                <td class="py-3 px-4 max-w-xs">
                                    <span class="text-slate-800 block"><?= htmlspecialchars($c['location_landmark']) ?></span>
                                    <span class="text-[10px] text-slate-400 block truncate"><?= htmlspecialchars($c['residential_address']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-slate-800 font-medium block"><?= htmlspecialchars($c['guarantor_name']) ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($c['guarantor_relation']) ?> &bull; <?= htmlspecialchars($c['guarantor_phone']) ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($c['agent_name']): ?>
                                        <span class="text-slate-800 block"><?= htmlspecialchars($c['agent_name']) ?></span>
                                        <span class="text-[10px] font-mono text-slate-400 block"><?= htmlspecialchars($c['agent_code']) ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-semibold block <?= $c['total_balance_pesewas'] > 0 ? 'text-slate-900' : 'text-slate-400' ?>">
                                        <?= formatMoney((int)$c['total_balance_pesewas']) ?>
                                    </span>
                                    <span class="text-[10px] text-slate-400"><?= (int)$c['total_loans_count'] ?> loans</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $c['id'] ?>" class="px-2 py-1 bg-purple-50 hover:bg-purple-100 text-slate-800 rounded text-[11px] font-medium transition">
                                            View
                                        </a>
                                        <a href="<?= BASE_URL ?>/admin/loans.php?action=new&customer_id=<?= $c['id'] ?>" class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-[11px] font-medium transition">
                                            + Loan
                                        </a>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
