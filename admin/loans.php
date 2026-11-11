<?php
/**
 * Loan Origination & Portfolio Directory - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Loans";
$action = $_GET['action'] ?? 'list';
$preselectedCustomerId = (int)($_GET['customer_id'] ?? 0);

$error = '';
$success = '';

// Handle New Loan Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'new') {
    if (!verifyCsrf()) {
        $error = 'Session security token expired. Please retry.';
    } else {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $agentId = !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : null;
        $principalInput = (float)($_POST['principal'] ?? 0);
        $principalPesewas = ghsToPesewas($principalInput);
        $interestRate = (float)($_POST['interest_rate'] ?? DEFAULT_INTEREST_RATE);
        $interestMethod = in_array($_POST['interest_method'] ?? '', ['flat', 'reducing_balance']) ? $_POST['interest_method'] : 'flat';
        $termDuration = (int)($_POST['term_duration'] ?? 22);
        $termPeriod = in_array($_POST['term_period'] ?? '', ['days', 'weeks', 'months']) ? $_POST['term_period'] : 'days';
        $frequency = in_array($_POST['repayment_frequency'] ?? '', ['daily', 'weekly', 'monthly']) ? $_POST['repayment_frequency'] : 'daily';
        $firstDueDate = !empty($_POST['first_due_date']) ? $_POST['first_due_date'] : date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        if ($customerId <= 0) {
            $error = 'Please select a valid customer.';
        } elseif ($principalPesewas <= 0) {
            $error = 'Principal amount must be greater than zero.';
        } elseif ($termDuration <= 0) {
            $error = 'Term duration must be at least 1.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Generate schedule breakdown
                $calc = generateLoanSchedule(
                    $principalPesewas, 
                    $interestRate, 
                    $interestMethod, 
                    $termDuration, 
                    $termPeriod, 
                    $frequency, 
                    $firstDueDate
                );

                // 2. Generate unique sequential loan code: EF-LN-XXXX
                $stmtCount = $pdo->query("SELECT COUNT(id) FROM loans");
                $count = (int)$stmtCount->fetchColumn() + 1;
                $loanCode = 'EF-LN-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
                $uuid = generateUuidV4();

                // 3. Insert Loan record
                $stmtIns = $pdo->prepare("
                    INSERT INTO loans (
                        uuid, loan_code, customer_id, agent_id, principal_pesewas,
                        interest_rate, interest_method, interest_pesewas, total_payable_pesewas,
                        total_paid_pesewas, remaining_balance_pesewas, term_duration, term_period,
                        repayment_frequency, installment_amount_pesewas, disbursed_at,
                        first_due_date, maturity_date, status, created_by, notes, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW(), ?, ?, 'active', ?, ?, NOW()
                    )
                ");
                $stmtIns->execute([
                    $uuid,
                    $loanCode,
                    $customerId,
                    $agentId,
                    $principalPesewas,
                    $interestRate,
                    $interestMethod,
                    $calc['interest_pesewas'],
                    $calc['total_payable_pesewas'],
                    $calc['total_payable_pesewas'], // initial remaining balance equals total payable
                    $termDuration,
                    $termPeriod,
                    $frequency,
                    $calc['installment_amount_pesewas'],
                    $firstDueDate,
                    $calc['maturity_date'],
                    currentUserId(),
                    $notes
                ]);
                $loanId = (int)$pdo->lastInsertId();

                // 4. Insert each installment in repayment_schedule
                $stmtSched = $pdo->prepare("
                    INSERT INTO repayment_schedule (
                        loan_id, installment_number, due_date, principal_component_pesewas,
                        interest_component_pesewas, expected_amount_pesewas, paid_amount_pesewas,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 0, 'pending', NOW())
                ");

                foreach ($calc['schedule'] as $row) {
                    $stmtSched->execute([
                        $loanId,
                        $row['installment_number'],
                        $row['due_date'],
                        $row['principal_component_pesewas'],
                        $row['interest_component_pesewas'],
                        $row['expected_amount_pesewas']
                    ]);
                }

                // 5. Audit Log
                logAudit(
                    $pdo, 
                    currentUserId(), 
                    'LOAN_DISBURSEMENT', 
                    'loans', 
                    $loanId, 
                    null, 
                    [
                        'loan_code' => $loanCode,
                        'customer_id' => $customerId,
                        'principal_pesewas' => $principalPesewas,
                        'total_payable_pesewas' => $calc['total_payable_pesewas'],
                        'term' => "{$termDuration} {$termPeriod}"
                    ]
                );

                $pdo->commit();

                setFlash('success', "Loan facility {$loanCode} disbursed and schedule created successfully!");
                header("Location: " . BASE_URL . "/admin/loan_view.php?id={$loanId}");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Loan origination failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch Customers & Agents for the loan origination form
$customers = $pdo->query("SELECT id, full_name, customer_code, phone, assigned_agent_id FROM customers WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();
$agents = $pdo->query("SELECT id, name, staff_code FROM users WHERE role = 'agent' AND status = 'active' ORDER BY name ASC")->fetchAll();

// List view data
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT l.*, c.full_name AS customer_name, c.customer_code, c.phone AS customer_phone,
           u.name AS agent_name, u.staff_code AS agent_code
    FROM loans l
    JOIN customers c ON l.customer_id = c.id
    LEFT JOIN users u ON l.agent_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND l.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (l.loan_code LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loansList = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-zinc-900 tracking-tight">Loans</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Loan facilities, terms, amortization & portfolio balance</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($action === 'new'): ?>
                <a href="<?= BASE_URL ?>/admin/loans.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-zinc-200 hover:bg-zinc-50 text-zinc-700 font-medium text-xs rounded-lg transition">
                    <i data-lucide="list" class="w-3.5 h-3.5"></i> View All Loans
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/admin/loans.php?action=new" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Issue Loan
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-zinc-50 border border-zinc-300 text-zinc-900 text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-zinc-700"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($action === 'new'): ?>
        <!-- ORIGINATE NEW LOAN FORM -->
        <div class="max-w-xl mx-auto bg-white rounded-xl border border-zinc-200 p-5 sm:p-6">
            <div class="mb-5 pb-3 border-b border-zinc-100">
                <h2 class="text-sm font-bold text-zinc-900">Issue New Loan Facility</h2>
                <p class="text-xs text-zinc-400 mt-0.5">Calculates schedule with integer-safe pesewas allocation</p>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/admin/loans.php?action=new" class="space-y-4 text-xs">
                <?= csrfField() ?>

                <!-- Customer Selection -->
                <div>
                    <label class="block text-[11px] font-medium text-zinc-700 mb-1">Borrower *</label>
                    <select id="cust-select" name="customer_id" required 
                        class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                        <option value="">-- Choose Borrower --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-agent-id="<?= $c['assigned_agent_id'] ?>" <?= $preselectedCustomerId === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['customer_code']) ?> &bull; <?= htmlspecialchars($c['phone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Assigned Agent -->
                <div>
                    <label class="block text-[11px] font-medium text-zinc-700 mb-1">Assigned Collection Agent</label>
                    <select id="agent-select" name="agent_id" 
                        class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                        <option value="">-- Choose Agent --</option>
                        <?php foreach ($agents as $ag): ?>
                            <option value="<?= $ag['id'] ?>">
                                <?= htmlspecialchars($ag['name']) ?> (<?= htmlspecialchars($ag['staff_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Principal Amount & Interest Rate -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Principal Amount (<?= CURRENCY_SYMBOL ?>) *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none font-bold text-zinc-400 text-xs">
                                <?= CURRENCY_SYMBOL ?>
                            </span>
                            <input type="number" step="10" min="10" id="principal" name="principal" required 
                                class="w-full pl-9 pr-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs font-semibold text-zinc-900 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                                placeholder="1000.00" value="1000">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Fixed Interest Rate (%) *</label>
                        <div class="relative">
                            <input type="number" step="0.5" min="0" max="100" id="interest_rate" name="interest_rate" required 
                                class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs font-semibold text-zinc-900 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                                value="<?= DEFAULT_INTEREST_RATE ?>">
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-zinc-400 text-xs">
                                %
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Interest Method & Repayment Frequency -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Calculation Method</label>
                        <select name="interest_method" class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                            <option value="flat" selected>Flat Rate (Policy Standard)</option>
                            <option value="reducing_balance">Reducing Balance Amortization</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Repayment Frequency</label>
                        <select id="frequency" name="repayment_frequency" class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                            <option value="daily" selected>Daily Collections (Mon - Sat)</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </div>

                <!-- Term Duration & First Due Date -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Term Duration *</label>
                        <div class="flex gap-2">
                            <input type="number" min="1" id="term_duration" name="term_duration" required 
                                class="w-20 px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs font-semibold text-zinc-900 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                                value="22">
                            <select id="term_period" name="term_period" class="flex-1 px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                                <option value="days" selected>Days</option>
                                <option value="weeks">Weeks</option>
                                <option value="months">Months</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">First Repayment Due Date *</label>
                        <input type="date" name="first_due_date" required 
                            class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs font-medium text-zinc-900 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                            value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Minimal Projection Box -->
                <div class="p-3.5 rounded-lg bg-zinc-50 border border-zinc-200 text-xs space-y-1.5">
                    <span class="text-[10px] font-medium text-zinc-400 uppercase tracking-wider block">Projection Summary</span>
                    <div class="grid grid-cols-3 gap-2 pt-1 text-zinc-800">
                        <div>
                            <span class="text-zinc-400 block text-[10px] uppercase">Interest</span>
                            <span id="proj-interest" class="font-semibold text-zinc-900">GH₵ 100.00</span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px] uppercase">Total Payable</span>
                            <span id="proj-total" class="font-semibold text-zinc-900">GH₵ 1,100.00</span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px] uppercase">Est. Installment</span>
                            <span id="proj-installment" class="font-semibold text-zinc-900">GH₵ 50.00 / day</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-[11px] font-medium text-zinc-700 mb-1">Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="e.g. Working capital facility"
                        class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i> Disburse & Generate Schedule
                    </button>
                </div>
            </form>
        </div>

        <script>
            const custSelect = document.getElementById('cust-select');
            const agentSelect = document.getElementById('agent-select');
            custSelect.addEventListener('change', () => {
                const opt = custSelect.options[custSelect.selectedIndex];
                if (opt && opt.dataset.agentId) {
                    agentSelect.value = opt.dataset.agentId;
                }
            });

            function updateProjection() {
                const p = parseFloat(document.getElementById('principal').value) || 0;
                const r = parseFloat(document.getElementById('interest_rate').value) || 0;
                const d = parseInt(document.getElementById('term_duration').value) || 1;
                const freq = document.getElementById('frequency').value;

                const interest = p * (r / 100);
                const total = p + interest;
                const inst = total / d;

                document.getElementById('proj-interest').textContent = 'GH₵ ' + interest.toFixed(2);
                document.getElementById('proj-total').textContent = 'GH₵ ' + total.toFixed(2);
                document.getElementById('proj-installment').textContent = 'GH₵ ' + inst.toFixed(2) + ' / ' + freq;
            }

            document.getElementById('principal').addEventListener('input', updateProjection);
            document.getElementById('interest_rate').addEventListener('input', updateProjection);
            document.getElementById('term_duration').addEventListener('input', updateProjection);
            document.getElementById('frequency').addEventListener('change', updateProjection);
            updateProjection();
        </script>

    <?php else: ?>
        <!-- LOANS LIST TABLE -->
        <!-- Search and Filter Bar -->
        <div class="bg-white rounded-xl border border-zinc-200 p-3.5">
            <form method="GET" class="flex flex-col sm:flex-row items-center gap-2.5 text-xs">
                <div class="relative flex-1 w-full">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                    </span>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                        class="w-full pl-9 pr-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs placeholder-zinc-400 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                        placeholder="Search loan code, borrower name, phone...">
                </div>

                <div class="w-full sm:w-auto">
                    <select name="status" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-zinc-800 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid Off</option>
                        <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                        <option value="defaulted" <?= $statusFilter === 'defaulted' ? 'selected' : '' ?>>Defaulted</option>
                    </select>
                </div>

                <button type="submit" class="w-full sm:w-auto px-3.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium rounded-lg text-xs transition">
                    Filter
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-700">All Loan Facilities</h2>
                <span class="text-xs text-zinc-400"><?= count($loansList) ?> Loans</span>
            </div>

            <?php if (empty($loansList)): ?>
                <div class="p-8 text-center text-zinc-400 text-xs">
                    No loan facilities match your criteria.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-50 text-zinc-400 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                            <tr>
                                <th class="py-2.5 px-4 font-normal">Loan Code</th>
                                <th class="py-2.5 px-4 font-normal">Borrower</th>
                                <th class="py-2.5 px-4 font-normal">Agent</th>
                                <th class="py-2.5 px-4 text-right font-normal">Principal</th>
                                <th class="py-2.5 px-4 text-right font-normal">Payable</th>
                                <th class="py-2.5 px-4 text-right font-normal">Remaining</th>
                                <th class="py-2.5 px-4 font-normal">Terms</th>
                                <th class="py-2.5 px-4 font-normal">Status</th>
                                <th class="py-2.5 px-4 text-center font-normal">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php foreach ($loansList as $l): ?>
                                <tr class="hover:bg-zinc-50/50 transition">
                                    <td class="py-3 px-4 font-mono font-medium text-zinc-900">
                                        <?= htmlspecialchars($l['loan_code']) ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $l['customer_id'] ?>" class="font-medium text-zinc-900 hover:underline block">
                                            <?= htmlspecialchars($l['customer_name']) ?>
                                        </a>
                                        <span class="text-[11px] font-mono text-zinc-400"><?= htmlspecialchars($l['customer_phone']) ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-700">
                                        <?= htmlspecialchars($l['agent_name'] ?? 'Unassigned') ?>
                                    </td>
                                    <td class="py-3 px-4 text-right text-zinc-600">
                                        <?= formatMoney((int)$l['principal_pesewas']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-medium text-zinc-900">
                                        <?= formatMoney((int)$l['total_payable_pesewas']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-right font-semibold text-zinc-900">
                                        <?= formatMoney((int)$l['remaining_balance_pesewas']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-zinc-400 text-[11px]">
                                        <?= $l['term_duration'] ?> <?= $l['term_period'] ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase <?= $l['status'] === 'active' ? 'bg-zinc-100 text-zinc-800' : ($l['status'] === 'overdue' ? 'bg-zinc-200 text-zinc-900 font-semibold' : 'bg-zinc-100 text-zinc-500') ?>">
                                            <?= htmlspecialchars($l['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <a href="<?= BASE_URL ?>/admin/loan_view.php?id=<?= $l['id'] ?>" class="px-2 py-1 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 rounded text-[11px] font-medium transition">
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
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
