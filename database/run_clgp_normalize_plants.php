<?php
/**
 * Normalize LIEO plant codes to canonical short form.
 * Example: NVCL_RCP, NVL-C-RCP, 87000003-NVL-C-RCP → RCP
 *
 * Run once: php database/run_clgp_normalize_plants.php
 */
require_once dirname(__DIR__) . '/clgp/config.php';

$db = clgp_db();
$tables = [
    'tbl_clgp_approval_matrix' => 'matrix_id',
    'tbl_clgp_user' => 'clgp_user_id',
    'tbl_clgp_workman' => 'workman_id',
    'tbl_clgp_application' => 'application_id',
];

$total = 0;
foreach ($tables as $table => $pk) {
    $res = $db->query("SELECT `$pk` AS id, plant FROM `$table` WHERE plant IS NOT NULL AND plant != ''");
    if (!$res) {
        echo "Skip $table: " . $db->error . PHP_EOL;
        continue;
    }
    $updated = 0;
    while ($row = $res->fetch_assoc()) {
        $old = $row['plant'];
        $new = clgp_ams_canonical_plant($old);
        if ($new === '' || $new === $old) {
            continue;
        }
        $stmt = $db->prepare("UPDATE `$table` SET plant=? WHERE `$pk`=?");
        $id = (int) $row['id'];
        $stmt->bind_param('si', $new, $id);
        if ($stmt->execute()) {
            $updated++;
            $total++;
            echo "$table #$id: $old → $new" . PHP_EOL;
        }
        $stmt->close();
    }
    echo "$table: $updated row(s) normalized" . PHP_EOL;
}

echo "Done. Total updates: $total" . PHP_EOL;
