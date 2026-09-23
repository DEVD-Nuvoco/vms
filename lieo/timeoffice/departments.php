<?php
/** Department Master moved to Admin — Time Office is view-only now. */
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);
header('Location: ' . lieo_nav_url('timeoffice', 'applications.php'));
exit;
