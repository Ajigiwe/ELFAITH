-- =====================================================================
-- EL-FAITH MICROFINANCE - SEED DATA (Shared cPanel & Local Compatible)
-- Default Admin & Agents, System Settings, Initial Customers & Schedules
-- =====================================================================

SET NAMES utf8mb4;

-- 1. SYSTEM SETTINGS
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'EL-FAITH MICROFINANCE'),
('branch_name', 'Wassa Powuako'),
('branch_region', 'Western Region, Ghana'),
('company_phone', '+233 24 556 7890'),
('company_email', 'admin@el-faithgroupofcompanies.com'),
('currency_code', 'GHS'),
('currency_symbol', CONCAT('GH', UNHEX('E282B5'))),
('default_interest_rate', '10.00'),
('default_interest_method', 'flat'),
('sms_provider', 'simulated'), -- 'hubtel', 'arkesel', 'mnotify', or 'simulated'
('sms_sender_id', 'ELFAITH'),
('sms_api_key', ''),
('email_provider', 'simulated'), -- 'brevo', 'resend', 'smtp', or 'simulated'
('email_api_key', ''),
('admin_notification_email', 'admin@el-faithgroupofcompanies.com'),
('admin_email_frequency', 'per_collection') -- 'per_collection' or 'daily_digest'
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 2. USERS (Admin & Field Agents)
-- SYLVESTER: username SYLVESTER / password VESTER442 (Super Administrator)
-- admin: username admin / password admin123 (Administrator)
-- agent_kwame: username agent_kwame / password agent123
-- agent_abena: username agent_abena / password agent123
INSERT INTO `users` (`id`, `uuid`, `staff_code`, `name`, `username`, `email`, `phone`, `password_hash`, `role`, `status`) VALUES
(1, 'c1000000-0000-4000-a000-000000000001', 'EF-SAD-001', 'SYLVESTER', 'SYLVESTER', 'sylvester@elfaith.com', '+233245567890', '$2y$10$jVaaGgg2v6dtZUuD1/WfyOs0He757sPFcwoBOCptiLZX0k7EsmLwa', 'super_admin', 'active'),
(2, 'c1000000-0000-4000-a000-000000000002', 'EF-ADM-002', 'System Administrator', 'admin', 'admin@elfaith.com', '+233240000001', '$2y$10$CdwrqlkFC3AXWaAzcUrP1OZuX5IvlPqxq14tsKWLRZN8zGN0lzLOW', 'admin', 'active'),
(3, 'c1000000-0000-4000-a000-000000000003', 'EF-AGT-001', 'Kwame Mensah', 'agent_kwame', 'kwame@elfaith.com', '+233241112233', '$2y$10$rS/lKZCUanOHqFCVXiA7f.wZ/JUa/bB0NwTiWbzP3rXZo6movetWi', 'agent', 'active'),
(4, 'c1000000-0000-4000-a000-000000000004', 'EF-AGT-002', 'Abena Serwaa', 'agent_abena', 'abena@elfaith.com', '+233249988776', '$2y$10$rS/lKZCUanOHqFCVXiA7f.wZ/JUa/bB0NwTiWbzP3rXZo6movetWi', 'agent', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. INITIAL CUSTOMERS (Wassa Powuako Area)
INSERT INTO `customers` (`id`, `uuid`, `customer_code`, `full_name`, `phone`, `alt_phone`, `id_type`, `id_number`, `residential_address`, `location_landmark`, `gps_lat`, `gps_lng`, `occupation`, `guarantor_name`, `guarantor_phone`, `guarantor_relation`, `guarantor_address`, `assigned_agent_id`, `created_by`, `status`) VALUES
(1, 'd1000000-0000-4000-a000-000000000001', 'EF-CUS-0001', 'Ama Konadu', '+233244123456', '+233501234567', 'Ghana Card', 'GHA-712345678-1', 'Market St, Near Mosque', 'Wassa Powuako Central Market Stall 14', 5.51240000, -2.12450000, 'Provisions Retailer', 'Kofi Konadu', '+233245678901', 'Brother', 'Powuako Market', 3, 1, 'active'),
(2, 'd1000000-0000-4000-a000-000000000002', 'EF-CUS-0002', 'Kwadwo Boateng', '+233244987654', NULL, 'Ghana Card', 'GHA-723456789-2', 'Main Road, House No. 42', 'Opposite Powuako Timber Shed', 5.51350000, -2.12600000, 'Cocoa & Palm Buyer', 'Yaw Boateng', '+233246789012', 'Uncle', 'Powuako Station', 3, 1, 'active'),
(3, 'd1000000-0000-4000-a000-000000000003', 'EF-CUS-0003', 'Esi Mansa', '+233243112244', '+233552233445', 'Ghana Card', 'GHA-734567890-3', 'Lorry Station Road', 'Near Powuako Primary School', 5.51120000, -2.12210000, 'Fast Food & Chop Bar', 'Akosua Mansa', '+233247890123', 'Mother', 'Lorry Station', 4, 1, 'active'),
(4, 'd1000000-0000-4000-a000-000000000004', 'EF-CUS-0004', 'Kofi Antwi', '+233248889900', NULL, 'Ghana Card', 'GHA-745678901-4', 'Zongo Line, House 15', 'Beside Chief Imam House', 5.51400000, -2.12750000, 'Auto Mechanic', 'Issah Antwi', '+233248901234', 'Cousin', 'Zongo Line', 3, 1, 'active')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 4. AGENT ASSIGNMENTS
INSERT INTO `agent_assignments` (`agent_id`, `customer_id`, `assigned_by`, `route_day`, `notes`, `is_active`) VALUES
(3, 1, 1, 'all_days', 'Daily collection at morning market opening (8:00 AM)', 1),
(3, 2, 1, 'all_days', 'Afternoon collection at timber shed (2:00 PM)', 1),
(4, 3, 1, 'all_days', 'Daily morning collection at chop bar (10:00 AM)', 1),
(3, 4, 1, 'all_days', 'Evening collection at workshop (4:30 PM)', 1)
ON DUPLICATE KEY UPDATE `route_day` = VALUES(`route_day`);

-- 5. ACTIVE LOANS
-- Loan 1: Ama Konadu - Principal GH₵ 1,000 (100,000 pesewas), 10% flat interest (10,000 pesewas), Total GH₵ 1,100 (110,000 pesewas), 22 daily installments of GH₵ 50 (5,000 pesewas)
INSERT INTO `loans` (`id`, `uuid`, `loan_code`, `customer_id`, `agent_id`, `principal_pesewas`, `interest_rate`, `interest_method`, `interest_pesewas`, `total_payable_pesewas`, `total_paid_pesewas`, `remaining_balance_pesewas`, `term_duration`, `term_period`, `repayment_frequency`, `installment_amount_pesewas`, `disbursed_at`, `first_due_date`, `maturity_date`, `status`, `created_by`, `notes`) VALUES
(1, 'e1000000-0000-4000-a000-000000000001', 'EF-LN-0001', 1, 3, 100000, 10.00, 'flat', 10000, 110000, 10000, 100000, 22, 'days', 'daily', 5000, NOW() - INTERVAL 3 DAY, CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 19 DAY, 'active', 1, 'Business expansion for provisions stall'),
-- Loan 2: Kwadwo Boateng - Principal GH₵ 2,000 (200,000 pesewas), 10% flat (20,000 pesewas), Total GH₵ 2,200 (220,000 pesewas), 22 daily installments of GH₵ 100 (10,000 pesewas)
(2, 'e1000000-0000-4000-a000-000000000002', 'EF-LN-0002', 2, 3, 200000, 10.00, 'flat', 20000, 220000, 0, 220000, 22, 'days', 'daily', 10000, NOW() - INTERVAL 1 DAY, CURDATE(), CURDATE() + INTERVAL 21 DAY, 'active', 1, 'Working capital for palm fruit aggregation'),
-- Loan 3: Esi Mansa - Principal GH₵ 1,500 (150,000 pesewas), 10% flat (15,000 pesewas), Total GH₵ 1,650 (165,000 pesewas), 30 daily installments of GH₵ 55 (5,500 pesewas)
(3, 'e1000000-0000-4000-a000-000000000003', 'EF-LN-0003', 3, 4, 150000, 10.00, 'flat', 15000, 165000, 0, 165000, 30, 'days', 'daily', 5500, NOW() - INTERVAL 2 DAY, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 28 DAY, 'active', 1, 'Chop bar inventory and deep freezer repairs'),
-- Loan 4: Kofi Antwi - Principal GH₵ 800 (80,000 pesewas), 10% flat (8,000 pesewas), Total GH₵ 880 (88,000 pesewas), overdue installment
(4, 'e1000000-0000-4000-a000-000000000004', 'EF-LN-0004', 4, 3, 80000, 10.00, 'flat', 8000, 88000, 0, 88000, 20, 'days', 'daily', 4400, NOW() - INTERVAL 5 DAY, CURDATE() - INTERVAL 4 DAY, CURDATE() + INTERVAL 15 DAY, 'overdue', 1, 'Tool procurement')
ON DUPLICATE KEY UPDATE `total_payable_pesewas` = VALUES(`total_payable_pesewas`);

-- 6. REPAYMENT SCHEDULES FOR LOAN 1 (Ama Konadu)
INSERT INTO `repayment_schedule` (`loan_id`, `installment_number`, `due_date`, `expected_amount_pesewas`, `principal_portion_pesewas`, `interest_portion_pesewas`, `paid_amount_pesewas`, `status`, `paid_at`) VALUES
(1, 1, CURDATE() - INTERVAL 2 DAY, 5000, 4545, 455, 5000, 'paid', NOW() - INTERVAL 2 DAY),
(1, 2, CURDATE() - INTERVAL 1 DAY, 5000, 4545, 455, 5000, 'paid', NOW() - INTERVAL 1 DAY),
(1, 3, CURDATE(), 5000, 4545, 455, 0, 'pending', NULL),
(1, 4, CURDATE() + INTERVAL 1 DAY, 5000, 4545, 455, 0, 'pending', NULL),
(1, 5, CURDATE() + INTERVAL 2 DAY, 5000, 4545, 455, 0, 'pending', NULL);

-- REPAYMENT SCHEDULES FOR LOAN 2 (Kwadwo Boateng) - Due today
INSERT INTO `repayment_schedule` (`loan_id`, `installment_number`, `due_date`, `expected_amount_pesewas`, `principal_portion_pesewas`, `interest_portion_pesewas`, `paid_amount_pesewas`, `status`, `paid_at`) VALUES
(2, 1, CURDATE(), 10000, 9091, 909, 0, 'pending', NULL),
(2, 2, CURDATE() + INTERVAL 1 DAY, 10000, 9091, 909, 0, 'pending', NULL),
(2, 3, CURDATE() + INTERVAL 2 DAY, 10000, 9091, 909, 0, 'pending', NULL);

-- REPAYMENT SCHEDULES FOR LOAN 3 (Esi Mansa) - Installment 1 overdue
INSERT INTO `repayment_schedule` (`loan_id`, `installment_number`, `due_date`, `expected_amount_pesewas`, `principal_portion_pesewas`, `interest_portion_pesewas`, `paid_amount_pesewas`, `status`, `paid_at`) VALUES
(3, 1, CURDATE() - INTERVAL 1 DAY, 5500, 5000, 500, 0, 'overdue', NULL),
(3, 2, CURDATE(), 5500, 5000, 500, 0, 'pending', NULL),
(3, 3, CURDATE() + INTERVAL 1 DAY, 5500, 5000, 500, 0, 'pending', NULL);

-- REPAYMENT SCHEDULES FOR LOAN 4 (Kofi Antwi) - Multiple overdue
INSERT INTO `repayment_schedule` (`loan_id`, `installment_number`, `due_date`, `expected_amount_pesewas`, `principal_portion_pesewas`, `interest_portion_pesewas`, `paid_amount_pesewas`, `status`, `paid_at`) VALUES
(4, 1, CURDATE() - INTERVAL 4 DAY, 4400, 4000, 400, 0, 'overdue', NULL),
(4, 2, CURDATE() - INTERVAL 3 DAY, 4400, 4000, 400, 0, 'overdue', NULL),
(4, 3, CURDATE() - INTERVAL 2 DAY, 4400, 4000, 400, 0, 'overdue', NULL),
(4, 4, CURDATE() - INTERVAL 1 DAY, 4400, 4000, 400, 0, 'overdue', NULL),
(4, 5, CURDATE(), 4400, 4000, 400, 0, 'pending', NULL);

-- 7. INITIAL PAYMENTS (For Ama Konadu, Installments 1 & 2)
INSERT INTO `payments` (`id`, `uuid`, `receipt_number`, `loan_id`, `customer_id`, `agent_id`, `amount_pesewas`, `payment_method`, `collection_timestamp`, `gps_lat`, `gps_lng`, `note`, `status`) VALUES
(1, 'f1000000-0000-4000-a000-000000000001', 'EF-REC-20261027-0001', 1, 1, 3, 5000, 'cash', NOW() - INTERVAL 2 DAY, 5.51240000, -2.12450000, 'Daily collection installment #1', 'recorded'),
(2, 'f1000000-0000-4000-a000-000000000002', 'EF-REC-20261028-0002', 1, 1, 3, 5000, 'momo', NOW() - INTERVAL 1 DAY, 5.51240000, -2.12450000, 'Daily collection installment #2 (MTN MoMo)', 'recorded');

-- 8. NOTIFICATION LOGS FOR THE 2 PAYMENTS
INSERT INTO `notifications_log` (`channel`, `recipient`, `customer_id`, `payment_id`, `message`, `status`, `attempts`, `provider`, `provider_response`, `sent_at`) VALUES
('sms', '+233244123456', 1, 1, 'EL-FAITH: Paid GH₵50.00 on LN-0001. Rec: EF-REC-20261027-0001. Bal: GH₵1,050.00. Thank you!', 'delivered', 1, 'simulated', 'OK: Delivered to MTN Ghana', NOW() - INTERVAL 2 DAY),
('sms', '+233244123456', 1, 2, 'EL-FAITH: Paid GH₵50.00 on LN-0001. Rec: EF-REC-20261028-0002. Bal: GH₵1,000.00. Thank you!', 'delivered', 1, 'simulated', 'OK: Delivered to MTN Ghana', NOW() - INTERVAL 1 DAY);

-- 9. SAMPLE AUDIT LOGS
INSERT INTO `audit_log` (`user_id`, `action`, `table_name`, `record_id`, `new_values`, `ip_address`, `user_agent`) VALUES
(1, 'DATABASE_SEED', 'system', 1, '{"status": "Initialized EL-FAITH Microfinance database"}', '127.0.0.1', 'Antigravity Installer');

