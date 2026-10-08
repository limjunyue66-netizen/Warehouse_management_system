<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Authentication & Authorization
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if the user is currently authenticated
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get the currently logged-in user array
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user ID
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

/**
 * Get current user Role ('admin' or 'user')
 */
function current_user_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Enforce authentication - redirect to login if unauthenticated
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access the system.');
        header('Location: ' . url('login.php'));
        exit;
    }
}

/**
 * Enforce role permission - checks if the user has the required role
 */
function require_role(string $requiredRole): void
{
    require_login();

    $userRole = current_user_role();
    if ($userRole !== $requiredRole) {
        set_flash('danger', 'Access denied. You do not have permission to view that page.');
        if ($userRole === 'admin') {
            header('Location: ' . url('admin/dashboard.php'));
        } else {
            header('Location: ' . url('user/dashboard.php'));
        }
        exit;
    }
}

/**
 * Attempt user login with credentials
 */
function login(string $username, string $password): array
{
    $username = trim($username);
    $password = trim($password);

    if ($username === '' || $password === '') {
        return ['success' => false, 'error' => 'Please enter both username and password.'];
    }

    $db = get_db_connection();
    $stmt = $db->prepare('SELECT id, full_name, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    if ((int)$user['is_active'] !== 1) {
        return ['success' => false, 'error' => 'This account has been deactivated. Please contact an administrator.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    // Regenerate session ID to prevent session fixation
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    // Store only minimal safe user information in session
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'username'  => $user['username'],
        'role'      => $user['role'],
        'logged_in' => time()
    ];

    return ['success' => true, 'user' => $_SESSION['user']];
}

/**
 * Logout and invalidate session
 */
function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
