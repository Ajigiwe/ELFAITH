<?php
/**
 * Live GitHub Pull & Deploy Engine
 * Pure-PHP deployment & rollback manager (works without cPanel or shell git access)
 * EL-FAITH MICROFINANCE
 */

defined('APP_INIT') or define('APP_INIT', true);

/**
 * Relative paths that must NEVER be overwritten or deleted during deploy/rollback.
 */
function getDeployProtectedPaths(): array {
    return [
        'config/database.prod.php',
        'config/database.local.php',
        'backups',
        'assets/uploads',
        '.git',
        '.well-known',
        'cgi-bin',
        'error_log',
    ];
}

/**
 * Check if a relative path (forward slashes) matches any protected rule.
 */
function isDeployProtectedPath(string $relPath): bool {
    $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
    if ($relPath === '') {
        return false;
    }

    foreach (getDeployProtectedPaths() as $protected) {
        if ($relPath === $protected || strpos($relPath, $protected . '/') === 0) {
            return true;
        }
    }

    // Never deploy editor/CI hidden folders other than root .htaccess / .cpanel.yml
    $firstSeg = explode('/', $relPath)[0];
    if (strpos($firstSeg, '.') === 0 && !in_array($firstSeg, ['.htaccess', '.cpanel.yml'], true)) {
        return true;
    }

    return false;
}

/**
 * Validate owner/repo format strictly to prevent SSRF or path injection.
 */
function normalizeDeployRepo(string $repo): string {
    $repo = trim($repo);
    $repo = preg_replace('#^https?://(www\.)?github\.com/#i', '', $repo);
    $repo = preg_replace('#\.git$#i', '', $repo);
    $repo = trim($repo, '/');
    if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
        throw new Exception('Repository must be in owner/repo format (e.g. Ajigiwe/ELFAITH).');
    }
    return $repo;
}

/**
 * Validate Git branch name strictly.
 */
function normalizeDeployBranch(string $branch): string {
    $branch = trim($branch);
    if ($branch === '' || strpos($branch, '..') !== false || !preg_match('#^[A-Za-z0-9_./-]+$#', $branch)) {
        throw new Exception('Invalid branch name.');
    }
    return $branch;
}

/**
 * Environment capability & status diagnostics for the Admin UI.
 */
function getDeployEnvironmentStatus(PDO $pdo): array {
    $appRoot = realpath(__DIR__ . '/..');
    $backupsDir = $appRoot . DIRECTORY_SEPARATOR . 'backups';
    $hasLocalGit = is_dir($appRoot . DIRECTORY_SEPARATOR . '.git');
    $hasZip = class_exists('ZipArchive');
    $hasCurl = function_exists('curl_init');
    $isWritable = is_writable($appRoot);

    $saved = [];
    $keys = [
        'deploy_repo', 'deploy_branch', 'deploy_github_token',
        'deploy_last_sha', 'deploy_last_message', 'deploy_last_author',
        'deploy_last_at', 'deploy_last_files_count', 'deploy_last_by',
        'deploy_last_rollback_file'
    ];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $saved[$r['setting_key']] = $r['setting_value'];
    }

    // List available pre-deploy rollback snapshots
    $rollbacks = [];
    if (is_dir($backupsDir)) {
        foreach (glob($backupsDir . DIRECTORY_SEPARATOR . 'pre_deploy_*.zip') ?: [] as $zf) {
            $rollbacks[] = [
                'filename' => basename($zf),
                'size'     => filesize($zf),
                'mtime'    => filemtime($zf),
            ];
        }
        usort($rollbacks, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    }

    return [
        'app_root'          => $appRoot,
        'is_writable'       => $isWritable,
        'has_zip'           => $hasZip,
        'has_curl'          => $hasCurl,
        'has_local_git'     => $hasLocalGit,
        'has_prod_db_file'  => is_file($appRoot . '/config/database.prod.php'),
        'repo'              => !empty($saved['deploy_repo']) ? $saved['deploy_repo'] : 'Ajigiwe/ELFAITH',
        'branch'            => !empty($saved['deploy_branch']) ? $saved['deploy_branch'] : 'main',
        'has_token'         => !empty($saved['deploy_github_token']),
        'token'             => $saved['deploy_github_token'] ?? '',
        'last_sha'          => $saved['deploy_last_sha'] ?? '',
        'last_message'      => $saved['deploy_last_message'] ?? '',
        'last_author'       => $saved['deploy_last_author'] ?? '',
        'last_at'           => $saved['deploy_last_at'] ?? '',
        'last_files_count'  => (int)($saved['deploy_last_files_count'] ?? 0),
        'last_by'           => $saved['deploy_last_by'] ?? '',
        'last_rollback'     => $saved['deploy_last_rollback_file'] ?? '',
        'rollbacks'         => $rollbacks,
    ];
}

