<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['n1', 'hod']);

$pageTitle = lieo_role_label($_SESSION['lieo_role']) . ' — Approvals';
$activeNav = 'pending';
$role = $_SESSION['lieo_role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int) ($_POST['pass_id'] ?? $_POST['application_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remark = trim($_POST['remark'] ?? '');

    if ($action === 'reject' && $remark === '') {
        $_SESSION['lieo_mess'] = 'Please enter remarks before rejecting.';
        $_SESSION['lieo_mess_type'] = 'danger';
        header('Location: pending.php');
        exit;
    }

    $before = lieo_get_application($appId);
    $result = lieo_advance_application(
        $appId,
        $role,
        $action,
        $remark,
        [
            'lieo_user_id' => (int) $_SESSION['lieo_user_id'],
            'emp_code' => $_SESSION['lieo_emp_code'] ?? '',
            'full_name' => $_SESSION['lieo_user_name'] ?? '',
        ]
    );

    if ($result['ok']) {
        $appNo = $before['application_no'] ?? ('#' . $appId);
        if (($result['status'] ?? '') === 'Rejected') {
            $_SESSION['lieo_mess'] = 'Rejected: ' . $appNo . ' has been rejected.';
            $_SESSION['lieo_mess_type'] = 'warning';
        } elseif (($result['status'] ?? '') === 'Approved') {
            $_SESSION['lieo_mess'] = 'Success! ' . $appNo . ' approved — ready for Security at gate.';
            $_SESSION['lieo_mess_type'] = 'success';
        } else {
            $next = $result['status'] ?? 'next step';
            $_SESSION['lieo_mess'] = 'Success! ' . $appNo . ' approved — moved to ' . str_replace('_', ' ', $next) . '.';
            $_SESSION['lieo_mess_type'] = 'success';
        }
    } else {
        $_SESSION['lieo_mess'] = $result['message'] ?? 'Could not process.';
        $_SESSION['lieo_mess_type'] = 'danger';
    }
    header('Location: pending.php');
    exit;
}

$hodDepts = [];
$deptFilter = '';
if ($role === 'hod') {
    $hodDepts = lieo_list_matrix_departments_for_user(
        (string) ($_SESSION['lieo_emp_code'] ?? ''),
        (string) ($_SESSION['lieo_plant'] ?? ''),
        'hod'
    );
    $deptFilter = trim($_GET['dept'] ?? '');
    if ($deptFilter !== '' && !in_array($deptFilter, $hodDepts, true)) {
        $deptFilter = '';
    }
}
$pendingFilters = ['pending_for_role' => $role];
if ($deptFilter !== '') {
    $pendingFilters['department'] = $deptFilter;
}
$pending = lieo_list_applications(lieo_apply_session_plant_scope($pendingFilters));

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2"><?= htmlspecialchars(lieo_step_label($role)) ?> Approvals</h2>
<p class="text-muted mb-4">
    Late IN / Early Out requests waiting for your action. Chain: N-1 creates → HOD approves → Security closes at gate.
</p>

<?php if (count($hodDepts) > 1): ?>
<div class="mb-3">
    <a href="pending.php" class="btn btn-sm <?= $deptFilter === '' ? 'btn-lieo' : 'btn-outline-secondary' ?>">All departments</a>
    <?php foreach ($hodDepts as $d): ?>
    <a href="pending.php?dept=<?= urlencode($d) ?>" class="btn btn-sm <?= $deptFilter === $d ? 'btn-lieo' : 'btn-outline-secondary' ?>"><?= htmlspecialchars($d) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($pending)): ?>
<div class="alert alert-success">No pending items for your role.</div>
<?php else: ?>
<div class="row">
    <?php foreach ($pending as $p): ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars($p['application_no']) ?></strong>
                <?= lieo_status_badge($p['status']) ?>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-3">
                    <tr><th>Type</th><td><?= htmlspecialchars(lieo_application_type_label($p['application_type'] ?? '')) ?></td></tr>
                    <tr><th>Workman Name</th><td><?= htmlspecialchars($p['workman_name']) ?></td></tr>
                    <tr><th>Workman ID Code</th><td><?= htmlspecialchars($p['workman_code']) ?></td></tr>
                    <tr><th>Contractor</th><td><?= htmlspecialchars($p['contractor_name']) ?></td></tr>
                    <tr><th>Department</th><td><?= htmlspecialchars($p['department']) ?></td></tr>
                    <tr><th>Shift</th><td><?= htmlspecialchars($p['shift'] ?: '—') ?></td></tr>
                    <tr><th>Plant</th><td><?= htmlspecialchars($p['plant']) ?></td></tr>
                    <tr><th>Reason</th><td><?= htmlspecialchars($p['reason']) ?></td></tr>
                </table>
                <form method="post">
                    <input type="hidden" name="application_id" value="<?= (int)$p['application_id'] ?>">
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remark" class="form-control" rows="2" placeholder="Optional / required on reject"></textarea>
                    </div>
                    <button type="submit" name="action" value="approve" class="btn btn-lieo">Approve</button>
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger">Reject</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
