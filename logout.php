<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

logout();
set_flash('info', 'You have been signed out safely.');
header('Location: ' . url('login.php'));
exit;
