<?php
/**
 * Immutable System Audit Trail - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Audit Trail";

$actionFilter = trim($_GET['action'] ?? '');

$sql = "
    SELECT a.*, u.name AS user_name, u.staff_code, u.role AS user_role
    FROM audit_log a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];
if (!empty($actionFilter)) {
    $sql .= " AND a.action = ?";
    $params[] = $actionFilter;
}
$sql .= " ORDER BY a.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch distinct actions
$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Audit Trail</h1>
            <p class="text-xs text-slate-400 mt-0.5">Immutable chronological log & forensic accountability</p>
        </div>
    </div>

    <!-- Filter Bar (Minimal) -->
    <div class="bg-white rounded-xl border border-slate-200 p-3.5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <form method="GET" class="flex items-center gap-2.5">
            <select name="action" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                <option value="">All Audited Actions</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-3.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition">
                Filter
            </button>
        </form>
        <span class="text-slate-400 text-xs"><?= count($logs) ?> Recorded Events</span>
    </div>

    <!-- Audit Table (Minimal) -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-400 uppercase font-medium text-[10px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="py-2.5 px-4 font-normal">Timestamp</th>
                        <th class="py-2.5 px-4 font-normal">Staff Member</th>
                        <th class="py-2.5 px-4 font-normal">Action</th>
                        <th class="py-2.5 px-4 font-normal">Target Record</th>
                        <th class="py-2.5 px-4 font-normal">Audit Payload</th>
                        <th class="py-2.5 px-4 font-normal">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                No audit events recorded.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-purple-50/30 transition">
                                <td class="py-2.5 px-4 text-slate-500 font-mono text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($l['created_at'])) ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="font-medium text-slate-900 block"><?= htmlspecialchars($l['user_name'] ?? 'System Process') ?></span>
                                    <span class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($l['staff_code'] ?? 'SYSTEM') ?></span>
                                </td>
                                <td class="py-2.5 px-4 font-mono">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-mono uppercase bg-purple-50 text-slate-800">
                                        <?= htmlspecialchars($l['action']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 font-mono text-slate-600">
                                    <?= htmlspecialchars($l['table_name'] ?? '—') ?>
                                    <?php if ($l['record_id']): ?>
                                        <span class="text-slate-400">#<?= $l['record_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 max-w-md font-mono text-[10px] text-slate-500 truncate">
                                    <?= htmlspecialchars($l['new_values'] ?? $l['old_values'] ?? '—') ?>
                                </td>
                                <td class="py-2.5 px-4 font-mono text-[11px] text-slate-400">
                                    <?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
