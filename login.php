<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    $role = current_user_role();
    header('Location: ' . ($role === 'admin' ? url('admin/dashboard.php') : url('user/dashboard.php')));
    exit;
}

$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submittedToken)) {
        $error = 'Security session expired or invalid token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $usernameVal = $username;

        $authResult = login($username, $password);
        if ($authResult['success']) {
            set_flash('success', 'Welcome back, ' . htmlspecialchars($authResult['user']['full_name']) . '!');
            if ($authResult['user']['role'] === 'admin') {
                header('Location: ' . url('admin/dashboard.php'));
            } else {
                header('Location: ' . url('user/dashboard.php'));
            }
            exit;
        } else {
            $error = $authResult['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo e(url('assets/css/style.css')); ?>">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <?php echo render_icon('box', 'icon'); ?>
            </div>
            <h1 class="auth-title">Warehouse Suite</h1>
            <p class="auth-subtitle">Sign in to manage and monitor warehouse inventory</p>
        </div>

        <div class="auth-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo render_icon('alert', 'flash-icon'); ?>
                    <div class="alert-content"><?php echo e($error); ?></div>
                </div>
            <?php endif; ?>

            <?php echo render_flash(); ?>

            <!-- Note: Username & password are intentionally empty per security guidelines -->
            <form action="<?php echo e(url('login.php')); ?>" method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        placeholder="Enter username" 
                        value="<?php echo e($usernameVal); ?>" 
                        required 
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Enter password" 
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                    Sign In to System
                </button>
            </form>

            <div class="demo-account-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-weight: 600; color: var(--text-primary);">Demo Test Credentials:</span>
                    <span style="font-size: 11px; color: var(--text-muted);">Click to auto-fill</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        <div>
                            <strong>Admin</strong>: <code>admin</code> / <code>admin123</code>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="autoFill('admin', 'admin123')" style="padding: 3px 8px; font-size: 11px;" title="Auto fill Admin credentials">
                            Fill Admin
                        </button>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                        <div>
                            <strong>User</strong>: <code>user</code> / <code>user123</code>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="autoFill('user', 'user123')" style="padding: 3px 8px; font-size: 11px;" title="Auto fill User credentials">
                            Fill User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function autoFill(u, p) {
        const userInput = document.getElementById('username');
        const passInput = document.getElementById('password');
        if (userInput && passInput) {
            userInput.value = u;
            passInput.value = p;
            passInput.focus();
        }
    }
    </script>
</body>
</html>
