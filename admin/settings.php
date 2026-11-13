<?php
/**
 * System Settings & Integrations - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/deploy_manager.php';

requireSuperAdmin();

$page_title = "Settings";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security session expired.';
    } else {
        $formAction = $_POST['form_action'] ?? 'save_settings';

        if ($formAction === 'save_deploy_config') {
            try {
                $repo = normalizeDeployRepo($_POST['deploy_repo'] ?? 'Ajigiwe/ELFAITH');
                $branch = normalizeDeployBranch($_POST['deploy_branch'] ?? 'main');
                $newToken = trim((string)($_POST['deploy_github_token'] ?? ''));
                $clearToken = !empty($_POST['clear_deploy_token']);

                $upsert = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                ");
                $upsert->execute(['deploy_repo', $repo]);
                $upsert->execute(['deploy_branch', $branch]);
                if ($clearToken) {
                    $upsert->execute(['deploy_github_token', '']);
                } elseif ($newToken !== '') {
                    $upsert->execute(['deploy_github_token', $newToken]);
                }

                logAudit($pdo, currentUserId(), 'UPDATE_DEPLOY_CONFIG', 'system_settings', null, null, ['repo' => $repo, 'branch' => $branch]);
                setFlash('success', 'Deploy engine repository settings saved.');
                header('Location: ' . BASE_URL . '/admin/settings.php#deploy-engine');
                exit;
            } catch (Exception $e) {
                $error = 'Deploy config error: ' . $e->getMessage();
            }
        } elseif ($formAction === 'check_deploy_updates') {
            try {
                $envStatus = getDeployEnvironmentStatus($pdo);
                $repo = trim((string)($_POST['deploy_repo'] ?? '')) !== ''
                    ? normalizeDeployRepo($_POST['deploy_repo'])
                    : $envStatus['repo'];
                $branch = trim((string)($_POST['deploy_branch'] ?? '')) !== ''
                    ? normalizeDeployBranch($_POST['deploy_branch'])
                    : $envStatus['branch'];
                $token = $envStatus['token'];

                $remote = fetchRemoteDeployStatus($repo, $branch, $token);
                $_SESSION['deploy_check_result'] = $remote;

                $isNew = empty($envStatus['last_sha']) || ($envStatus['last_sha'] !== $remote['sha']);
                setFlash('info', $isNew
                    ? "Latest commit on {$repo}@{$branch}: {$remote['short_sha']} — \"{$remote['message']}\""
                    : "Already on latest tracked commit {$remote['short_sha']}."
                );
                header('Location: ' . BASE_URL . '/admin/settings.php#deploy-engine');
                exit;
            } catch (Exception $e) {
                $error = 'Update check failed: ' . $e->getMessage();
            }
        } elseif ($formAction === 'run_live_deploy') {
            try {
                @set_time_limit(180);
                $envStatus = getDeployEnvironmentStatus($pdo);
                $repo = trim((string)($_POST['deploy_repo'] ?? '')) !== ''
                    ? normalizeDeployRepo($_POST['deploy_repo'])
                    : $envStatus['repo'];
                $branch = trim((string)($_POST['deploy_branch'] ?? '')) !== ''
                    ? normalizeDeployBranch($_POST['deploy_branch'])
                    : $envStatus['branch'];
                $allowLocal = !empty($_POST['allow_local_overwrite']);

                $result = executeLiveDeploy($pdo, $repo, $branch, $envStatus['token'], currentUserId(), $allowLocal);
                $_SESSION['deploy_run_result'] = $result;

                setFlash('success', "Deployed commit {$result['short_sha']} ({$result['files_written']} file(s) updated). Pre-deploy backup: {$result['rollback_file']}");
                header('Location: ' . BASE_URL . '/admin/settings.php#deploy-engine');
                exit;
            } catch (Exception $e) {
                $error = 'Live deploy failed: ' . $e->getMessage();
            }
        } elseif ($formAction === 'rollback_deploy') {
            try {
                @set_time_limit(120);
                $snapFile = trim((string)($_POST['rollback_file'] ?? ''));
                $rb = executeDeployRollback($pdo, $snapFile, currentUserId());
                setFlash('success', "Rolled back {$rb['files_restored']} file(s) from {$rb['snapshot']}.");
                header('Location: ' . BASE_URL . '/admin/settings.php#deploy-engine');
                exit;
            } catch (Exception $e) {
                $error = 'Rollback failed: ' . $e->getMessage();
            }
        } elseif ($formAction === 'upload_logo') {
            try {
                $relPath = saveUploadedCompanyLogo($_FILES['company_logo_file'] ?? []);

                $stmtUpd = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value)
                    VALUES ('company_logo', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                ");
                $stmtUpd->execute([$relPath]);

                cleanupOldCompanyLogos($relPath);
                logAudit($pdo, currentUserId(), 'UPLOAD_COMPANY_LOGO', 'system_settings', null, null, ['company_logo' => $relPath]);

                setFlash('success', 'Company logo uploaded and applied across the portal and receipts!');
                header('Location: ' . BASE_URL . '/admin/settings.php');
                exit;
            } catch (Exception $e) {
                $error = 'Logo upload failed: ' . $e->getMessage();
            }
        } elseif ($formAction === 'remove_logo') {
            try {
                $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'company_logo'")->execute();
                cleanupOldCompanyLogos(null);
                logAudit($pdo, currentUserId(), 'REMOVE_COMPANY_LOGO', 'system_settings', null, null, ['removed_by' => currentUserId()]);

                setFlash('success', 'Custom company logo removed. Default badge restored.');
                header('Location: ' . BASE_URL . '/admin/settings.php');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to remove logo: ' . $e->getMessage();
            }
        } else {
            $allowedKeys = [
                'company_name', 'branch_name', 'branch_region', 'company_phone', 'company_email',
                'currency_code', 'currency_symbol', 'default_interest_rate', 'sms_provider',
                'sms_sender_id', 'sms_api_key', 'email_provider', 'email_api_key',
                'admin_notification_email', 'admin_email_frequency'
            ];

            try {
                $stmtUpd = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                ");

                foreach ($allowedKeys as $key) {
                    if (isset($_POST[$key])) {
                        $val = trim($_POST[$key]);
                        if ($key === 'currency_symbol' && ($val === '' || strpos($val, '262145') !== false || preg_match('/^[\d\s\x00-\x1F]+$/', $val))) {
                            $val = 'GH₵';
                        }
                        $stmtUpd->execute([$key, $val]);
                    }
                }

                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate(__DIR__ . '/../config/app.php', true);
                }

                logAudit($pdo, currentUserId(), 'UPDATE_SETTINGS', 'system_settings', null, null, ['updated_by' => currentUserId()]);

                setFlash('success', 'System settings updated successfully!');
                header('Location: ' . BASE_URL . '/admin/settings.php');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to save settings: ' . $e->getMessage();
            }
        }
    }
}

// Reload fresh settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($r = $stmt->fetch()) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$currentLogoUrl = getCompanyLogoUrl();
$deployEnv = getDeployEnvironmentStatus($pdo);
$deployCheckResult = $_SESSION['deploy_check_result'] ?? null;
unset($_SESSION['deploy_check_result']);
$deployRunResult = $_SESSION['deploy_run_result'] ?? null;
unset($_SESSION['deploy_run_result']);

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-lg font-bold text-black tracking-tight">System Settings</h1>
            <p class="text-xs text-zinc-500 mt-0.5">Live GitHub deploy engine, company branding, business profile &amp; gateways</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-black text-white font-mono text-[10px] font-semibold uppercase tracking-wider shrink-0">
            Version <?= htmlspecialchars(APP_VERSION) ?>
        </span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-rose-600"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Live Deploy Engine Card (cPanel-free GitHub Sync) -->
    <div id="deploy-engine" class="bg-white rounded-xl border border-zinc-200 border-t-2 border-t-black p-5 sm:p-6 space-y-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-zinc-100">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-black">Live Deploy Engine (GitHub Sync)</h2>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wider <?= ($deployEnv['has_zip'] && $deployEnv['is_writable']) ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                        <?= ($deployEnv['has_zip'] && $deployEnv['is_writable']) ? 'Ready' : 'Check Permissions' ?>
                    </span>
                </div>
                <p class="text-[11px] text-zinc-500 mt-0.5">Pull &amp; deploy commits directly from GitHub to this server without opening cPanel</p>
            </div>
            <div class="text-[10px] font-mono text-zinc-600 bg-zinc-100 border border-zinc-200 px-2 py-1 rounded">
                <?= htmlspecialchars($deployEnv['repo']) ?> @ <?= htmlspecialchars($deployEnv['branch']) ?>
            </div>
        </div>

        <!-- Current Live Status Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 text-xs">
            <div class="bg-white rounded-2xl border border-slate-200/90 p-4 space-y-1.5 shadow-xs">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block">Running Release</span>
                <div class="text-base font-extrabold text-slate-950 font-mono"><?= htmlspecialchars(APP_VERSION) ?></div>
                <span class="text-[11px] text-blue-600 font-mono block">
                    <?= $deployEnv['last_sha'] ? 'SHA: ' . htmlspecialchars(substr($deployEnv['last_sha'], 0, 7)) : 'Synced via cPanel / Git' ?>
                </span>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200/90 p-4 space-y-1.5 shadow-xs">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block">Last Engine Deploy</span>
                <div class="text-xs font-bold text-slate-950 truncate">
                    <?= $deployEnv['last_at'] ? htmlspecialchars(date('d M Y, h:i A', strtotime($deployEnv['last_at']))) : 'Not deployed via engine yet' ?>
                </div>
                <span class="text-[11px] text-slate-500 block truncate">
                    <?= $deployEnv['last_message'] ? htmlspecialchars($deployEnv['last_message']) : 'First pull records commit SHA' ?>
                </span>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200/90 p-4 space-y-1.5 shadow-xs">
                <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block">Protected Server Data</span>
                <div class="text-xs font-bold text-slate-950">
                    <?= $deployEnv['has_prod_db_file'] ? 'Prod DB Config Preserved' : 'Safe Selective Overlay' ?>
                </div>
                <span class="text-[11px] text-slate-500 block">Preserves <code class="font-mono text-slate-700">database.prod.php</code> &amp; <code class="font-mono text-slate-700">uploads/</code></span>
            </div>
        </div>

        <?php if (is_array($deployCheckResult)): ?>
            <?php $hasPendingUpdate = empty($deployEnv['last_sha']) || ($deployEnv['last_sha'] !== $deployCheckResult['sha']); ?>
            <div class="p-3.5 rounded-lg border <?= $hasPendingUpdate ? 'bg-blue-50/70 border-blue-200' : 'bg-zinc-50 border-zinc-200' ?> text-xs space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-black uppercase text-[11px] tracking-wider">
                        <?= $hasPendingUpdate ? 'Remote Commit Ready to Deploy' : 'Server Matches Latest Remote Commit' ?>
                    </span>
                    <span class="font-mono text-[10px] px-2 py-0.5 rounded bg-black text-white">
                        <?= htmlspecialchars($deployCheckResult['short_sha']) ?><?= !empty($deployCheckResult['remote_version']) ? ' • ' . htmlspecialchars($deployCheckResult['remote_version']) : '' ?>
                    </span>
                </div>
                <p class="text-zinc-800 font-medium"><?= htmlspecialchars($deployCheckResult['message']) ?></p>
                <p class="text-[11px] text-zinc-500">
                    Author: <?= htmlspecialchars($deployCheckResult['author']) ?>
                    <?php if (!empty($deployCheckResult['date'])): ?>
                        &bull; <?= htmlspecialchars(date('d M Y, h:i A', strtotime($deployCheckResult['date']))) ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (is_array($deployRunResult)): ?>
            <div class="p-3.5 rounded-lg bg-zinc-950 text-white border border-zinc-800 text-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold uppercase text-[11px] tracking-wider text-blue-400">
                        Deploy Completed — <?= htmlspecialchars($deployRunResult['short_sha']) ?>
                    </span>
                    <span class="font-mono text-[10px] text-zinc-300">
                        <?= (int)$deployRunResult['files_written'] ?> updated &bull; <?= (int)$deployRunResult['files_unchanged'] ?> unchanged
                    </span>
                </div>
                <?php if (!empty($deployRunResult['updated_sample'])): ?>
                    <div class="text-[10px] font-mono text-zinc-300 bg-black/60 rounded p-2 max-h-28 overflow-y-auto border border-zinc-800">
                        <?php foreach ($deployRunResult['updated_sample'] as $uf): ?>
                            <div>+ <?= htmlspecialchars($uf) ?></div>
                        <?php endforeach; ?>
                        <?php if ($deployRunResult['files_written'] > count($deployRunResult['updated_sample'])): ?>
                            <div class="text-zinc-500">... and <?= $deployRunResult['files_written'] - count($deployRunResult['updated_sample']) ?> more files</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons: Check Updates & Pull & Deploy -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="settings.php#deploy-engine" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="check_deploy_updates">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-zinc-300 hover:bg-zinc-100 text-black font-medium text-xs rounded-lg transition">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-zinc-700"></i> Check for Updates
                    </button>
                </form>

                <form method="POST" action="settings.php#deploy-engine" id="run-deploy-form" class="inline-flex flex-wrap items-center gap-2"
                      onsubmit="if (!confirm('Pull latest files from GitHub (<?= htmlspecialchars(addslashes($deployEnv['repo'] . '@' . $deployEnv['branch'])) ?>) and deploy to the live site now? A rollback snapshot will be created automatically.')) return false; const b = this.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.innerHTML = 'Deploying...'; }">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="run_live_deploy">
                    <?php if ($deployEnv['has_local_git']): ?>
                        <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-zinc-100 border border-zinc-200 text-[11px] text-zinc-700 cursor-pointer" title="Local .git folder detected">
                            <input type="checkbox" name="allow_local_overwrite" value="1" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-600">
                            <span>Local Dev (.git): Force overwrite</span>
                        </label>
                    <?php endif; ?>
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition shadow-sm">
                        <i data-lucide="rocket" class="w-3.5 h-3.5"></i> Pull &amp; Deploy Live Site
                    </button>
                </form>
            </div>

            <button type="button" onclick="document.getElementById('deploy-advanced-panel').classList.toggle('hidden')"
                class="text-[11px] font-medium text-zinc-600 hover:text-black underline self-start sm:self-center">
                Repository &amp; Rollback Options
            </button>
        </div>

        <!-- Collapsible Repository Settings & Rollback Snapshots -->
        <div id="deploy-advanced-panel" class="hidden pt-4 border-t border-zinc-100 space-y-4 text-xs">
            <form method="POST" action="settings.php#deploy-engine" class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-zinc-50 p-3.5 rounded-lg border border-zinc-200">
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="save_deploy_config">

                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-600 mb-1">GitHub Repo (owner/repo)</label>
                    <input type="text" name="deploy_repo" required value="<?= htmlspecialchars($deployEnv['repo']) ?>"
                        class="w-full px-2.5 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-600 mb-1">Branch</label>
                    <input type="text" name="deploy_branch" required value="<?= htmlspecialchars($deployEnv['branch']) ?>"
                        class="w-full px-2.5 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-600 mb-1">
                        GitHub Token (Optional) <?= $deployEnv['has_token'] ? '<span class="text-blue-600">• Saved</span>' : '' ?>
                    </label>
                    <input type="password" name="deploy_github_token" placeholder="<?= $deployEnv['has_token'] ? '•••••••• (leave blank to keep)' : 'For private repo / higher rate limit' ?>"
                        class="w-full px-2.5 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600">
                </div>
                <div class="sm:col-span-3 flex items-center justify-between pt-1">
                    <?php if ($deployEnv['has_token']): ?>
                        <label class="inline-flex items-center gap-1.5 text-[11px] text-zinc-600 cursor-pointer">
                            <input type="checkbox" name="clear_deploy_token" value="1" class="rounded border-zinc-300">
                            <span>Clear saved token</span>
                        </label>
                    <?php else: ?>
                        <span class="text-[10px] text-zinc-500">Target Path: <code class="font-mono"><?= htmlspecialchars($deployEnv['app_root']) ?></code></span>
                    <?php endif; ?>
                    <button type="submit" class="px-3 py-1.5 bg-black hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition">
                        Save Source Config
                    </button>
                </div>
            </form>

            <?php if (!empty($deployEnv['rollbacks'])): ?>
                <div class="space-y-2">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-zinc-600">Pre-Deploy Rollback Snapshots (Latest 5)</span>
                    <div class="divide-y divide-zinc-200 border border-zinc-200 rounded-lg bg-white">
                        <?php foreach ($deployEnv['rollbacks'] as $rb): ?>
                            <div class="px-3 py-2 flex items-center justify-between gap-2 text-[11px]">
                                <div>
                                    <span class="font-mono font-semibold text-black"><?= htmlspecialchars($rb['filename']) ?></span>
                                    <span class="text-zinc-500 ml-2"><?= round($rb['size'] / 1024, 1) ?> KB &bull; <?= date('d M Y, h:i A', $rb['mtime']) ?></span>
                                </div>
                                <form method="POST" action="settings.php#deploy-engine" onsubmit="return confirm('Restore files from <?= htmlspecialchars($rb['filename']) ?>? Your database & uploads will NOT be affected.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="form_action" value="rollback_deploy">
                                    <input type="hidden" name="rollback_file" value="<?= htmlspecialchars($rb['filename']) ?>">
                                    <button type="submit" class="px-2.5 py-1 bg-zinc-100 hover:bg-black text-black hover:text-white rounded font-medium transition">
                                        Rollback
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Company Logo Upload Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-black">Company Logo & Branding</h2>
                <p class="text-[11px] text-zinc-500 mt-0.5">Displays across header navigation, sign-in screen, payment receipts, offline receipts & printouts</p>
            </div>
            <?php if ($currentLogoUrl): ?>
                <form method="POST" action="settings.php" onsubmit="return confirm('Remove custom company logo and revert to the default EF badge?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="remove_logo">
                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white border border-zinc-200 hover:bg-zinc-100 text-zinc-700 hover:text-black text-[11px] font-medium rounded-lg transition">
                        <i data-lucide="trash-2" class="w-3 h-3"></i> Remove Logo
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <form method="POST" action="settings.php" enctype="multipart/form-data" class="flex flex-col sm:flex-row sm:items-center gap-5 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="upload_logo">

            <!-- Current / Preview Box -->
            <div class="w-28 h-24 rounded-xl border border-zinc-200 bg-zinc-50 flex items-center justify-center p-2 shrink-0 overflow-hidden" id="logo-preview-box">
                <?php if ($currentLogoUrl): ?>
                    <img id="logo-preview-img" src="<?= htmlspecialchars($currentLogoUrl) ?>" alt="Company Logo" class="max-h-20 max-w-full object-contain">
                <?php else: ?>
                    <div id="logo-fallback-badge" class="w-10 h-10 rounded-lg bg-black text-white flex items-center justify-center font-bold text-sm tracking-tight">EF</div>
                    <img id="logo-preview-img" src="" alt="Logo Preview" class="hidden max-h-20 max-w-full object-contain">
                <?php endif; ?>
            </div>

            <!-- Upload Controls -->
            <div class="flex-1 space-y-2.5">
                <div>
                    <label for="company_logo_file" class="block text-[11px] font-medium text-zinc-800 mb-1">Upload Logo Image (PNG, JPG, WebP, GIF &bull; Max 2 MB)</label>
                    <input type="file" id="company_logo_file" name="company_logo_file" required accept="image/png,image/jpeg,image/webp,image/gif"
                        onchange="previewSelectedLogo(this)"
                        class="block w-full text-xs text-zinc-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-zinc-200 file:text-xs file:font-medium file:bg-zinc-50 file:text-zinc-950 hover:file:bg-zinc-200 cursor-pointer">
                </div>
                <div class="flex items-center justify-between gap-2 pt-0.5">
                    <span class="text-[10px] text-zinc-500">Automatically resized up to 600px while preserving transparency.</span>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition shrink-0">
                        <i data-lucide="upload" class="w-3.5 h-3.5"></i> Upload Logo
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-zinc-200 p-5 sm:p-6">
        <form method="POST" action="settings.php" class="space-y-6 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="save_settings">

            <!-- Section 1: Business Identity -->
            <div class="space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-black pb-2 border-b border-zinc-100">
                    Business Profile
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Company Name</label>
                        <input type="text" name="company_name" required value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Branch Name</label>
                        <input type="text" name="branch_name" required value="<?= htmlspecialchars($settings['branch_name'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Branch Region</label>
                        <input type="text" name="branch_region" required value="<?= htmlspecialchars($settings['branch_region'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Official Phone</label>
                        <input type="text" name="company_phone" required value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Official Email</label>
                        <input type="email" name="company_email" required value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>
                </div>
            </div>

            <!-- Section 2: Currency & Lending Policy -->
            <div class="space-y-3 pt-3 border-t border-zinc-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-black pb-2 border-b border-zinc-100">
                    Currency & Financial Policy
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Currency Code</label>
                        <input type="text" name="currency_code" required value="<?= htmlspecialchars($settings['currency_code'] ?? 'GHS') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Currency Symbol</label>
                        <?php
                            $rawSettingSym = trim((string)($settings['currency_symbol'] ?? ''));
                            $displaySettingSym = ($rawSettingSym === '' || strpos($rawSettingSym, '262145') !== false || preg_match('/^[\d\s\x00-\x1F]+$/', $rawSettingSym))
                                ? safeCurrencySymbol()
                                : $rawSettingSym;
                        ?>
                        <input type="text" name="currency_symbol" required value="<?= htmlspecialchars($displaySettingSym) ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-bold focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Default Flat Rate (%)</label>
                        <input type="number" step="0.5" name="default_interest_rate" required value="<?= htmlspecialchars($settings['default_interest_rate'] ?? '10.00') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-medium focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>
                </div>
            </div>

            <!-- Section 3: SMS Gateway -->
            <div class="space-y-3 pt-3 border-t border-zinc-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-black pb-2 border-b border-zinc-100">
                    SMS Receipt Gateway (Ghana)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Provider</label>
                        <select name="sms_provider" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                            <option value="simulated" <?= ($settings['sms_provider'] ?? '') === 'simulated' ? 'selected' : '' ?>>Simulated (Local Dev Mode)</option>
                            <option value="arkesel" <?= ($settings['sms_provider'] ?? '') === 'arkesel' ? 'selected' : '' ?>>Arkesel Ghana</option>
                            <option value="hubtel" <?= ($settings['sms_provider'] ?? '') === 'hubtel' ? 'selected' : '' ?>>Hubtel</option>
                            <option value="mnotify" <?= ($settings['sms_provider'] ?? '') === 'mnotify' ? 'selected' : '' ?>>mNotify</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">SMS Sender ID</label>
                        <input type="text" name="sms_sender_id" maxlength="11" value="<?= htmlspecialchars($settings['sms_sender_id'] ?? 'ELFAITH') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono uppercase focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">SMS API Key</label>
                        <input type="password" name="sms_api_key" value="<?= htmlspecialchars($settings['sms_api_key'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="Gateway API key">
                    </div>
                </div>
            </div>

            <!-- Section 4: Email Notifications -->
            <div class="space-y-3 pt-3 border-t border-zinc-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-black pb-2 border-b border-zinc-100">
                    Admin Email Alerts
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Notification Email</label>
                        <input type="email" name="admin_notification_email" value="<?= htmlspecialchars($settings['admin_notification_email'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600"
                            placeholder="admin@elfaith.com">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-800 mb-1">Alert Frequency</label>
                        <select name="admin_email_frequency" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600">
                            <option value="per_collection" <?= ($settings['admin_email_frequency'] ?? '') === 'per_collection' ? 'selected' : '' ?>>Per Collection (Instant)</option>
                            <option value="daily_digest" <?= ($settings['admin_email_frequency'] ?? '') === 'daily_digest' ? 'selected' : '' ?>>Daily Evening Digest</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition">
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Database Backup & Data Wipe Shortcut Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-black">Database Backup & Data Management</h3>
            <p class="text-xs text-zinc-500 mt-0.5">Export point-in-time system backups, restore snapshots, or execute guarded data wipes</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/backup.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition shrink-0">
            <i data-lucide="database" class="w-3.5 h-3.5"></i> Manage Backups & Wipe &rarr;
        </a>
    </div>
</div>

<script>
    function previewSelectedLogo(input) {
        if (!input || !input.files || !input.files[0]) return;
        const file = input.files[0];
        if (!file.type || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('logo-preview-img');
            const badge = document.getElementById('logo-fallback-badge');
            if (img && e.target && e.target.result) {
                img.src = e.target.result;
                img.classList.remove('hidden');
            }
            if (badge) {
                badge.classList.add('hidden');
            }
        };
        reader.readAsDataURL(file);
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