/**
 * Perform HTTP GET request using cURL (preferred) or PHP streams.
 */
function deployHttpGet(string $url, array $headers = [], ?string $saveToFile = null, int $timeout = 45): array {
    $headers[] = 'User-Agent: EL-FAITH-DeployEngine/1.0';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $fp = null;
        if ($saveToFile !== null) {
            $fp = fopen($saveToFile, 'wb');
            if (!$fp) {
                curl_close($ch);
                throw new Exception('Unable to open temporary file for download.');
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($fp) {
            fclose($fp);
        }

        if ($err !== '') {
            throw new Exception('Network error connecting to GitHub: ' . $err);
        }
        return ['code' => $httpCode, 'body' => is_string($body) ? $body : ''];
    }

    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        throw new Exception('Neither PHP cURL nor allow_url_fopen is enabled on this server.');
    }

    $ctx = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'header'          => implode("\r\n", $headers) . "\r\n",
            'timeout'         => $timeout,
            'follow_location' => 1,
            'ignore_errors'   => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $httpCode = 0;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
    if ($raw === false) {
        throw new Exception('Failed to fetch resource from GitHub.');
    }
    if ($saveToFile !== null) {
        file_put_contents($saveToFile, $raw);
        return ['code' => $httpCode, 'body' => ''];
    }
    return ['code' => $httpCode, 'body' => $raw];
}

/**
 * Fetch latest remote commit info & remote APP_VERSION without modifying local files.
 * Uses GitHub REST API with automatic Atom feed fallback if API is rate-limited.
 */
