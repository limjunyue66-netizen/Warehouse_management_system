<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    $role = current_user_role();
    if ($role === 'admin') {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    } else {
        header('Location: ' . url('user/dashboard.php'));
        exit;
    }
}

header('Location: ' . url('login.php'));
exit;
