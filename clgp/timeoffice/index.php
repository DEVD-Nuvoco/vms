<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['timeoffice']);

$pageTitle = 'Time Office Dashboard';
$activeNav = 'dashboard';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$inApps = clgp_list_applications(['access_type' => 'Entry', 'date' => $date]);
$outApps = clgp_list_applications(['access_type' => 'Exit', 'date' => $date]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <h2 class="clgp-title mb-0">Time Office — IN / OUT</h2>
    <form method="get" class="form-inline">
        <input type="date" name="date" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($date) ?>">
        <button class="btn btn-sm btn-clgp">Filter</button>
    </form>
</div>

<div class="mb-3">
    <a href="create_application.php" class="btn btn-clgp mr-2">Create Application</a>
    <a href="applications.php" class="btn btn-outline-secondary">All Applications</a>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold text-success">IN — Late Coming (<?= count($inApps) ?>)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>No</th><th>Workman</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$inApps): ?><tr><td colspan="3" class="text-center text-muted">None</td></tr><?php endif; ?>
                    <?php foreach ($inApps as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['application_no']) ?></td>
                            <td><?= htmlspecialchars($a['workman_name']) ?></td>
                            <td><?= clgp_status_badge($a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold text-warning">OUT — Early Going (<?= count($outApps) ?>)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>No</th><th>Workman</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$outApps): ?><tr><td colspan="3" class="text-center text-muted">None</td></tr><?php endif; ?>
                    <?php foreach ($outApps as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['application_no']) ?></td>
                            <td><?= htmlspecialchars($a['workman_name']) ?></td>
                            <td><?= clgp_status_badge($a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
