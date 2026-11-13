<?php
/**
 * Database Backup, Restore & Data Wipe Control Center - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backup_manager.php';

requireAdmin();

$page_title = "Backup & Data Wipe";
$adminUser = currentUser();
$error = '';
$success = '';

// 1. Direct File Download (From server archive)
if (isset($_GET['action']) && $_GET['action'] === 'download_file') {
    $fn = basename($_GET['file'] ?? '');
    $filePath = BACKUP_DIR . '/' . $fn;

    if (!empty($fn) && file_exists($filePath) && str_ends_with($fn, '.sql')) {
        logAudit($pdo, currentUserId(), 'DOWNLOAD_ARCHIVED_BACKUP', 'backups', null, null, ['file' => $fn]);
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fn . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Pragma: no-cache');
        readfile($filePath);
        exit;
    } else {
        setFlash('error', 'Requested backup file does not exist.');
        header('Location: ' . BASE_URL . '/admin/backup.php');
        exit;
    }
}

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Session security token expired. Please reload and try again.';
    } else {
        $backupAction = $_POST['backup_action'] ?? '';
        $adminPassword = $_POST['admin_password'] ?? '';

        // Helper password verification for dangerous actions
        $verifyAdminPass = function() use ($pdo, $adminUser, $adminPassword): bool {
            if (empty($adminPassword)) return false;
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$adminUser['id']]);
            $hash = $stmt->fetchColumn();
            return $hash && password_verify($adminPassword, $hash);
        };

        // ACTION A: Instant Download Backup (No password needed to back up)
        if ($backupAction === 'download_sql') {
            try {
                $sqlDump = generateDatabaseSqlDump($pdo);
                $fn = 'elfaith_backup_' . date('Ymd_His') . '.sql';

                logAudit($pdo, currentUserId(), 'EXPORT_DATABASE_BACKUP', 'all_tables', null, null, [
                    'filename' => $fn,
                    'size_bytes' => strlen($sqlDump)
                ]);

                header('Content-Type: application/sql; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $fn . '"');
                header('Content-Length: ' . strlen($sqlDump));
                header('Pragma: no-cache');
                echo $sqlDump;
                exit;
            } catch (Exception $e) {
                $error = 'Failed to generate database backup: ' . $e->getMessage();
            }

        // ACTION B: Create Server Snapshot
        } elseif ($backupAction === 'create_snapshot') {
            try {
                $sqlDump = generateDatabaseSqlDump($pdo);
                $savedFile = saveBackupToFile($sqlDump);

                logAudit($pdo, currentUserId(), 'CREATE_BACKUP_SNAPSHOT', 'backups', null, null, [
                    'filename' => $savedFile,
                    'size_bytes' => strlen($sqlDump)
                ]);

                setFlash('success', "Backup snapshot '{$savedFile}' successfully saved to server archive!");
                header('Location: ' . BASE_URL . '/admin/backup.php');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to save snapshot to server: ' . $e->getMessage();
            }

        // ACTION C: Restore from Uploaded .sql File
        } elseif ($backupAction === 'restore_upload') {
            if (!$verifyAdminPass()) {
                $error = 'Invalid administrator password confirmation. Restore aborted.';
            } elseif (empty($_FILES['sql_file']['tmp_name']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a valid .sql backup file to upload.';
            } else {
                $uploadedName = $_FILES['sql_file']['name'];
                if (!str_ends_with(strtolower($uploadedName), '.sql')) {
                    $error = 'Only .sql database dump files are supported.';
                } else {
                    $sqlContent = file_get_contents($_FILES['sql_file']['tmp_name']);
                    try {
                        $res = restoreDatabaseFromSql($pdo, $sqlContent, currentUserId());
                        setFlash('success', "Database restored successfully from '{$uploadedName}'! Safety snapshot '{$res['safety_snapshot']}' was created.");
                        header('Location: ' . BASE_URL . '/admin/backup.php');
                        exit;
                    } catch (Exception $e) {
                        $error = 'Restore failed: ' . $e->getMessage();
                    }
                }
            }

        // ACTION D: Restore from Existing Server Snapshot
        } elseif ($backupAction === 'restore_existing') {
            $selectedFile = basename($_POST['snapshot_file'] ?? '');
            $filePath = BACKUP_DIR . '/' . $selectedFile;

            if (!$verifyAdminPass()) {
                $error = 'Invalid administrator password confirmation. Restore aborted.';
            } elseif (empty($selectedFile) || !file_exists($filePath)) {
                $error = 'Selected backup snapshot does not exist on server.';
            } else {
                $sqlContent = file_get_contents($filePath);
                try {
                    $res = restoreDatabaseFromSql($pdo, $sqlContent, currentUserId());
                    setFlash('success', "Database restored successfully from '{$selectedFile}'! Safety snapshot '{$res['safety_snapshot']}' was created.");
                    header('Location: ' . BASE_URL . '/admin/backup.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Restore failed: ' . $e->getMessage();
                }
            }

        // ACTION E: Data Wipe / Reset
        } elseif ($backupAction === 'wipe_data') {
            $wipeMode = $_POST['wipe_mode'] ?? '';
            $confirmPhrase = trim($_POST['confirm_phrase'] ?? '');

            if (!$verifyAdminPass()) {
                $error = 'Invalid administrator password confirmation. Data wipe aborted.';
            } elseif ($confirmPhrase !== 'CONFIRM WIPE') {
                $error = 'Confirmation text did not match "CONFIRM WIPE". Wipe operation aborted.';
            } elseif (!in_array($wipeMode, ['transactions_only', 'operational_data', 'factory_reset'])) {
                $error = 'Please choose a valid data wipe scope.';
            } else {
                try {
                    $res = wipeDatabaseData($pdo, $wipeMode, currentUserId());
                    setFlash('success', "Data wipe completed! {$res['description']}. Safety snapshot '{$res['safety_snapshot']}' was saved.");
                    header('Location: ' . BASE_URL . '/admin/backup.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Data wipe failed: ' . $e->getMessage();
                }
            }

        // ACTION F: Delete Backup Snapshot
        } elseif ($backupAction === 'delete_snapshot') {
            $delFile = basename($_POST['delete_file'] ?? '');
            $filePath = BACKUP_DIR . '/' . $delFile;

            if (!$verifyAdminPass()) {
                $error = 'Invalid administrator password confirmation. Deletion aborted.';
            } elseif (empty($delFile) || !file_exists($filePath)) {
                $error = 'Snapshot file not found.';
            } else {
                unlink($filePath);
                logAudit($pdo, currentUserId(), 'DELETE_BACKUP_SNAPSHOT', 'backups', null, null, ['deleted_file' => $delFile]);
                setFlash('success', "Snapshot '{$delFile}' was removed from server archive.");
                header('Location: ' . BASE_URL . '/admin/backup.php');
                exit;
            }
        }
    }
}

// Fetch stats & backup files
$dbStats = getDatabaseStats($pdo);
$backupFiles = getBackupFilesList();

include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">Database Backup & Data Management</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Export system backups, point-in-time restores, and secure data reset controls</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/admin/settings.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-zinc-200 hover:bg-zinc-100 text-zinc-800 font-medium text-xs rounded-lg transition">
                <i data-lucide="settings" class="w-3.5 h-3.5"></i> Settings
            </a>
            <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-zinc-200 hover:bg-zinc-100 text-zinc-800 font-medium text-xs rounded-lg transition">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Audit Trail
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3.5 rounded-lg bg-zinc-50 border border-zinc-200 text-black text-xs font-medium flex items-center gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-zinc-950"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Modern Fintech Summary Metrics Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Database Size</span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center ring-1 ring-blue-600/10">
                    <i data-lucide="database" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums"><?= $dbStats['size_formatted'] ?></div>
            <span class="text-[11px] text-slate-500 block font-mono"><?= htmlspecialchars(DB_NAME) ?></span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Ledger Records</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center ring-1 ring-indigo-600/10">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums"><?= number_format($dbStats['total_records']) ?></div>
            <span class="text-[11px] text-slate-500 block">Across 10 relational tables</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Server Snapshots</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center ring-1 ring-emerald-600/10">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-950 tracking-tight tabular-nums"><?= count($backupFiles) ?></div>
            <span class="text-[11px] text-slate-500 block">Encrypted /backups</span>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-2 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Protection Lock</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center ring-1 ring-emerald-600/10">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 tracking-tight">Active</div>
            <span class="text-[11px] text-emerald-700 font-medium block">Pre-wipe auto-snapshots</span>
        </div>
    </div>

    <!-- Main Actions Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- 1. System Backup Card -->
        <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-4">
            <div class="pb-3 border-b border-zinc-100 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-black">1. System Backup (Export)</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Generate a complete standalone SQL archive of all tables and ledger records</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-zinc-100 flex items-center justify-center text-zinc-800">
                    <i data-lucide="download" class="w-4 h-4"></i>
                </div>
            </div>

            <div class="text-xs text-zinc-700 space-y-2">
                <p>The backup dump includes schema definitions and insert statements for all tables:</p>
                <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-[11px] text-zinc-600 font-mono pt-1">
                    <span>&bull; system_settings</span>
                    <span>&bull; users (admin/agent)</span>
                    <span>&bull; customers</span>
                    <span>&bull; agent_assignments</span>
                    <span>&bull; loans</span>
                    <span>&bull; repayment_schedule</span>
                    <span>&bull; payments (ledger)</span>
                    <span>&bull; cash_reconciliations</span>
                    <span>&bull; notifications_log</span>
                    <span>&bull; audit_log</span>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <form method="POST" action="backup.php" class="flex-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="backup_action" value="download_sql">
                    <button type="submit" class="w-full py-2 px-3.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5">
                        <i data-lucide="file-down" class="w-3.5 h-3.5"></i> Download SQL Backup
                    </button>
                </form>

                <form method="POST" action="backup.php" class="flex-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="backup_action" value="create_snapshot">
                    <button type="submit" class="w-full py-2 px-3.5 bg-white border border-zinc-200 hover:bg-zinc-100 text-zinc-950 font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5">
                        <i data-lucide="hard-drive" class="w-3.5 h-3.5"></i> Save to Server Archive
                    </button>
                </form>
            </div>
        </div>

        <!-- 2. Database Restore Card -->
        <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-4">
            <div class="pb-3 border-b border-zinc-100 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-black">2. Restore Database (Import)</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Upload a .sql backup dump to restore the system state</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-zinc-100 flex items-center justify-center text-zinc-800">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                </div>
            </div>

            <form method="POST" action="backup.php" enctype="multipart/form-data" class="space-y-3.5 text-xs">
                <?= csrfField() ?>
                <input type="hidden" name="backup_action" value="restore_upload">

                <div>
                    <label class="block text-[11px] font-medium text-zinc-800 mb-1">
                        Select .sql Backup File *
                    </label>
                    <input type="file" name="sql_file" accept=".sql" required
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs file:mr-3 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-xs file:font-medium file:bg-zinc-100 file:text-zinc-800 hover:file:bg-zinc-200">
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-zinc-800 mb-1">
                        Confirm Admin Password (<?= htmlspecialchars($adminUser['username']) ?>) *
                    </label>
                    <input type="password" name="admin_password" required placeholder="Enter password to authorize restore"
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                </div>

                <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-200 text-[11px] text-zinc-600">
                    <span class="font-medium text-zinc-950 block">Automatic Safety Snapshot:</span>
                    A full snapshot of current data will automatically be archived to server before execution so no data is ever lost.
                </div>

                <div class="pt-1">
                    <button type="submit" onclick="return confirm('WARNING: Restoring will overwrite database records. Are you sure you wish to proceed?')"
                        class="w-full py-2 px-3.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-1.5">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Execute File Restore
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Secure Data Wipe (Danger Zone) -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 space-y-4">
        <div class="pb-3 border-b border-zinc-200 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-black">3. Secure Data Wipe & Reset</h2>
                <p class="text-xs text-zinc-500 mt-0.5">Safely purge ledger records, customer rosters, or reset back to clean factory seed</p>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-zinc-100 text-zinc-950">
                Guarded Action
            </span>
        </div>

        <form method="POST" action="backup.php" class="space-y-4 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="backup_action" value="wipe_data">

            <!-- Wipe Mode Selection -->
            <div>
                <label class="block text-[11px] font-medium text-zinc-800 mb-2">
                    Select Data Wipe Scope *
                </label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="flex flex-col p-3 rounded-lg border border-zinc-200 cursor-pointer hover:bg-zinc-100 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/60 transition">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="wipe_mode" value="transactions_only" checked class="text-blue-600 focus:ring-blue-600">
                            <span class="font-semibold text-black">1. Transactions & Loans Only</span>
                        </div>
                        <p class="text-[11px] text-zinc-600 mt-1.5">
                            Purges payments, repayment schedule, active loans, reconciliations & SMS logs. 
                            <strong>Keeps:</strong> Borrowers, staff accounts, assignments, and settings.
                        </p>
                    </label>

                    <label class="flex flex-col p-3 rounded-lg border border-zinc-200 cursor-pointer hover:bg-zinc-100 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/60 transition">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="wipe_mode" value="operational_data" class="text-blue-600 focus:ring-blue-600">
                            <span class="font-semibold text-black">2. All Operational Data</span>
                        </div>
                        <p class="text-[11px] text-zinc-600 mt-1.5">
                            Purges all transactions, loans, schedules, routes, customers & field agents.
                            <strong>Keeps:</strong> Administrator accounts & company settings intact.
                        </p>
                    </label>

                    <label class="flex flex-col p-3 rounded-lg border border-zinc-200 cursor-pointer hover:bg-zinc-100 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/60 transition">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="wipe_mode" value="factory_reset" class="text-blue-600 focus:ring-blue-600">
                            <span class="font-semibold text-black">3. Factory Reset</span>
                        </div>
                        <p class="text-[11px] text-zinc-600 mt-1.5">
                            Rebuilds database cleanly from schema and reapplies initial seed data (default admin ELIJAH/APPIAH and sample accounts).
                        </p>
                    </label>
                </div>
            </div>

            <!-- Double-Lock Verification Inputs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                <div>
                    <label class="block text-[11px] font-medium text-zinc-800 mb-1">
                        Type <span class="font-mono font-bold text-black">CONFIRM WIPE</span> to verify *
                    </label>
                    <input type="text" name="confirm_phrase" required autocomplete="off" placeholder="Type CONFIRM WIPE exactly"
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                </div>

                <div>
                    <label class="block text-[11px] font-medium text-zinc-800 mb-1">
                        Administrator Password *
                    </label>
                    <input type="password" name="admin_password" required placeholder="Enter password to authorize wipe"
                        class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                </div>
            </div>

            <div class="p-3 rounded-lg bg-zinc-50 border border-zinc-200 text-[11px] text-zinc-700 flex items-start gap-2">
                <i data-lucide="shield" class="w-4 h-4 shrink-0 text-zinc-800 mt-0.5"></i>
                <div>
                    <span class="font-medium text-black">Pre-Wipe Safeguard Guaranteed:</span>
                    An immutable pre-wipe SQL dump will be automatically saved into the server archive (<code class="font-mono text-zinc-950">backups/pre_wipe_...sql</code>) before any tables are altered.
                </div>
            </div>

            <div class="pt-1 flex justify-end">
                <button type="submit" onclick="return confirm('DANGER: This action will permanently purge selected data according to the scope chosen. A safety backup will be made. Proceed?')"
                    class="py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition active:scale-[0.99] flex items-center gap-1.5">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Execute Authorized Data Wipe
                </button>
            </div>
        </form>
    </div>

    <!-- 4. Server Backup Archive Table -->
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-black">Server Backup Archive (<?= count($backupFiles) ?> Snapshots)</h2>
                <p class="text-[11px] text-zinc-500">Archived backups on server disk (protected against direct web access)</p>
            </div>
            <form method="POST" action="backup.php">
                <?= csrfField() ?>
                <input type="hidden" name="backup_action" value="create_snapshot">
                <button type="submit" class="px-2.5 py-1 bg-zinc-100 hover:bg-zinc-200 text-zinc-950 rounded text-xs font-medium transition flex items-center gap-1">
                    <i data-lucide="plus" class="w-3 h-3"></i> Take Snapshot
                </button>
            </form>
        </div>

        <?php if (empty($backupFiles)): ?>
            <div class="p-8 text-center text-zinc-500 text-xs">
                No backup snapshots currently saved on server disk.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-zinc-50 text-zinc-500 uppercase font-medium text-[10px] tracking-wider border-b border-zinc-100">
                        <tr>
                            <th class="py-2.5 px-4 font-normal">Snapshot File</th>
                            <th class="py-2.5 px-4 font-normal">Classification</th>
                            <th class="py-2.5 px-4 font-normal">Size</th>
                            <th class="py-2.5 px-4 font-normal">Created Date</th>
                            <th class="py-2.5 px-4 text-center font-normal">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($backupFiles as $bf): ?>
                            <tr class="hover:bg-blue-50/30 transition">
                                <td class="py-2.5 px-4 font-mono font-medium text-black">
                                    <?= htmlspecialchars($bf['filename']) ?>
                                </td>
                                <td class="py-2.5 px-4">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase bg-zinc-100 text-zinc-800">
                                        <?= htmlspecialchars($bf['type']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 font-mono text-zinc-700 text-[11px]">
                                    <?= htmlspecialchars($bf['size_formatted']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-zinc-600 text-[11px]">
                                    <?= htmlspecialchars($bf['date_formatted']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Download -->
                                        <a href="<?= BASE_URL ?>/admin/backup.php?action=download_file&file=<?= urlencode($bf['filename']) ?>" 
                                           class="text-zinc-800 hover:text-black text-xs font-medium underline">
                                            Download
                                        </a>

                                        <span>&bull;</span>

                                        <!-- Restore Trigger -->
                                        <button type="button" onclick="openRestoreModal('<?= htmlspecialchars($bf['filename']) ?>')"
                                            class="text-zinc-800 hover:text-black text-xs font-medium underline">
                                            Restore
                                        </button>

                                        <span>&bull;</span>

                                        <!-- Delete Trigger -->
                                        <button type="button" onclick="openDeleteModal('<?= htmlspecialchars($bf['filename']) ?>')"
                                            class="text-zinc-500 hover:text-black text-xs font-medium">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Restore Existing Snapshot -->
<div id="restore-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-zinc-200 max-w-md w-full p-6 space-y-4 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <h3 class="text-xs font-semibold text-black uppercase tracking-wider">
                Restore Server Snapshot
            </h3>
            <button onclick="closeRestoreModal()" class="text-zinc-500 hover:text-zinc-800">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <p class="text-xs text-zinc-700">
            You are restoring the database from snapshot: <strong id="restore-modal-filename" class="font-mono text-black block mt-1"></strong>
        </p>

        <form method="POST" action="backup.php" class="space-y-3.5 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="backup_action" value="restore_existing">
            <input type="hidden" id="restore-modal-input-file" name="snapshot_file" value="">

            <div>
                <label class="block text-[11px] font-medium text-zinc-800 mb-1">
                    Confirm Admin Password *
                </label>
                <input type="password" name="admin_password" required placeholder="Enter administrator password"
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
            </div>

            <div class="p-2.5 rounded-lg bg-zinc-50 border border-zinc-200 text-[11px] text-zinc-600">
                A pre-restore safety snapshot will automatically be archived before this snapshot is applied.
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                <button type="button" onclick="closeRestoreModal()" class="px-3 py-1.5 bg-zinc-100 text-zinc-800 rounded-lg text-xs font-medium hover:bg-zinc-200">
                    Cancel
                </button>
                <button type="submit" class="px-3.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition">
                    Confirm & Restore
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Snapshot -->
<div id="delete-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-zinc-200 max-w-md w-full p-6 space-y-4 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <h3 class="text-xs font-semibold text-black uppercase tracking-wider">
                Delete Backup Snapshot
            </h3>
            <button onclick="closeDeleteModal()" class="text-zinc-500 hover:text-zinc-800">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <p class="text-xs text-zinc-700">
            Are you sure you want to permanently delete snapshot <strong id="delete-modal-filename" class="font-mono text-black block mt-1"></strong>
        </p>

        <form method="POST" action="backup.php" class="space-y-3.5 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="backup_action" value="delete_snapshot">
            <input type="hidden" id="delete-modal-input-file" name="delete_file" value="">

            <div>
                <label class="block text-[11px] font-medium text-zinc-800 mb-1">
                    Confirm Admin Password *
                </label>
                <input type="password" name="admin_password" required placeholder="Enter administrator password"
                    class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
                <button type="button" onclick="closeDeleteModal()" class="px-3 py-1.5 bg-zinc-100 text-zinc-800 rounded-lg text-xs font-medium hover:bg-zinc-200">
                    Cancel
                </button>
                <button type="submit" class="px-3.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition">
                    Delete Snapshot
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRestoreModal(filename) {
        document.getElementById('restore-modal-filename').textContent = filename;
        document.getElementById('restore-modal-input-file').value = filename;
        document.getElementById('restore-modal').classList.remove('hidden');
    }
    function closeRestoreModal() {
        document.getElementById('restore-modal').classList.add('hidden');
    }

    function openDeleteModal(filename) {
        document.getElementById('delete-modal-filename').textContent = filename;
        document.getElementById('delete-modal-input-file').value = filename;
        document.getElementById('delete-modal').classList.remove('hidden');
    }
    function closeDeleteModal() {
        document.getElementById('delete-modal').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

