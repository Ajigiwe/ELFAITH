<?php
/**
 * API Endpoint: Bulk Sync Offline Collections
 * Allows PWA agent phones to upload queued collections taken while offline
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/email.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$agentId = currentUserId();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];
$collections = $data['collections'] ?? [];

if (!is_array($collections) || empty($collections)) {
    echo json_encode(['success' => true, 'synced_uuids' => [], 'count' => 0]);
    exit;
}

$syncedUuids = [];
$errors = [];

foreach ($collections as $item) {
    $uuid = trim($item['uuid'] ?? '');
    if (empty($uuid)) continue;

    $loanId = (int)($item['loan_id'] ?? 0);
    $customerId = (int)($item['customer_id'] ?? 0);
    $amountPesewas = (int)($item['amount_pesewas'] ?? 0);
    $method = in_array($item['payment_method'] ?? '', ['cash', 'momo', 'bank_transfer']) ? $item['payment_method'] : 'cash';
    $momoRef = !empty($item['momo_reference']) ? trim($item['momo_reference']) : null;
    $gpsLat = !empty($item['gps_lat']) ? (float)$item['gps_lat'] : null;
    $gpsLng = !empty($item['gps_lng']) ? (float)$item['gps_lng'] : null;
    $note = !empty($item['note']) ? trim($item['note']) : null;
    $timestamp = !empty($item['collection_timestamp']) ? $item['collection_timestamp'] : date('Y-m-d H:i:s');

    if ($amountPesewas <= 0 || $loanId <= 0) {
        $errors[] = ['uuid' => $uuid, 'error' => 'Invalid amount or loan ID'];
        continue;
    }

    // Check if already processed
    $stmtCheck = $pdo->prepare("SELECT id FROM payments WHERE uuid = ?");
    $stmtCheck->execute([$uuid]);
    if ($stmtCheck->fetch()) {
        $syncedUuids[] = $uuid;
        continue;
    }

    try {
        $pdo->beginTransaction();

        $receiptNumber = 'EF-REC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $ins = $pdo->prepare("
            INSERT INTO payments (
                uuid, receipt_number, loan_id, customer_id, agent_id, 
                amount_pesewas, payment_method, momo_reference, collection_timestamp, 
                gps_lat, gps_lng, note, is_reversal, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'recorded', NOW())
        ");
        $ins->execute([
            $uuid,
            $receiptNumber,
            $loanId,
            $customerId,
            $agentId,
            $amountPesewas,
            $method,
            $momoRef,
            $timestamp,
            $gpsLat,
            $gpsLng,
            $note
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        applyPaymentToLoan($pdo, $loanId, $amountPesewas, $timestamp);

        logAudit(
            $pdo, 
            $agentId, 
            'OFFLINE_SYNC_COLLECTION', 
            'payments', 
            $paymentId, 
            null, 
            ['uuid' => $uuid, 'amount_pesewas' => $amountPesewas]
        );

        $pdo->commit();

        sendCollectionSms($pdo, $paymentId);
        queueAdminCollectionEmail($pdo, $paymentId);

        $syncedUuids[] = $uuid;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = ['uuid' => $uuid, 'error' => $e->getMessage()];
    }
}

echo json_encode([
    'success' => true,
    'synced_uuids' => $syncedUuids,
    'synced_count' => count($syncedUuids),
    'errors' => $errors
]);

