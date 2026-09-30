<?php
require_once __DIR__ . '/xlsx_writer.php';

$list = $list ?? [];
$contractorMap = $contractorMap ?? lieo_contractor_supervisor_map($_SESSION['lieo_plant'] ?? null);

$headers = ['App No', 'Type', 'Workman Name', 'Workman Code', 'Shift', 'Contractor', 'Supervisor', 'Plant', 'Department', 'Reason', 'Date', 'Time', 'Status', 'Step'];
$rows = [];
foreach ($list as $a) {
    $rows[] = [
        $a['application_no'],
        lieo_application_type_label($a['application_type'] ?? ''),
        $a['workman_name'],
        $a['workman_code'],
        $a['shift'],
        $a['contractor_name'],
        $contractorMap[(int) $a['contractor_id']] ?? '',
        $a['plant'],
        $a['department'],
        $a['reason'] ?? '',
        $a['application_date'],
        $a['created_at'] ? date('H:i', strtotime($a['created_at'])) : '',
        str_replace('_', ' ', $a['status']),
        lieo_step_label($a['current_step']),
    ];
}
lieo_export_xlsx('applications_' . date('Y-m-d_His') . '.xlsx', $headers, $rows);
