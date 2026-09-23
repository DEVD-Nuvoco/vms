<?php
/**
 * Bulk-add HOD/N-1/Security/Time Office assignments, one tbl_lieo_user_request
 * per row (see resources/HOD & N+1.xlsx for the expected shape). Each row is
 * queued for HR-department-HOD approval exactly like a manual add — this
 * endpoint never writes tbl_lieo_approval_matrix directly.
 */
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['plant'], $body['rows']) || !is_array($body['rows'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid payload.']);
    exit;
}

$plant = lieo_ams_canonical_plant((string) $body['plant']);
if ($plant === '' || !in_array($plant, lieo_list_ams_plants(), true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Unknown plant: ' . $body['plant']]);
    exit;
}

$typeMap = ['HOD' => 'hod', 'N+1' => 'n1', 'N-1' => 'n1', 'SECURITY' => 'security', 'TIME OFFICE' => 'timeoffice', 'TIMEOFFICE' => 'timeoffice'];
$deptMaster = array_map('strtolower', array_column(lieo_list_all_plant_departments($plant), 'department_name'));
$amsDepts = array_map('strtolower', lieo_list_ams_departments($plant));

$seen = [];
$results = [];
foreach ($body['rows'] as $i => $row) {
    if (!is_array($row)) {
        $results[] = ['row' => $i + 1, 'ok' => false, 'message' => 'Malformed row.'];
        continue;
    }
    $type = strtoupper(trim((string) ($row['type'] ?? '')));
    $step = $typeMap[$type] ?? null;
    $dept = trim((string) ($row['department'] ?? ''));
    $empCode = trim((string) ($row['emp_code'] ?? ''));
    $empEmail = strtolower(trim((string) ($row['emp_email'] ?? '')));
    $empName = trim((string) ($row['emp_name'] ?? ''));

    if (!$step) {
        $results[] = ['row' => $i + 1, 'ok' => false, 'message' => "Unknown Type '{$type}' (expected HOD or N+1)."];
        continue;
    }
    if ($empCode === '' || $empEmail === '' || $empName === '') {
        $results[] = ['row' => $i + 1, 'ok' => false, 'message' => 'Missing SF Code, Emp Name or Company Email ID.'];
        continue;
    }
    if (lieo_matrix_needs_department($step) && $dept === '') {
        $results[] = ['row' => $i + 1, 'ok' => false, 'message' => 'Department is required for ' . lieo_role_label($step) . '.'];
        continue;
    }
    if (lieo_matrix_needs_department($step) && !in_array(strtolower($dept), $deptMaster, true) && !in_array(strtolower($dept), $amsDepts, true)) {
        $results[] = ['row' => $i + 1, 'ok' => false, 'message' => "Department '{$dept}' is not in the plant's department master — add it first (Department Master)."];
        continue;
    }

    $dedupeKey = strtolower($plant . '|' . $dept . '|' . $step . '|' . $empCode);
    if (isset($seen[$dedupeKey])) {
        $results[] = ['row' => $i + 1, 'ok' => false, 'message' => 'Duplicate of an earlier row in this upload — skipped.'];
        continue;
    }
    $seen[$dedupeKey] = true;

    // Existing slot with a DIFFERENT person -> queue as an update (transfer),
    // not a plain create, per the single-assignee-slot rule.
    $requestType = 'create';
    $targetMatrixId = null;
    if (in_array($step, lieo_matrix_single_assignee_steps(), true)) {
        $conflict = lieo_matrix_slot_taken_by_other($plant, $dept, $step, $empCode);
        if ($conflict) {
            $requestType = 'update';
            $targetMatrixId = (int) $conflict['matrix_id'];
        }
    }

    $result = lieo_submit_user_request([
        'request_type' => $requestType,
        'target_matrix_id' => $targetMatrixId,
        'plant' => $plant,
        'department' => $dept,
        'approval_step' => $step,
        'emp_code' => $empCode,
        'emp_name' => $empName,
        'emp_email' => $empEmail,
    ]);
    $results[] = [
        'row' => $i + 1,
        'ok' => (bool) ($result['ok'] ?? false),
        'message' => $result['ok']
            ? ($requestType === 'update' ? 'Queued as replacement request.' : 'Queued for approval.')
            : (string) ($result['message'] ?? 'Could not queue.'),
    ];
}

$okCount = count(array_filter($results, static fn($r) => $r['ok']));
echo json_encode([
    'ok' => true,
    'summary' => $okCount . ' of ' . count($results) . ' rows queued for HR department HOD approval.',
    'results' => $results,
]);
