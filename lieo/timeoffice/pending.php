<?php
/** Time Office is no longer part of the approval chain (N-1 creates -> HOD approves -> Security closes). View-only now. */
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);
header('Location: ' . lieo_nav_url('timeoffice', 'applications.php'));
exit;
