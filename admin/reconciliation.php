<?php
/**
 * Agent Cash Reconciliation Desk - Minimalist Theme
 * Verifies Field Cash Collected vs Cash Handed In at Branch Office
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Cash Reconciliation";

$error = '';
$success = '';

$agentId = !empty($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$date = $_GET['date'] ?? date('Y-m-d');

// Handle Reconciliation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Session security token expired. Please retry.';
    } else {
        $postAgentId = (int)($_POST['agent_id'] ?? 0);
        $postDate = $_POST['reconciliation_date'] ?? date('Y-m-d');
        $expectedPesewas = (int)($_POST['expected_cash_pesewas'] ?? 0);
        $handedInInput = (float)($_POST['handed_in_cash'] ?? 0);
        $handedInPesewas = ghsToPesewas($handedInInput);
        $notes = trim($_POST['notes'] ?? '');

        if ($postAgentId <= 0) {
            $error = 'Please select a valid field agent.';
        } else {
            $discrepancyPesewas = $handedInPesewas - $expectedPesewas;
            $status = ($discrepancyPesewas === 0) ? 'verified' : 'discrepancy_flagged';

            try {
                $stmtIns = $pdo->prepare("
                    INSERT INTO cash_reconciliations (
                        agent_id, reconciliation_date, expected_cash_pesewas, handed_in_cash_pesewas,
                        discrepancy_pesewas, status, verified_by, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        expected_cash_pesewas = VALUES(expected_cash_pesewas),
                        handed_in_cash_pesewas = VALUES(handed_in_cash_pesewas),
                        discrepancy_pesewas = VALUES(discrepancy_pesewas),
                        status = VALUES(status),
                        verified_by = VALUES(verified_by),
                        notes = VALUES(notes),
                        updated_at = NOW()
                ");
                $stmtIns->execute([
                    $postAgentId,
                    $postDate,
                    $expectedPesewas,
                    $handedInPesewas,
                    $discrepancyPesewas,
                    $status,
                    currentUserId(),
                    $notes
                ]);

                logAudit(
                    $pdo, 
                    currentUserId(), 
                    'CASH_RECONCILIATION', 
                    'cash_reconciliations', 
                    null, 
                    null, 
                    [
                        'agent_id' => $postAgentId,
                        'date' => $postDate,
                        'expected' => $expectedPesewas,
                        'handed_in' => $handedInPesewas,
                        'discrepancy' => $discrepancyPesewas,
                        'status' => $status
                    ]
                );

                setFlash('success', "Cash reconciliation for " . formatDate($postDate) . " saved successfully!");
                header("Location: " . BASE_URL . "/admin/reconciliation.php?agent_id={$postAgentId}&date={$postDate}");
                exit;
            } catch (Exception $e) {
                $error = 'Error saving reconciliation: ' . $e->getMessage();
            }
        }
    }
}

// Fetch agents list
$agents = $pdo->query("SELECT id, name, staff_code FROM users WHERE role = 'agent' AND status = 'active' ORDER BY name ASC")->fetchAll();
if ($agentId <= 0 && !empty($agents)) {
    $agentId = (int)$agents[0]['id'];
}

// Compute Expected Cash collected by this agent on this date
$stmtExpected = $pdo->prepare("
    SELECT 
        COALESCE(SUM(amount_pesewas), 0) AS total_collected,
        COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_pesewas ELSE 0 END), 0) AS expected_cash,
        COALESCE(SUM(CASE WHEN payment_method = 'momo' THEN amount_pesewas ELSE 0 END), 0) AS expected_momo,
        COUNT(id) AS tx_count
    FROM payments
    WHERE agent_id = ? AND status = 'recorded' AND DATE(collection_timestamp) = ?
");
$stmtExpected->execute([$agentId, $date]);
$expectedData = $stmtExpected->fetch();

// Check if a reconciliation already exists for this agent on this date
$stmtRecon = $pdo->prepare("SELECT * FROM cash_reconciliations WHERE agent_id = ? AND reconciliation_date = ?");
$stmtRecon->execute([$agentId, $date]);
$existingRecon = $stmtRecon->fetch();

// Fetch recent reconciliation history
$recentRecons = $pdo->query("
    SELECT cr.*, u.name AS agent_name, u.staff_code, v.name AS verifier_name
    FROM cash_reconciliations cr
    JOIN users u ON cr.agent_id = u.id
    LEFT JOIN users v ON cr.verified_by = v.id
    ORDER BY cr.reconciliation_date DESC, cr.created_at DESC
    LIMIT 10
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-zinc-900 tracking-tight">Agent Cash Reconciliation</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Physical cash handed in vs system collection ledger</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-zinc-50 border border-zinc-300 text-zinc-900 text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-zinc-700"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Agent & Date Selector (Minimal) -->
    <div class="bg-white rounded-xl border border-zinc-200 p-3.5">
        <form method="GET" class="flex flex-col sm:flex-row items-center gap-2.5 text-xs">
            <div class="w-full sm:flex-1">
                <label class="block text-[10px] font-medium text-zinc-400 uppercase tracking-wider mb-1">Field Agent</label>
                <select name="agent_id" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-800 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $agentId === (int)$ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['name']) ?> (<?= htmlspecialchars($ag['staff_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label class="block text-[10px] font-medium text-zinc-400 uppercase tracking-wider mb-1">Reconciliation Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" 
                    class="px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
            </div>

            <div class="w-full sm:w-auto flex items-end">
                <button type="submit" class="w-full sm:w-auto px-3.5 py-1.5 bg-zinc-900 text-white rounded-lg text-xs font-medium hover:bg-zinc-800 transition">
                    Inspect Day
                </button>
            </div>
        </form>
    </div>

    <!-- Active Reconciliation Form Card -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Expected Cash Overview -->
        <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 pb-2 border-b border-zinc-100">
                System Records
            </h2>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-zinc-400 block text-[10px] uppercase">Total Collections</span>
                    <span class="text-lg font-bold text-zinc-900"><?= formatMoney((int)$expectedData['total_collected']) ?></span>
                    <span class="text-[10px] text-zinc-400 block"><?= (int)$expectedData['tx_count'] ?> Transactions</span>
                </div>
                <div>
                    <span class="text-zinc-400 block text-[10px] uppercase">Mobile Money (Electronic)</span>
                    <span class="text-sm font-semibold text-zinc-700"><?= formatMoney((int)$expectedData['expected_momo']) ?></span>
                </div>
                <div class="p-3.5 bg-zinc-50 rounded-lg border border-zinc-200">
                    <span class="text-zinc-500 font-medium uppercase text-[10px] block tracking-wider">
                        Target Physical Cash Hand-In
                    </span>
                    <div class="text-2xl font-bold text-zinc-900 mt-1">
                        <?= formatMoney((int)$expectedData['expected_cash']) ?>
                    </div>
                    <span class="text-[10px] text-zinc-400 block mt-0.5">
                        Cash to be handed in physically at office.
                    </span>
                </div>
            </div>
        </div>

        <!-- Hand-In Form -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-zinc-200 p-5">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 pb-3 border-b border-zinc-100">
                Physical Hand-In & Verification
            </h2>

            <form method="POST" class="space-y-4 mt-3">
                <?= csrfField() ?>
                <input type="hidden" name="agent_id" value="<?= $agentId ?>">
                <input type="hidden" name="reconciliation_date" value="<?= $date ?>">
                <input type="hidden" id="expected_cash_pesewas" name="expected_cash_pesewas" value="<?= $expectedData['expected_cash'] ?>">

                <div>
                    <label class="block text-[11px] font-medium text-zinc-700 mb-1">
                        Physical Cash Handed In at Office (<?= CURRENCY_SYMBOL ?>) *
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none font-bold text-zinc-400 text-xs">
                            <?= CURRENCY_SYMBOL ?>
                        </span>
                        <input type="number" step="0.50" min="0" id="handed_in_cash" name="handed_in_cash" required 
                            class="w-full pl-9 pr-3 py-2 bg-white border border-zinc-200 rounded-lg text-lg font-bold text-zinc-900 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                            placeholder="0.00" 
                            value="<?= $existingRecon ? pesewasToGHS((int)$existingRecon['handed_in_cash_pesewas']) : pesewasToGHS((int)$expectedData['expected_cash']) ?>">
                    </div>
                </div>

                <!-- Live Discrepancy Indicator -->
                <div id="discrepancy-box" class="p-3.5 rounded-lg border border-zinc-200 bg-zinc-50 text-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-medium text-zinc-400 block">Variance</span>
                        <span id="discrepancy-amount" class="text-base font-bold text-zinc-900">GH₵ 0.00</span>
                    </div>
                    <div>
                        <span id="discrepancy-tag" class="px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-zinc-200 text-zinc-800">
                            Balanced
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-zinc-700 mb-1">
                        Supervisor Notes (Optional)
                    </label>
                    <textarea name="notes" rows="2" 
                        class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                        placeholder="e.g. Cash counted and placed in safe."><?= htmlspecialchars($existingRecon['notes'] ?? '') ?></textarea>
                </div>

                <div>
                    <button type="submit" class="w-full py-2.5 px-4 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i> Save Reconciliation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent Reconciliations Table -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-700">Audit History (Last 10 Records)</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 text-zinc-400 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                    <tr>
                        <th class="py-2.5 px-4 font-normal">Date</th>
                        <th class="py-2.5 px-4 font-normal">Agent</th>
                        <th class="py-2.5 px-4 text-right font-normal">Expected</th>
                        <th class="py-2.5 px-4 text-right font-normal">Handed In</th>
                        <th class="py-2.5 px-4 text-right font-normal">Discrepancy</th>
                        <th class="py-2.5 px-4 font-normal">Status</th>
                        <th class="py-2.5 px-4 font-normal">Verified By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($recentRecons as $rc): ?>
                        <tr class="hover:bg-zinc-50/50 transition">
                            <td class="py-2.5 px-4 font-mono font-medium text-zinc-900">
                                <?= formatDate($rc['reconciliation_date']) ?>
                            </td>
                            <td class="py-2.5 px-4">
                                <span class="font-medium text-zinc-900 block"><?= htmlspecialchars($rc['agent_name']) ?></span>
                                <span class="text-[10px] font-mono text-zinc-400"><?= htmlspecialchars($rc['staff_code']) ?></span>
                            </td>
                            <td class="py-2.5 px-4 text-right text-zinc-700">
                                <?= formatMoney((int)$rc['expected_cash_pesewas']) ?>
                            </td>
                            <td class="py-2.5 px-4 text-right font-semibold text-zinc-900">
                                <?= formatMoney((int)$rc['handed_in_cash_pesewas']) ?>
                            </td>
                            <td class="py-2.5 px-4 text-right font-medium <?= (int)$rc['discrepancy_pesewas'] !== 0 ? 'text-zinc-900 font-bold' : 'text-zinc-400' ?>">
                                <?= formatMoney((int)$rc['discrepancy_pesewas']) ?>
                            </td>
                            <td class="py-2.5 px-4">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $rc['status'] === 'verified' ? 'bg-zinc-100 text-zinc-800' : 'bg-zinc-200 text-zinc-900 font-semibold' ?>">
                                    <?= $rc['status'] === 'verified' ? 'Verified' : 'Flagged' ?>
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-zinc-500">
                                <?= htmlspecialchars($rc['verifier_name'] ?? 'System') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const expectedPesewas = parseInt(document.getElementById('expected_cash_pesewas').value) || 0;
    const handedInput = document.getElementById('handed_in_cash');
    const discAmount = document.getElementById('discrepancy-amount');
    const discTag = document.getElementById('discrepancy-tag');

    function checkVariance() {
        const handedPesewas = Math.round((parseFloat(handedInput.value) || 0) * 100);
        const diff = handedPesewas - expectedPesewas;
        const diffGHS = (diff / 100).toFixed(2);

        discAmount.textContent = 'GH₵ ' + diffGHS;

        if (diff === 0) {
            discTag.textContent = 'Balanced';
            discTag.className = 'px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-zinc-100 text-zinc-800';
        } else if (diff < 0) {
            discTag.textContent = 'Shortage (' + Math.abs(diffGHS) + ')';
            discTag.className = 'px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-zinc-200 text-zinc-900';
        } else {
            discTag.textContent = 'Excess (+' + diffGHS + ')';
            discTag.className = 'px-2 py-0.5 rounded text-[10px] font-medium uppercase bg-zinc-100 text-zinc-700';
        }
    }

    handedInput.addEventListener('input', checkVariance);
    checkVariance();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
