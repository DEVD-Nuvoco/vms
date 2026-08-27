<?php
require_once dirname(__DIR__) . '/lieo/config.php';
$db = lieo_db();
$pass = '123456';
$stmt = $db->prepare("UPDATE tbl_lieo_user SET password = ?, must_change_password = 'f' WHERE email LIKE '%@local.test'");
$stmt->bind_param('s', $pass);
$stmt->execute();
echo 'updated ' . $stmt->affected_rows . " local.test users\n";
