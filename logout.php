<?php
/**
 * Logout Script (FR-34, SEC-05)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

header('Location: ' . app_url('login.php?logged_out=1'));
exit;
