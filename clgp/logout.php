<?php
require_once __DIR__ . '/config.php';
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
header('Location: login.php');
exit;