function fetchRemoteDeployStatus(string $repo, string $branch, string $token = ''): array {
    $repo = normalizeDeployRepo($repo);
    $branch = normalizeDeployBranch($branch);

    $headers = ['Accept: application/vnd.github+json'];
    if (trim($token) !== '') {
        $headers[] = 'Authorization: Bearer ' . trim($token);
    }

    $apiUrl = "https://api.github.com/repos/{$repo}/commits/" . rawurlencode($branch);
    $res = deployHttpGet($apiUrl, $headers, null, 20);

    $info = null;
    if ($res['code'] === 200) {
        $data = json_decode($res['body'], true);
        if (is_array($data) && !empty($data['sha'])) {
            $msg = (string)($data['commit']['message'] ?? '');
            $firstLine = trim(strtok($msg, "\r\n") ?: '');
            $info = [
                'sha'       => $data['sha'],
                'short_sha' => substr($data['sha'], 0, 7),
                'message'   => $firstLine,
                'author'    => $data['commit']['author']['name'] ?? ($data['author']['login'] ?? 'GitHub'),
                'date'      => $data['commit']['author']['date'] ?? '',
                'html_url'  => $data['html_url'] ?? "https://github.com/{$repo}/commit/{$data['sha']}",
                'source'    => 'api',
            ];
        }
    }

    // Fallback to public Atom feed (avoids unauthenticated REST API rate limits on shared IPs)
    if ($info === null && trim($token) === '') {
        $atomUrl = "https://github.com/{$repo}/commits/" . rawurlencode($branch) . ".atom";
        $atomRes = deployHttpGet($atomUrl, ['Accept: application/atom+xml'], null, 20);
        if ($atomRes['code'] === 200 && strpos($atomRes['body'], '<entry>') !== false) {
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($atomRes['body']);
            libxml_clear_errors();
            if ($xml && isset($xml->entry[0])) {
                $entry = $xml->entry[0];
                $idStr = (string)$entry->id;
                $sha = '';
                if (preg_match('/([0-9a-f]{40})/i', $idStr, $m)) {
                    $sha = $m[1];
                }
                if ($sha !== '') {
                    $info = [
                        'sha'       => $sha,
                        'short_sha' => substr($sha, 0, 7),
                        'message'   => trim((string)$entry->title),
                        'author'    => trim((string)($entry->author->name ?? 'GitHub')),
                        'date'      => trim((string)$entry->updated),
                        'html_url'  => "https://github.com/{$repo}/commit/{$sha}",
                        'source'    => 'atom',
                    ];
                }
            }
        }
    }

    if ($info === null) {
        $msg = 'Unable to inspect remote commit (' . $res['code'] . ').';
        $decoded = json_decode($res['body'], true);
        if (is_array($decoded) && !empty($decoded['message'])) {
            $msg .= ' GitHub says: ' . $decoded['message'];
        }
        throw new Exception($msg);
    }

    // Best-effort inspect remote APP_VERSION from raw config/app.php
    $info['remote_version'] = '';
    try {
        $rawHeaders = [];
        if (trim($token) !== '') {
            $rawHeaders[] = 'Authorization: Bearer ' . trim($token);
        }
        $rawUrl = "https://raw.githubusercontent.com/{$repo}/" . rawurlencode($branch) . "/config/app.php";
        $verRes = deployHttpGet($rawUrl, $rawHeaders, null, 15);
        if ($verRes['code'] === 200 && preg_match("/define\s*\(\s*'APP_VERSION'\s*,\s*'([^']+)'\s*\)/", $verRes['body'], $vm)) {
            $info['remote_version'] = $vm[1];
        }
    } catch (Exception $e) {
        // Non-fatal
    }

    return $info;
}

/**
 * Recursively remove a directory.
 */
function deployRemoveDir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

/**
 * Create a pre-deploy ZIP backup of existing site code (excluding protected dirs like backups & uploads).
 */
function createPreDeploySnapshot(string $appRoot, string $backupsDir): string {
    if (!class_exists('ZipArchive')) {
        throw new Exception('PHP ZipArchive extension is required.');
    }
    $filename = 'pre_deploy_' . date('Y-m-d_His') . '.zip';
    $zipPath = $backupsDir . DIRECTORY_SEPARATOR . $filename;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Unable to create pre-deploy rollback snapshot.');
    }

    $rootLen = strlen(rtrim(str_replace('\\', '/', $appRoot), '/')) + 1;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $file) {
        if ($file->isDir()) {
            continue;
        }
        $fullPath = str_replace('\\', '/', $file->getPathname());
        $relPath = substr($fullPath, $rootLen);
        if ($relPath === false || $relPath === '' || isDeployProtectedPath($relPath)) {
            continue;
        }
        $zip->addFile($file->getPathname(), $relPath);
    }
    $zip->close();

    // Prune older rollback snapshots, keeping latest 5
    $snaps = glob($backupsDir . DIRECTORY_SEPARATOR . 'pre_deploy_*.zip') ?: [];
    usort($snaps, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($snaps, 5) as $old) {
        @unlink($old);
    }

    return $filename;
}

/**
 * Pull latest archive from GitHub and deploy selectively to live document root.
 */
