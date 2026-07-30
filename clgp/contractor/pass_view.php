<?php
require_once __DIR__ . '/../config.php';
clgp_require_login();

$passId = (int)($_GET['id'] ?? 0);
$pass = clgp_find_pass($passId);
if (!$pass) {
    http_response_code(404);
    die('Gate pass not found.');
}

$pageTitle = 'Gate Pass #' . $passId;
$activeNav = $_SESSION['clgp_role'] === 'contractor' ? 'passes' : '';

$backUrl = '../contractor/my_passes.php';
if ($_SESSION['clgp_role'] === 'security') {
    $backUrl = clgp_nav_url('security', 'attendance.php');
} elseif ($_SESSION['clgp_role'] === 'admin') {
    $backUrl = clgp_nav_url('admin', 'index.php');
} elseif ($_SESSION['clgp_role'] !== 'contractor') {
    $backUrl = clgp_nav_url($_SESSION['clgp_role'], 'pending.php');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="clgp-title mb-0">Gate Pass #<?= $passId ?></h2>
    <div>
        <a href="javascript:window.print()" class="btn btn-outline-secondary btn-sm"><i class="typcn typcn-printer"></i> Print</a>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-success btn-sm">← Back</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/pass_detail_card.php'; ?>

<?php if (!empty($pass['attendance_logs'])): ?>
<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold">Early Out / Late Entry Records</div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <thead>
                <tr>
                    <th>Date</th><th>Type</th><th>Reason</th>
                    <th>Workman</th><th>Reporting Superior</th><th>HOD</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pass['attendance_logs'] as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['date']) ?></td>
                    <td><span class="badge badge-<?= $log['type'] === 'Late Entry' ? 'danger' : 'warning' ?>"><?= htmlspecialchars($log['type']) ?></span></td>
                    <td><?= htmlspecialchars($log['reason']) ?></td>
                    <td><?= htmlspecialchars($log['sig_workman']) ?></td>
                    <td><?= htmlspecialchars($log['sig_superior']) ?></td>
                    <td><?= htmlspecialchars($log['sig_hod']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>@media print { .clgp-sidebar, .clgp-topbar, .proto-strip, .btn { display: none !important; } }</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
