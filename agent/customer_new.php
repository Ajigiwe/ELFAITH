<?php
/**
 * Register New Customer (Agent / Admin) - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$agentId = currentUserId();
$role = currentUserRole();
$page_title = "Register Borrower";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security session expired. Please resubmit.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $altPhone = trim($_POST['alt_phone'] ?? '') ?: null;
        $idType = trim($_POST['id_type'] ?? 'Ghana Card');
        $idNumber = trim($_POST['id_number'] ?? '');
        $address = trim($_POST['residential_address'] ?? '');
        $landmark = trim($_POST['location_landmark'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $guarantorName = trim($_POST['guarantor_name'] ?? '');
        $guarantorPhone = trim($_POST['guarantor_phone'] ?? '');
        $guarantorRelation = trim($_POST['guarantor_relation'] ?? '');
        $guarantorAddress = trim($_POST['guarantor_address'] ?? '') ?: null;
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $gpsLat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
        $gpsLng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;

        // If admin, can specify assigned agent; if agent, assign to self
        $assignedAgentId = ($role === 'admin' && !empty($_POST['assigned_agent_id'])) ? (int)$_POST['assigned_agent_id'] : $agentId;

        if (empty($fullName) || empty($phone) || empty($idNumber) || empty($address) || empty($guarantorName) || empty($guarantorPhone)) {
            $error = 'Please fill in all required fields (Name, Phone, ID, Address, Guarantor).';
        } else {
            try {
                // Generate sequential customer code: EF-CUS-XXXX
                $stmtCount = $pdo->query("SELECT COUNT(id) FROM customers");
                $count = (int)$stmtCount->fetchColumn() + 1;
                $customerCode = 'EF-CUS-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
                $uuid = generateUuidV4();

                $stmtIns = $pdo->prepare("
                    INSERT INTO customers (
                        uuid, customer_code, full_name, phone, alt_phone, id_type, id_number,
                        residential_address, location_landmark, gps_lat, gps_lng, occupation,
                        guarantor_name, guarantor_phone, guarantor_relation, guarantor_address,
                        assigned_agent_id, created_by, status, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                ");
                $stmtIns->execute([
                    $uuid, $customerCode, $fullName, $phone, $altPhone, $idType, $idNumber,
                    $address, $landmark, $gpsLat, $gpsLng, $occupation,
                    $guarantorName, $guarantorPhone, $guarantorRelation, $guarantorAddress,
                    $assignedAgentId, $agentId, $notes
                ]);
                $newCustomerId = (int)$pdo->lastInsertId();

                // Create default assignment record
                $stmtAssign = $pdo->prepare("
                    INSERT INTO agent_assignments (agent_id, customer_id, assigned_by, route_day, is_active)
                    VALUES (?, ?, ?, 'all_days', 1)
                    ON DUPLICATE KEY UPDATE is_active = 1
                ");
                $stmtAssign->execute([$assignedAgentId, $newCustomerId, $agentId]);

                logAudit($pdo, $agentId, 'CREATE_CUSTOMER', 'customers', $newCustomerId, null, ['code' => $customerCode, 'name' => $fullName]);

                setFlash('success', "Customer {$fullName} ({$customerCode}) registered successfully!");
                if ($role === 'admin') {
                    header("Location: " . BASE_URL . "/admin/customer_view.php?id={$newCustomerId}");
                } else {
                    header("Location: " . BASE_URL . "/agent/my_customers.php");
                }
                exit;

            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error = 'A borrower with this phone or ID number is already registered.';
                } else {
                    $error = 'Failed to register customer: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch agents list for admin selector
$agentsList = [];
if ($role === 'admin') {
    $agentsList = $pdo->query("SELECT id, name, staff_code FROM users WHERE role = 'agent' AND status = 'active' ORDER BY name ASC")->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Register Borrower</h1>
            <p class="text-xs text-slate-400 mt-0.5">Customer KYC, location landmark & guarantor registration</p>
        </div>
        <a href="<?= $role === 'admin' ? BASE_URL . '/admin/customers.php' : BASE_URL . '/agent/my_customers.php' ?>" 
           class="inline-flex items-center gap-1 text-xs text-slate-600 bg-white border border-slate-200 px-2.5 py-1.5 rounded-lg hover:bg-slate-50">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-slate-50 border border-purple-200 text-slate-900 text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-slate-700"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6">
        <form method="POST" class="space-y-5 text-xs">
            <?= csrfField() ?>
            <input type="hidden" id="gps_lat" name="gps_lat" value="">
            <input type="hidden" id="gps_lng" name="gps_lng" value="">

            <!-- Section 1: Customer Personal Details -->
            <div class="space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-700 pb-2 border-b border-slate-100">
                    Borrower Personal Details
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Full Name *</label>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. Kwadwo Mensah">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Phone Number *</label>
                        <input type="text" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. +233241234567">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Alternative Phone (Optional)</label>
                        <input type="text" name="alt_phone" value="<?= htmlspecialchars($_POST['alt_phone'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. +233501234567">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">ID Type *</label>
                        <select name="id_type" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                            <option value="Ghana Card" selected>Ghana Card</option>
                            <option value="Voters ID">Voters ID</option>
                            <option value="Passport">Passport</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Ghana Card / ID Number *</label>
                        <input type="text" name="id_number" required value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. GHA-712345678-1">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Occupation / Business *</label>
                        <input type="text" name="occupation" required value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. Market Trader, Cocoa Farmer, Mechanic">
                    </div>
                </div>
            </div>

            <!-- Section 2: Location & Address -->
            <div class="space-y-3 pt-3 border-t border-slate-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-700 pb-2 border-b border-slate-100">
                    Location & Landmark
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Residential Address *</label>
                        <input type="text" name="residential_address" required value="<?= htmlspecialchars($_POST['residential_address'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. House No 24, Powuako Market Road">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Location Landmark (For Route Navigation) *</label>
                        <input type="text" name="location_landmark" required value="<?= htmlspecialchars($_POST['location_landmark'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. Behind Central Mosque, opposite Chief Palace">
                    </div>
                </div>
            </div>

            <!-- Section 3: Guarantor Information -->
            <div class="space-y-3 pt-3 border-t border-slate-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-700 pb-2 border-b border-slate-100">
                    Guarantor Information
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Guarantor Full Name *</label>
                        <input type="text" name="guarantor_name" required value="<?= htmlspecialchars($_POST['guarantor_name'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. Akua Donkor">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Guarantor Phone *</label>
                        <input type="text" name="guarantor_phone" required value="<?= htmlspecialchars($_POST['guarantor_phone'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. +233245678901">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Relationship to Borrower *</label>
                        <input type="text" name="guarantor_relation" required value="<?= htmlspecialchars($_POST['guarantor_relation'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. Brother, Mother, Business Partner">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Guarantor Address (Optional)</label>
                        <input type="text" name="guarantor_address" value="<?= htmlspecialchars($_POST['guarantor_address'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="e.g. Wassa Powuako Station">
                    </div>
                </div>
            </div>

            <!-- Admin Only: Assign to Agent -->
            <?php if ($role === 'admin' && !empty($agentsList)): ?>
                <div class="pt-3 border-t border-slate-100">
                    <label class="block text-[11px] font-medium text-slate-700 mb-1">Assign Field Agent</label>
                    <select name="assigned_agent_id" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                        <?php foreach ($agentsList as $ag): ?>
                            <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['name']) ?> (<?= htmlspecialchars($ag['staff_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="pt-2">
                <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Complete Registration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    if (typeof captureAgentGps === 'function') {
        captureAgentGps((lat, lng) => {
            if (lat && lng) {
                document.getElementById('gps_lat').value = lat;
                document.getElementById('gps_lng').value = lng;
            }
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
