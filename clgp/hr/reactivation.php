<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['hr']);

$pageTitle = 'Contractor Reactivation';
$activeNav = 'reactivation';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    $ok = clgp_approve_reactivation((int) ($_POST['id'] ?? 0), (int) $_SESSION['clgp_user_id']);
    $_SESSION['clgp_mess'] = $ok ? 'Contractor reactivated.' : 'Could not approve reactivation.';
    header('Location: reactivation.php');
    exit;
}

$list = clgp_list_reactivation_requests();
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-2">Reactivation Requests</h2>
<p class="text-muted mb-4">Deactivated contractors require Head HR approval before reactivation.</p>

<?php if (!$list): ?>
<div class="alert alert-success">No pending reactivation requests.</div>
<?php else: ?>
<table class="table table-bordered clgp-datatable">
    <thead>
        <tr><th>Contractor</th><th>Type</th><th>Deactivated</th><th>Reason</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['contractor_name']) ?></td>
            <td><?= htmlspecialchars($c['contractor_type']) ?></td>
            <td><?= htmlspecialchars($c['deactivated_at'] ?: '—') ?></td>
            <td><?= htmlspecialchars($c['deactivation_reason'] ?: '—') ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= (int)$c['contractor_id'] ?>">
                    <button class="btn btn-sm btn-clgp">Approve Reactivation</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
