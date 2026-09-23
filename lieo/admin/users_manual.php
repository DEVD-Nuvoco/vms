<?php
/**
 * "/lieo/login.php?add=manually" — manual add-user entry point.
 * admin/approval_matrix.php's "Add Role Assignment" form already IS the
 * manual add-user flow (plant + department + role + AMS employee picker ->
 * login provisioning), so this just opens straight there instead of
 * duplicating that form and its employee-picker JS.
 */
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);
header('Location: ' . lieo_nav_url('admin', 'approval_matrix.php'));
exit;
