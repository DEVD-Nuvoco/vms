<?php
/**
 * Shared role dashboard — stats + quick actions + pending + today's list.
 * Expects: $date, $stats; optional $pending, $roleTitle, $showCreate, $recentApps
 */
$role = (string) ($_SESSION['lieo_role'] ?? '');
$roleTitle = $roleTitle ?? lieo_role_label($role);
$showCreate = !empty($showCreate);
$pending = is_array($pending ?? null) ? $pending : [];
$plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
$dept = trim((string) ($_SESSION['lieo_department'] ?? ''));

if (!isset($recentApps) || !is_array($recentApps)) {
    $recentApps = lieo_list_applications(lieo_apply_session_plant_scope(['date' => $date]));
}
$recentApps = array_slice($recentApps, 0, 10);

$pendingUrl = match ($role) {
    'n1', 'hod' => 'pending.php',
    default => 'applications.php',
};
$trackUrl = 'applications.php';
$historyUrl = 'history.php';

$quickActions = [];
if ($role === 'timeoffice') {
    $quickActions = [
        ['label' => 'Application Tracking', 'url' => 'applications.php', 'icon' => 'typcn-th-list', 'primary' => true],
        ['label' => 'My History', 'url' => 'history.php', 'icon' => 'typcn-time'],
    ];
} elseif ($role === 'n1') {
    $quickActions = [
        ['label' => 'Create Application', 'url' => 'create_application.php', 'icon' => 'typcn-document-add', 'primary' => true],
        ['label' => 'Application Tracking', 'url' => 'applications.php', 'icon' => 'typcn-th-list'],
        ['label' => 'My History', 'url' => 'history.php', 'icon' => 'typcn-time'],
    ];
} elseif ($role === 'hod') {
    $quickActions = [
        ['label' => 'Pending Approvals', 'url' => 'pending.php', 'icon' => 'typcn-tick-outline', 'primary' => true, 'badge' => (int) ($stats['pending_mine'] ?? 0)],
        ['label' => 'Application Tracking', 'url' => 'applications.php', 'icon' => 'typcn-th-list'],
        ['label' => 'My History', 'url' => 'history.php', 'icon' => 'typcn-time'],
    ];
    if (function_exists('lieo_is_hr_hod') && lieo_is_hr_hod((string) ($_SESSION['lieo_emp_code'] ?? ''), $plant)) {
        // Admin's HOD/N-1/Security role-assignment requests — a separate
        // pending queue from the application stats above, so it needs its
        // own badge or a pending one is easy to miss on this dashboard.
        $pendingUserRequests = function_exists('lieo_list_user_requests') ? count(lieo_list_user_requests($plant, 'Pending')) : 0;
        $quickActions[] = ['label' => 'User Approval', 'url' => 'user_requests.php', 'icon' => 'typcn-user-add-outline', 'badge' => $pendingUserRequests];
        $quickActions[] = ['label' => 'Plant Users', 'url' => 'plant_users.php', 'icon' => 'typcn-group-outline'];
    }
}
if ($role === 'n1') {
    $showCreate = true;
}

