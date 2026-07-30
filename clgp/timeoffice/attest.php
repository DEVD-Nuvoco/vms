<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['timeoffice']);
header('Location: ' . clgp_nav_url('timeoffice', 'applications.php'));
exit;
