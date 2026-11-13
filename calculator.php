<?php
/**
 * Loan & Repayment Schedule Calculator (Editable Interest & Installments)
 * Accessible to both Administrators and Field Staff (Offline-Ready)
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$page_title = "Loan Calculator";
$role = currentUserRole();
$backUrl = isAdmin() ? BASE_URL . '/admin/loans.php' : BASE_URL . '/agent/index.php';
$defaultRateFormatted = number_format(DEFAULT_INTEREST_RATE, 2, '.', '');
$defaultRateShort = rtrim(rtrim($defaultRateFormatted, '0'), '.');

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Page Header -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">Loan Calculator</h1>
            <p class="text-xs text-blue-600 font-medium mt-0.5">Instant repayment projection with editable interest rate &amp; installments (works online &amp; offline)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="copyQuoteSummary()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-zinc-300 hover:bg-zinc-100 text-black font-medium text-xs rounded-lg transition">
                <i data-lucide="copy" class="w-3.5 h-3.5 text-zinc-700"></i> Copy Quote
            </button>
            <button type="button" onclick="window.print()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-zinc-300 hover:bg-zinc-100 text-black font-medium text-xs rounded-lg transition">
                <i data-lucide="printer" class="w-3.5 h-3.5 text-zinc-700"></i> Print
            </button>
            <a href="<?= $backUrl ?>" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition shadow-sm">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back
            </a>
        </div>
    </div>

    <!-- Print-only Header -->
    <div class="print-only pb-4 border-b border-zinc-300 mb-4">
        <div class="flex items-center gap-3">
            <?= renderCompanyLogo('print') ?>
            <div>
                <h1 class="text-base font-bold uppercase text-black"><?= htmlspecialchars(COMPANY_NAME) ?> — Loan Repayment Quotation</h1>
                <p class="text-xs text-zinc-600"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_REGION) ?> &bull; Generated <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- LEFT COLUMN: Calculator Inputs -->
        <div class="no-print lg:col-span-5 bg-white rounded-xl border border-zinc-200 border-t-2 border-t-blue-600 p-5 space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-200">
                <h2 class="text-xs font-bold uppercase tracking-wider text-black flex items-center gap-1.5">
                    <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 text-blue-600"></i> Loan Parameters
                </h2>
                <span id="calc-rate-badge" class="px-2 py-0.5 rounded text-[10px] font-semibold bg-black text-white"><?= htmlspecialchars($defaultRateShort) ?>% Rate</span>
            </div>

            <!-- Principal Input & Presets -->
            <div class="space-y-2">
                <label for="calc-principal" class="block text-[11px] font-medium text-zinc-800">
                    Principal Amount (<?= htmlspecialchars(safeCurrencySymbol()) ?>) *
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none font-bold text-blue-600 text-xs">
                        <?= htmlspecialchars(safeCurrencySymbol()) ?>
                    </span>
                    <input type="number" id="calc-principal" min="1" step="0.01" value="1000" oninput="onParameterInput()"
                        class="w-full pl-11 pr-3 py-2 bg-white border border-zinc-300 rounded-lg text-sm font-bold text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                </div>
                <div class="grid grid-cols-5 gap-1.5">
                    <button type="button" onclick="setPrincipal(500)" class="py-1 text-[11px] font-medium bg-blue-50/70 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-200 rounded transition">500</button>
                    <button type="button" onclick="setPrincipal(1000)" class="py-1 text-[11px] font-medium bg-blue-50/70 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-200 rounded transition">1,000</button>
                    <button type="button" onclick="setPrincipal(2000)" class="py-1 text-[11px] font-medium bg-blue-50/70 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-200 rounded transition">2,000</button>
                    <button type="button" onclick="setPrincipal(5000)" class="py-1 text-[11px] font-medium bg-blue-50/70 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-200 rounded transition">5,000</button>
                    <button type="button" onclick="setPrincipal(10000)" class="py-1 text-[11px] font-medium bg-blue-50/70 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-200 rounded transition">10,000</button>
                </div>
            </div>

            <!-- Editable Interest Rate Input -->
            <div class="p-3 rounded-lg bg-zinc-50 border border-zinc-200 flex items-center justify-between gap-3 text-xs">
                <div>
                    <label for="calc-interest-rate" class="block text-[11px] font-semibold text-black">Interest Rate (% Flat) *</label>
                    <span class="text-[10px] text-blue-600 font-medium">Default policy rate: <?= htmlspecialchars($defaultRateFormatted) ?>%</span>
                </div>
                <div class="relative w-28 shrink-0">
                    <input type="number" id="calc-interest-rate" min="0" max="500" step="0.01"
                        value="<?= htmlspecialchars($defaultRateFormatted) ?>"
                        oninput="onParameterInput()"
                        class="w-full pl-3 pr-7 py-1.5 bg-white border border-zinc-300 rounded-lg text-sm font-bold text-black text-right focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                    <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none font-semibold text-zinc-600 text-xs">%</span>
                </div>
            </div>

            <!-- Quick Term Presets -->
            <div class="space-y-1.5">
                <span class="block text-[11px] font-medium text-zinc-800">Quick Plan Presets</span>
                <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                    <button type="button" onclick="applyPlan(22, 'days', 'daily')" class="p-2 text-left rounded-lg border border-blue-200/80 bg-blue-50/40 hover:bg-blue-600 hover:text-white group transition">
                        <span class="font-semibold text-black group-hover:text-white block">22 Days Daily</span>
                        <span class="text-[10px] text-zinc-500 group-hover:text-blue-100">Mon–Sat collection cycle</span>
                    </button>
                    <button type="button" onclick="applyPlan(1, 'months', 'daily')" class="p-2 text-left rounded-lg border border-blue-200/80 bg-blue-50/40 hover:bg-blue-600 hover:text-white group transition">
                        <span class="font-semibold text-black group-hover:text-white block">1 Month Daily</span>
                        <span class="text-[10px] text-zinc-500 group-hover:text-blue-100">22 working days</span>
                    </button>
                    <button type="button" onclick="applyPlan(4, 'weeks', 'weekly')" class="p-2 text-left rounded-lg border border-blue-200/80 bg-blue-50/40 hover:bg-blue-600 hover:text-white group transition">
                        <span class="font-semibold text-black group-hover:text-white block">4 Weeks Weekly</span>
                        <span class="text-[10px] text-zinc-500 group-hover:text-blue-100">4 weekly installments</span>
                    </button>
                    <button type="button" onclick="applyPlan(3, 'months', 'monthly')" class="p-2 text-left rounded-lg border border-blue-200/80 bg-blue-50/40 hover:bg-blue-600 hover:text-white group transition">
                        <span class="font-semibold text-black group-hover:text-white block">3 Months Monthly</span>
                        <span class="text-[10px] text-zinc-500 group-hover:text-blue-100">3 monthly installments</span>
                    </button>
                </div>
            </div>

            <!-- Term Duration & Period -->
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <label for="calc-duration" class="block text-[11px] font-medium text-zinc-800 mb-1">Term Duration *</label>
                    <div class="flex gap-1.5">
                        <input type="number" id="calc-duration" min="1" max="1095" value="22" oninput="onParameterInput()"
                            class="w-16 px-2.5 py-1.5 bg-white border border-zinc-300 rounded-lg text-xs font-semibold text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                        <select id="calc-period" onchange="onParameterInput()"
                            class="flex-1 px-2.5 py-1.5 bg-white border border-zinc-300 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                            <option value="days" selected>Days</option>
                            <option value="weeks">Weeks</option>
                            <option value="months">Months</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="calc-frequency" class="block text-[11px] font-medium text-zinc-800 mb-1">Repayment Frequency *</label>
                    <select id="calc-frequency" onchange="onParameterInput()"
                        class="w-full px-2.5 py-1.5 bg-white border border-zinc-300 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                        <option value="daily" selected>Daily (Mon–Sat)</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
            </div>

            <!-- First Due Date -->
            <div>
                <label for="calc-start-date" class="block text-[11px] font-medium text-zinc-800 mb-1">First Repayment Due Date</label>
                <input type="date" id="calc-start-date" value="<?= date('Y-m-d') ?>" onchange="calculateLoan(customInstallmentActive)"
                    class="w-full px-3 py-1.5 bg-white border border-zinc-300 rounded-lg text-xs font-medium text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
            </div>

            <?php if ($role === 'admin'): ?>
                <div class="pt-2 border-t border-zinc-200">
                    <a id="admin-issue-loan-link" href="<?= BASE_URL ?>/admin/loans.php?action=new"
                        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition flex items-center justify-center gap-1.5 shadow-sm">
                        <span>Proceed to Disburse This Loan</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: Summary Breakdown & Repayment Schedule -->
        <div class="lg:col-span-7 space-y-4">
            <!-- Top Summary Metrics -->
            <div class="bg-white rounded-xl border border-zinc-200 border-t-2 border-t-black p-5 space-y-4 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-200">
                    <span class="text-xs font-bold uppercase tracking-wider text-black">Repayment Summary (<span id="res-summary-rate"><?= htmlspecialchars($defaultRateShort) ?></span>% Interest)</span>
                    <span id="res-installments-badge" class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-black text-white">22 Installments</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-start">
                    <div>
                        <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider block">Principal</span>
                        <div id="res-principal" class="text-base font-bold text-black mt-0.5"><?= htmlspecialchars(safeCurrencySymbol()) ?> 1,000.00</div>
                    </div>
                    <div>
                        <span class="text-[10px] font-medium text-zinc-600 uppercase tracking-wider block">Interest (<span id="res-interest-rate-label"><?= htmlspecialchars($defaultRateShort) ?></span>%)</span>
                        <div id="res-interest" class="text-base font-bold text-black mt-0.5"><?= htmlspecialchars(safeCurrencySymbol()) ?> 100.00</div>
                    </div>
                    <div>
                        <span class="text-[10px] font-medium text-blue-600 uppercase tracking-wider block">Total Payable</span>
                        <div id="res-total" class="text-base font-bold text-blue-700 mt-0.5"><?= htmlspecialchars(safeCurrencySymbol()) ?> 1,100.00</div>
                    </div>
                    <div>
                        <label for="calc-installment" class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider block">Installment</label>
                        <div class="relative mt-0.5">
                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none font-bold text-blue-600 text-[11px]">
                                <?= htmlspecialchars(safeCurrencySymbol()) ?>
                            </span>
                            <input type="number" id="calc-installment" min="0.01" step="0.01" value="50.00"
                                oninput="onInstallmentInput()"
                                class="w-full pl-9 pr-1.5 py-0.5 bg-white border border-zinc-300 rounded-lg text-sm font-bold text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                        </div>
                        <div class="flex items-center justify-between gap-1 mt-1">
                            <span id="res-freq-label" class="text-[10px] text-zinc-500 leading-tight">per day (Mon–Sat)</span>
                            <button type="button" id="installment-solve-toggle" onclick="toggleInstallmentSolveMode()"
                                title="Switch whether editing installment adjusts Term or Principal"
                                class="no-print text-[9px] font-medium px-1.5 py-0.5 rounded bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-200 transition shrink-0">
                                ⇄ Term
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-zinc-600">
                    <span>First Due: <strong id="res-first-date" class="text-black">—</strong></span>
                    <span>Maturity Date: <strong id="res-maturity-date" class="text-blue-700">—</strong></span>
                </div>
            </div>

            <!-- Full Schedule Breakdown Table -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden shadow-sm">
                <div class="px-5 py-3 border-b border-zinc-200 bg-zinc-50 flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black">Repayment Schedule Preview</h2>
                    <span id="schedule-note" class="text-[11px] text-zinc-500">Exact pesewa-safe breakdown</span>
                </div>

                <div class="overflow-x-auto max-h-[460px] overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-100/80 text-zinc-700 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-200 sticky top-0">
                            <tr>
                                <th class="py-2.5 px-4 font-normal">#</th>
                                <th class="py-2.5 px-4 font-normal">Due Date</th>
                                <th class="py-2.5 px-4 text-right font-normal">Principal</th>
                                <th class="py-2.5 px-4 text-right font-normal">Interest</th>
                                <th class="py-2.5 px-4 text-right font-normal">Installment</th>
                                <th class="py-2.5 px-4 text-right font-normal">Balance Left</th>
                            </tr>
                        </thead>
                        <tbody id="schedule-tbody" class="divide-y divide-zinc-100">
                            <!-- Populated cleanly via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CURRENCY_SYM = <?= json_encode(safeCurrencySymbol()) ?>;
    const BASE_APP_URL = <?= json_encode(BASE_URL) ?>;
    let lastCalculationSummary = '';
    let installmentSolveMode = 'term'; // 'term' or 'principal'
    let customInstallmentActive = false;
    let customInstallmentPesewas = 0;

    function formatMoneyGHS(pesewas) {
        return CURRENCY_SYM + ' ' + (pesewas / 100).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatRateDisplay(rate) {
        return Number(rate.toFixed(2)).toString();
    }

    function formatShortDate(dateObj) {
        return dateObj.toLocaleDateString('en-GB', {
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatISODate(dateObj) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function getConfiguredNumInstallments(duration, period, freq) {
        let numInstallments = duration;
        if (period === 'months') {
            if (freq === 'daily') numInstallments = duration * 22;
            else if (freq === 'weekly') numInstallments = duration * 4;
            else numInstallments = duration;
        } else if (period === 'weeks') {
            if (freq === 'daily') numInstallments = duration * 6;
            else numInstallments = duration;
        }
        return Math.min(1095, Math.max(1, numInstallments));
    }

    function setPrincipal(amt) {
        document.getElementById('calc-principal').value = amt;
        onParameterInput();
    }

    function applyPlan(duration, period, freq) {
        document.getElementById('calc-duration').value = duration;
        document.getElementById('calc-period').value = period;
        document.getElementById('calc-frequency').value = freq;
        onParameterInput();
    }

    function toggleInstallmentSolveMode() {
        installmentSolveMode = (installmentSolveMode === 'term') ? 'principal' : 'term';
        const btn = document.getElementById('installment-solve-toggle');
        if (btn) {
            btn.textContent = (installmentSolveMode === 'term') ? '⇄ Term' : '⇄ Principal';
        }
        onInstallmentInput();
    }

    function onParameterInput() {
        customInstallmentActive = false;
        customInstallmentPesewas = 0;
        calculateLoan(false);
    }

    function onInstallmentInput() {
        const instInput = document.getElementById('calc-installment');
        const instFloat = parseFloat(instInput.value);
        if (!instFloat || instFloat <= 0) {
            return;
        }

        const interestRate = Math.max(0, parseFloat(document.getElementById('calc-interest-rate').value) || 0);
        const freq = document.getElementById('calc-frequency').value;
        const instPesewas = Math.round(instFloat * 100);

        if (installmentSolveMode === 'principal') {
            const duration = Math.max(1, parseInt(document.getElementById('calc-duration').value) || 1);
            const period = document.getElementById('calc-period').value;
            const numInstallments = getConfiguredNumInstallments(duration, period, freq);

            const targetTotalPesewas = instPesewas * numInstallments;
            const newPrincipalPesewas = Math.round(targetTotalPesewas / (1 + (interestRate / 100)));
            document.getElementById('calc-principal').value = (newPrincipalPesewas / 100).toFixed(2);

            customInstallmentActive = true;
            customInstallmentPesewas = instPesewas;
            calculateLoan(true, true);
        } else {
            const principalFloat = Math.max(0, parseFloat(document.getElementById('calc-principal').value) || 0);
            const principalPesewas = Math.round(principalFloat * 100);
            const interestPesewas = Math.round(principalPesewas * (interestRate / 100));
            const totalPayablePesewas = principalPesewas + interestPesewas;

            const newInstallments = totalPayablePesewas > 0
                ? Math.min(1095, Math.max(1, Math.ceil(totalPayablePesewas / instPesewas)))
                : 1;

            document.getElementById('calc-duration').value = newInstallments;
            document.getElementById('calc-period').value = (freq === 'daily') ? 'days' : (freq === 'weekly' ? 'weeks' : 'months');

            customInstallmentActive = true;
            customInstallmentPesewas = instPesewas;
            calculateLoan(true, true);
        }
    }

    function calculateLoan(useCustomInstallment = false, preserveInstallmentInput = false) {
        const principalFloat = Math.max(0, parseFloat(document.getElementById('calc-principal').value) || 0);
        const interestRate = Math.max(0, parseFloat(document.getElementById('calc-interest-rate').value) || 0);
        const duration = Math.max(1, parseInt(document.getElementById('calc-duration').value) || 1);
        const period = document.getElementById('calc-period').value;
        const freq = document.getElementById('calc-frequency').value;
        const startDateStr = document.getElementById('calc-start-date').value || formatISODate(new Date());

        let numInstallments = getConfiguredNumInstallments(duration, period, freq);

        const principalPesewas = Math.round(principalFloat * 100);
        const interestPesewas = Math.round(principalPesewas * (interestRate / 100));
        const totalPayablePesewas = principalPesewas + interestPesewas;

        let activeCustomInst = false;
        let displayInstallmentPesewas = 0;

        if (useCustomInstallment && customInstallmentPesewas > 0 && totalPayablePesewas > 0) {
            activeCustomInst = true;
            if (installmentSolveMode === 'term') {
                numInstallments = Math.min(1095, Math.max(1, Math.ceil(totalPayablePesewas / customInstallmentPesewas)));
            }
            displayInstallmentPesewas = Math.min(totalPayablePesewas, customInstallmentPesewas);
        } else {
            displayInstallmentPesewas = Math.ceil(totalPayablePesewas / numInstallments);
        }

        if (!preserveInstallmentInput) {
            document.getElementById('calc-installment').value = (displayInstallmentPesewas / 100).toFixed(2);
        }

        const principalPer = Math.floor(principalPesewas / numInstallments);
        const interestPer = Math.floor(interestPesewas / numInstallments);

        // Parse date in local midnight to avoid timezone drift
        const parts = startDateStr.split('-');
        let currentDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        if (isNaN(currentDate.getTime())) currentDate = new Date();

        // Match server generateLoanSchedule(): start on chosen date, skip Sundays on daily steps
        const firstDateFormatted = formatShortDate(currentDate);

        let accumPrincipal = 0;
        let accumInterest = 0;
        let balanceLeft = totalPayablePesewas;
        let maturityDateObj = new Date(currentDate.getTime());
        let finalInstallmentPesewas = displayInstallmentPesewas;

        const tbody = document.getElementById('schedule-tbody');
        tbody.innerHTML = '';

        const maxRenderedRows = 500;

        for (let i = 1; i <= numInstallments; i++) {
            let pPortion = 0;
            let iPortion = 0;

            if (i === numInstallments) {
                pPortion = principalPesewas - accumPrincipal;
                iPortion = interestPesewas - accumInterest;
            } else if (activeCustomInst) {
                const remainingTotal = totalPayablePesewas - (accumPrincipal + accumInterest);
                const targetRow = Math.min(displayInstallmentPesewas, remainingTotal);
                pPortion = Math.min(
                    principalPesewas - accumPrincipal,
                    totalPayablePesewas > 0 ? Math.round(targetRow * (principalPesewas / totalPayablePesewas)) : 0
                );
                iPortion = Math.min(interestPesewas - accumInterest, Math.max(0, targetRow - pPortion));
                if (pPortion + iPortion < targetRow) {
                    const shortfall = targetRow - (pPortion + iPortion);
                    const extraP = Math.min((principalPesewas - accumPrincipal) - pPortion, shortfall);
                    pPortion += extraP;
                    const extraI = Math.min((interestPesewas - accumInterest) - iPortion, shortfall - extraP);
                    iPortion += extraI;
                }
                accumPrincipal += pPortion;
                accumInterest += iPortion;
            } else {
                pPortion = principalPer;
                iPortion = interestPer;
                accumPrincipal += pPortion;
                accumInterest += iPortion;
            }

            const instTotal = pPortion + iPortion;
            if (i === numInstallments) {
                finalInstallmentPesewas = instTotal;
            }
            balanceLeft = Math.max(0, balanceLeft - instTotal);
            maturityDateObj = new Date(currentDate.getTime());

            if (i <= maxRenderedRows) {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-blue-50/30 transition';
                tr.innerHTML = `
                    <td class="py-2 px-4 font-mono text-zinc-600">${i}</td>
                    <td class="py-2 px-4 font-medium text-black">${formatShortDate(currentDate)}</td>
                    <td class="py-2 px-4 text-right text-zinc-700">${formatMoneyGHS(pPortion)}</td>
                    <td class="py-2 px-4 text-right text-zinc-800">${formatMoneyGHS(iPortion)}</td>
                    <td class="py-2 px-4 text-right font-semibold text-blue-700">${formatMoneyGHS(instTotal)}</td>
                    <td class="py-2 px-4 text-right font-mono text-zinc-500">${formatMoneyGHS(balanceLeft)}</td>
                `;
                tbody.appendChild(tr);
            }

            // Advance date for next installment
            if (freq === 'daily') {
                currentDate.setDate(currentDate.getDate() + 1);
                if (currentDate.getDay() === 0) {
                    currentDate.setDate(currentDate.getDate() + 1); // Skip Sunday
                }
            } else if (freq === 'weekly') {
                currentDate.setDate(currentDate.getDate() + 7);
            } else if (freq === 'monthly') {
                currentDate.setMonth(currentDate.getMonth() + 1);
            }
        }

        const scheduleNote = document.getElementById('schedule-note');
        if (scheduleNote) {
            scheduleNote.textContent = (numInstallments > maxRenderedRows)
                ? `Showing first ${maxRenderedRows} of ${numInstallments} installments`
                : 'Exact pesewa-safe breakdown';
        }

        // Update Summary Display & Rate Badges
        const rateStr = formatRateDisplay(interestRate);
        document.getElementById('calc-rate-badge').textContent = `${rateStr}% Rate`;
        document.getElementById('res-summary-rate').textContent = rateStr;
        document.getElementById('res-interest-rate-label').textContent = rateStr;

        document.getElementById('res-principal').textContent = formatMoneyGHS(principalPesewas);
        document.getElementById('res-interest').textContent = formatMoneyGHS(interestPesewas);
        document.getElementById('res-total').textContent = formatMoneyGHS(totalPayablePesewas);
        document.getElementById('res-installments-badge').textContent = `${numInstallments} Installments`;

        const freqLabels = {
            daily: 'per day (Mon–Sat)',
            weekly: 'per week',
            monthly: 'per month'
        };
        document.getElementById('res-freq-label').textContent = freqLabels[freq] || '';
        document.getElementById('res-first-date').textContent = firstDateFormatted;
        document.getElementById('res-maturity-date').textContent = formatShortDate(maturityDateObj);

        // Update Admin Disburse shortcut link if visible
        const issueLink = document.getElementById('admin-issue-loan-link');
        if (issueLink) {
            const params = new URLSearchParams({
                action: 'new',
                principal: (principalPesewas / 100).toFixed(2),
                interest_rate: interestRate.toFixed(2),
                term_duration: duration,
                term_period: period,
                repayment_frequency: freq,
                first_due_date: startDateStr
            });
            if (activeCustomInst) {
                params.set('custom_installment', (displayInstallmentPesewas / 100).toFixed(2));
            }
            issueLink.href = `${BASE_APP_URL}/admin/loans.php?${params.toString()}`;
        }

        // Prepare clipboard summary
        const finalDiffers = activeCustomInst && finalInstallmentPesewas !== displayInstallmentPesewas;
        const instSummaryLine = finalDiffers
            ? `${formatMoneyGHS(displayInstallmentPesewas)} ${freqLabels[freq]} (final: ${formatMoneyGHS(finalInstallmentPesewas)})`
            : `${formatMoneyGHS(displayInstallmentPesewas)} ${freqLabels[freq]}`;

        lastCalculationSummary =
            `EL-FAITH MICROFINANCE — Loan Quote\n` +
            `Principal: ${formatMoneyGHS(principalPesewas)}\n` +
            `Interest (${rateStr}%): ${formatMoneyGHS(interestPesewas)}\n` +
            `Total Payable: ${formatMoneyGHS(totalPayablePesewas)}\n` +
            `Term: ${numInstallments} installments (${freq})\n` +
            `Installment: ${instSummaryLine}\n` +
            `Start Date: ${firstDateFormatted}\n` +
            `Maturity Date: ${formatShortDate(maturityDateObj)}`;
    }

    function copyQuoteSummary() {
        if (!lastCalculationSummary) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(lastCalculationSummary).then(() => {
                if (typeof showToast === 'function') {
                    showToast('✓ Loan quote copied to clipboard!', 'success');
                } else {
                    alert('Loan quote copied to clipboard!');
                }
            }).catch(() => fallbackCopy(lastCalculationSummary));
        } else {
            fallbackCopy(lastCalculationSummary);
        }
    }

    function fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        if (typeof showToast === 'function') {
            showToast('✓ Loan quote copied to clipboard!', 'success');
        } else {
            alert('Loan quote copied to clipboard!');
        }
    }

    // Run calculation on initial load
    calculateLoan(false);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
