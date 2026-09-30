<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['hod']);

$plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
$empCode = (string) ($_SESSION['lieo_emp_code'] ?? '');
if (!lieo_is_hr_hod($empCode, $plant)) {
    http_response_code(403);
    die('Access denied — this page is only for the HR department HOD.');
}

$pageTitle = 'Department Requests';
$activeNav = 'department_requests';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remark = trim($_POST['remark'] ?? '');
    $decision = $action === 'approve' ? 'Approved' : ($action === 'reject' ? 'Rejected' : '');
    if ($decision === '') {
        $_SESSION['lieo_mess'] = 'Invalid action.';
        $_SESSION['lieo_mess_type'] = 'danger';
    } elseif ($remark === '') {
        $_SESSION['lieo_mess'] = 'Please enter a remark before approving or rejecting.';
        $_SESSION['lieo_mess_type'] = 'danger';
    } else {
        $result = lieo_decide_department_request($requestId, $decision, (int) $_SESSION['lieo_user_id'], $remark);
        $_SESSION['lieo_mess'] = $result['message'] ?? ($result['ok'] ? 'Decision recorded.' : 'Could not process.');
        if (empty($result['ok'])) {
            $_SESSION['lieo_mess_type'] = 'danger';
        }
    }
    header('Location: department_requests.php');
    exit;
}

$pending = lieo_list_department_requests($plant, 'Pending');
$decided = array_merge(
    lieo_list_department_requests($plant, 'Approved'),
    lieo_list_department_requests($plant, 'Rejected')
);
usort($decided, static fn($a, $b) => strcmp((string) ($b['decided_at'] ?? ''), (string) ($a['decided_at'] ?? '')));
$decided = array_slice($decided, 0, 20);

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Department Requests</h2>
<p class="text-muted mb-4">
    Admin's add/update requests for Department Master in plant <strong><?= htmlspecialchars($plant) ?></strong>,
    awaiting your approval as HR department HOD.
</p>

<?php if (!$pending): ?>
<div class="alert alert-success">No pending department requests.</div>
<?php else: ?>
<div class="row">
    <?php foreach ($pending as $r): ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= $r['request_type'] === 'edit' ? 'Update' : 'Add' ?> — <?= htmlspecialchars((string) $r['department_name']) ?></strong>
                <span class="badge badge-warning">Pending</span>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-3">
                    <tr><th>Plant</th><td><?= htmlspecialchars((string) $r['plant']) ?></td></tr>
                    <tr><th>Department</th><td><?= htmlspecialchars((string) $r['department_name']) ?></td></tr>
                    <tr><th>HR department?</th><td><?= $r['is_hr'] ? 'Yes' : 'No' ?></td></tr>
                    <tr><th>Requested</th><td><?= htmlspecialchars((string) $r['created_at']) ?></td></tr>
                </table>
                <form method="post">
                    <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                    <div class="form-group">
                        <label>Remark <span class="text-danger">*</span></label>
                        <textarea name="remark" class="form-control" rows="2" required placeholder="Enter remark"></textarea>
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
            <thead><tr><th>Type</th><th>Department</th><th>Decision</th><th>Decided</th></tr></thead>
            <tbody>
            <?php foreach ($decided as $r): ?>
                <tr>
                    <td><?= $r['request_type'] === 'edit' ? 'Update' : 'Add' ?></td>
                    <td><?= htmlspecialchars((string) $r['department_name']) ?></td>
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
