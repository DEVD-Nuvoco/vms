<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['contractor']);

$pageTitle = 'Contractor Dashboard';
$activeNav = 'dashboard';

$email = $_SESSION['clgp_user_email'];
$myPasses = array_filter($_SESSION['clgp_passes'], function ($p) use ($email) {
    foreach ($_SESSION['clgp_contractors'] as $c) {
        if (strtolower($c['email']) === strtolower($email) && $c['name'] === $p['contractor']) {
            return true;
        }
    }
    return strtolower($email) === 'ramesh@abc.com' || strtolower($email) === 'sunil@xyz.com';
});

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-4">Contractor Dashboard</h2>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card card-stat shadow-sm">
            <div class="card-body">
                <h6 class="text-muted small">MY REQUESTS</h6>
                <h3><?= count($myPasses) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-8 d-flex align-items-center">
        <a href="create_pass.php" class="btn btn-clgp btn-lg"><i class="typcn typcn-document-add"></i> Create New Gate Pass</a>
        <a href="my_passes.php" class="btn btn-outline-success btn-lg ml-2">View All Passes</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold">My Gate Pass Requests</div>
    <div class="card-body">
        <table class="table table-bordered">
            <thead><tr><th>ID</th><th>Workman</th><th>Type</th><th>Plant</th><th>Pool</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($myPasses)): ?>
                <tr><td colspan="6" class="text-center text-muted">No passes yet. Create your first gate pass request.</td></tr>
                <?php else: foreach ($myPasses as $p): ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><?= htmlspecialchars($p['workman']) ?></td>
                    <td><?= htmlspecialchars($p['vendor_type']) ?></td>
                    <td><?= htmlspecialchars($p['plant']) ?></td>
                    <td><?= htmlspecialchars($p['pool']) ?></td>
                    <td><?= clgp_status_badge($p['status']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
