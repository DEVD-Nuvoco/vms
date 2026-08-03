<?php
/**
 * CLGP DB bootstrap — dedicated connection (does not exit on VMS $mysqli2 failure).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $clgpDb;

if (!isset($clgpDb) || !($clgpDb instanceof mysqli)) {
    // Match root db.php local credentials
    $clgpDb = @new mysqli('localhost', 'root', '', 'vms');
    if ($clgpDb->connect_errno) {
        // Fallback: try including root db.php only for $mysqli
        $rootDb = dirname(__DIR__, 2) . '/db.php';
        if (is_file($rootDb)) {
            // Prevent mysqli2 hard-exit: use output buffering + custom error handling
            $prev = error_reporting(0);
            ob_start();
            // Define a stub so we can connect ourselves if include dies
            try {
                include_once $rootDb;
            } catch (Throwable $e) {
                // ignore
            }
            ob_end_clean();
            error_reporting($prev);
            if (isset($mysqli) && $mysqli instanceof mysqli && !$mysqli->connect_errno) {
                $clgpDb = $mysqli;
            }
        }
    }
}

if (!isset($clgpDb) || !($clgpDb instanceof mysqli) || $clgpDb->connect_errno) {
    http_response_code(500);
    die('LIEO: database connection unavailable. Ensure MySQL is running and database/clgp_phase1.sql has been applied.');
}

$clgpDb->set_charset('utf8mb4');
