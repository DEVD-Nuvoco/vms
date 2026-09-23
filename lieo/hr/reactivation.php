<?php
/** HR Head role discontinued — contractor reactivation now approved by the HR department HOD. */
require_once __DIR__ . '/../config.php';
lieo_require_role(['hr']);
header('Location: ' . lieo_nav_url('hod', 'reactivation.php'));
exit;
