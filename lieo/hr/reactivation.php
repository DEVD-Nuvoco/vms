<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['hr']);

$pageTitle = 'Contractor Reactivation';
$activeNav = 'reactivation';
$plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $ok = lieo_approve_reactivation($id, (int) $_SESSION['lieo_user_id']);
        $_SESSION['lieo_mess'] = $ok ? 'Contractor reactivated.' : 'Could not approve reactivation.';
    } elseif ($action === 'reject') {
        $ok = lieo_reject_reactivation($id);
        $_SESSION['lieo_mess'] = $ok ? 'Reactivation rejected.' : 'Could not reject.';
    }
    header('Location: reactivation.php');
    exit;
}

$list = lieo_list_reactivation_requests($plant !== '' ? $plant : null);
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Reactivation Requests</h2>
<p class="text-muted mb-4">Deactivated contractors require Head HR approval before reactivation<?= $plant !== '' ? ' (plant ' . htmlspecialchars($plant) . ')' : '' ?>.</p>

<?php if (!$list): ?>
<div class="alert alert-success">No pending reactivation requests.</div>
<?php else: ?>
<table class="table table-bordered lieo-datatable">
    <thead>
        <tr><th>Contractor</th><th>Plant</th><th>Type</th><th>Deactivated</th><th>Reason</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['contractor_name']) ?></td>
            <td><?= htmlspecialchars($c['plant'] ?? '—') ?></td>
            <td><?= htmlspecialchars($c['contractor_type']) ?></td>
            <td><?= htmlspecialchars($c['deactivated_at'] ?: '—') ?></td>
            <td><?= htmlspecialchars($c['deactivation_reason'] ?: '—') ?></td>
            <td class="text-nowrap">
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= (int)$c['contractor_id'] ?>">
                    <button class="btn btn-sm btn-lieo">Approve</button>
                </form>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= (int)$c['contractor_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
