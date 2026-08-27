<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['contractor']);

$pageTitle = 'My Passes';
$activeNav = 'passes';

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-4">My Gate Passes</h2>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered lieo-datatable">
            <thead>
                <tr>
                    <th>ID</th><th>Workman</th><th>ID Code</th><th>Contractor</th><th>Dept</th><th>Shift</th>
                    <th>Created</th><th>Expiry</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['lieo_passes'] as $p): $p = lieo_normalize_pass($p); ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><?= htmlspecialchars($p['workman']) ?></td>
                    <td><?= htmlspecialchars($p['workman_id_code'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['contractor']) ?></td>
                    <td><?= htmlspecialchars($p['department']) ?></td>
                    <td><?= htmlspecialchars($p['shift'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['created']) ?></td>
                    <td><?= $p['expiry'] ? htmlspecialchars($p['expiry']) : '—' ?></td>
                    <td><?= lieo_status_badge($p['status']) ?></td>
                    <td><a href="pass_view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-success">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
