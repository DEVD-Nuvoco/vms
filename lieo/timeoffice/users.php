<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);

$pageTitle = 'Roles';
$activeNav = 'users';
global $LIEO_ROLES, $LIEO_APP_CHAIN;

$roleDescriptions = [
    'admin'            => 'Creates HOD, N-1, Security and Time Office assignments — HOD/N-1/Security need HR department HOD approval first.',
    'timeoffice'       => 'View-only tracking for your plant. Must be an HR department employee. Not part of the approval chain.',
    'n1'               => 'Creates Late IN / Early Out applications (department-wise, multiple N-1 allowed per department).',
    'hod'              => 'Approves applications for their department(s) — one HOD per department, but one person can hold multiple departments. The HR department HOD also approves user requests and contractor reactivation.',
    'security'         => 'Closes applications at gate with a mandatory remark. Must be an HR department employee (one per plant).',
];

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Roles</h2>
<p class="text-muted mb-4">
    LIEO roles are fixed and view-only from here — Time Office no longer maintains role assignments (see Admin's LIEO Users).
    Chain: <strong>N-1 creates</strong> → <strong>HOD approves</strong> → <strong>Security</strong> closes at gate with a remark.
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
                <?php foreach ($LIEO_ROLES as $key => $meta): ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($meta['label']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($roleDescriptions[$key] ?? '—') ?></td>
                    <td>
                        <?php if (in_array($key, $LIEO_APP_CHAIN, true)): ?>
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
