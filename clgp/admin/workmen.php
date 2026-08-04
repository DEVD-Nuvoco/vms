<?php
/** Workmen master removed from Admin — Time Office enters workman details on create. */
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);
$_SESSION['clgp_mess'] = 'Workmen are no longer managed by Admin. Time Office enters workman details when creating an application.';
$_SESSION['clgp_mess_type'] = 'info';
header('Location: index.php');
exit;
