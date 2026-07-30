<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['timeoffice']);

$pageTitle = 'Create Application';
$activeNav = 'create';

$workmen = clgp_list_workmen('Active');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = clgp_create_application([
        'workman_id' => (int) ($_POST['workman_id'] ?? 0),
        'application_type' => $_POST['application_type'] ?? '',
        'reason' => $_POST['reason'] ?? '',
        'created_by' => (int) $_SESSION['clgp_user_id'],
    ]);
    if ($result['ok']) {
        $_SESSION['clgp_mess'] = 'Success! Application ' . $result['application_no']
            . ' submitted. It is now pending Supervisor approval.';
        $_SESSION['clgp_mess_type'] = 'success';
    } else {
        $_SESSION['clgp_mess'] = $result['message'] ?: 'Could not create application.';
        $_SESSION['clgp_mess_type'] = 'danger';
    }
    header('Location: create_application.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-2">Create Late Coming / Early Going</h2>
<p class="text-muted mb-4">Create request on workman’s behalf. Approval: Supervisor → N-1 → HOD, then Security closes at gate.</p>

<div class="card shadow-sm" style="max-width:640px;">
    <div class="card-body">
        <form method="post">
            <div class="form-group">
                <label>Workman *</label>
                <select name="workman_id" id="workman_id" class="form-control" required>
                    <option value="">— Select workman —</option>
                    <?php foreach ($workmen as $w): ?>
                    <option value="<?= (int)$w['workman_id'] ?>"
                        data-code="<?= htmlspecialchars($w['workman_code']) ?>"
                        data-contractor="<?= htmlspecialchars($w['vendor_name']) ?>"
                        data-plant="<?= htmlspecialchars($w['plant']) ?>"
                        data-dept="<?= htmlspecialchars($w['department']) ?>"
                        data-shift="<?= htmlspecialchars($w['shift']) ?>">
                        <?= htmlspecialchars($w['workman_name'] . ' (' . $w['workman_code'] . ')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Workman ID Code</label>
                    <input type="text" id="f_code" class="form-control" readonly>
                </div>
                <div class="form-group col-md-6">
                    <label>Contractor / Vendor</label>
                    <input type="text" id="f_contractor" class="form-control" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Plant</label>
                    <input type="text" id="f_plant" class="form-control" readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Department</label>
                    <input type="text" id="f_dept" class="form-control" readonly>
                </div>
                <div class="form-group col-md-4">
                    <label>Shift</label>
                    <input type="text" id="f_shift" class="form-control" readonly>
                </div>
            </div>
            <div class="form-group">
                <label>Application Type *</label>
                <select name="application_type" class="form-control" required>
                    <option value="Late Coming">Late Coming (IN / Entry)</option>
                    <option value="Early Going">Early Going (OUT / Exit)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason *</label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Reason for late coming / early going"></textarea>
            </div>
            <button type="submit" class="btn btn-clgp">Submit for Approval</button>
        </form>
    </div>
</div>

<script>
document.getElementById('workman_id').addEventListener('change', function () {
    var o = this.options[this.selectedIndex];
    document.getElementById('f_code').value = o.getAttribute('data-code') || '';
    document.getElementById('f_contractor').value = o.getAttribute('data-contractor') || '';
    document.getElementById('f_plant').value = o.getAttribute('data-plant') || '';
    document.getElementById('f_dept').value = o.getAttribute('data-dept') || '';
    document.getElementById('f_shift').value = o.getAttribute('data-shift') || '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
