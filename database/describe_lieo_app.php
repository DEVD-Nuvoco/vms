<?php
require_once dirname(__DIR__) . '/lieo/config.php';
$r = lieo_db()->query('DESCRIBE tbl_lieo_application');
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
