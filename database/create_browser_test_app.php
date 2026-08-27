<?php
require_once dirname(__DIR__) . '/lieo/config.php';
foreach (lieo_list_workmen('Active') as $w) {
    if ($w['workman_code'] === 'WM-LIEO-001') {
        $to = lieo_find_user_by_email('lieo.timeoffice@nuvoco.com');
        $r = lieo_create_application([
            'workman_id' => (int) $w['workman_id'],
            'application_type' => 'Early Going',
            'reason' => 'Browser E2E test — personal errand',
            'created_by' => $to ? (int) $to['lieo_user_id'] : 1,
        ]);
        echo json_encode($r) . PHP_EOL;
        exit($r['ok'] ? 0 : 1);
    }
}
echo "no workman\n";
exit(1);
