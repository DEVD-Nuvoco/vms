<?php
/**
 * One-shot runner: php database/run_clgp_migration.php
 */
$mysqli = @new mysqli('localhost', 'root', '', 'vms');
if ($mysqli->connect_errno) {
    fwrite(STDERR, "CONNECT FAIL: {$mysqli->connect_error}\n");
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/clgp_phase1.sql');
// Split on semicolons that end statements (simple splitter)
$parts = preg_split('/;\s*[\r\n]+/', $sql);
$ok = 0;
$fail = 0;
foreach ($parts as $stmt) {
    $lines = explode("\n", $stmt);
    $clean = [];
    foreach ($lines as $l) {
        if (preg_match('/^\s*--/', $l)) {
            continue;
        }
        $clean[] = $l;
    }
    $stmt = trim(implode("\n", $clean));
    if ($stmt === '') {
        continue;
    }
    if ($mysqli->query($stmt)) {
        $ok++;
    } else {
        $fail++;
        echo "ERR: {$mysqli->error}\nSTMT: " . substr($stmt, 0, 160) . "...\n\n";
    }
}
echo "OK={$ok} FAIL={$fail}\n";
$r = $mysqli->query("SHOW TABLES LIKE 'tbl_clgp%'");
while ($row = $r->fetch_array()) {
    echo $row[0] . "\n";
}
$u = $mysqli->query("SELECT email, role FROM tbl_clgp_user");
if ($u) {
    while ($row = $u->fetch_assoc()) {
        echo "USER: {$row['email']} / {$row['role']}\n";
    }
}
