<?php
/** Fallback — login lives at /lieo/login.php, not under role folders. */
require_once __DIR__ . '/../config.php';
header('Location: ' . lieo_login_url());
exit;
