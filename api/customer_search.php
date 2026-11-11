<?php
/**
 * API Endpoint: Customer Search (Fast autocomplete)
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$agentId = currentUserId();
$role = currentUserRole();

$sql = "
    SELECT c.id, c.customer_code, c.full_name, c.phone, c.residential_address, c.location_landmark,
           l.id AS active_loan_id, l.loan_code, l.installment_amount_pesewas, l.remaining_balance_pesewas
    FROM customers c
    LEFT JOIN loans l ON c.id = l.customer_id AND l.status IN ('active', 'overdue')
    WHERE (c.full_name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ? OR c.id_number LIKE ?)
";

$params = ["%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%"];

// If agent, limit to assigned customers
if ($role === 'agent') {
    $sql .= " AND (c.assigned_agent_id = ? OR c.created_by = ?)";
    $params[] = $agentId;
    $params[] = $agentId;
}

$sql .= " ORDER BY c.full_name ASC LIMIT 15";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$formatted = array_map(function($r) {
    return [
        'id' => (int)$r['id'],
        'customer_code' => $r['customer_code'],
        'full_name' => $r['full_name'],
        'phone' => $r['phone'],
        'address' => $r['residential_address'] . ' (' . $r['location_landmark'] . ')',
        'active_loan_id' => $r['active_loan_id'] ? (int)$r['active_loan_id'] : null,
        'loan_code' => $r['loan_code'],
        'installment_amount_pesewas' => (int)($r['installment_amount_pesewas'] ?? 0),
        'installment_formatted' => formatMoney((int)($r['installment_amount_pesewas'] ?? 0)),
        'balance_formatted' => formatMoney((int)($r['remaining_balance_pesewas'] ?? 0))
    ];
}, $results);

echo json_encode($formatted);

