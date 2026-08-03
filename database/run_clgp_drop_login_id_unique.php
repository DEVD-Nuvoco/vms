<?php
/**
 * LIEO no longer uses tbl_logindetail — drop unique login_id and allow NULL.
 * Run: php database/run_clgp_drop_login_id_unique.php
 */
require_once dirname(__DIR__) . '/clgp/config.php';

$db = clgp_db();
echo "Fixing tbl_clgp_user.login_id for standalone LIEO auth…\n";

$idx = $db->query("SHOW INDEX FROM tbl_clgp_user WHERE Key_name = 'uk_login_id'");
if ($idx && $idx->num_rows > 0) {
    if ($db->query('ALTER TABLE tbl_clgp_user DROP INDEX uk_login_id')) {
        echo "Dropped unique index uk_login_id\n";
    } else {
        fwrite(STDERR, 'Failed dropping uk_login_id: ' . $db->error . "\n");
        exit(1);
    }
} else {
    echo "uk_login_id already absent\n";
}

// Make login_id nullable (legacy column only).
$col = $db->query("SHOW COLUMNS FROM tbl_clgp_user LIKE 'login_id'");
$row = $col ? $col->fetch_assoc() : null;
if ($row && strtoupper((string) $row['Null']) !== 'YES') {
    if ($db->query('ALTER TABLE tbl_clgp_user MODIFY login_id INT NULL DEFAULT NULL')) {
        echo "Made login_id nullable\n";
    } else {
        fwrite(STDERR, 'Failed modifying login_id: ' . $db->error . "\n");
        exit(1);
    }
} else {
    echo "login_id already nullable\n";
}

// Normalize legacy 0 values to NULL.
if ($db->query('UPDATE tbl_clgp_user SET login_id = NULL WHERE login_id = 0')) {
    echo 'Normalized login_id 0 → NULL (' . $db->affected_rows . " rows)\n";
}

echo "Done.\n";
