<?php
require_once dirname(__DIR__) . '/clgp/config.php';
$db = clgp_db();
$sqls = [
    "ALTER TABLE `tbl_clgp_application`
      MODIFY COLUMN `status` ENUM(
        'Pending_timeoffice',
        'Pending_supervisor',
        'Pending_n1',
        'Pending_hod',
        'Approved',
        'Attested',
        'Gate_completed',
        'Rejected'
      ) NOT NULL DEFAULT 'Pending_timeoffice'",
    "ALTER TABLE `tbl_clgp_application`
      MODIFY COLUMN `current_step` ENUM(
        'timeoffice','supervisor','n1','hod','attestation','gate','done','rejected'
      ) NOT NULL DEFAULT 'timeoffice'",
    "UPDATE `tbl_clgp_application`
      SET `status` = 'Pending_timeoffice', `current_step` = 'timeoffice'
      WHERE `status` = '' OR `current_step` = '' OR (`status` = 'Pending_supervisor' AND `current_step` = 'timeoffice')",
    "ALTER TABLE `tbl_clgp_application_approval`
      MODIFY COLUMN `step` ENUM(
        'timeoffice','supervisor','n1','hod','attestation','gate','done','rejected'
      ) NOT NULL",
];
foreach ($sqls as $i => $sql) {
    if (!$db->query($sql)) {
        echo "ERR #$i: {$db->error}\n";
        exit(1);
    }
    echo "OK #$i\n";
}
$r = $db->query("SHOW COLUMNS FROM tbl_clgp_application LIKE 'status'");
$row = $r->fetch_assoc();
echo "status type: {$row['Type']}\n";
