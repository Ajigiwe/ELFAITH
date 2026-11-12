<?php
/**
 * Outbound Notification Logs & Delivery Proof - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';

requireAdmin();

$page_title = "SMS Logs";

// Handle manual retry trigger
if (isset($_GET['retry_id'])) {
    $retryId = (int)$_GET['retry_id'];
    dispatchSms($pdo, $retryId);
    setFlash('success', "Notification #{$retryId} dispatched for retry!");
    header('Location: ' . BASE_URL . '/admin/notifications.php');
    exit;
}

$statusFilter = trim($_GET['status'] ?? '');
$channelFilter = trim($_GET['channel'] ?? '');

$sql = "
    SELECT n.*, c.full_name AS customer_name, p.receipt_number
    FROM notifications_log n
    LEFT JOIN customers c ON n.customer_id = c.id
    LEFT JOIN payments p ON n.payment_id = p.id
    WHERE 1=1
";
$params = [];
if (!empty($statusFilter)) {
    $sql .= " AND n.status = ?";
    $params[] = $statusFilter;
}
if (!empty($channelFilter)) {
    $sql .= " AND n.channel = ?";
    $params[] = $channelFilter;
}
$sql .= " ORDER BY n.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">SMS & Notification Logs</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Automated SMS receipts, reminders & gateway proof</p>
        </div>
    </div>

    <!-- Filter Bar (Minimal) -->
    <div class="bg-white rounded-xl border border-zinc-200 p-3.5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <form method="GET" class="flex flex-wrap items-center gap-2.5">
            <div>
                <select name="status" class="px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-950 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">All Statuses</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="queued" <?= $statusFilter === 'queued' ? 'selected' : '' ?>>Queued</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div>
                <select name="channel" class="px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-950 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    <option value="">All Channels</option>
                    <option value="sms" <?= $channelFilter === 'sms' ? 'selected' : '' ?>>SMS Only</option>
                    <option value="email" <?= $channelFilter === 'email' ? 'selected' : '' ?>>Email Only</option>
                </select>
            </div>
            <button type="submit" class="px-3.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition">
                Filter
            </button>
        </form>
        <span class="text-zinc-500 text-xs"><?= count($logs) ?> Records</span>
    </div>

    <!-- Notifications Table (Minimal) -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 text-zinc-500 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                    <tr>
                        <th class="py-2.5 px-4 font-normal">Date Time</th>
                        <th class="py-2.5 px-4 font-normal">Channel</th>
                        <th class="py-2.5 px-4 font-normal">Recipient</th>
                        <th class="py-2.5 px-4 font-normal">Message Content</th>
                        <th class="py-2.5 px-4 font-normal">Gateway</th>
                        <th class="py-2.5 px-4 text-center font-normal">Status</th>
                        <th class="py-2.5 px-4 text-center font-normal">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                No notification logs found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="py-2.5 px-4 text-zinc-600 font-mono text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($l['created_at'])) ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase bg-zinc-100 text-zinc-800">
                                        <?= strtoupper($l['channel']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 font-mono text-zinc-950">
                                    <?= htmlspecialchars($l['recipient']) ?>
                                    <?php if ($l['customer_name']): ?>
                                        <span class="block font-sans text-[10px] text-zinc-500"><?= htmlspecialchars($l['customer_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 max-w-sm text-zinc-700 text-[11px]">
                                    <?= htmlspecialchars($l['message']) ?>
                                    <?php if ($l['receipt_number']): ?>
                                        <span class="block font-mono text-[10px] text-zinc-500 mt-0.5">Ref: <?= htmlspecialchars($l['receipt_number']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-4 text-[11px] text-zinc-600">
                                    <span class="text-zinc-800 uppercase block font-medium"><?= htmlspecialchars($l['provider']) ?></span>
                                    <span class="text-zinc-500 font-mono text-[10px]"><?= htmlspecialchars($l['provider_response'] ?? 'Pending Response') ?></span>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <?php
                                        $sc = 'bg-zinc-100 text-zinc-800';
                                        if ($l['status'] === 'delivered') $sc = 'bg-zinc-100 text-black font-medium';
                                        elseif ($l['status'] === 'failed') $sc = 'bg-zinc-200 text-black font-bold';
                                        elseif ($l['status'] === 'queued') $sc = 'bg-zinc-50 text-zinc-600';
                                    ?>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] uppercase <?= $sc ?>">
                                        <?= htmlspecialchars($l['status']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <?php if ($l['status'] === 'failed' || $l['status'] === 'queued'): ?>
                                        <a href="<?= BASE_URL ?>/admin/notifications.php?retry_id=<?= $l['id'] ?>" class="text-[11px] text-black font-medium underline">
                                            Retry
                                        </a>
                                    <?php else: ?>
                                        <span class="text-zinc-300">—</span>
                                    <?php endif; ?>
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
