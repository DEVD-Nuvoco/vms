<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['hod']);

$plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
$empCode = (string) ($_SESSION['lieo_emp_code'] ?? '');
if (!lieo_is_hr_hod($empCode, $plant)) {
    http_response_code(403);
    die('Access denied — this page is only for the HR department HOD.');
}

$pageTitle = 'User Approval';
$activeNav = 'user_requests';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remark = trim($_POST['remark'] ?? '');
    $decision = $action === 'approve' ? 'Approved' : ($action === 'reject' ? 'Rejected' : '');
    if ($decision === '') {
        $_SESSION['lieo_mess'] = 'Invalid action.';
        $_SESSION['lieo_mess_type'] = 'danger';
    } else {
        $result = lieo_decide_user_request($requestId, $decision, (int) $_SESSION['lieo_user_id'], $remark);
        $_SESSION['lieo_mess'] = $result['message'] ?? ($result['ok'] ? 'Decision recorded.' : 'Could not process.');
        if (empty($result['ok'])) {
            $_SESSION['lieo_mess_type'] = 'danger';
        }
    }
    header('Location: user_requests.php');
    exit;
}

$pending = lieo_list_user_requests($plant, 'Pending');
$decided = array_merge(
    lieo_list_user_requests($plant, 'Approved'),
    lieo_list_user_requests($plant, 'Rejected')
);
usort($decided, static fn($a, $b) => strcmp((string) ($b['decided_at'] ?? ''), (string) ($a['decided_at'] ?? '')));
$decided = array_slice($decided, 0, 20);

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">User Approval</h2>
<p class="text-muted mb-4">
    Admin's create/update/delete requests for HOD, N-1 and Security roles in plant <strong><?= htmlspecialchars($plant) ?></strong>,
    awaiting your approval as HR department HOD.
</p>

<?php if (!$pending): ?>
<div class="alert alert-success">No pending user requests.</div>
<?php else: ?>
<div class="row">
    <?php foreach ($pending as $r): ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars(ucfirst((string) $r['request_type'])) ?> — <?= htmlspecialchars(lieo_role_label((string) $r['approval_step'])) ?></strong>
                <span class="badge badge-warning">Pending</span>
            </div>
            <?php
                $oldRow = !empty($r['target_matrix_id']) ? lieo_get_matrix_row((int) $r['target_matrix_id']) : null;
            ?>
            <div class="card-body">
                <?php if ($oldRow && $r['request_type'] === 'update'): ?>
                <ul class="mb-3 pl-3">
                    <?php if ((string) $r['approval_step'] !== (string) $oldRow['approval_step']): ?>
                    <li>Role: <strong><?= htmlspecialchars(lieo_step_label($oldRow['approval_step'])) ?></strong> → <strong><?= htmlspecialchars(lieo_step_label($r['approval_step'])) ?></strong></li>
                    <?php endif; ?>
                    <?php if (!lieo_dept_names_equal((string) $r['department'], (string) $oldRow['department'])): ?>
                    <li>Department: <strong><?= htmlspecialchars($oldRow['department']) ?></strong> → <strong><?= htmlspecialchars($r['department']) ?></strong></li>
                    <?php endif; ?>
                    <?php if (strcasecmp((string) $r['emp_email'], (string) $oldRow['emp_email']) !== 0): ?>
                    <li>Employee: <strong><?= htmlspecialchars($oldRow['emp_name']) ?> (<?= htmlspecialchars($oldRow['emp_email']) ?>)</strong> → <strong><?= htmlspecialchars($r['emp_name']) ?> (<?= htmlspecialchars($r['emp_email']) ?>)</strong></li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>
                <table class="table table-sm table-borderless mb-3">
                    <tr><th>Employee</th><td><?= htmlspecialchars($r['emp_name']) ?> (<?= htmlspecialchars($r['emp_code']) ?>)</td></tr>
                    <tr><th>Email</th><td><?= htmlspecialchars($r['emp_email']) ?></td></tr>
                    <tr><th>Plant / Dept</th><td><?= htmlspecialchars($r['plant']) ?> / <?= htmlspecialchars($r['department'] === 'All' ? 'All' : $r['department']) ?></td></tr>
                    <tr><th>Requested</th><td><?= htmlspecialchars($r['created_at']) ?></td></tr>
                </table>
                <form method="post">
                    <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                    <div class="form-group">
                        <label>Remark</label>
                        <textarea name="remark" class="form-control" rows="2" placeholder="Optional"></textarea>
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

<?php if ($decided): ?>
<h5 class="mt-4 mb-3">Recently decided</h5>
<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Type</th><th>Role</th><th>Employee</th><th>Decision</th><th>Decided</th></tr></thead>
            <tbody>
            <?php foreach ($decided as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst((string) $r['request_type'])) ?></td>
                    <td><?= htmlspecialchars(lieo_role_label((string) $r['approval_step'])) ?></td>
                    <td><?= htmlspecialchars($r['emp_name']) ?></td>
                    <td><?= lieo_status_badge((string) $r['status']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars((string) $r['decided_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
