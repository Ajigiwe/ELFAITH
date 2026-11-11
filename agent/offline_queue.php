<?php
/**
 * Agent Offline Queue Manager - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAgent();

$page_title = "Offline Queue & Field Sync";
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-xl mx-auto space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-bold text-zinc-900 tracking-tight">Offline Queue & Sync</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Collections stored securely on phone during offline field operations</p>
        </div>
        <div class="flex items-center gap-2">
            <button id="sync-now-btn" onclick="triggerManualSync()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs transition active:scale-[0.99] shadow-sm">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5" id="sync-icon"></i> 
                <span>Sync Now</span>
            </button>
        </div>
    </div>

    <!-- Live Status Overview Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-4 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
        <div>
            <span class="text-[10px] uppercase font-medium text-zinc-400 tracking-wider block">Network Status</span>
            <div id="device-network-status" class="font-semibold text-zinc-900 mt-0.5 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Checking...</span>
            </div>
        </div>
        <div>
            <span class="text-[10px] uppercase font-medium text-zinc-400 tracking-wider block">Queued Items</span>
            <div id="queue-total-count" class="font-bold text-zinc-900 text-sm mt-0.5">
                0 Items
            </div>
        </div>
        <div class="col-span-2 sm:col-span-1">
            <span class="text-[10px] uppercase font-medium text-zinc-400 tracking-wider block">Queued Value</span>
            <div id="queue-total-amount" class="font-bold text-zinc-900 text-sm mt-0.5">
                GH₵ 0.00
            </div>
        </div>
    </div>

    <!-- Queue List Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-4 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-700">Locally Queued Receipts</span>
            <span id="queue-count-badge" class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-zinc-100 text-zinc-800">
                0 Items
            </span>
        </div>

        <!-- Empty State -->
        <div id="queue-empty" class="text-center py-10 text-zinc-400 text-xs">
            <div class="w-10 h-10 rounded-full bg-zinc-100 text-zinc-500 flex items-center justify-center mx-auto mb-2.5">
                <i data-lucide="check" class="w-5 h-5"></i>
            </div>
            <p class="text-sm font-semibold text-zinc-900">Queue is Clear</p>
            <p class="text-xs text-zinc-400 mt-1 max-w-xs mx-auto">All collections recorded on this device have been synchronized to the central ledger.</p>
            <div class="mt-4">
                <a href="<?= BASE_URL ?>/agent/collect.php" class="inline-flex items-center gap-1 px-3 py-1.5 bg-zinc-900 text-white rounded-lg text-xs font-medium hover:bg-zinc-800 transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Record Collection
                </a>
            </div>
        </div>

        <!-- Populated List -->
        <div id="queue-list" class="divide-y divide-zinc-100 hidden">
            <!-- Dynamically populated from OfflineStorage JS -->
        </div>

        <!-- Footer Actions -->
        <div id="queue-actions-bar" class="pt-3 border-t border-zinc-100 flex items-center justify-between hidden">
            <button onclick="clearQueuePrompt()" class="text-[11px] text-zinc-400 hover:text-red-600 transition flex items-center gap-1">
                <i data-lucide="trash-2" class="w-3 h-3"></i> Clear Local Queue
            </button>
            <span class="text-[10px] text-zinc-400">Stored safely in encrypted browser storage</span>
        </div>
    </div>
</div>

<!-- Offline Receipt Modal Preview -->
<div id="offline-receipt-modal" class="hidden fixed inset-0 bg-zinc-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-zinc-200 max-w-sm w-full p-5 space-y-4 shadow-sm animate-in fade-in duration-100">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded bg-zinc-900 text-white text-[10px] font-bold flex items-center justify-center">EF</div>
                <h3 class="text-xs font-semibold text-zinc-900 uppercase tracking-wider">Offline Field Receipt</h3>
            </div>
            <button type="button" onclick="closeReceiptModal()" class="text-zinc-400 hover:text-zinc-600 p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="space-y-3 text-xs" id="receipt-modal-body">
            <!-- Populated dynamically -->
        </div>

        <div class="pt-3 border-t border-zinc-100 flex items-center justify-between">
            <button type="button" onclick="window.print()" class="px-3 py-1.5 bg-zinc-100 text-zinc-800 rounded-lg text-xs font-medium hover:bg-zinc-200 transition flex items-center gap-1">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print
            </button>
            <button type="button" onclick="closeReceiptModal()" class="px-3 py-1.5 bg-zinc-900 text-white rounded-lg text-xs font-medium hover:bg-zinc-800 transition">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    const APP_BASE_URL = '<?= BASE_URL ?>';

    function updateNetworkStatusCard() {
        const statusEl = document.getElementById('device-network-status');
        if (!statusEl) return;
        if (navigator.onLine) {
            statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span> Online (Ready to sync)';
            statusEl.className = 'font-semibold text-emerald-700 mt-0.5 flex items-center gap-1.5 text-xs';
        } else {
            statusEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-500"></span> Offline (Queuing)';
            statusEl.className = 'font-semibold text-amber-700 mt-0.5 flex items-center gap-1.5 text-xs';
        }
    }

    function renderQueue() {
        updateNetworkStatusCard();
        const items = OfflineStorage.getAll();
        const countBadge = document.getElementById('queue-count-badge');
        const emptyState = document.getElementById('queue-empty');
        const listState = document.getElementById('queue-list');
        const totalCountEl = document.getElementById('queue-total-count');
        const totalAmountEl = document.getElementById('queue-total-amount');
        const actionsBar = document.getElementById('queue-actions-bar');

        const totalPesewas = OfflineStorage.totalPesewas();
        totalCountEl.textContent = items.length + ' Items';
        totalAmountEl.textContent = 'GH₵ ' + (totalPesewas / 100).toFixed(2);
        countBadge.textContent = items.length + ' Items';

        if (items.length === 0) {
            emptyState.classList.remove('hidden');
            listState.classList.add('hidden');
            if (actionsBar) actionsBar.classList.add('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        listState.classList.remove('hidden');
        if (actionsBar) actionsBar.classList.remove('hidden');
        listState.innerHTML = '';

        items.forEach((item, idx) => {
            const row = document.createElement('div');
            row.className = 'py-3 flex items-center justify-between gap-3 text-xs';
            const shortUuid = item.uuid ? item.uuid.substring(0, 8) : 'PENDING';
            const formattedAmount = (item.amount_pesewas / 100).toFixed(2);
            
            row.innerHTML = `
                <div class="space-y-0.5">
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono text-[10px] text-zinc-400 block">UUID: ${shortUuid}</span>
                        <span class="px-1 py-0.2 rounded text-[9px] font-mono uppercase bg-amber-50 text-amber-700 border border-amber-200">Offline</span>
                    </div>
                    <span class="font-medium text-zinc-900 block">Loan #${item.loan_id} &bull; Customer #${item.customer_id}</span>
                    <span class="block text-[11px] text-zinc-400 font-mono">${item.collection_timestamp} &bull; ${item.payment_method.toUpperCase()}${item.momo_reference ? ' (' + item.momo_reference + ')' : ''}</span>
                </div>
                <div class="text-right space-y-1">
                    <span class="text-xs font-bold text-zinc-900 block">GH₵ ${formattedAmount}</span>
                    <button type="button" onclick='viewOfflineReceipt(${JSON.stringify(item)})' 
                        class="text-[10px] text-zinc-600 hover:text-zinc-900 underline font-medium">
                        Receipt &rarr;
                    </button>
                </div>
            `;
            listState.appendChild(row);
        });

        if (window.lucide) lucide.createIcons();
    }

    function viewOfflineReceipt(item) {
        const body = document.getElementById('receipt-modal-body');
        const formattedAmount = (item.amount_pesewas / 100).toFixed(2);
        body.innerHTML = `
            <div class="p-3 bg-zinc-50 rounded-lg border border-zinc-200 space-y-2">
                <div class="flex justify-between text-zinc-500 text-[11px]">
                    <span>Receipt Code:</span>
                    <span class="font-mono font-bold text-zinc-900">EF-OFFLINE-${item.uuid.substring(0, 8).toUpperCase()}</span>
                </div>
                <div class="flex justify-between text-zinc-500 text-[11px]">
                    <span>Date & Time:</span>
                    <span class="text-zinc-800">${item.collection_timestamp}</span>
                </div>
                <div class="flex justify-between text-zinc-500 text-[11px]">
                    <span>Payment Method:</span>
                    <span class="uppercase font-medium text-zinc-800">${item.payment_method}</span>
                </div>
                ${item.momo_reference ? `
                <div class="flex justify-between text-zinc-500 text-[11px]">
                    <span>MoMo Reference:</span>
                    <span class="font-mono text-zinc-800">${item.momo_reference}</span>
                </div>` : ''}
                <div class="pt-2 border-t border-zinc-200 flex justify-between items-center text-sm font-bold text-zinc-900">
                    <span>Amount Collected:</span>
                    <span class="text-base">GH₵ ${formattedAmount}</span>
                </div>
            </div>
            <p class="text-[10px] text-zinc-400 text-center">
                This receipt was captured offline and will be officially booked to the central ledger upon internet connection.
            </p>
        `;
        document.getElementById('offline-receipt-modal').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeReceiptModal() {
        document.getElementById('offline-receipt-modal').classList.add('hidden');
    }

    async function triggerManualSync() {
        const btn = document.getElementById('sync-now-btn');
        const icon = document.getElementById('sync-icon');
        btn.disabled = true;
        icon.classList.add('animate-spin');

        if (!navigator.onLine) {
            alert('Your phone/browser is currently offline. Please connect to Wi-Fi or mobile data before syncing.');
            btn.disabled = false;
            icon.classList.remove('animate-spin');
            return;
        }

        await syncPendingCollections(APP_BASE_URL);
        renderQueue();

        btn.disabled = false;
        icon.classList.remove('animate-spin');
    }

    function clearQueuePrompt() {
        if (confirm('Are you sure you want to delete locally queued collections? Unsynced payments will be permanently lost from this device.')) {
            OfflineStorage.clear();
            renderQueue();
        }
    }

    window.addEventListener('online', renderQueue);
    window.addEventListener('offline', renderQueue);
    renderQueue();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
