<?php
/**
 * Staff & Agent Management - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Staff & User Management";

$error = '';
$currentAdminId = (int)currentUserId();

// Handle Staff Form Submissions (Create, Edit, Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security session expired. Please reload and try again.';
    } else {
        $action = trim($_POST['action'] ?? 'create');

        // 1. CREATE NEW STAFF ACCOUNT
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = in_array($_POST['role'] ?? '', ['admin', 'agent']) ? $_POST['role'] : 'agent';

            if (empty($name) || empty($username) || empty($email) || empty($password)) {
                $error = 'Please fill in all required fields to create a staff user.';
            } else {
                try {
                    // Check duplicate username or email
                    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                    $stmtCheck->execute([$username, $email]);
                    if ($stmtCheck->fetch()) {
                        $error = 'A staff user with this username or email already exists.';
                    } else {
                        $prefix = ($role === 'admin') ? 'EF-ADM-' : 'EF-AGT-';
                        $stmtCount = $pdo->query("SELECT COUNT(id) FROM users");
                        $count = (int)$stmtCount->fetchColumn() + 1;
                        $staffCode = $prefix . str_pad((string)$count, 3, '0', STR_PAD_LEFT);
                        $uuid = generateUuidV4();
                        $passHash = password_hash($password, PASSWORD_BCRYPT);

                        $stmtIns = $pdo->prepare("
                            INSERT INTO users (uuid, staff_code, name, username, email, phone, password_hash, role, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                        ");
                        $stmtIns->execute([$uuid, $staffCode, $name, $username, $email, $phone, $passHash, $role]);
                        $newUserId = (int)$pdo->lastInsertId();

                        logAudit($pdo, $currentAdminId, 'CREATE_STAFF_USER', 'users', $newUserId, null, [
                            'staff_code' => $staffCode,
                            'name' => $name,
                            'username' => $username,
                            'role' => $role
                        ]);

                        setFlash('success', "Staff member {$name} ({$staffCode}) created successfully.");
                        header('Location: ' . BASE_URL . '/admin/agents.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }

        // 2. EDIT EXISTING USER ACCOUNT
        } elseif ($action === 'edit') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['admin', 'agent']) ? $_POST['role'] : 'agent';
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'suspended']) ? $_POST['status'] : 'active';
            $password = trim($_POST['password'] ?? '');

            if ($userId <= 0 || empty($name) || empty($username) || empty($email) || empty($phone)) {
                $error = 'Please fill in all required fields for the user.';
            } else {
                // Fetch current user details
                $stmtCur = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmtCur->execute([$userId]);
                $targetUser = $stmtCur->fetch();

                if (!$targetUser) {
                    $error = 'The requested staff member was not found.';
                } elseif ($userId === $currentAdminId && $status !== 'active') {
                    $error = 'Security Guard: You cannot deactivate your own administrative account.';
                } elseif ($userId === $currentAdminId && $role !== 'admin') {
                    $error = 'Security Guard: You cannot revoke your own administrator privileges.';
                } else {
                    // Check if demoting or deactivating the last active administrator
                    if ($targetUser['role'] === 'admin' && ($role !== 'admin' || $status !== 'active')) {
                        $activeAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id != {$userId}")->fetchColumn();
                        if ($activeAdmins < 1) {
                            $error = 'Security Guard: Operation denied. At least one active administrator must remain in the system.';
                        }
                    }

                    // Check for duplicate username
                    if (empty($error)) {
                        $stmtDupU = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
                        $stmtDupU->execute([$username, $userId]);
                        if ($stmtDupU->fetch()) {
                            $error = "The username '{$username}' is already in use by another account.";
                        }
                    }

                    // Check for duplicate email
                    if (empty($error)) {
                        $stmtDupE = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                        $stmtDupE->execute([$email, $userId]);
                        if ($stmtDupE->fetch()) {
                            $error = "The email '{$email}' is already in use by another account.";
                        }
                    }

                    // Execute update
                    if (empty($error)) {
                        try {
                            if (!empty($password)) {
                                if (strlen($password) < 4) {
                                    $error = 'Password must be at least 4 characters long.';
                                } else {
                                    $passHash = password_hash($password, PASSWORD_BCRYPT);
                                    $stmtUpd = $pdo->prepare("
                                        UPDATE users 
                                        SET name = ?, username = ?, email = ?, phone = ?, role = ?, status = ?, password_hash = ?
                                        WHERE id = ?
                                    ");
                                    $stmtUpd->execute([$name, $username, $email, $phone, $role, $status, $passHash, $userId]);
                                }
                            } else {
                                $stmtUpd = $pdo->prepare("
                                    UPDATE users 
                                    SET name = ?, username = ?, email = ?, phone = ?, role = ?, status = ?
                                    WHERE id = ?
                                ");
                                $stmtUpd->execute([$name, $username, $email, $phone, $role, $status, $userId]);
                            }

                            if (empty($error)) {
                                // Update session if updating own profile
                                if ($userId === $currentAdminId) {
                                    $_SESSION['user_name'] = $name;
                                    $_SESSION['user_username'] = $username;
                                    $_SESSION['user_email'] = $email;
                                }

                                logAudit($pdo, $currentAdminId, 'EDIT_STAFF_USER', 'users', $userId, [
                                    'name' => $targetUser['name'],
                                    'username' => $targetUser['username'],
                                    'email' => $targetUser['email'],
                                    'phone' => $targetUser['phone'],
                                    'role' => $targetUser['role'],
                                    'status' => $targetUser['status']
                                ], [
                                    'name' => $name,
                                    'username' => $username,
                                    'email' => $email,
                                    'phone' => $phone,
                                    'role' => $role,
                                    'status' => $status,
                                    'password_reset' => !empty($password)
                                ]);

                                setFlash('success', "Staff user '{$name}' was successfully updated.");
                                header('Location: ' . BASE_URL . '/admin/agents.php');
                                exit;
                            }
                        } catch (PDOException $e) {
                            $error = 'Database error: ' . $e->getMessage();
                        }
                    }
                }
            }

        // 3. TOGGLE USER STATUS (ACTIVATE / DEACTIVATE)
        } elseif ($action === 'toggle_status') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $targetStatus = in_array($_POST['target_status'] ?? '', ['active', 'inactive']) ? $_POST['target_status'] : 'inactive';

            $stmtCur = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtCur->execute([$userId]);
            $targetUser = $stmtCur->fetch();

            if (!$targetUser) {
                $error = 'The requested staff member was not found.';
            } elseif ($userId === $currentAdminId) {
                $error = 'Security Guard: You cannot deactivate your own administrative account.';
            } else {
                // Check last active admin protection
                if ($targetUser['role'] === 'admin' && $targetStatus !== 'active') {
                    $activeAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id != {$userId}")->fetchColumn();
                    if ($activeAdmins < 1) {
                        $error = 'Security Guard: Cannot deactivate the only active administrator in the system.';
                    }
                }

                if (empty($error)) {
                    try {
                        $stmtTog = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                        $stmtTog->execute([$targetStatus, $userId]);

                        $auditAction = ($targetStatus === 'active') ? 'ACTIVATE_USER' : 'DEACTIVATE_USER';
                        logAudit($pdo, $currentAdminId, $auditAction, 'users', $userId, [
                            'status' => $targetUser['status']
                        ], [
                            'status' => $targetStatus
                        ]);

                        $label = ($targetStatus === 'active') ? 'activated' : 'deactivated';
                        setFlash('success', "Account for '{$targetUser['name']}' has been {$label}.");
                        header('Location: ' . BASE_URL . '/admin/agents.php');
                        exit;
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }

        // 4. DELETE (SOFT DELETE) STAFF ACCOUNT
        } elseif ($action === 'delete') {
            $userId = (int)($_POST['user_id'] ?? 0);

            $stmtCur = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtCur->execute([$userId]);
            $targetUser = $stmtCur->fetch();

            if (!$targetUser) {
                $error = 'The requested staff member was not found.';
            } elseif ($userId === $currentAdminId) {
                $error = 'Security Guard: You cannot delete your own administrative account.';
            } else {
                // Check if deleting the last active admin
                if ($targetUser['role'] === 'admin') {
                    $activeAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND deleted_at IS NULL AND id != {$userId}")->fetchColumn();
                    if ($activeAdmins < 1) {
                        $error = 'Security Guard: Operation denied. At least one active administrator must remain in the system.';
                    }
                }

                if (empty($error)) {
                    try {
                        $pdo->beginTransaction();

                        // Soft-delete user
                        $stmtDel = $pdo->prepare("UPDATE users SET deleted_at = NOW(), status = 'inactive' WHERE id = ?");
                        $stmtDel->execute([$userId]);

                        // Safely unassign customers assigned to this agent so they are not left trapped
                        $stmtUnassign = $pdo->prepare("UPDATE customers SET assigned_agent_id = NULL WHERE assigned_agent_id = ?");
                        $stmtUnassign->execute([$userId]);

                        // Deactivate any active agent routes / assignments
                        $stmtAa = $pdo->prepare("UPDATE agent_assignments SET is_active = 0 WHERE agent_id = ?");
                        $stmtAa->execute([$userId]);

                        logAudit($pdo, $currentAdminId, 'DELETE_STAFF_USER', 'users', $userId, [
                            'name' => $targetUser['name'],
                            'username' => $targetUser['username'],
                            'role' => $targetUser['role']
                        ], [
                            'action' => 'soft_deleted',
                            'deleted_at' => date('Y-m-d H:i:s')
                        ]);

                        $pdo->commit();

                        setFlash('success', "Staff account '{$targetUser['name']}' ({$targetUser['staff_code']}) removed. All historical collections, issued loans, and audit trails remain completely preserved.");
                        header('Location: ' . BASE_URL . '/admin/agents.php');
                        exit;
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }

        // 5. RESTORE STAFF ACCOUNT
        } elseif ($action === 'restore') {
            $userId = (int)($_POST['user_id'] ?? 0);

            $stmtCur = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtCur->execute([$userId]);
            $targetUser = $stmtCur->fetch();

            if (!$targetUser) {
                $error = 'The requested staff member was not found.';
            } else {
                try {
                    $stmtRes = $pdo->prepare("UPDATE users SET deleted_at = NULL, status = 'active' WHERE id = ?");
                    $stmtRes->execute([$userId]);

                    logAudit($pdo, $currentAdminId, 'RESTORE_STAFF_USER', 'users', $userId, null, [
                        'action' => 'restored',
                        'name' => $targetUser['name']
                    ]);

                    setFlash('success', "Staff account '{$targetUser['name']}' ({$targetUser['staff_code']}) has been restored to active status.");
                    header('Location: ' . BASE_URL . '/admin/agents.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch staff users list with statistics
$staffList = $pdo->query("
    SELECT u.*,
           COUNT(DISTINCT c.id) AS assigned_customers_count,
           COUNT(DISTINCT l.id) AS active_loans_count,
           COALESCE(SUM(DISTINCT CASE WHEN l.status IN ('active', 'overdue') THEN l.remaining_balance_pesewas ELSE 0 END), 0) AS portfolio_pesewas
    FROM users u
    LEFT JOIN customers c ON u.id = c.assigned_agent_id AND c.deleted_at IS NULL
    LEFT JOIN loans l ON u.id = l.agent_id AND l.status IN ('active', 'overdue')
    GROUP BY u.id
    ORDER BY (u.deleted_at IS NOT NULL) ASC, u.role ASC, (u.status = 'active') DESC, u.name ASC
")->fetchAll();

// Calculate summary stats
$totalUsers = 0;
$activeCount = 0;
$inactiveCount = 0;
$deletedCount = 0;
$agentCount = 0;
$adminCount = 0;

foreach ($staffList as $st) {
    if (!empty($st['deleted_at'])) {
        $deletedCount++;
        continue;
    }
    $totalUsers++;
    if ($st['status'] === 'active') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if ($st['role'] === 'admin') {
        $adminCount++;
    } else {
        $agentCount++;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header & Main Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">Staff & User Management</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Manage personnel accounts, credentials, administrative roles, and system access</p>
        </div>
        <button type="button" onclick="openAddModal()" 
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] self-start sm:self-auto shadow-sm">
            <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Add Staff Account
        </button>
    </div>

    <!-- Feedback Message -->
    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-zinc-50 border border-zinc-200 text-black text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-zinc-800"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Modern Fintech Summary Statistics Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white rounded-2xl border border-slate-200/90 p-4 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Total Personnel</span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center ring-1 ring-blue-600/10">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-950 tracking-tight tabular-nums"><?= $totalUsers ?></div>
            <span class="text-[11px] text-slate-500 block"><?= $adminCount ?> Admin &bull; <?= $agentCount ?> Field Agents</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/90 p-4 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Active Staff</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center ring-1 ring-emerald-600/10">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-950 tracking-tight tabular-nums flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <?= $activeCount ?>
            </div>
            <span class="text-[11px] text-emerald-700 font-medium block">Authorized to collect</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/90 p-4 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Deactivated</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center ring-1 ring-amber-600/10">
                    <i data-lucide="user-x" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-950 tracking-tight tabular-nums"><?= $inactiveCount ?></div>
            <span class="text-[11px] text-slate-500 block">Login suspended</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/90 p-4 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Archived / Deleted</span>
                <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center ring-1 ring-rose-600/10">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-950 tracking-tight tabular-nums"><?= $deletedCount ?></div>
            <span class="text-[11px] text-slate-500 block">Records preserved</span>
        </div>
    </div>

    <!-- Filter & Live Search Toolbar -->
    <div class="bg-white rounded-xl border border-zinc-200 p-3 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
        <div class="relative flex-1">
            <i data-lucide="search" class="w-3.5 h-3.5 text-zinc-500 absolute left-3 top-1/2 -translate-y-1/2"></i>
            <input type="text" id="staff-search" oninput="filterStaffCards()" 
                placeholder="Search staff by name, username, staff code, or email..."
                class="w-full pl-8 pr-3 py-1.5 bg-zinc-50 border border-zinc-200 rounded-lg text-xs text-black placeholder-zinc-400 focus:outline-none focus:border-blue-600 focus:bg-white transition">
        </div>
        <div class="flex items-center gap-2">
            <select id="role-filter" onchange="filterStaffCards()" 
                class="px-2.5 py-1.5 bg-zinc-50 border border-zinc-200 rounded-lg text-xs text-zinc-800 focus:outline-none focus:border-blue-600 focus:bg-white transition">
                <option value="all">All Roles</option>
                <option value="admin">Administrators</option>
                <option value="agent">Field Agents</option>
            </select>
            <select id="status-filter" onchange="filterStaffCards()" 
                class="px-2.5 py-1.5 bg-zinc-50 border border-zinc-200 rounded-lg text-xs text-zinc-800 focus:outline-none focus:border-blue-600 focus:bg-white transition">
                <option value="all">All Personnel</option>
                <option value="active">Active Only</option>
                <option value="inactive">Deactivated Only</option>
                <option value="deleted">Deleted / Archived</option>
            </select>
        </div>
    </div>

    <!-- Staff Cards Grid -->
    <div id="staff-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($staffList as $st): ?>
            <?php 
                $isSelf = ((int)$st['id'] === $currentAdminId);
                $isDeleted = !empty($st['deleted_at']);
                $isActive = ($st['status'] === 'active' && !$isDeleted);
                $userJson = htmlspecialchars(json_encode([
                    'id' => (int)$st['id'],
                    'staff_code' => $st['staff_code'],
                    'name' => $st['name'],
                    'username' => $st['username'],
                    'email' => $st['email'],
                    'phone' => $st['phone'],
                    'role' => $st['role'],
                    'status' => $st['status'],
                    'is_self' => $isSelf
                ]), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="staff-card bg-white rounded-xl border <?= $isDeleted ? 'border-red-200 bg-red-50/20 opacity-80' : ($isActive ? 'border-zinc-200' : 'border-zinc-200 bg-zinc-100/70') ?> p-4 space-y-3 transition flex flex-col justify-between"
                data-name="<?= htmlspecialchars(strtolower($st['name'])) ?>"
                data-username="<?= htmlspecialchars(strtolower($st['username'])) ?>"
                data-code="<?= htmlspecialchars(strtolower($st['staff_code'])) ?>"
                data-email="<?= htmlspecialchars(strtolower($st['email'])) ?>"
                data-role="<?= htmlspecialchars($st['role']) ?>"
                data-status="<?= $isDeleted ? 'deleted' : htmlspecialchars($st['status']) ?>">
                
                <div class="space-y-3">
                    <!-- Top Row: Staff Code, Badges & Role -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-mono text-zinc-500 block"><?= htmlspecialchars($st['staff_code']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="px-1 py-0.2 text-[9px] font-semibold bg-zinc-100 text-zinc-700 rounded">YOU</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="text-sm font-bold text-black mt-0.5 <?= $isDeleted ? 'text-zinc-600 line-through' : (!$isActive ? 'text-zinc-600' : '') ?>">
                                <?= htmlspecialchars($st['name']) ?>
                            </h2>
                            <span class="text-xs text-zinc-500 font-mono">@<?= htmlspecialchars($st['username']) ?></span>
                        </div>

                        <div class="flex flex-col items-end gap-1">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider <?= $st['role'] === 'admin' ? 'bg-black text-white' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>">
                                <?= htmlspecialchars($st['role']) ?>
                            </span>

                            <!-- Status Badge -->
                            <?php if ($isDeleted): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-red-700 bg-red-100 px-1.5 py-0.5 rounded border border-red-200">
                                    DELETED
                                </span>
                            <?php elseif ($isActive): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-zinc-700 bg-zinc-200 px-1.5 py-0.5 rounded border border-zinc-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span> <?= ucfirst(htmlspecialchars($st['status'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <div class="space-y-1 text-xs text-zinc-700 pt-1">
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="phone" class="w-3.5 h-3.5 text-zinc-500 shrink-0"></i>
                            <span class="font-mono text-[11px]"><?= htmlspecialchars($st['phone']) ?></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="mail" class="w-3.5 h-3.5 text-zinc-500 shrink-0"></i>
                            <span class="text-[11px] text-zinc-600 truncate" title="<?= htmlspecialchars($st['email']) ?>"><?= htmlspecialchars($st['email']) ?></span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[10px] text-zinc-500">
                            <i data-lucide="clock" class="w-3 h-3 text-zinc-500 shrink-0"></i>
                            <span>Last sign in: <?= $st['last_login_at'] ? formatDateTime($st['last_login_at']) : 'Never' ?></span>
                        </div>
                    </div>

                    <!-- Portfolio / Borrower Statistics -->
                    <div class="pt-2.5 border-t border-zinc-100 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-zinc-500 block text-[10px] uppercase">Borrowers</span>
                            <span class="text-sm font-semibold text-black"><?= (int)$st['assigned_customers_count'] ?></span>
                        </div>
                        <div class="text-right">
                            <span class="text-zinc-500 block text-[10px] uppercase">Portfolio</span>
                            <span class="text-sm font-semibold text-black"><?= formatMoney((int)$st['portfolio_pesewas']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Action Toolbar: Edit & Deactivate / Activate / Delete -->
                <div class="pt-2.5 border-t border-zinc-100 flex items-center justify-between gap-1 text-xs">
                    <?php if ($isDeleted): ?>
                        <div class="flex items-center gap-1.5">
                            <form method="POST" action="agents.php" class="inline" onsubmit="return confirm('Restore account for <?= htmlspecialchars(addslashes($st['name'])) ?>?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="user_id" value="<?= (int)$st['id'] ?>">
                                <button type="submit" 
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition shadow-sm">
                                    <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Restore Account
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-1.5">
                            <!-- Edit Button -->
                            <button type="button" 
                                onclick='openEditModal(<?= $userJson ?>)' 
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium text-zinc-800 bg-white border border-zinc-200 rounded-lg hover:bg-zinc-100 hover:text-black transition">
                                <i data-lucide="edit-2" class="w-3 h-3 text-zinc-500"></i> Edit
                            </button>

                            <!-- Deactivate / Activate Action Button -->
                            <?php if ($isSelf): ?>
                                <span class="text-[11px] text-zinc-500 italic px-1" title="You cannot deactivate your own account">Current User</span>
                            <?php elseif ($isActive): ?>
                                <form method="POST" action="agents.php" class="inline" onsubmit="return confirm('Deactivate account for <?= htmlspecialchars(addslashes($st['name'])) ?>? They will immediately lose login and collection privileges.')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= (int)$st['id'] ?>">
                                    <input type="hidden" name="target_status" value="inactive">
                                    <button type="submit" 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium text-zinc-700 bg-zinc-50 border border-zinc-200 rounded-lg hover:bg-zinc-100 hover:text-red-700 transition">
                                        <i data-lucide="user-x" class="w-3 h-3 text-zinc-500"></i> Deactivate
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="agents.php" class="inline" onsubmit="return confirm('Re-activate account for <?= htmlspecialchars(addslashes($st['name'])) ?>? They will be able to sign in again.')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= (int)$st['id'] ?>">
                                    <input type="hidden" name="target_status" value="active">
                                    <button type="submit" 
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium text-zinc-950 bg-white border border-zinc-200 rounded-lg hover:bg-zinc-100 transition">
                                        <i data-lucide="user-check" class="w-3 h-3 text-emerald-600"></i> Activate
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$isSelf): ?>
                                <button type="button" 
                                    onclick="openDeleteStaffModal(<?= (int)$st['id'] ?>, '<?= htmlspecialchars(addslashes($st['name'])) ?>', '<?= htmlspecialchars(addslashes($st['staff_code'])) ?>', '<?= htmlspecialchars(addslashes($st['role'])) ?>', <?= (int)$st['assigned_customers_count'] ?>)"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-zinc-500 hover:text-red-700 bg-zinc-50 hover:bg-red-50 border border-zinc-200 rounded-lg transition"
                                    title="Delete staff account">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($st['role'] === 'agent'): ?>
                        <a href="<?= BASE_URL ?>/admin/reconciliation.php?agent_id=<?= $st['id'] ?>" 
                            class="text-[11px] text-zinc-700 hover:text-black font-medium hover:underline inline-flex items-center gap-0.5">
                            Reconcile &rarr;
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===================================================================== -->
<!-- EDIT USER MODAL                                                       -->
<!-- ===================================================================== -->
<div id="edit-user-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-zinc-200 max-w-md w-full p-5 sm:p-6 space-y-4 shadow-sm animate-in fade-in duration-100">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <div>
                <h3 class="text-xs font-semibold text-black uppercase tracking-wider">
                    Edit Staff Account
                </h3>
                <span id="edit-staff-code-badge" class="text-[10px] font-mono text-zinc-500"></span>
            </div>
            <button type="button" onclick="closeEditModal()" class="text-zinc-500 hover:text-zinc-800 p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="agents.php" class="space-y-3.5 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit-user-id" name="user_id" value="">

            <div>
                <label for="edit-name" class="block text-[11px] font-medium text-zinc-800 mb-1">Full Name *</label>
                <input type="text" id="edit-name" name="name" required 
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label for="edit-username" class="block text-[11px] font-medium text-zinc-800 mb-1">Username *</label>
                    <input type="text" id="edit-username" name="username" required 
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                </div>
                <div>
                    <label for="edit-role" class="block text-[11px] font-medium text-zinc-800 mb-1">System Role *</label>
                    <select id="edit-role" name="role" 
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                        <option value="agent">Field Agent</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label for="edit-status" class="block text-[11px] font-medium text-zinc-800 mb-1">Account Status *</label>
                    <select id="edit-status" name="status" 
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                        <option value="active">Active (Can Sign In)</option>
                        <option value="inactive">Inactive / Deactivated</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div>
                    <label for="edit-phone" class="block text-[11px] font-medium text-zinc-800 mb-1">Phone Number *</label>
                    <input type="text" id="edit-phone" name="phone" required 
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition" placeholder="+233241234567">
                </div>
            </div>

            <div>
                <label for="edit-email" class="block text-[11px] font-medium text-zinc-800 mb-1">Email Address *</label>
                <input type="email" id="edit-email" name="email" required 
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
            </div>

            <div class="pt-2 border-t border-zinc-100">
                <label for="edit-password" class="block text-[11px] font-medium text-zinc-800 mb-0.5">
                    Reset Password <span class="text-zinc-500 font-normal">(Optional)</span>
                </label>
                <p class="text-[10px] text-zinc-500 mb-1.5">Leave completely blank to retain user's current password.</p>
                <input type="password" id="edit-password" name="password" minlength="4" 
                    placeholder="Enter new password or leave blank"
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-100">
                <button type="button" onclick="closeEditModal()" 
                    class="px-3 py-1.5 bg-zinc-100 text-zinc-800 rounded-lg text-xs font-medium hover:bg-zinc-200 transition">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition shadow-sm">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===================================================================== -->
<!-- ADD STAFF ACCOUNT MODAL                                               -->
<!-- ===================================================================== -->
<div id="add-agent-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-zinc-200 max-w-md w-full p-5 sm:p-6 space-y-4 shadow-sm animate-in fade-in duration-100">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <h3 class="text-xs font-semibold text-black uppercase tracking-wider">
                Create Staff Account
            </h3>
            <button type="button" onclick="closeAddModal()" class="text-zinc-500 hover:text-zinc-800 p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form method="POST" action="agents.php" class="space-y-3.5 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div>
                <label for="new-name" class="block text-[11px] font-medium text-zinc-800 mb-1">Full Name *</label>
                <input type="text" id="new-name" name="name" required 
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition" 
                    placeholder="e.g. John Mensah">
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label for="new-username" class="block text-[11px] font-medium text-zinc-800 mb-1">Username *</label>
                    <input type="text" id="new-username" name="username" required 
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition" 
                        placeholder="e.g. jmensah">
                </div>
                <div>
                    <label for="new-role" class="block text-[11px] font-medium text-zinc-800 mb-1">System Role *</label>
                    <select id="new-role" name="role" 
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition">
                        <option value="agent" selected>Field Agent</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="new-email" class="block text-[11px] font-medium text-zinc-800 mb-1">Email Address *</label>
                <input type="email" id="new-email" name="email" required 
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition" 
                    placeholder="john@elfaith.com">
            </div>

            <div>
                <label for="new-phone" class="block text-[11px] font-medium text-zinc-800 mb-1">Phone Number *</label>
                <input type="text" id="new-phone" name="phone" required 
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition" 
                    placeholder="+233241234567">
            </div>

            <div>
                <label for="new-password" class="block text-[11px] font-medium text-zinc-800 mb-1">Initial Password *</label>
                <input type="password" id="new-password" name="password" required minlength="4" 
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs text-black focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition" 
                    placeholder="Initial sign-in password">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-100">
                <button type="button" onclick="closeAddModal()" 
                    class="px-3 py-1.5 bg-zinc-100 text-zinc-800 rounded-lg text-xs font-medium hover:bg-zinc-200 transition">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition shadow-sm">
                    Create Staff
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Edit Modal Management
    function openEditModal(user) {
        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-staff-code-badge').textContent = user.staff_code;
        document.getElementById('edit-name').value = user.name;
        document.getElementById('edit-username').value = user.username;
        document.getElementById('edit-email').value = user.email;
        document.getElementById('edit-phone').value = user.phone;
        document.getElementById('edit-role').value = user.role;
        document.getElementById('edit-status').value = user.status;
        document.getElementById('edit-password').value = '';

        // Handle self-protection in UI
        const statusSelect = document.getElementById('edit-status');
        const roleSelect = document.getElementById('edit-role');
        if (user.is_self) {
            statusSelect.disabled = true;
            roleSelect.disabled = true;
            statusSelect.title = "You cannot deactivate your own account";
            roleSelect.title = "You cannot revoke your own administrator role";
        } else {
            statusSelect.disabled = false;
            roleSelect.disabled = false;
            statusSelect.title = "";
            roleSelect.title = "";
        }

        document.getElementById('edit-user-modal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('edit-user-modal').classList.add('hidden');
    }

    // Add Modal Management
    function openAddModal() {
        document.getElementById('add-agent-modal').classList.remove('hidden');
    }

    function closeAddModal() {
        document.getElementById('add-agent-modal').classList.add('hidden');
    }

    // Live Staff Search & Filtering
    function filterStaffCards() {
        const query = (document.getElementById('staff-search').value || '').toLowerCase().trim();
        const role = document.getElementById('role-filter').value;
        const status = document.getElementById('status-filter').value;

        const cards = document.querySelectorAll('.staff-card');
        cards.forEach(card => {
            const name = card.dataset.name || '';
            const username = card.dataset.username || '';
            const code = card.dataset.code || '';
            const email = card.dataset.email || '';
            const cardRole = card.dataset.role || '';
            const cardStatus = card.dataset.status || '';

            const matchesQuery = !query || 
                name.includes(query) || 
                username.includes(query) || 
                code.includes(query) || 
                email.includes(query);

            const matchesRole = (role === 'all') || (cardRole === role);
            const matchesStatus = (status === 'all') ? (cardStatus !== 'deleted') :
                (status === 'deleted' ? (cardStatus === 'deleted') :
                (status === 'active' ? (cardStatus === 'active') :
                (status === 'inactive' ? (cardStatus === 'inactive') : true)));

            if (matchesQuery && matchesRole && matchesStatus) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Delete Staff Modal Management
    function openDeleteStaffModal(id, name, code, role, borrowerCount) {
        document.getElementById('del-staff-id').value = id;
        document.getElementById('del-staff-name').textContent = name;
        document.getElementById('del-staff-code').textContent = code;
        document.getElementById('del-staff-role').textContent = role.toUpperCase();

        const warnDiv = document.getElementById('del-staff-borrowers-warn');
        if (borrowerCount > 0) {
            document.getElementById('del-staff-borrower-count').textContent = borrowerCount;
            warnDiv.classList.remove('hidden');
        } else {
            warnDiv.classList.add('hidden');
        }

        document.getElementById('delete-staff-modal').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeDeleteStaffModal() {
        document.getElementById('delete-staff-modal').classList.add('hidden');
    }

    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeEditModal();
            closeAddModal();
            closeDeleteStaffModal();
        }
    });
</script>

<!-- ===================================================================== -->
<!-- DELETE STAFF CONFIRMATION MODAL                                       -->
<!-- ===================================================================== -->
<div id="delete-staff-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 shadow-xl border border-zinc-200">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                <i data-lucide="trash-2" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-black">Delete Staff Account?</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Non-destructive removal & archiving</p>
            </div>
        </div>

        <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-xs space-y-1.5 text-zinc-700">
            <div><strong>Staff Member:</strong> <span id="del-staff-name" class="font-medium text-black"></span> (<span id="del-staff-code" class="font-mono"></span>)</div>
            <div><strong>System Role:</strong> <span id="del-staff-role" class="font-mono text-zinc-900"></span></div>
            <div id="del-staff-borrowers-warn" class="hidden text-amber-700 font-medium">
                Note: <span id="del-staff-borrower-count"></span> assigned borrower(s) will be unassigned so they can be reassigned to other field agents.
            </div>
        </div>

        <div class="bg-blue-50/60 border border-blue-200 rounded-lg p-3 text-[11px] text-blue-900 space-y-1 leading-relaxed">
            <div class="font-bold flex items-center gap-1.5 text-blue-950">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-blue-600"></i> Financial Records Protected
            </div>
            <div>All previous payments collected by this user, issued loans, audit trails, and reconciliation statements remain completely preserved and attributed in the ledger. The user will be immediately logged out and unable to sign in.</div>
        </div>

        <form method="POST" action="agents.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="del-staff-id" value="">

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                <button type="button" onclick="closeDeleteStaffModal()" class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 rounded-lg text-xs font-medium transition">
                    Cancel
                </button>
                <button type="submit" class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-medium transition shadow-sm">
                    Confirm Delete
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
