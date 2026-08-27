<?php
/** Workmen master removed from Admin — Time Office enters workman details on create. */
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);
$_SESSION['lieo_mess'] = 'Workmen are no longer managed by Admin. Time Office enters workman details when creating an application.';
$_SESSION['lieo_mess_type'] = 'info';
header('Location: index.php');
exit;
