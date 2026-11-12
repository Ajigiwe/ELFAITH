<?php
/**
 * Agent Record Collection Interface - Progressive Web App Offline Ready
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAgent();

$agentId = currentUserId();
$page_title = "Record Collection";

$error = '';
$preselectedLoanId = (int)($_GET['loan_id'] ?? 0);
$preselectedCustomerId = (int)($_GET['customer_id'] ?? 0);
$preselectedAmount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0.0;

// Direct Server-Side POST Fallback Handler (when traditional browser submit occurs)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security session expired. Please retry.';
    } else {
        $loanId = (int)($_POST['loan_id'] ?? 0);
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $amountInput = (float)($_POST['amount'] ?? 0);
        $amountPesewas = ghsToPesewas($amountInput);
        $method = in_array($_POST['payment_method'] ?? '', ['cash', 'momo', 'bank_transfer']) ? $_POST['payment_method'] : 'cash';
        $momoRef = trim($_POST['momo_reference'] ?? '') ?: null;
        $gpsLat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
        $gpsLng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
        $note = trim($_POST['note'] ?? '') ?: null;
        $uuid = !empty($_POST['uuid']) ? trim($_POST['uuid']) : generateUuidV4();

        try {
            $result = recordPaymentTransaction($pdo, [
                'uuid' => $uuid,
                'loan_id' => $loanId,
                'customer_id' => $customerId,
                'agent_id' => $agentId,
                'amount_pesewas' => $amountPesewas,
                'payment_method' => $method,
                'momo_reference' => $momoRef,
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
                'note' => $note,
                'audit_action' => 'RECORD_COLLECTION'
            ]);

            setFlash('success', 'Collection of ' . formatMoney($amountPesewas) . ' recorded successfully!');
            header('Location: ' . $result['receipt_url']);
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch all active loans assigned to this agent for the selector
$activeLoans = [];
try {
    $stmtLoans = $pdo->prepare("
        SELECT l.id, l.loan_code, l.principal_pesewas, l.installment_amount_pesewas, l.remaining_balance_pesewas,
               c.id AS customer_id, c.customer_code, c.full_name, c.phone, c.location_landmark
        FROM loans l
        JOIN customers c ON l.customer_id = c.id
        WHERE (l.agent_id = ? OR c.assigned_agent_id = ?)
          AND l.status IN ('active', 'overdue')
        ORDER BY c.full_name ASC
    ");
    $stmtLoans->execute([$agentId, $agentId]);
    $activeLoans = $stmtLoans->fetchAll();
} catch (Exception $e) {
    // If db down, active loans will hydrate from OfflineStorage
}

$activeLoansJson = json_encode(array_map(function($l) {
    return [
        'id' => (int)$l['id'],
        'loan_code' => $l['loan_code'],
        'customer_id' => (int)$l['customer_id'],
        'customer_name' => $l['full_name'],
        'customer_phone' => $l['phone'],
        'landmark' => $l['location_landmark'],
        'installment' => pesewasToGHS($l['installment_amount_pesewas']),
        'balance' => pesewasToGHS($l['remaining_balance_pesewas']),
        'balance_pesewas' => (int)$l['remaining_balance_pesewas']
    ];
}, $activeLoans));

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-lg mx-auto space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-bold text-zinc-900 tracking-tight">Record Collection</h1>
            <p class="text-xs text-zinc-400">Repayment collection & automatic SMS receipt</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/agent/offline_queue.php" class="inline-flex items-center gap-1 text-xs text-zinc-600 bg-white border border-zinc-200 px-2.5 py-1.5 rounded-lg hover:bg-zinc-50 transition">
                <i data-lucide="cloud" class="w-3.5 h-3.5 text-zinc-400"></i> Queue
            </a>
            <a href="<?= BASE_URL ?>/agent/index.php" class="inline-flex items-center gap-1 text-xs text-zinc-600 bg-white border border-zinc-200 px-2.5 py-1.5 rounded-lg hover:bg-zinc-50 transition">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Route
            </a>
        </div>
    </div>

    <!-- Offline Active Mode Callout -->
    <div id="collect-offline-notice" class="hidden p-3 rounded-lg bg-zinc-900 text-white text-xs font-medium flex items-center justify-between gap-2 shadow-sm">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
            <span><strong>Offline Mode:</strong> Payment will be saved locally with secure cryptographic UUID & auto-synced.</span>
        </div>
        <span class="text-[10px] font-mono text-zinc-400 uppercase">Offline</span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-zinc-50 border border-zinc-300 text-zinc-900 text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-zinc-700"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Collection Form Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-4 shadow-sm">
        <form id="collection-form" method="POST" action="<?= BASE_URL ?>/agent/collect.php" class="space-y-4">
            <?= csrfField() ?>
            <!-- Client UUID for Idempotency & Offline Sync -->
            <input type="hidden" id="uuid" name="uuid" value="">
            <input type="hidden" id="customer_id" name="customer_id" value="<?= $preselectedCustomerId ?>">
            <input type="hidden" id="gps_lat" name="gps_lat" value="">
            <input type="hidden" id="gps_lng" name="gps_lng" value="">

            <!-- Loan / Borrower Selector -->
            <div>
                <label for="loan_id" class="block text-[11px] font-medium text-zinc-700 mb-1">
                    Borrower / Loan Facility *
                </label>
                <select id="loan_id" name="loan_id" required 
                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900 transition">
                    <option value="">-- Choose active borrower --</option>
                    <?php foreach ($activeLoans as $al): ?>
                        <option value="<?= $al['id'] ?>" 
                            data-customer-id="<?= $al['customer_id'] ?>"
                            data-customer-name="<?= htmlspecialchars($al['full_name']) ?>"
                            data-customer-phone="<?= htmlspecialchars($al['phone']) ?>"
                            data-landmark="<?= htmlspecialchars($al['location_landmark']) ?>"
                            data-installment="<?= pesewasToGHS($al['installment_amount_pesewas']) ?>"
                            data-balance="<?= pesewasToGHS($al['remaining_balance_pesewas']) ?>"
                            data-balance-pesewas="<?= $al['remaining_balance_pesewas'] ?>"
                            <?= ($preselectedLoanId === (int)$al['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($al['full_name']) ?> — <?= htmlspecialchars($al['loan_code']) ?> (Bal: <?= formatMoney($al['remaining_balance_pesewas']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dynamic Loan Summary Card -->
            <div id="loan-summary" class="hidden rounded-lg bg-zinc-50 border border-zinc-200 p-3 text-xs space-y-1.5">
                <div class="flex justify-between items-start">
                    <div>
                        <span id="summary-name" class="font-semibold text-zinc-900 block"></span>
                        <span id="summary-location" class="text-zinc-400 text-[11px]"></span>
                    </div>
                    <span id="summary-phone" class="font-mono text-zinc-600 font-medium"></span>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-zinc-200 text-xs">
                    <div>
                        <span class="text-zinc-400 block text-[10px] uppercase">Installment Due</span>
                        <span id="summary-installment" class="font-bold text-zinc-900 text-sm"><?= htmlspecialchars(safeCurrencySymbol()) ?> 0.00</span>
                    </div>
                    <div class="text-right">
                        <span class="text-zinc-400 block text-[10px] uppercase">Remaining Balance</span>
                        <span id="summary-balance" class="font-bold text-zinc-900 text-sm"><?= htmlspecialchars(safeCurrencySymbol()) ?> 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Repayment Amount Field -->
            <div>
                <label for="amount" class="block text-[11px] font-medium text-zinc-700 mb-1">
                    Amount Collected (<?= htmlspecialchars(safeCurrencySymbol()) ?>) *
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 font-semibold text-xs"><?= htmlspecialchars(safeCurrencySymbol()) ?></span>
                    <input type="number" step="0.50" min="0.50" id="amount" name="amount" required
                        value="<?= $preselectedAmount > 0 ? number_format($preselectedAmount, 2, '.', '') : '' ?>"
                        class="w-full pl-12 pr-3 py-2 bg-white border border-zinc-200 rounded-lg text-sm font-bold text-zinc-900 focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900 transition"
                        placeholder="0.00">
                </div>
                
                <!-- Quick Amount Presets -->
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="setPreset('full')" 
                        class="flex-1 py-1 px-2 text-xs font-medium bg-zinc-50 hover:bg-zinc-100 text-zinc-700 border border-zinc-200 rounded-lg transition active:scale-95">
                        Full Installment
                    </button>
                    <button type="button" onclick="setPreset('double')" 
                        class="flex-1 py-1 px-2 text-xs font-medium bg-zinc-50 hover:bg-zinc-100 text-zinc-700 border border-zinc-200 rounded-lg transition active:scale-95">
                        2x Installment
                    </button>
                    <button type="button" onclick="setPreset('half')" 
                        class="flex-1 py-1 px-2 text-xs font-medium bg-zinc-50 hover:bg-zinc-100 text-zinc-700 border border-zinc-200 rounded-lg transition active:scale-95">
                        Half
                    </button>
                </div>
            </div>

            <!-- Payment Method Radio Buttons -->
            <div>
                <label class="block text-[11px] font-medium text-zinc-700 mb-1.5">
                    Payment Method *
                </label>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <label class="flex items-center gap-2 p-2.5 rounded-lg border border-zinc-200 cursor-pointer hover:bg-zinc-50 transition">
                        <input type="radio" name="payment_method" value="cash" checked class="text-zinc-900 focus:ring-zinc-900">
                        <span class="font-medium text-zinc-900">Physical Cash</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-lg border border-zinc-200 cursor-pointer hover:bg-zinc-50 transition">
                        <input type="radio" name="payment_method" value="momo" class="text-zinc-900 focus:ring-zinc-900">
                        <span class="font-medium text-zinc-900">Mobile Money (MoMo)</span>
                    </label>
                </div>
            </div>

            <!-- Mobile Money Reference (Conditional) -->
            <div id="momo-ref-box" class="hidden">
                <label for="momo_reference" class="block text-[11px] font-medium text-zinc-700 mb-1">
                    MoMo Transaction Reference (Optional)
                </label>
                <input type="text" id="momo_reference" name="momo_reference" 
                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                    placeholder="e.g. 2026102912345678">
            </div>

            <!-- Notes -->
            <div>
                <label for="note" class="block text-[11px] font-medium text-zinc-700 mb-1">
                    Note (Optional)
                </label>
                <input type="text" id="note" name="note" 
                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                    placeholder="e.g. Paid at market stall">
            </div>

            <!-- GPS Status Indicator -->
            <div class="flex items-center justify-between text-[11px] text-zinc-400 pt-1">
                <span class="flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                    <span id="gps-status">GPS Optional</span>
                </span>
                <button type="button" onclick="refreshGps()" class="text-zinc-600 font-medium hover:underline">
                    Capture GPS
                </button>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submit-btn" 
                class="w-full py-2.5 px-4 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5 shadow-sm">
                Submit Collection
            </button>
        </form>
    </div>
</div>

<script>
    const COLLECT_CURRENCY_SYM = <?= json_encode(safeCurrencySymbol()) ?>;
    // 1. Initialize Client UUID using universal generator
    document.getElementById('uuid').value = typeof generateUUID === 'function' ? generateUUID() : '<?= generateUuidV4() ?>';

    // 2. Cache active loans locally in browser for offline field capability
    const initialLoans = <?= $activeLoansJson ?>;
    if (initialLoans && initialLoans.length > 0 && typeof OfflineStorage !== 'undefined') {
        OfflineStorage.cacheLoans(initialLoans);
    }

    // 3. Hydrate loans dropdown from OfflineStorage if offline or if empty
    function hydrateLoansDropdown() {
        const select = document.getElementById('loan_id');
        if (select.options.length <= 1 && typeof OfflineStorage !== 'undefined') {
            const cached = OfflineStorage.getCachedLoans();
            if (cached && cached.length > 0) {
                console.log('[PWA] Populating loans selector from offline cache:', cached.length);
                cached.forEach(al => {
                    const opt = document.createElement('option');
                    opt.value = al.id;
                    opt.dataset.customerId = al.customer_id;
                    opt.dataset.customerName = al.customer_name;
                    opt.dataset.customerPhone = al.customer_phone;
                    opt.dataset.landmark = al.landmark;
                    opt.dataset.installment = al.installment;
                    opt.dataset.balance = al.balance;
                    opt.dataset.balancePesewas = al.balance_pesewas;
                    opt.textContent = `${al.customer_name} — ${al.loan_code} (Bal: ${COLLECT_CURRENCY_SYM} ${parseFloat(al.balance).toFixed(2)})`;
                    select.appendChild(opt);
                });
            }
        }
    }
    hydrateLoansDropdown();

    // 4. Update Offline Mode notice in form
    function updateFormOfflineBanner() {
        const notice = document.getElementById('collect-offline-notice');
        if (!notice) return;
        if (!navigator.onLine) {
            notice.classList.remove('hidden');
        } else {
            notice.classList.add('hidden');
        }
    }
    window.addEventListener('online', updateFormOfflineBanner);
    window.addEventListener('offline', updateFormOfflineBanner);
    updateFormOfflineBanner();

    // 5. Toggle MoMo field
    const methodRadios = document.querySelectorAll('input[name="payment_method"]');
    const momoBox = document.getElementById('momo-ref-box');
    methodRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.value === 'momo' && radio.checked) {
                momoBox.classList.remove('hidden');
            } else {
                momoBox.classList.add('hidden');
            }
        });
    });

    // 6. Update Loan Summary when selection changes
    const loanSelect = document.getElementById('loan_id');
    const loanSummary = document.getElementById('loan-summary');
    let currentInstallment = 0;
    let currentBalance = 0;

    function updateLoanSummary() {
        const selected = loanSelect.options[loanSelect.selectedIndex];
        if (selected && selected.value) {
            document.getElementById('customer_id').value = selected.dataset.customerId;
            document.getElementById('summary-name').textContent = selected.dataset.customerName;
            document.getElementById('summary-location').textContent = selected.dataset.landmark;
            document.getElementById('summary-phone').textContent = selected.dataset.customerPhone;
            currentInstallment = parseFloat(selected.dataset.installment) || 0;
            currentBalance = parseFloat(selected.dataset.balance) || 0;

            document.getElementById('summary-installment').textContent = COLLECT_CURRENCY_SYM + ' ' + currentInstallment.toFixed(2);
            document.getElementById('summary-balance').textContent = COLLECT_CURRENCY_SYM + ' ' + currentBalance.toFixed(2);

            loanSummary.classList.remove('hidden');

            // Auto-fill amount if empty
            const amountInput = document.getElementById('amount');
            if (!amountInput.value || parseFloat(amountInput.value) <= 0) {
                amountInput.value = currentInstallment.toFixed(2);
            }
        } else {
            loanSummary.classList.add('hidden');
        }
    }

    loanSelect.addEventListener('change', updateLoanSummary);
    if (loanSelect.value) {
        updateLoanSummary();
    }

    // 7. Quick presets
    function setPreset(type) {
        if (!currentInstallment && loanSelect.value) updateLoanSummary();
        const amountInput = document.getElementById('amount');
        if (type === 'full') {
            amountInput.value = currentInstallment.toFixed(2);
        } else if (type === 'double') {
            amountInput.value = (currentInstallment * 2).toFixed(2);
        } else if (type === 'half') {
            amountInput.value = (currentInstallment / 2).toFixed(2);
        }
    }

    // 8. Non-blocking GPS Capture
    function refreshGps() {
        const gpsStatus = document.getElementById('gps-status');
        if (!gpsStatus) return;
        gpsStatus.textContent = 'Acquiring GPS...';

        if (typeof captureAgentGps === 'function') {
            captureAgentGps((lat, lng) => {
                if (lat && lng) {
                    document.getElementById('gps_lat').value = lat;
                    document.getElementById('gps_lng').value = lng;
                    gpsStatus.textContent = `GPS: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                    gpsStatus.classList.add('text-zinc-800', 'font-medium');
                } else {
                    gpsStatus.textContent = 'GPS Skipped (optional)';
                }
            });
        } else {
            gpsStatus.textContent = 'GPS Skipped (optional)';
        }
    }

    // 9. Handle Form Submit (Resilient Online AJAX + Instant Offline Fallback)
    const form = document.getElementById('collection-form');
    const submitBtn = document.getElementById('submit-btn');

    form.addEventListener('submit', async (e) => {
        const amountVal = parseFloat(document.getElementById('amount').value);
        if (isNaN(amountVal) || amountVal <= 0) {
            e.preventDefault();
            alert('Please enter a valid repayment amount.');
            return;
        }

        const buildPayload = () => ({
            uuid: document.getElementById('uuid').value || (typeof generateUUID === 'function' ? generateUUID() : ''),
            loan_id: parseInt(document.getElementById('loan_id').value),
            customer_id: parseInt(document.getElementById('customer_id').value) || 0,
            amount: amountVal,
            amount_pesewas: Math.round(amountVal * 100),
            payment_method: document.querySelector('input[name="payment_method"]:checked').value,
            momo_reference: document.getElementById('momo_reference').value,
            gps_lat: document.getElementById('gps_lat').value,
            gps_lng: document.getElementById('gps_lng').value,
            note: document.getElementById('note').value,
            collection_timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
        });

        const saveOfflineAndRedirect = (payload) => {
            if (typeof OfflineStorage !== 'undefined') {
                OfflineStorage.add(payload);
            }
            alert('✓ Payment Safely Saved Offline!\n\nReceipt # EF-OFFLINE-' + payload.uuid.substring(0, 8).toUpperCase() + '\nAmount: ' + COLLECT_CURRENCY_SYM + ' ' + (payload.amount_pesewas / 100).toFixed(2) + '\n\nQueued locally on your phone. It will automatically synchronize as soon as internet connection is restored.');
            window.location.href = '<?= BASE_URL ?>/agent/offline_queue.php';
        };

        // IF OFFLINE: Queue immediately
        if (!navigator.onLine) {
            e.preventDefault();
            const payload = buildPayload();
            saveOfflineAndRedirect(payload);
            return;
        }

        // IF ONLINE: Attempt fast AJAX submission with graceful timeout fallback
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.textContent = 'Recording Collection...';

        const payload = buildPayload();

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 7000); // 7s timeout for weak mobile connections

            const res = await fetch('<?= BASE_URL ?>/api/record_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            const data = await res.json();
            if (data.success) {
                window.location.href = data.receipt_url;
            } else {
                alert('Error: ' + (data.error || 'Failed to record collection.'));
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Collection';
            }
        } catch (err) {
            console.warn('[PWA] Network disconnected or timed out during submission. Queuing offline:', err);
            saveOfflineAndRedirect(payload);
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
