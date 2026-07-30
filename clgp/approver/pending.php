<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['supervisor', 'n1', 'hod']);

$pageTitle = clgp_role_label($_SESSION['clgp_role']) . ' — Approvals';
$activeNav = 'pending';
$role = $_SESSION['clgp_role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int) ($_POST['pass_id'] ?? $_POST['application_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remark = trim($_POST['remark'] ?? '');

    if ($action === 'reject' && $remark === '') {
        $_SESSION['clgp_mess'] = 'Please enter remarks before rejecting.';
        $_SESSION['clgp_mess_type'] = 'danger';
        header('Location: pending.php');
        exit;
    }

    $before = clgp_get_application($appId);
    $result = clgp_advance_application(
        $appId,
        $role,
        $action,
        $remark,
        [
            'clgp_user_id' => (int) $_SESSION['clgp_user_id'],
            'emp_code' => $_SESSION['clgp_emp_code'] ?? '',
            'full_name' => $_SESSION['clgp_user_name'] ?? '',
        ]
    );

    if ($result['ok']) {
        $appNo = $before['application_no'] ?? ('#' . $appId);
        if (($result['status'] ?? '') === 'Rejected') {
            $_SESSION['clgp_mess'] = 'Rejected: ' . $appNo . ' has been rejected.';
            $_SESSION['clgp_mess_type'] = 'warning';
        } elseif (($result['status'] ?? '') === 'Approved') {
            $_SESSION['clgp_mess'] = 'Success! ' . $appNo . ' approved — ready for Security at gate.';
            $_SESSION['clgp_mess_type'] = 'success';
        } else {
            $next = $result['status'] ?? 'next step';
            $_SESSION['clgp_mess'] = 'Success! ' . $appNo . ' approved — moved to ' . str_replace('_', ' ', $next) . '.';
            $_SESSION['clgp_mess_type'] = 'success';
        }
    } else {
        $_SESSION['clgp_mess'] = $result['message'] ?? 'Could not process.';
        $_SESSION['clgp_mess_type'] = 'danger';
    }
    header('Location: pending.php');
    exit;
}

$pending = clgp_list_applications(clgp_apply_session_plant_scope(['pending_for_role' => $role]));

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-2"><?= htmlspecialchars(clgp_step_label($role)) ?> Approvals</h2>
<p class="text-muted mb-4">
    Late Coming / Early Going requests waiting for your action. Chain: Supervisor → N-1 → HOD.
    <a href="history.php">View my history (full remark trail)</a>
</p>

<?php if (empty($pending)): ?>
<div class="alert alert-success">No pending items for your role.</div>
<?php else: ?>
<div class="row">
    <?php foreach ($pending as $p): ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars($p['application_no']) ?></strong>
                <?= clgp_status_badge($p['status']) ?>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-3">
                    <tr><th>Type</th><td><?= htmlspecialchars($p['application_type']) ?></td></tr>
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
                    <button type="submit" name="action" value="approve" class="btn btn-clgp">Approve</button>
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger">Reject</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
