<?php
/**
 * Official Payment Receipt View & Print Page - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$paymentId = (int)($_GET['id'] ?? 0);
$receiptNo = trim($_GET['receipt'] ?? '');

$stmt = null;
if ($paymentId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.full_name AS customer_name, c.phone AS customer_phone, c.residential_address, c.location_landmark,
               l.loan_code, l.total_payable_pesewas, l.remaining_balance_pesewas, l.total_paid_pesewas,
               u.name AS agent_name, u.staff_code AS agent_staff_code
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        JOIN loans l ON p.loan_id = l.id
        JOIN users u ON p.agent_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$paymentId]);
} elseif (!empty($receiptNo)) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.full_name AS customer_name, c.phone AS customer_phone, c.residential_address, c.location_landmark,
               l.loan_code, l.total_payable_pesewas, l.remaining_balance_pesewas, l.total_paid_pesewas,
               u.name AS agent_name, u.staff_code AS agent_staff_code
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        JOIN loans l ON p.loan_id = l.id
        JOIN users u ON p.agent_id = u.id
        WHERE p.receipt_number = ?
    ");
    $stmt->execute([$receiptNo]);
}

$payment = $stmt ? $stmt->fetch() : null;

if (!$payment) {
    die("Receipt not found or unauthorized access.");
}

$page_title = "Receipt " . $payment['receipt_number'];
$amountGHS = formatMoney($payment['amount_pesewas']);
$balanceGHS = formatMoney($payment['remaining_balance_pesewas']);

// Build WhatsApp text
$waMsg = urlencode("EL-FAITH MICROFINANCE RECEIPT\nReceipt No: {$payment['receipt_number']}\nLoan: {$payment['loan_code']}\nCustomer: {$payment['customer_name']}\nAmount Paid: {$amountGHS}\nMethod: " . strtoupper($payment['payment_method']) . "\nRemaining Balance: {$balanceGHS}\nDate: " . date('d M Y, h:i A', strtotime($payment['collection_timestamp'])) . "\nAgent: {$payment['agent_name']}\nThank you for choosing EL-FAITH!");
$cleanPhone = preg_replace('/[^0-9]/', '', $payment['customer_phone']);
$waUrl = "https://api.whatsapp.com/send?phone={$cleanPhone}&text={$waMsg}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($payment['receipt_number']) ?> — <?= htmlspecialchars(COMPANY_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(getFaviconUrl()) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(getCompanyLogoUrl() ?: (BASE_URL . '/assets/icons/apple-touch-icon.png')) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .receipt-card { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen py-8 px-4 flex flex-col items-center justify-center font-sans text-slate-900 antialiased">

    <!-- Action Toolbar (Hidden in Print) -->
    <div class="no-print max-w-sm w-full mb-4 flex items-center justify-between">
        <a href="javascript:history.back()" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-700 hover:text-purple-700 bg-white px-3 py-1.5 rounded-lg border border-slate-200 hover:border-purple-200 transition">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back
        </a>
        <div class="flex items-center gap-2">
            <a href="<?= $waUrl ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-medium text-purple-700 bg-white hover:bg-purple-50 border border-purple-200 px-3 py-1.5 rounded-lg transition">
                <i data-lucide="share-2" class="w-3.5 h-3.5"></i> WhatsApp
            </a>
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded-lg transition shadow-sm">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print
            </button>
        </div>
    </div>

    <!-- Official Receipt Container -->
    <div class="receipt-card max-w-sm w-full bg-white rounded-xl border border-purple-100 border-t-4 border-t-purple-600 p-6 sm:p-7 space-y-4 shadow-sm">
        <!-- Brand Header -->
        <div class="text-center pb-4 border-b border-slate-100">
            <?= renderCompanyLogo('receipt') ?>
            <h1 class="text-sm font-bold uppercase tracking-tight text-slate-900"><?= htmlspecialchars(COMPANY_NAME) ?></h1>
            <p class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars(BRANCH_NAME) ?> &bull; <?= htmlspecialchars(BRANCH_REGION) ?></p>
            <div class="mt-2 inline-block px-2.5 py-0.5 bg-purple-50 border border-purple-200 rounded text-[10px] font-semibold uppercase tracking-wider text-purple-700">
                Payment Receipt
            </div>
        </div>

        <?php if ($payment['is_reversal']): ?>
            <div class="p-2.5 bg-purple-50 border border-purple-200 rounded-lg text-center">
                <span class="text-xs font-bold text-purple-900 uppercase tracking-wider block">REVERSED TRANSACTION</span>
                <span class="text-[11px] text-purple-700 mt-0.5 block"><?= htmlspecialchars($payment['reversal_reason'] ?? 'Reversal recorded') ?></span>
            </div>
        <?php endif; ?>

        <!-- Key Meta -->
        <div class="py-3 border-b border-slate-100 grid grid-cols-2 gap-y-2 text-xs">
            <div>
                <span class="text-slate-400 block text-[10px] uppercase">Receipt Number</span>
                <span class="font-mono font-medium text-slate-900"><?= htmlspecialchars($payment['receipt_number']) ?></span>
            </div>
            <div class="text-right">
                <span class="text-slate-400 block text-[10px] uppercase">Date & Time</span>
                <span class="text-slate-700"><?= date('d M Y, h:i A', strtotime($payment['collection_timestamp'])) ?></span>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px] uppercase">Loan Facility</span>
                <span class="font-mono text-purple-700 font-medium"><?= htmlspecialchars($payment['loan_code']) ?></span>
            </div>
            <div class="text-right">
                <span class="text-slate-400 block text-[10px] uppercase">Payment Method</span>
                <span class="uppercase text-slate-800 font-medium">
                    <?= htmlspecialchars($payment['payment_method']) ?>
                    <?php if ($payment['momo_reference']): ?>
                        <span class="block text-[10px] font-mono text-slate-400"><?= htmlspecialchars($payment['momo_reference']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Borrower Information -->
        <div class="py-3 border-b border-slate-100 text-xs space-y-1">
            <span class="text-slate-400 block text-[10px] uppercase">Borrower</span>
            <div class="flex justify-between items-baseline">
                <span class="font-semibold text-slate-900"><?= htmlspecialchars($payment['customer_name']) ?></span>
                <span class="font-mono text-slate-500 text-[11px]"><?= htmlspecialchars($payment['customer_phone']) ?></span>
            </div>
            <p class="text-[11px] text-slate-400"><?= htmlspecialchars($payment['residential_address']) ?> (<?= htmlspecialchars($payment['location_landmark']) ?>)</p>
        </div>

        <!-- Amount Highlight -->
        <div class="py-4 text-center bg-blue-50/60 rounded-lg border border-blue-100">
            <span class="text-[10px] uppercase font-semibold text-purple-700 tracking-wider block">Amount Collected</span>
            <div class="text-2xl font-bold text-blue-700 tracking-tight mt-0.5">
                <?= $amountGHS ?>
            </div>
            <div class="mt-1 text-xs text-slate-600">
                Remaining Balance: <span class="font-semibold text-slate-900"><?= $balanceGHS ?></span>
            </div>
        </div>

        <!-- Agent Footer -->
        <div class="pt-1 text-xs text-slate-500 flex justify-between items-center">
            <div>
                <span class="text-[10px] text-slate-400 uppercase block">Field Agent</span>
                <span class="font-medium text-slate-800"><?= htmlspecialchars($payment['agent_name']) ?></span>
                <span class="block text-[10px] font-mono text-slate-400"><?= htmlspecialchars($payment['agent_staff_code']) ?></span>
            </div>
            <div class="text-right">
                <div class="inline-flex items-center gap-1 text-[10px] font-mono uppercase bg-purple-50 border border-purple-200 px-2 py-0.5 rounded text-purple-700">
                    <i data-lucide="check" class="w-3 h-3"></i> Logged
                </div>
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="pt-3 border-t border-slate-100 text-center text-[10px] text-slate-400">
            Thank you for your repayment. All transactions are logged into an append-only ledger.
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
