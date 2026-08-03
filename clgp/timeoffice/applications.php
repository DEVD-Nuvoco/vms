<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['timeoffice', 'admin']);

$pageTitle = 'All Applications';
$activeNav = 'list';

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$filters = [];
if ($dateFrom) {
    $filters['date_from'] = $dateFrom;
}
if ($dateTo) {
    $filters['date_to'] = $dateTo;
}
$list = clgp_list_applications(clgp_apply_session_plant_scope($filters));

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-3">Applications</h2>

<form method="get" class="form-inline mb-3">
    <label class="mr-2">From</label>
    <input type="date" name="date_from" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($dateFrom) ?>">
    <label class="mr-2">To</label>
    <input type="date" name="date_to" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($dateTo) ?>">
    <button class="btn btn-sm btn-clgp">Filter</button>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered clgp-datatable">
            <thead>
                <tr>
                    <th>No</th><th>Type</th><th>Workman</th><th>Plant</th><th>Date</th><th>Status</th><th>Step</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['application_no']) ?></td>
                    <td><?= htmlspecialchars(clgp_application_type_label($a['application_type'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($a['workman_name']) ?></td>
                    <td><?= htmlspecialchars($a['plant']) ?></td>
                    <td><?= htmlspecialchars($a['application_date']) ?></td>
                    <td><?= clgp_status_badge($a['status']) ?></td>
                    <td><?= htmlspecialchars(clgp_step_label($a['current_step'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
