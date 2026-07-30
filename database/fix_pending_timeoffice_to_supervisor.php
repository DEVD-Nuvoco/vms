<?php
require_once dirname(__DIR__) . '/clgp/config.php';
$db = clgp_db();
$sql = "UPDATE tbl_clgp_application
        SET status='Pending_supervisor', current_step='supervisor'
        WHERE status='Pending_timeoffice' OR current_step='timeoffice'";
if (!$db->query($sql)) {
    echo 'ERR: ' . $db->error . PHP_EOL;
    exit(1);
}
echo 'Moved to Supervisor: ' . $db->affected_rows . ' application(s)' . PHP_EOL;
