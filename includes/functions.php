<?php
/**
 * Shared Helper Functions & Financial Calculation Engine
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';

if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__DIR__ . '/../config/app.php', true);
    @opcache_invalidate(__DIR__ . '/../config/database.php', true);
}

/**
 * Return a validated currency symbol (never numeric garbage like 262145)
 */
function safeCurrencySymbol(): string {
    $sym = defined('CURRENCY_SYMBOL') ? trim((string)CURRENCY_SYMBOL) : 'GH₵';
    if ($sym === '' || strpos($sym, '262145') !== false || preg_match('/^[\d\s\x00-\x1F]+$/', $sym)) {
        return 'GH₵';
    }
    return $sym;
}

/**
 * Convert integer pesewas to decimal Ghana Cedis (GHS)
 */
function pesewasToGHS(int $pesewas): float {
    return round($pesewas / 100, 2);
}

/**
 * Convert Ghana Cedis (GHS) float or numeric string to integer pesewas
 */
function ghsToPesewas($ghs): int {
    $clean = str_replace([',', ' '], '', (string)$ghs);
    return (int)round((float)$clean * 100);
}

/**
 * Format pesewas into human-readable currency with symbol
 * e.g. 150000 -> "GH₵ 1,500.00"
 */
function formatMoney(int $pesewas, bool $includeSymbol = true): string {
    $formatted = number_format($pesewas / 100, 2);
    return $includeSymbol ? safeCurrencySymbol() . ' ' . $formatted : $formatted;
}

/**
 * Format a standard date/datetime
 */
