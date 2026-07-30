<?php
/**
 * After removing Time Office attestation: HOD-approved apps use current_step = gate.
 * Run once: php database/fix_approved_gate_step.php
 */
require_once dirname(__DIR__) . '/clgp/config.php';

$db = clgp_db();
$db->query(
    "UPDATE tbl_clgp_application
     SET current_step = 'gate'
     WHERE status IN ('Approved', 'Attested')
       AND current_step IN ('attestation', '')"
);
echo 'Updated rows: ' . $db->affected_rows . PHP_EOL;
