<?php
/** Time Office does not create applications — N-1 does. */
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);
header('Location: applications.php');
exit;
