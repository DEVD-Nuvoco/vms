<?php
require_once dirname(__DIR__) . '/clgp/config.php';
$r = clgp_db()->query('DESCRIBE tbl_clgp_application');
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
