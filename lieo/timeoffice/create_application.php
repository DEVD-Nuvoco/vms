<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);
header('Location: pending.php');
exit;
