<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);

$pageTitle = 'Roles';
$activeNav = 'users';
global $CLGP_ROLES, $CLGP_APP_CHAIN;

$roleDescriptions = [
    'admin'      => 'System administration — masters, approval matrix, and configuration.',
    'supervisor' => 'First approval step (assigned via Approval Matrix).',
    'n1'         => 'Second approval step (N-1) after Supervisor.',
    'hod'        => 'Final approval step (HOD) in the application chain.',
    'timeoffice' => 'Creates LC/EG applications (no approval step).',
    'security'   => 'Views all plant applications; closes at gate after HOD approval.',
    'hr'         => 'Approves contractor reactivation requests (plant-scoped via Approval Matrix).',
];

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-2">Roles</h2>
<p class="text-muted mb-4">
    LIEO roles are fixed. Assign people to roles in the <a href="approval_matrix.php">Approval Matrix</a>
    (login + default password emailed). LC/EG chain: Time Office creates → Supervisor → N-1 → HOD → Security closes at gate.
</p>

<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold">System roles (read-only)</div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <thead>
                <tr>
                    <th style="width: 180px;">Role</th>
                    <th>Description</th>
                    <th style="width: 200px;">In approval chain</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($CLGP_ROLES as $key => $meta): ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($meta['label']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($roleDescriptions[$key] ?? '—') ?></td>
                    <td>
                        <?php if (in_array($key, $CLGP_APP_CHAIN, true)): ?>
                        <span class="badge badge-success">Yes</span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
