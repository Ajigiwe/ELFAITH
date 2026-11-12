<?php
/**
 * System Settings & Integrations - Minimalist Theme
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$page_title = "Settings";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security session expired.';
    } else {
        $formAction = $_POST['form_action'] ?? 'save_settings';

        if ($formAction === 'upload_logo') {
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

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-lg font-bold text-zinc-900 tracking-tight">System Settings</h1>
        <p class="text-xs text-zinc-400 mt-0.5">Company branding, business profile, telecommunication gateways & configurations</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-3 rounded-lg bg-zinc-50 border border-zinc-300 text-zinc-900 text-xs font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-zinc-700"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Company Logo Upload Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Company Logo & Branding</h2>
                <p class="text-[11px] text-zinc-400 mt-0.5">Displays across header navigation, sign-in screen, payment receipts, offline receipts & printouts</p>
            </div>
            <?php if ($currentLogoUrl): ?>
                <form method="POST" action="settings.php" onsubmit="return confirm('Remove custom company logo and revert to the default EF badge?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="remove_logo">
                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white border border-zinc-200 hover:bg-zinc-50 text-zinc-600 hover:text-zinc-900 text-[11px] font-medium rounded-lg transition">
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
                    <div id="logo-fallback-badge" class="w-10 h-10 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-bold text-sm tracking-tight">EF</div>
                    <img id="logo-preview-img" src="" alt="Logo Preview" class="hidden max-h-20 max-w-full object-contain">
                <?php endif; ?>
            </div>

            <!-- Upload Controls -->
            <div class="flex-1 space-y-2.5">
                <div>
                    <label for="company_logo_file" class="block text-[11px] font-medium text-zinc-700 mb-1">Upload Logo Image (PNG, JPG, WebP, GIF &bull; Max 2 MB)</label>
                    <input type="file" id="company_logo_file" name="company_logo_file" required accept="image/png,image/jpeg,image/webp,image/gif"
                        onchange="previewSelectedLogo(this)"
                        class="block w-full text-xs text-zinc-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-zinc-200 file:text-xs file:font-medium file:bg-zinc-50 file:text-zinc-800 hover:file:bg-zinc-100 cursor-pointer">
                </div>
                <div class="flex items-center justify-between gap-2 pt-0.5">
                    <span class="text-[10px] text-zinc-400">Automatically resized up to 600px while preserving transparency.</span>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition shrink-0">
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
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 pb-2 border-b border-zinc-100">
                    Business Profile
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Company Name</label>
                        <input type="text" name="company_name" required value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Branch Name</label>
                        <input type="text" name="branch_name" required value="<?= htmlspecialchars($settings['branch_name'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Branch Region</label>
                        <input type="text" name="branch_region" required value="<?= htmlspecialchars($settings['branch_region'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Official Phone</label>
                        <input type="text" name="company_phone" required value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Official Email</label>
                        <input type="email" name="company_email" required value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>
                </div>
            </div>

            <!-- Section 2: Currency & Lending Policy -->
            <div class="space-y-3 pt-3 border-t border-zinc-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 pb-2 border-b border-zinc-100">
                    Currency & Financial Policy
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Currency Code</label>
                        <input type="text" name="currency_code" required value="<?= htmlspecialchars($settings['currency_code'] ?? 'GHS') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Currency Symbol</label>
                        <?php
                            $rawSettingSym = trim((string)($settings['currency_symbol'] ?? ''));
                            $displaySettingSym = ($rawSettingSym === '' || strpos($rawSettingSym, '262145') !== false || preg_match('/^[\d\s\x00-\x1F]+$/', $rawSettingSym))
                                ? safeCurrencySymbol()
                                : $rawSettingSym;
                        ?>
                        <input type="text" name="currency_symbol" required value="<?= htmlspecialchars($displaySettingSym) ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-bold focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Default Flat Rate (%)</label>
                        <input type="number" step="0.5" name="default_interest_rate" required value="<?= htmlspecialchars($settings['default_interest_rate'] ?? '10.00') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-medium focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>
                </div>
            </div>

            <!-- Section 3: SMS Gateway -->
            <div class="space-y-3 pt-3 border-t border-zinc-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 pb-2 border-b border-zinc-100">
                    SMS Receipt Gateway (Ghana)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Provider</label>
                        <select name="sms_provider" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                            <option value="simulated" <?= ($settings['sms_provider'] ?? '') === 'simulated' ? 'selected' : '' ?>>Simulated (Local Dev Mode)</option>
                            <option value="arkesel" <?= ($settings['sms_provider'] ?? '') === 'arkesel' ? 'selected' : '' ?>>Arkesel Ghana</option>
                            <option value="hubtel" <?= ($settings['sms_provider'] ?? '') === 'hubtel' ? 'selected' : '' ?>>Hubtel</option>
                            <option value="mnotify" <?= ($settings['sms_provider'] ?? '') === 'mnotify' ? 'selected' : '' ?>>mNotify</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">SMS Sender ID</label>
                        <input type="text" name="sms_sender_id" maxlength="11" value="<?= htmlspecialchars($settings['sms_sender_id'] ?? 'ELFAITH') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono uppercase focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">SMS API Key</label>
                        <input type="password" name="sms_api_key" value="<?= htmlspecialchars($settings['sms_api_key'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs font-mono focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                            placeholder="Gateway API key">
                    </div>
                </div>
            </div>

            <!-- Section 4: Email Notifications -->
            <div class="space-y-3 pt-3 border-t border-zinc-100">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 pb-2 border-b border-zinc-100">
                    Admin Email Alerts
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Notification Email</label>
                        <input type="email" name="admin_notification_email" value="<?= htmlspecialchars($settings['admin_notification_email'] ?? '') ?>"
                            class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900"
                            placeholder="admin@elfaith.com">
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-zinc-700 mb-1">Alert Frequency</label>
                        <select name="admin_email_frequency" class="w-full px-3 py-1.5 bg-white border border-zinc-200 rounded-lg text-xs focus:outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900">
                            <option value="per_collection" <?= ($settings['admin_email_frequency'] ?? '') === 'per_collection' ? 'selected' : '' ?>>Per Collection (Instant)</option>
                            <option value="daily_digest" <?= ($settings['admin_email_frequency'] ?? '') === 'daily_digest' ? 'selected' : '' ?>>Daily Evening Digest</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition">
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Database Backup & Data Wipe Shortcut Card -->
    <div class="bg-white rounded-xl border border-zinc-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-900">Database Backup & Data Management</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Export point-in-time system backups, restore snapshots, or execute guarded data wipes</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/backup.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-zinc-900 hover:bg-zinc-800 text-white font-medium text-xs rounded-lg transition shrink-0">
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