function executeLiveDeploy(PDO $pdo, string $repo, string $branch, string $token, int $adminUserId, bool $allowLocalOverwrite = false): array {
    $repo = normalizeDeployRepo($repo);
    $branch = normalizeDeployBranch($branch);

    if (!class_exists('ZipArchive')) {
        throw new Exception('PHP ZipArchive extension is not enabled on this server.');
    }

    $appRoot = realpath(__DIR__ . '/..');
    if (!$appRoot || !is_writable($appRoot)) {
        throw new Exception('Application root directory is not writable by PHP.');
    }

    if (is_dir($appRoot . DIRECTORY_SEPARATOR . '.git') && !$allowLocalOverwrite) {
        throw new Exception('Local .git checkout detected. Check "Force overwrite local Git checkout" if you intentionally want to pull remote files over this workspace.');
    }

    $backupsDir = $appRoot . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($backupsDir)) {
        mkdir($backupsDir, 0755, true);
    }

    // Fetch remote commit metadata first (gives SHA, message, author)
    $remoteInfo = fetchRemoteDeployStatus($repo, $branch, $token);

    $tokenClean = trim($token);
    $tmpPkg = $backupsDir . DIRECTORY_SEPARATOR . '.deploy_pkg_' . bin2hex(random_bytes(5)) . '.zip';
    $tmpStage = $backupsDir . DIRECTORY_SEPARATOR . '.deploy_stage_' . bin2hex(random_bytes(5));

    try {
        // Prefer codeload direct archive for public repos (no API rate limit); API zipball when token provided
        if ($tokenClean !== '') {
            $dlUrl = "https://api.github.com/repos/{$repo}/zipball/" . rawurlencode($branch);
            $dlHeaders = [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $tokenClean,
            ];
        } else {
            $dlUrl = "https://github.com/{$repo}/archive/refs/heads/" . rawurlencode($branch) . ".zip";
            $dlHeaders = [];
        }

        $dlRes = deployHttpGet($dlUrl, $dlHeaders, $tmpPkg, 90);
        if ($dlRes['code'] !== 200 || !is_file($tmpPkg) || filesize($tmpPkg) < 4096) {
            throw new Exception('Failed to download repository archive from GitHub (HTTP ' . $dlRes['code'] . ').');
        }

        if (!mkdir($tmpStage, 0755, true)) {
            throw new Exception('Failed to create staging directory.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPkg) !== true) {
            throw new Exception('Downloaded package is not a valid ZIP archive.');
        }
        if (!$zip->extractTo($tmpStage)) {
            $zip->close();
            throw new Exception('Failed to extract ZIP archive into staging directory.');
        }
        $zip->close();

        // Locate extracted root subfolder (GitHub wraps everything in <repo>-<branch|sha>/)
        $entries = array_values(array_filter(
            scandir($tmpStage) ?: [],
            fn($e) => $e !== '.' && $e !== '..' && is_dir($tmpStage . DIRECTORY_SEPARATOR . $e)
        ));
        if (count($entries) !== 1) {
            throw new Exception('Unexpected archive structure in GitHub package.');
        }
        $sourceRoot = $tmpStage . DIRECTORY_SEPARATOR . $entries[0];

        // Validate essential application markers before touching live files
        foreach (['index.php', 'config/app.php', 'includes/functions.php', 'admin/settings.php'] as $marker) {
            if (!is_file($sourceRoot . DIRECTORY_SEPARATOR . $marker)) {
                throw new Exception("Archive validation failed: missing {$marker}. Aborting deploy.");
            }
        }

        // Snapshot current code so Admin can roll back in one click
        $rollbackFile = createPreDeploySnapshot($appRoot, $backupsDir);

        // Selective overlay copy (never overwrites config/database.prod.php, backups/, assets/uploads/, etc.)
        $filesWritten = 0;
        $filesUnchanged = 0;
        $updatedList = [];
        $phpFilesUpdated = [];

        $srcLen = strlen(rtrim(str_replace('\\', '/', $sourceRoot), '/')) + 1;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $srcFile) {
            if ($srcFile->isDir()) {
                continue;
            }
            $srcPath = $srcFile->getPathname();
            $relPath = substr(str_replace('\\', '/', $srcPath), $srcLen);
            if ($relPath === false || $relPath === '' || strpos($relPath, '..') !== false || isDeployProtectedPath($relPath)) {
                continue;
            }

            $destPath = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                    throw new Exception("Unable to create directory: {$relPath}");
                }
            }

            $srcHash = @sha1_file($srcPath);
            $destHash = is_file($destPath) ? @sha1_file($destPath) : false;

            if ($srcHash !== false && $destHash !== false && $srcHash === $destHash) {
                $filesUnchanged++;
                continue;
            }

            if (!@copy($srcPath, $destPath)) {
                throw new Exception("Failed writing file: {$relPath}");
            }
            @chmod($destPath, 0644);
            $filesWritten++;
            if (count($updatedList) < 25) {
                $updatedList[] = $relPath;
            }
            if (substr($relPath, -4) === '.php') {
                $phpFilesUpdated[] = $destPath;
            }
        }

        // Flush OPcache so live PHP reflects changes immediately
        foreach ($phpFilesUpdated as $pf) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($pf, true);
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        // Determine admin user name for metadata
        $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$adminUserId]);
        $adminName = (string)($uStmt->fetchColumn() ?: 'Administrator');

        // Persist deploy metadata into system_settings
        $meta = [
            'deploy_repo'               => $repo,
            'deploy_branch'             => $branch,
            'deploy_github_token'       => $tokenClean,
            'deploy_last_sha'           => $remoteInfo['sha'],
            'deploy_last_message'       => $remoteInfo['message'],
            'deploy_last_author'        => $remoteInfo['author'],
            'deploy_last_at'            => date('Y-m-d H:i:s'),
            'deploy_last_files_count'   => (string)$filesWritten,
            'deploy_last_by'            => $adminName,
            'deploy_last_rollback_file' => $rollbackFile,
        ];
        $upsert = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        foreach ($meta as $k => $v) {
            $upsert->execute([$k, $v]);
        }

        logAudit($pdo, $adminUserId, 'SYSTEM_DEPLOY', 'system_settings', null, null, [
            'repo'          => $repo,
            'branch'        => $branch,
            'sha'           => $remoteInfo['short_sha'],
            'files_written' => $filesWritten,
            'rollback'      => $rollbackFile,
        ]);

        return [
            'sha'             => $remoteInfo['sha'],
            'short_sha'       => $remoteInfo['short_sha'],
            'message'         => $remoteInfo['message'],
            'author'          => $remoteInfo['author'],
            'remote_version'  => $remoteInfo['remote_version'] ?? '',
            'files_written'   => $filesWritten,
            'files_unchanged' => $filesUnchanged,
            'updated_sample'  => $updatedList,
            'rollback_file'   => $rollbackFile,
        ];
    } finally {
        @unlink($tmpPkg);
        deployRemoveDir($tmpStage);
    }
}