$workflowHint = match ($role) {
    'timeoffice' => 'View-only tracking role for your plant (HR department, plant-specific). Not part of the approval chain.',
    'n1' => 'Create Late IN / Early Out applications for your department. HOD approves next, then Security closes at gate.',
    'hod' => 'Approve applications created by N-1 for your department(s). After you approve, Security closes at gate with remark and time.',
    default => 'Late IN / Early Out (LIEO) workflow for your plant.',
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <div>
        <h2 class="lieo-title mb-1"><?= htmlspecialchars($roleTitle) ?> Dashboard</h2>
        <p class="text-muted mb-0 small">
            Counts for <strong><?= htmlspecialchars($date) ?></strong>
            <?php if ($plant !== ''): ?> · Plant <strong><?= htmlspecialchars($plant) ?></strong><?php endif; ?>
            <?php if ($dept !== '' && $dept !== 'All'): ?> · Dept <strong><?= htmlspecialchars($dept) ?></strong><?php endif; ?>
        </p>
    </div>
    <form method="get" class="form-inline mt-2 mt-md-0">
        <label class="mr-2 mb-0">Date</label>
        <input type="date" name="date" class="form-control form-control-sm mr-2" value="<?= htmlspecialchars($date) ?>">
        <button class="btn btn-sm btn-lieo">Filter</button>
    </form>
</div>

<div class="alert alert-light border mb-4 py-2 px-3 small mb-3">
    <strong class="text-success">Your role:</strong> <?= htmlspecialchars($workflowHint) ?>
</div>

<div class="row mb-4">
    <?php
    $statCards = [
        ['Pending (mine)', $stats['pending_mine'], 'warning', $pendingUrl],
        ['Created / listed', $stats['created'], 'success', $trackUrl],
        ['Approved (HOD done)', $stats['approved'], 'info', $trackUrl],
        ['Rejected', $stats['rejected'], 'danger', $trackUrl],
        ['Gate completed', $stats['gate_completed'], 'success', $trackUrl],
        ['Any pending in scope', $stats['pending'], 'secondary', $trackUrl],
    ];
    foreach ($statCards as [$label, $val, $color, $href]):
    ?>
    <div class="col-6 col-md-4 col-lg-2 mb-3">
        <a href="<?= htmlspecialchars($href) ?>" class="text-decoration-none">
            <div class="card card-stat shadow-sm h-100 lieo-dash-stat">
                <div class="card-body py-3">
                    <h6 class="text-muted text-uppercase small mb-1"><?= htmlspecialchars($label) ?></h6>
                    <h3 class="mb-0 text-<?= htmlspecialchars($color) ?>"><?= (int) $val ?></h3>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($quickActions): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold">Quick actions</div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($quickActions as $qa): ?>
            <div class="col-6 col-md-4 col-lg-3 mb-2">
                <a href="<?= htmlspecialchars($qa['url']) ?>"
                   class="btn btn-block text-left <?= !empty($qa['primary']) ? 'btn-lieo' : 'btn-outline-secondary' ?> lieo-dash-action">
                    <i class="typcn <?= htmlspecialchars($qa['icon']) ?> mr-1"></i>
                    <?= htmlspecialchars($qa['label']) ?>
                    <?php if (!empty($qa['badge'])): ?>
                        <span class="badge badge-light text-dark ml-1"><?= (int) $qa['badge'] ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center">
                <span>Waiting for you</span>
                <a href="<?= htmlspecialchars($pendingUrl) ?>" class="small">Open pending →</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$pending): ?>
                <div class="p-4 text-center text-muted">
                    <div class="mb-2" style="font-size:1.75rem;opacity:.45;"><i class="typcn typcn-tick-outline"></i></div>
                    <div class="font-weight-bold text-dark">Nothing pending for you</div>
                    <div class="small mt-1">
                        <?php if ($showCreate): ?>
                            Create a new Late IN / Early Out application when needed.
                        <?php else: ?>
                            New items appear here when N-1 creates an application for your department.
                        <?php endif; ?>
                    </div>
                    <?php if ($showCreate): ?>
                    <a href="create_application.php" class="btn btn-sm btn-lieo mt-3">Create Application</a>
                    <?php elseif ($role === 'hod'): ?>
                    <a href="<?= htmlspecialchars($pendingUrl) ?>" class="btn btn-sm btn-outline-secondary mt-3">Go to Pending</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Workman</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($pending, 0, 8) as $a): ?>
                            <tr>
                                <td class="font-weight-bold small"><?= htmlspecialchars($a['application_no'] ?? '') ?></td>
                                <td class="small"><?= htmlspecialchars($a['workman_name'] ?? '') ?></td>
                                <td class="small"><?= htmlspecialchars(lieo_application_type_label($a['application_type'] ?? '')) ?></td>
                                <td><?= lieo_status_badge($a['status'] ?? '') ?></td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-lieo" href="<?= htmlspecialchars($pendingUrl) ?>">Action</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center">
                <span>Today’s applications</span>
                <a href="<?= htmlspecialchars($trackUrl) ?>" class="small">View all →</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$recentApps): ?>
                <div class="p-4 text-center text-muted">
                    <div class="mb-2" style="font-size:1.75rem;opacity:.45;"><i class="typcn typcn-document-text"></i></div>
                    <div class="font-weight-bold text-dark">No applications on this date</div>
                    <div class="small mt-1">Try another date filter, or create / wait for new applications.</div>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Workman</th>
                                <th>Dept</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentApps as $a): ?>
                            <tr>
                                <td class="font-weight-bold small"><?= htmlspecialchars($a['application_no'] ?? '') ?></td>
                                <td class="small">
                                    <?= htmlspecialchars($a['workman_name'] ?? '') ?>
                                    <span class="text-muted d-block"><?= htmlspecialchars($a['workman_code'] ?? '') ?></span>
                                </td>
                                <td class="small"><?= htmlspecialchars($a['department'] ?? '') ?></td>
                                <td><?= lieo_status_badge($a['status'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-2">
    <div class="card-header bg-white font-weight-bold">LIEO process</div>
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center lieo-dash-flow small">
            <?php
            $steps = [
                'n1' => 'N-1 (creates)',
                'hod' => 'HOD (approves)',
                'security' => 'Security (Gate)',
            ];
            $i = 0;
            foreach ($steps as $key => $label):
                $active = ($key === $role);
            ?>
                <?php if ($i++ > 0): ?><span class="text-muted mx-2">→</span><?php endif; ?>
                <span class="badge <?= $active ? 'badge-success' : 'badge-light border' ?> px-2 py-2">
                    <?= htmlspecialchars($label) ?><?= $active ? ' (you)' : '' ?>
                </span>
            <?php endforeach; ?>
        </div>
        <p class="text-muted small mb-0 mt-2">
            Security closes at gate with mandatory remark and actual enter/leave date-time.
            <a href="<?= htmlspecialchars($historyUrl) ?>">View your history</a>
        </p>
    </div>
</div>

<style>
.lieo-dash-stat { transition: box-shadow .15s ease, transform .15s ease; }
.lieo-dash-stat:hover { box-shadow: 0 .35rem .9rem rgba(15,23,42,.12) !important; transform: translateY(-1px); }
.lieo-dash-action { white-space: normal; min-height: 42px; display: flex; align-items: center; }
.lieo-dash-flow .badge { font-weight: 600; font-size: .75rem; }
</style>
