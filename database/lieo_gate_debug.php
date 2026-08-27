<?php
require_once __DIR__ . '/../lieo/config.php';

$today = date('Y-m-d');
echo "Server date: $today\n\nRecent applications:\n";
$res = lieo_db()->query(
    'SELECT application_no, plant, status, application_date, gate_in_at, gate_out_at
     FROM tbl_lieo_application ORDER BY application_id DESC LIMIT 15'
);
while ($row = $res->fetch_assoc()) {
    echo implode(' | ', $row) . "\n";
}
echo "\nBy plant + status:\n";
$res2 = lieo_db()->query(
    'SELECT plant, status, COUNT(*) AS c FROM tbl_lieo_application GROUP BY plant, status ORDER BY plant, status'
);
while ($row = $res2->fetch_assoc()) {
    echo $row['plant'] . ' / ' . $row['status'] . ' = ' . $row['c'] . "\n";
}
foreach (['JCP', 'Nimbol'] as $plant) {
    $st = lieo_db()->prepare('SELECT COUNT(*) AS c FROM tbl_lieo_application WHERE plant=? AND status=?');
    $att = 'Attested';
    $st->bind_param('ss', $plant, $att);
    $st->execute();
    $c = $st->get_result()->fetch_assoc()['c'];
    $st->close();
    echo "\n$plant Attested: $c\n";
    $st2 = lieo_db()->prepare('SELECT COUNT(*) AS c FROM tbl_lieo_application WHERE plant=? AND application_date=?');
    $st2->bind_param('ss', $plant, $today);
    $st2->execute();
    $c2 = $st2->get_result()->fetch_assoc()['c'];
    $st2->close();
    echo "$plant applications on $today: $c2\n";
}
