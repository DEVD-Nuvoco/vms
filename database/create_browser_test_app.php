<?php
require_once dirname(__DIR__) . '/clgp/config.php';
foreach (clgp_list_workmen('Active') as $w) {
    if ($w['workman_code'] === 'WM-CLGP-001') {
        $to = clgp_find_user_by_email('clgp.timeoffice@nuvoco.com');
        $r = clgp_create_application([
            'workman_id' => (int) $w['workman_id'],
            'application_type' => 'Early Going',
            'reason' => 'Browser E2E test — personal errand',
            'created_by' => $to ? (int) $to['clgp_user_id'] : 1,
        ]);
        echo json_encode($r) . PHP_EOL;
        exit($r['ok'] ? 0 : 1);
    }
}
echo "no workman\n";
exit(1);
