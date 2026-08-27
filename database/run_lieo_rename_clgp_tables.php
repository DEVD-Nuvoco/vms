<?php
/**
 * Rename leftover CLGP tables/columns to LIEO (data preserved).
 * Run: php database/run_lieo_rename_clgp_tables.php
 */
require_once dirname(__DIR__) . '/lieo/config.php';

$db = lieo_db();
mysqli_report(MYSQLI_REPORT_OFF);

echo "Renaming tbl_clgp_* → tbl_lieo_* …\n";

$res = $db->query("SHOW TABLES LIKE 'tbl_clgp_%'");
$oldTables = [];
if ($res) {
    while ($row = $res->fetch_row()) {
        $oldTables[] = $row[0];
    }
}

if (!$oldTables) {
    $check = $db->query("SHOW TABLES LIKE 'tbl_lieo_user'");
    if ($check && $check->num_rows > 0) {
        echo "Already using tbl_lieo_* — nothing to rename.\n";
    } else {
        fwrite(STDERR, "No tbl_clgp_* or tbl_lieo_user tables found. Run the LIEO schema first.\n");
        exit(1);
    }
} else {
    $parts = [];
    foreach ($oldTables as $old) {
        $new = preg_replace('/^tbl_clgp_/', 'tbl_lieo_', $old);
        $existsNew = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($new) . "'");
        if ($existsNew && $existsNew->num_rows > 0) {
            echo "  skip $old → $new (target already exists)\n";
            continue;
        }
        $parts[] = "`$old` TO `$new`";
    }
    if ($parts) {
        $sql = 'RENAME TABLE ' . implode(', ', $parts);
        if (!$db->query($sql)) {
            fwrite(STDERR, "RENAME failed: " . $db->error . "\nSQL: $sql\n");
            exit(1);
        }
        echo "  ok " . implode("\n  ok ", $parts) . "\n";
    }
}

$col = $db->query("SHOW COLUMNS FROM tbl_lieo_user LIKE 'clgp_user_id'");
if ($col && $col->num_rows > 0) {
    if (!$db->query(
        "ALTER TABLE tbl_lieo_user CHANGE `clgp_user_id` `lieo_user_id` INT NOT NULL AUTO_INCREMENT"
    )) {
        fwrite(STDERR, "Column rename failed: " . $db->error . "\n");
        exit(1);
    }
    echo "  ok column clgp_user_id → lieo_user_id\n";
} else {
    echo "  column lieo_user_id already present (or table missing)\n";
}

echo "Done.\n";
