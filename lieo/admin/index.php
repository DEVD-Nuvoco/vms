<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);

$pageTitle = 'Admin Dashboard';
$activeNav = 'dashboard';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stats = lieo_dashboard_counts($date);
$inApps = lieo_list_applications(['access_type' => 'Entry', 'date' => $date]);
$outApps = lieo_list_applications(['access_type' => 'Exit', 'date' => $date]);
$recent = lieo_list_applications([]);
$recent = array_slice($recent, 0, 15);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <h2 class="lieo-title mb-0">Admin Dashboard</h2>
    <form method="get" class="form-inline">
        <label class="mr-2 mb-0">Date</label>
        <input type="date" name="date" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($date) ?>">
        <button class="btn btn-sm btn-lieo">Filter</button>
    </form>
</div>
<p class="text-muted">IN / OUT for <strong><?= htmlspecialchars($date) ?></strong>. Default is today.</p>

<div class="row mb-4">
    <?php foreach ([
        ['Active Contractors', $stats['contractors'], 'primary'],
        ['Approval Rules', $stats['matrix_rules'], 'info'],
        ['Pending Approvals', $stats['pending'], 'warning'],
        ['Gate Completed (day)', $stats['completed_today'], 'success'],
    ] as [$label, $val, $color]): ?>
    <div class="col-md-3 mb-3">
        <div class="card card-stat shadow-sm">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small"><?= $label ?></h6>
                <h3 class="mb-0 text-<?= $color ?>"><?= (int)$val ?></h3>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white font-weight-bold text-success">IN (Late Coming) — <?= count($inApps) ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>No</th><th>Workman</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$inApps): ?><tr><td colspan="3" class="text-muted text-center">No IN records</td></tr><?php endif; ?>
                    <?php foreach ($inApps as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['application_no']) ?></td>
                            <td><?= htmlspecialchars($a['workman_name']) ?></td>
                            <td><?= lieo_status_badge($a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white font-weight-bold text-warning">OUT (Early Going) — <?= count($outApps) ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>No</th><th>Workman</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$outApps): ?><tr><td colspan="3" class="text-muted text-center">No OUT records</td></tr><?php endif; ?>
                    <?php foreach ($outApps as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['application_no']) ?></td>
                            <td><?= htmlspecialchars($a['workman_name']) ?></td>
                            <td><?= lieo_status_badge($a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold">Quick Actions</div>
            <div class="card-body">
                <a href="approval_matrix.php" class="btn btn-lieo btn-block mb-2">Approval Matrix (HOD / N-1 / Security / Time Office)</a>
                <a href="<?= htmlspecialchars(lieo_web_base()) ?>/login.php?add=manually" class="btn btn-outline-success btn-block mb-2">Add User (Manual)</a>
                <a href="users_bulk_upload.php" class="btn btn-outline-success btn-block mb-2">Bulk Upload Users</a>
                <a href="departments.php" class="btn btn-outline-secondary btn-block">Department Master</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold">Approval Flow</div>
            <div class="card-body small">
                <ol class="mb-0 pl-3">
                    <li>N-1 creates the Late IN / Early Out application</li>
                    <li>HOD approves (per department)</li>
                    <li>Security closes at gate with a remark (IN / OUT)</li>
                </ol>
                <p class="text-muted mt-2 mb-0">HOD, N-1 and Security assignments need the HR department HOD's approval before they take effect.</p>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold">Recent Applications</div>
    <div class="card-body">
        <table class="table table-bordered lieo-datatable">
            <thead>
                <tr>
                    <th>No</th><th>Type</th><th>Workman</th><th>Plant</th><th>Date</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['application_no']) ?></td>
                    <td><?= htmlspecialchars(lieo_application_type_label($a['application_type'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($a['workman_name']) ?></td>
                    <td><?= htmlspecialchars($a['plant']) ?></td>
                    <td><?= htmlspecialchars($a['application_date']) ?></td>
                    <td><?= lieo_status_badge($a['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
