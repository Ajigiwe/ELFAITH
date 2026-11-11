<?php
/**
 * API Endpoint: Record Payment (Idempotent with Client-Generated UUID)
 * Supports both JSON API requests and standard form submissions
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

// Parse input (supports JSON body or POST form data)
$input = [];
if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
} else {
    $input = $_POST;
    // Check CSRF for standard form submissions
    if (!verifyCsrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token']);
        exit;
    }
}

$uuid = !empty($input['uuid']) ? trim($input['uuid']) : generateUuidV4();
$loanId = (int)($input['loan_id'] ?? 0);
$customerId = (int)($input['customer_id'] ?? 0);
$amountInput = $input['amount'] ?? 0;
$amountPesewas = isset($input['amount_pesewas']) ? (int)$input['amount_pesewas'] : ghsToPesewas($amountInput);
$method = in_array($input['payment_method'] ?? '', ['cash', 'momo', 'bank_transfer']) ? $input['payment_method'] : 'cash';
$momoRef = !empty($input['momo_reference']) ? trim($input['momo_reference']) : null;
$gpsLat = !empty($input['gps_lat']) ? (float)$input['gps_lat'] : null;
$gpsLng = !empty($input['gps_lng']) ? (float)$input['gps_lng'] : null;
$note = !empty($input['note']) ? trim($input['note']) : null;
$timestamp = !empty($input['collection_timestamp']) ? $input['collection_timestamp'] : date('Y-m-d H:i:s');

if ($amountPesewas <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Collection amount must be greater than zero']);
    exit;
}

// Process payment via unified transaction engine
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
        'collection_timestamp' => $timestamp,
        'audit_action' => 'RECORD_COLLECTION'
    ]);

    echo json_encode([
        'success' => true,
        'idempotent' => $result['idempotent'],
        'message' => $result['idempotent'] ? 'Payment already recorded previously' : 'Collection recorded successfully',
        'payment_id' => $result['payment_id'],
        'uuid' => $result['uuid'],
        'receipt_number' => $result['receipt_number'],
        'receipt_url' => $result['receipt_url']
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

