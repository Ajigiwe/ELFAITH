<?php
/**
 * Authentication and Access Control Helpers
 * EL-FAITH MICROFINANCE
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'uuid' => $_SESSION['user_uuid'] ?? '',
        'staff_code' => $_SESSION['user_staff_code'] ?? '',
        'name' => $_SESSION['user_name'] ?? '',
        'username' => $_SESSION['user_username'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'agent'
    ];
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

function isSuperAdmin(): bool {
    return currentUserRole() === 'super_admin';
}

function isAdmin(): bool {
    return in_array(currentUserRole(), ['admin', 'super_admin']);
}

function requireLogin(): void {
    global $pdo;
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access this area.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    // Verify session user is still active in database & sync role
    if (isset($pdo) && !empty($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT role, status, deleted_at FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $uRow = $stmt->fetch();
            if (!$uRow || ($uRow['status'] ?? '') !== 'active' || !empty($uRow['deleted_at'])) {
                logoutUser();
                setFlash('danger', 'Your account has been deactivated or removed. Please contact an administrator.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
            if (!empty($uRow['role']) && ($_SESSION['user_role'] ?? '') !== $uRow['role']) {
                $_SESSION['user_role'] = $uRow['role'];
            }
        } catch (Exception $e) {
            // Non-blocking fallback
        }
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'Access denied. Administrative privileges required.');
        header('Location: ' . BASE_URL . '/agent/index.php');
        exit;
    }
}

function requireSuperAdmin(): void {
    requireLogin();
    if (!isSuperAdmin()) {
        setFlash('danger', 'Access restricted: Technical settings, system backups, and infrastructure configurations require Super Administrator privileges.');
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }
}

function requireAgent(): void {
    requireLogin();
    // Admins can also access agent views if needed, but agents cannot access admin views
}

function attemptLogin(PDO $pdo, string $username, string $password): bool {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'active' || !empty($user['deleted_at'])) {
            $_SESSION['login_error'] = 'This account has been deactivated. Please contact an administrator.';
            return false;
        }

        // Regenerate session id to protect against session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['user_staff_code'] = $user['staff_code'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        // Update last login
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

        // Audit Log
        logAudit($pdo, $user['id'], 'USER_LOGIN', 'users', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

        return true;
    }

    return false;
}

function logoutUser(): void {
    global $pdo;
    if (isLoggedIn()) {
        logAudit($pdo, currentUserId(), 'USER_LOGOUT', 'users', currentUserId());
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

