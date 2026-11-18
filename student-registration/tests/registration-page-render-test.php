<?php

$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require dirname(__DIR__) . '/public/register.php';
$page = ob_get_clean();

if (strpos($page, '<h1>Student registration</h1>') === false) {
    throw new RuntimeException('The registration page did not render its heading.');
}

if (strpos($page, 'Fatal error') !== false || strpos($page, 'Warning:') !== false) {
    throw new RuntimeException('The registration page rendered with a PHP error.');
}

echo "Registration page render test passed.\n";
