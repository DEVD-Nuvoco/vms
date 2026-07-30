<?php
/** Fallback — login lives at /clgp/login.php, not under role folders. */
require_once __DIR__ . '/../config.php';
header('Location: ' . clgp_login_url());
exit;