function formatDate(?string $datetime, string $format = 'd M Y'): string {
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

function formatDateTime(?string $datetime): string {
    if (!$datetime) return '—';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Generate a standard RFC 4122 compliant UUID v4
 */
function generateUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * CSRF Protection Helpers
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * Flash messaging
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize user input
 */
function clean($data) {
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Append to immutable Audit Log
 */
function logAudit(PDO $pdo, ?int $userId, string $action, ?string $tableName = null, ?int $recordId = null, $oldVal = null, $newVal = null): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
        $oldJson = $oldVal ? json_encode($oldVal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newVal ? json_encode($newVal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $tableName, $recordId, $oldJson, $newJson, $ip, $agent]);
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

/**
 * Financial Calculation Engine: Generate Loan Repayment Schedule
 * Supports Flat Rate and Reducing Balance
 */
function generateLoanSchedule(
    int $principalPesewas, 
    float $interestRatePercent, 
    string $interestMethod, 
    int $termDuration, 
    string $termPeriod, 
    string $frequency, 
    string $firstDueDate,
    ?int $customInstallmentPesewas = null
): array {
    // Determine number of installments based on frequency and term duration
    $numInstallments = $termDuration; // default mapping for daily loans: duration in days = duration installments

    if ($termPeriod === 'months') {
        if ($frequency === 'daily') {
            $numInstallments = $termDuration * 22; // standard 22 collection days per month
        } elseif ($frequency === 'weekly') {
            $numInstallments = $termDuration * 4;
        } else { // monthly
            $numInstallments = $termDuration;
        }
    } elseif ($termPeriod === 'weeks') {
        if ($frequency === 'daily') {
            $numInstallments = $termDuration * 6; // 6 working days per week
        } else {
            $numInstallments = $termDuration;
        }
    }

    if ($numInstallments <= 0) $numInstallments = 1;

    // Calculate Total Interest in Pesewas
    // Standard Flat Rate policy: Interest = Principal * (Rate / 100)
    $interestPesewas = (int)round($principalPesewas * ($interestRatePercent / 100));
    $totalPayablePesewas = $principalPesewas + $interestPesewas;

    $useCustomInstallment = ($customInstallmentPesewas !== null && $customInstallmentPesewas > 0 && $totalPayablePesewas > 0);
    if ($useCustomInstallment) {
        $numInstallments = max(1, (int)ceil($totalPayablePesewas / $customInstallmentPesewas));
        $installmentAmountPesewas = min($totalPayablePesewas, $customInstallmentPesewas);
    } else {
        $installmentAmountPesewas = (int)ceil($totalPayablePesewas / $numInstallments);
    }

    $principalPerInstallment = (int)floor($principalPesewas / $numInstallments);
    $interestPerInstallment = (int)floor($interestPesewas / $numInstallments);

    $schedule = [];
    $currentDate = new DateTime($firstDueDate);

    $accumPrincipal = 0;
    $accumInterest = 0;

    for ($i = 1; $i <= $numInstallments; $i++) {
        // Last installment absorbs any pesewa rounding discrepancy
        if ($i === $numInstallments) {
            $pPortion = $principalPesewas - $accumPrincipal;
            $iPortion = $interestPesewas - $accumInterest;
            $instAmount = $pPortion + $iPortion;
        } elseif ($useCustomInstallment) {
            $remainingTotal = $totalPayablePesewas - ($accumPrincipal + $accumInterest);
            $targetRow = min($installmentAmountPesewas, $remainingTotal);
            $pPortion = min($principalPesewas - $accumPrincipal, (int)round($targetRow * ($principalPesewas / $totalPayablePesewas)));
            $iPortion = min($interestPesewas - $accumInterest, max(0, $targetRow - $pPortion));
            if ($pPortion + $iPortion < $targetRow) {
                $shortfall = $targetRow - ($pPortion + $iPortion);
                $extraP = min(($principalPesewas - $accumPrincipal) - $pPortion, $shortfall);
                $pPortion += $extraP;
                $extraI = min(($interestPesewas - $accumInterest) - $iPortion, $shortfall - $extraP);
                $iPortion += $extraI;
            }
            $instAmount = $pPortion + $iPortion;
            $accumPrincipal += $pPortion;
            $accumInterest += $iPortion;
        } else {
            $pPortion = $principalPerInstallment;
            $iPortion = $interestPerInstallment;
            $instAmount = $pPortion + $iPortion;
            $accumPrincipal += $pPortion;
            $accumInterest += $iPortion;
        }

        $schedule[] = [
            'installment_number' => $i,
            'due_date' => $currentDate->format('Y-m-d'),
            'expected_amount_pesewas' => $instAmount,
            'principal_portion_pesewas' => $pPortion,
            'interest_portion_pesewas' => $iPortion
        ];

        // Increment date for next installment
        if ($frequency === 'daily') {
            $currentDate->modify('+1 day');
            // Skip Sunday if business operates Mon-Sat
            if ($currentDate->format('w') == 0) {
                $currentDate->modify('+1 day');
            }
        } elseif ($frequency === 'weekly') {
            $currentDate->modify('+1 week');
        } elseif ($frequency === 'monthly') {
            $currentDate->modify('+1 month');
        }
    }

    return [
        'num_installments' => $numInstallments,
        'interest_pesewas' => $interestPesewas,
        'total_payable_pesewas' => $totalPayablePesewas,
        'installment_amount_pesewas' => $installmentAmountPesewas,
        'maturity_date' => end($schedule)['due_date'],
        'schedule' => $schedule
    ];
}

/**
 * Allocate payment against repayment schedule and update loan balance
 * Must be executed within a database transaction!
 */
function applyPaymentToLoan(PDO $pdo, int $loanId, int $amountPesewas, string $collectionTimestamp): void {
    // 1. Fetch loan
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? FOR UPDATE");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    if (!$loan) throw new Exception("Loan #{$loanId} not found");

    // 2. Fetch pending / partially_paid / overdue schedule items in chronological order
    $stmtSched = $pdo->prepare("
        SELECT * FROM repayment_schedule 
        WHERE loan_id = ? AND status IN ('pending', 'partially_paid', 'overdue')
        ORDER BY due_date ASC, installment_number ASC 
        FOR UPDATE
    ");
    $stmtSched->execute([$loanId]);
    $items = $stmtSched->fetchAll();

    $remainingToAllocate = $amountPesewas;

    foreach ($items as $item) {
        if ($remainingToAllocate <= 0) break;

        $unpaidForItem = $item['expected_amount_pesewas'] - $item['paid_amount_pesewas'];
        if ($unpaidForItem <= 0) continue;

        if ($remainingToAllocate >= $unpaidForItem) {
            // Fully settles this installment
            $newPaid = $item['expected_amount_pesewas'];
            $newStatus = 'paid';
            $remainingToAllocate -= $unpaidForItem;
        } else {
            // Partially settles this installment
            $newPaid = $item['paid_amount_pesewas'] + $remainingToAllocate;
            $newStatus = 'partially_paid';
            $remainingToAllocate = 0;
        }

        $upd = $pdo->prepare("
            UPDATE repayment_schedule 
            SET paid_amount_pesewas = ?, status = ?, paid_at = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $upd->execute([$newPaid, $newStatus, $collectionTimestamp, $item['id']]);
    }

    // 3. Update Loan totals
    $newTotalPaid = $loan['total_paid_pesewas'] + $amountPesewas;
    $newRemaining = max(0, $loan['total_payable_pesewas'] - $newTotalPaid);
    $loanStatus = $newRemaining === 0 ? 'completed' : ($loan['status'] === 'pending' ? 'active' : $loan['status']);

    $updLoan = $pdo->prepare("
        UPDATE loans 
        SET total_paid_pesewas = ?, remaining_balance_pesewas = ?, status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updLoan->execute([$newTotalPaid, $newRemaining, $loanStatus, $loanId]);
}

/**
 * Reversal Engine: Reverse a payment append-only
 */
function reversePayment(PDO $pdo, int $paymentId, int $reversedByUserId, string $reason): int {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
        $stmt->execute([$paymentId]);
        $orig = $stmt->fetch();

        if (!$orig) throw new Exception("Payment not found");
        if ($orig['status'] === 'reversed') throw new Exception("Payment is already reversed");
        if ($orig['is_reversal']) throw new Exception("Cannot reverse a reversal transaction");

        // 1. Mark original payment as reversed
        $updOrig = $pdo->prepare("UPDATE payments SET status = 'reversed' WHERE id = ?");
        $updOrig->execute([$paymentId]);

        // 2. Insert new Reversal payment entry (Append-only)
        $reversalUuid = generateUuidV4();
        $reversalReceipt = 'REV-' . $orig['receipt_number'];
        $revAmount = $orig['amount_pesewas'];

        $insRev = $pdo->prepare("
            INSERT INTO payments (
                uuid, receipt_number, loan_id, customer_id, agent_id, 
                amount_pesewas, payment_method, collection_timestamp, 
                note, is_reversal, reversal_of_payment_id, reversal_reason, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 1, ?, ?, 'recorded')
        ");
        $insRev->execute([
            $reversalUuid,
            $reversalReceipt,
            $orig['loan_id'],
            $orig['customer_id'],
            $reversedByUserId,
            $revAmount,
            $orig['payment_method'],
            "Reversal of {$orig['receipt_number']}: {$reason}",
            $paymentId,
            $reason
        ]);
        $reversalPaymentId = (int)$pdo->lastInsertId();

        // 3. Recalculate Loan Schedule from scratch to ensure perfect balance integrity
        recalculateLoanFromLedger($pdo, $orig['loan_id']);

        // 4. Log Audit
        logAudit(
            $pdo, 
            $reversedByUserId, 
            'PAYMENT_REVERSAL', 
            'payments', 
            $paymentId, 
            ['original_payment' => $orig], 
            ['reversal_payment_id' => $reversalPaymentId, 'reason' => $reason]
        );

        $pdo->commit();
        return $reversalPaymentId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Reconstruct schedule state and balances purely from the append-only payments ledger
 */
function recalculateLoanFromLedger(PDO $pdo, int $loanId): void {
    // Reset all installments to pending / overdue depending on date
    $pdo->prepare("
        UPDATE repayment_schedule 
        SET paid_amount_pesewas = 0, 
            status = CASE WHEN due_date < CURDATE() THEN 'overdue' ELSE 'pending' END, 
            paid_at = NULL 
        WHERE loan_id = ?
    ")->execute([$loanId]);

    // Fetch net sum of non-reversed, non-reversal payments for this loan
    $stmtNet = $pdo->prepare("
        SELECT COALESCE(SUM(amount_pesewas), 0) AS net_collected 
        FROM payments 
        WHERE loan_id = ? AND status = 'recorded' AND is_reversal = 0
    ");
    $stmtNet->execute([$loanId]);
    $netCollected = (int)$stmtNet->fetchColumn();

    // Fetch loan details
    $stmtLoan = $pdo->prepare("SELECT * FROM loans WHERE id = ? FOR UPDATE");
    $stmtLoan->execute([$loanId]);
    $loan = $stmtLoan->fetch();

    $newRemaining = max(0, $loan['total_payable_pesewas'] - $netCollected);
    $newStatus = $newRemaining === 0 ? 'completed' : 'active';
    if ($newRemaining > 0 && $loan['status'] === 'overdue') {
        $newStatus = 'overdue';
    }

    $pdo->prepare("
        UPDATE loans 
        SET total_paid_pesewas = ?, remaining_balance_pesewas = ?, status = ?, updated_at = NOW() 
        WHERE id = ?
    ")->execute([$netCollected, $newRemaining, $newStatus, $loanId]);

    // Walk through schedule and reapply net collected
    if ($netCollected > 0) {
        $stmtSched = $pdo->prepare("
            SELECT * FROM repayment_schedule 
            WHERE loan_id = ? 
            ORDER BY due_date ASC, installment_number ASC 
            FOR UPDATE
        ");
        $stmtSched->execute([$loanId]);
        $items = $stmtSched->fetchAll();

        $remainingToAllocate = $netCollected;
        foreach ($items as $item) {
            if ($remainingToAllocate <= 0) break;

            if ($remainingToAllocate >= $item['expected_amount_pesewas']) {
                $newPaid = $item['expected_amount_pesewas'];
                $status = 'paid';
                $remainingToAllocate -= $item['expected_amount_pesewas'];
            } else {
                $newPaid = $remainingToAllocate;
                $status = 'partially_paid';
                $remainingToAllocate = 0;
            }

            $pdo->prepare("
                UPDATE repayment_schedule 
                SET paid_amount_pesewas = ?, status = ?, paid_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ")->execute([$newPaid, $status, $item['id']]);
        }
    }
}

/**
 * Record a payment collection transaction (Idempotent, ACID-compliant)
 */
function recordPaymentTransaction(PDO $pdo, array $params): array {
    if (file_exists(__DIR__ . '/sms.php')) {
        require_once __DIR__ . '/sms.php';
    }
    if (file_exists(__DIR__ . '/email.php')) {
        require_once __DIR__ . '/email.php';
    }

    $uuid = !empty($params['uuid']) ? trim($params['uuid']) : generateUuidV4();
    $loanId = (int)($params['loan_id'] ?? 0);
    $customerId = (int)($params['customer_id'] ?? 0);
    $agentId = (int)($params['agent_id'] ?? 0);
    $amountPesewas = (int)($params['amount_pesewas'] ?? 0);
    $method = in_array($params['payment_method'] ?? '', ['cash', 'momo', 'bank_transfer']) ? $params['payment_method'] : 'cash';
    $momoRef = !empty($params['momo_reference']) ? trim($params['momo_reference']) : null;
    $gpsLat = !empty($params['gps_lat']) ? (float)$params['gps_lat'] : null;
    $gpsLng = !empty($params['gps_lng']) ? (float)$params['gps_lng'] : null;
    $note = !empty($params['note']) ? trim($params['note']) : null;
    $timestamp = !empty($params['collection_timestamp']) ? $params['collection_timestamp'] : date('Y-m-d H:i:s');
    $auditAction = $params['audit_action'] ?? 'RECORD_COLLECTION';

    if ($amountPesewas <= 0) {
        throw new Exception('Collection amount must be greater than zero');
    }
    if ($loanId <= 0) {
        throw new Exception('Invalid loan ID');
    }

    // 1. Idempotency check
    $stmtCheck = $pdo->prepare("SELECT * FROM payments WHERE uuid = ?");
    $stmtCheck->execute([$uuid]);
    $existing = $stmtCheck->fetch();
    if ($existing) {
        return [
            'payment_id' => (int)$existing['id'],
            'receipt_number' => $existing['receipt_number'],
            'uuid' => $uuid,
            'idempotent' => true,
            'receipt_url' => BASE_URL . '/receipt.php?id=' . $existing['id']
        ];
    }

    // 2. Resolve customer_id from loan if missing
    if ($customerId <= 0) {
        $stmtLoan = $pdo->prepare("SELECT customer_id FROM loans WHERE id = ?");
        $stmtLoan->execute([$loanId]);
        $customerId = (int)$stmtLoan->fetchColumn();
        if ($customerId <= 0) {
            throw new Exception('Loan facility not found');
        }
    }

    // 3. Begin Transaction
    $pdo->beginTransaction();
    try {
        $receiptNumber = 'EF-REC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $ins = $pdo->prepare("
            INSERT INTO payments (
                uuid, receipt_number, loan_id, customer_id, agent_id, 
                amount_pesewas, payment_method, momo_reference, collection_timestamp, 
                gps_lat, gps_lng, note, is_reversal, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'recorded', NOW())
        ");
        $ins->execute([
            $uuid, $receiptNumber, $loanId, $customerId, $agentId,
            $amountPesewas, $method, $momoRef, $timestamp,
            $gpsLat, $gpsLng, $note
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        applyPaymentToLoan($pdo, $loanId, $amountPesewas, $timestamp);

        logAudit(
            $pdo,
            $agentId,
            $auditAction,
            'payments',
            $paymentId,
            null,
            [
                'amount_pesewas' => $amountPesewas,
                'method' => $method,
                'receipt' => $receiptNumber,
                'loan_id' => $loanId,
                'gps' => ['lat' => $gpsLat, 'lng' => $gpsLng]
            ]
        );

        $pdo->commit();

        // Send notifications
        if (function_exists('sendCollectionSms')) {
            sendCollectionSms($pdo, $paymentId);
        }
        if (function_exists('queueAdminCollectionEmail')) {
            queueAdminCollectionEmail($pdo, $paymentId);
        }

        return [
            'payment_id' => $paymentId,
            'receipt_number' => $receiptNumber,
            'uuid' => $uuid,
            'idempotent' => false,
            'receipt_url' => BASE_URL . '/receipt.php?id=' . $paymentId
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Resolve validated relative path of the uploaded company logo, or null if none exists.
 */
function getCompanyLogoRelativePath(): ?string {
    $raw = $GLOBALS['system_settings']['company_logo'] ?? (defined('COMPANY_LOGO') ? COMPANY_LOGO : '');
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^assets\/uploads\/company_logo_[A-Za-z0-9_]+\.(png|jpe?g|webp|gif)$/', $raw)) {
        return null;
    }
    $fullPath = __DIR__ . '/../' . $raw;
    if (!is_file($fullPath)) {
        return null;
    }
    return $raw;
}

/**
 * Get public URL of the uploaded company logo, or null if fallback badge should be used.
 */
function getCompanyLogoUrl(): ?string {
    $rel = getCompanyLogoRelativePath();
    if ($rel === null) {
        return null;
    }
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    return $base . '/' . $rel;
}

/**
 * Get public favicon URL (uses uploaded company logo when present, otherwise default icon).
 */
function getFaviconUrl(): string {
    $logoUrl = getCompanyLogoUrl();
    if ($logoUrl !== null) {
        return $logoUrl;
    }
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    return $base . '/assets/icons/favicon.png';
}

/**
 * Render the company logo image (when uploaded) or clean minimalist EF fallback mark.
 * Variants: 'navbar', 'receipt', 'auth', 'report', 'compact', 'print'
 */
function renderCompanyLogo(string $variant = 'navbar'): string {
    $url = getCompanyLogoUrl();
    $alt = htmlspecialchars(defined('COMPANY_NAME') ? COMPANY_NAME : 'Company Logo');

    if ($url !== null) {
        $escUrl = htmlspecialchars($url);
        switch ($variant) {
            case 'receipt':
                return '<img src="' . $escUrl . '" alt="' . $alt . ' Logo" class="h-14 w-auto max-w-[180px] object-contain mx-auto mb-2.5">';
            case 'auth':
                return '<img src="' . $escUrl . '" alt="' . $alt . ' Logo" class="h-14 w-auto max-w-[180px] object-contain mx-auto mb-3">';
            case 'report':
                return '<img src="' . $escUrl . '" alt="' . $alt . ' Logo" class="h-12 w-auto max-w-[160px] object-contain mx-auto mb-2">';
            case 'compact':
                return '<img src="' . $escUrl . '" alt="' . $alt . ' Logo" class="h-6 w-auto max-w-[72px] object-contain rounded shrink-0">';
            case 'print':
                return '<img src="' . $escUrl . '" alt="' . $alt . ' Logo" class="h-10 w-auto max-w-[140px] object-contain shrink-0">';
            case 'navbar':
            default:
                return '<img src="' . $escUrl . '" alt="' . $alt . ' Logo" class="h-8 w-auto max-w-[110px] object-contain rounded shrink-0">';
        }
    }

    switch ($variant) {
        case 'receipt':
            return '<div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-purple-600 text-white rounded-xl flex items-center justify-center mx-auto mb-2 font-bold text-xs shadow-sm">EF</div>';
        case 'auth':
            return '<div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-2.5 text-white font-bold text-xs tracking-tight shadow-sm">EF</div>';
        case 'compact':
            return '<div class="w-6 h-6 rounded bg-gradient-to-br from-blue-600 to-purple-600 text-white text-[10px] font-bold flex items-center justify-center shrink-0">EF</div>';
        case 'report':
        case 'print':
            return '';
        case 'navbar':
        default:
            return '<div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center text-white text-xs font-bold tracking-tight shrink-0 shadow-sm">EF</div>';
    }
}

/**
 * Validate, normalize (re-encode to PNG & resize up to 600px), and store uploaded company logo.
 * Returns the relative path (e.g. 'assets/uploads/company_logo_xxx.png').
 */
function saveUploadedCompanyLogo(array $file): string {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid file upload payload.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE => 'Uploaded logo exceeds server upload size limit.',
            UPLOAD_ERR_FORM_SIZE => 'Uploaded logo exceeds maximum allowed size.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please retry.',
            UPLOAD_ERR_NO_FILE => 'Please choose a logo image file to upload.',
        ];
        throw new Exception($errMap[$file['error']] ?? 'File upload failed with error code ' . $file['error'] . '.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (empty($tmpPath) || !is_uploaded_file($tmpPath)) {
        throw new Exception('Invalid uploaded file source.');
    }

    $maxBytes = 2 * 1024 * 1024; // 2 MB
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxBytes) {
        throw new Exception('Logo image must be smaller than 2 MB.');
    }

    $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
    $detectedMime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string)$finfo->file($tmpPath);
    }

    $imgInfo = @getimagesize($tmpPath);
    if ($imgInfo === false || empty($imgInfo[0]) || empty($imgInfo[1])) {
        throw new Exception('The uploaded file is not a valid image.');
    }
    $imgMime = $imgInfo['mime'] ?? $detectedMime;
    if (!in_array($imgMime, $allowedMimes, true) || (!empty($detectedMime) && !in_array($detectedMime, $allowedMimes, true))) {
        throw new Exception('Unsupported image format. Please upload a PNG, JPG, WebP, or GIF file.');
    }

    $srcWidth = (int)$imgInfo[0];
    $srcHeight = (int)$imgInfo[1];
    if ($srcWidth > 5000 || $srcHeight > 5000) {
        throw new Exception('Image dimensions are too large (maximum 5000x5000px).');
    }

    if (!extension_loaded('gd')) {
        throw new Exception('GD image extension is required to process uploaded logos safely.');
    }

    switch ($imgMime) {
        case 'image/png':
            $srcImg = @imagecreatefrompng($tmpPath);
            break;
        case 'image/jpeg':
            $srcImg = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/webp':
            $srcImg = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false;
            break;
        case 'image/gif':
            $srcImg = @imagecreatefromgif($tmpPath);
            break;
        default:
            $srcImg = false;
    }

    if (!$srcImg) {
        throw new Exception('Could not decode uploaded image. Please try another PNG or JPG file.');
    }

    $maxDim = 600;
    $dstWidth = $srcWidth;
    $dstHeight = $srcHeight;
    if ($srcWidth > $maxDim || $srcHeight > $maxDim) {
        $scale = min($maxDim / $srcWidth, $maxDim / $srcHeight);
        $dstWidth = max(1, (int)round($srcWidth * $scale));
        $dstHeight = max(1, (int)round($srcHeight * $scale));
    }

    $dstImg = imagecreatetruecolor($dstWidth, $dstHeight);
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
    imagefilledrectangle($dstImg, 0, 0, $dstWidth, $dstHeight, $transparent);
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

    $uploadDir = __DIR__ . '/../assets/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'company_logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.png';
    $destPath = $uploadDir . '/' . $filename;

    $saved = imagepng($dstImg, $destPath, 6);
    imagedestroy($srcImg);
    imagedestroy($dstImg);

    if (!$saved || !is_file($destPath)) {
        throw new Exception('Failed to save processed logo image on server.');
    }

    return 'assets/uploads/' . $filename;
}

/**
 * Delete stored company logo files inside assets/uploads directory.
 */
function cleanupOldCompanyLogos(?string $keepRelativePath = null): void {
    $uploadDir = __DIR__ . '/../assets/uploads';
    if (!is_dir($uploadDir)) {
        return;
    }
    $keepBase = $keepRelativePath ? basename($keepRelativePath) : null;
    foreach (glob($uploadDir . '/company_logo_*') ?: [] as $filePath) {
        if ($keepBase !== null && basename($filePath) === $keepBase) {
            continue;
        }
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}

