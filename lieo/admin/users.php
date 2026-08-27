<?php
/** Roles moved to Time Office. */
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);
header('Location: ' . lieo_nav_url('admin', 'index.php'));
exit;
