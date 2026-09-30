<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['n1']);

$pageTitle = 'Create Application';
$activeNav = 'create';

$LIEO_SHIFTS = array_column(lieo_list_shifts(), 'shift_name');

$userPlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
$userDept = lieo_session_n1_department();
$contractors = lieo_list_contractors('Active', $userPlant);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = lieo_create_application([
        'workman_name' => $_POST['workman_name'] ?? '',
        'workman_code' => $_POST['workman_code'] ?? '',
        'contractor_id' => (int) ($_POST['contractor_id'] ?? 0),
        'plant' => $userPlant,
        'department' => $userDept,
        'shift' => $_POST['shift'] ?? '',
        'application_type' => $_POST['application_type'] ?? '',
        'reason' => $_POST['reason'] ?? '',
        'created_by' => (int) $_SESSION['lieo_user_id'],
        'actor_emp_code' => $_SESSION['lieo_emp_code'] ?? '',
    ]);
    if ($result['ok']) {
        $_SESSION['lieo_mess'] = 'Success! Application ' . $result['application_no']
            . ' submitted. It is now pending HOD approval.';
        $_SESSION['lieo_mess_type'] = 'success';
    } else {
        $_SESSION['lieo_mess'] = $result['message'] ?: 'Could not create application.';
        $_SESSION['lieo_mess_type'] = 'danger';
    }
    header('Location: create_application.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Create Late IN / Early Out</h2>
<p class="text-muted mb-4">
    Plant <strong><?= htmlspecialchars($userPlant) ?></strong>
    / Department <strong><?= htmlspecialchars($userDept) ?></strong>.
    Approval: HOD approves, then Security closes at gate.
</p>

<?php if ($userPlant === '' || $userDept === '' || $userDept === 'All'): ?>
<div class="alert alert-warning">Your matrix assignment must include a plant and department before you can create applications.</div>
<?php else: ?>
<div class="card shadow-sm" style="max-width:720px;">
    <div class="card-body">
        <form method="post" autocomplete="off">
            <h6 class="text-success mb-3">Workman details</h6>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Workman Name *</label>
                    <input type="text" name="workman_name" class="form-control" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Workman ID Code *</label>
                    <input type="text" name="workman_code" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Contractor *</label>
                <select name="contractor_id" class="form-control" required>
                    <option value="">— Select contractor —</option>
                    <?php foreach ($contractors as $c): ?>
                    <option value="<?= (int) $c['contractor_id'] ?>">
                        <?= htmlspecialchars($c['contractor_name']) ?>
                        (<?= htmlspecialchars($c['contractor_type'] ?? '') ?>)
                        <?php if (!empty($c['supervisor_name'])): ?>
                        — Supervisor: <?= htmlspecialchars($c['supervisor_name']) ?>
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Shift *</label>
                <select name="shift" class="form-control" required>
                    <option value="">— Select shift —</option>
                    <?php foreach ($LIEO_SHIFTS as $s): ?>
                    <option><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <hr>
            <h6 class="text-success mb-3">Application</h6>
            <div class="form-group">
                <label>Application Type *</label>
                <select name="application_type" class="form-control" required>
                    <option value="Late Coming">Late IN (Entry)</option>
                    <option value="Early Going">Early Out (Exit)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason *</label>
                <textarea name="reason" class="form-control" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-lieo">Submit for Approval</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
