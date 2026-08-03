<?php
/**
 * Add LIEO-only password on tbl_clgp_user (stop using tbl_logindetail for LIEO auth).
 * Run once: php database/run_clgp_lieo_password.php
 */
require_once dirname(__DIR__) . '/clgp/config.php';

$db = clgp_db();

function column_exists(mysqli $db, string $table, string $column): bool
{
    $table = $db->real_escape_string($table);
    $column = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

echo "Migrating LIEO password storage onto tbl_clgp_user…\n";

if (!column_exists($db, 'tbl_clgp_user', 'password')) {
    if (!$db->query("ALTER TABLE tbl_clgp_user ADD COLUMN `password` VARCHAR(100) NOT NULL DEFAULT '' AFTER `email`")) {
        fwrite(STDERR, "Failed to add password column: " . $db->error . "\n");
        exit(1);
    }
    echo "Added column tbl_clgp_user.password\n";
} else {
    echo "Column password already exists\n";
}

// Copy passwords from linked VMS login rows where LIEO password is still empty.
$copied = 0;
$res = $db->query(
    "SELECT u.clgp_user_id, u.password, l.userPassword
     FROM tbl_clgp_user u
     LEFT JOIN tbl_logindetail l ON l.id = u.login_id
     WHERE IFNULL(u.password, '') = '' AND IFNULL(l.userPassword, '') <> ''"
);
if ($res) {
    $upd = $db->prepare('UPDATE tbl_clgp_user SET password = ? WHERE clgp_user_id = ?');
    while ($row = $res->fetch_assoc()) {
        $pass = (string) $row['userPassword'];
        $id = (int) $row['clgp_user_id'];
        $upd->bind_param('si', $pass, $id);
        if ($upd->execute()) {
            $copied++;
        }
    }
    $upd->close();
}
echo "Copied $copied password(s) from legacy login rows.\n";

// Ensure email is unique within LIEO users only.
$idx = $db->query("SHOW INDEX FROM tbl_clgp_user WHERE Key_name = 'uq_clgp_user_email'");
if (!$idx || $idx->num_rows === 0) {
    // Ignore failure if duplicates already exist.
    if ($db->query("ALTER TABLE tbl_clgp_user ADD UNIQUE KEY uq_clgp_user_email (email)")) {
        echo "Added unique key on tbl_clgp_user.email\n";
    } else {
        echo "Note: could not add unique email key: " . $db->error . "\n";
    }
} else {
    echo "Unique email key already present\n";
}

echo "Done. LIEO auth is now independent of tbl_logindetail.\n";
