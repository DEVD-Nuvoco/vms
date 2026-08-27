<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);

$pageTitle = 'Roles';
$activeNav = 'users';
global $LIEO_ROLES, $LIEO_APP_CHAIN;

$roleDescriptions = [
    'admin'            => 'Assigns Time Office users only; system dashboard.',
    'section_incharge' => 'Creates Late IN / Early Out applications (department-wise).',
    'timeoffice'       => 'First approver after Section Incharge; owns plant masters (matrix, departments, contractors, notification mail).',
    'n1'               => 'Approver after Time Office (department-wise).',
    'hod'              => 'Plant HOD — final approval before Security.',
    'security'         => 'Closes applications at gate with a mandatory remark (one per plant).',
    'hr'               => 'Approves or rejects contractor reactivation (one per plant).',
];

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Roles</h2>
<p class="text-muted mb-4">
    LIEO roles are fixed. Assign people to roles in the <a href="approval_matrix.php">Approval Matrix</a>
    (login + default password emailed). LC/EG chain: Section Incharge creates → Time Office → N-1 → HOD → Security closes at gate.
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
