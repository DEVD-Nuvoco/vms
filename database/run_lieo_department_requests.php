<?php
/**
 * Admin's Department Master add/edit now goes through the HR department
 * HOD's approval, same pattern as the existing HOD/N-1/Security user
 * requests. Run: php database/run_lieo_department_requests.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

$db = lieo_db();
$tbl = $db->query("SHOW TABLES LIKE 'tbl_lieo_department_request'");
if ($tbl && $tbl->num_rows > 0) {
    echo "Table tbl_lieo_department_request already exists.\n";
    exit(0);
}
$ok = $db->query(
    "CREATE TABLE tbl_lieo_department_request (
        request_id INT NOT NULL AUTO_INCREMENT,
        request_type ENUM('add','edit') NOT NULL DEFAULT 'add',
        plant VARCHAR(100) NOT NULL,
        department_name VARCHAR(150) NOT NULL,
        is_hr TINYINT(1) NOT NULL DEFAULT 0,
        ams_department_name VARCHAR(150) NOT NULL DEFAULT '',
        target_dept_id INT DEFAULT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        requested_by INT NOT NULL,
        decided_by INT DEFAULT NULL,
        decision_remark VARCHAR(500) DEFAULT NULL,
        decided_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (request_id),
        KEY idx_plant_status (plant, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo $ok ? "Created tbl_lieo_department_request.\n" : 'Failed: ' . $db->error . "\n";
