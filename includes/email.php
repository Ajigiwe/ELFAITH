<?php
/**
 * Email Notification Dispatcher
 * Sends collection alerts and daily summaries to Administrator
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

function queueAdminCollectionEmail(PDO $pdo, int $paymentId): void {
    try {
        $frequency = $GLOBALS['system_settings']['admin_email_frequency'] ?? 'per_collection';
        if ($frequency !== 'per_collection') {
            return; // Scheduled batch digest handles this
        }

        $adminEmail = $GLOBALS['system_settings']['admin_notification_email'] ?? COMPANY_EMAIL;
        if (empty($adminEmail)) return;

        $stmt = $pdo->prepare("
            SELECT p.*, c.full_name AS customer_name, l.loan_code, u.name AS agent_name
            FROM payments p
            JOIN customers c ON p.customer_id = c.id
            JOIN loans l ON p.loan_id = l.id
            JOIN users u ON p.agent_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch();
        if (!$row) return;

        $amountFormatted = formatMoney($row['amount_pesewas']);
        $message = "Collection Alert: Agent {$row['agent_name']} collected {$amountFormatted} ({$row['payment_method']}) from {$row['customer_name']} on Loan {$row['loan_code']}. Receipt: {$row['receipt_number']}. Time: {$row['collection_timestamp']}.";

        $stmtIns = $pdo->prepare("
            INSERT INTO notifications_log (channel, recipient, customer_id, payment_id, message, status, attempts, provider, created_at, sent_at)
            VALUES ('email', ?, ?, ?, ?, 'delivered', 1, 'simulated', NOW(), NOW())
        ");
        $stmtIns->execute([$adminEmail, $row['customer_id'], $paymentId, $message]);
    } catch (Exception $e) {
        error_log("Failed to send admin email alert: " . $e->getMessage());
    }
}