/**
 * Restore codebase from a pre-deploy ZIP snapshot without touching protected data/config.
 */
function executeDeployRollback(PDO $pdo, string $rollbackFilename, int $adminUserId): array {
    if (!preg_match('/^pre_deploy_[0-9_-]+\.zip$/', $rollbackFilename)) {
        throw new Exception('Invalid rollback snapshot filename.');
    }
    if (!class_exists('ZipArchive')) {
        throw new Exception('PHP ZipArchive extension is not enabled.');
    }

    $appRoot = realpath(__DIR__ . '/..');
    $zipPath = $appRoot . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $rollbackFilename;
    if (!is_file($zipPath)) {
        throw new Exception('Selected rollback snapshot does not exist.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new Exception('Unable to open rollback archive.');
    }

    $restored = 0;
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $relPath = str_replace('\\', '/', $zip->getNameIndex($i));
            if ($relPath === '' || substr($relPath, -1) === '/' || isDeployProtectedPath($relPath)) {
                continue;
            }
            if (strpos($relPath, '..') !== false) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }
            $destPath = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            file_put_contents($destPath, $content);
            @chmod($destPath, 0644);
            if (substr($relPath, -4) === '.php' && function_exists('opcache_invalidate')) {
                @opcache_invalidate($destPath, true);
            }
            $restored++;
        }
    } finally {
        $zip->close();
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    logAudit($pdo, $adminUserId, 'SYSTEM_DEPLOY_ROLLBACK', 'system_settings', null, null, [
        'snapshot'       => $rollbackFilename,
        'files_restored' => $restored,
    ]);

    return ['files_restored' => $restored, 'snapshot' => $rollbackFilename];
}
