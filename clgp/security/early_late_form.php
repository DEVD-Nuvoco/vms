<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['security']);

$pageTitle = 'Early Out / Late Entry Form';
$activeNav = 'attendance';

$passId = (int)($_GET['pass_id'] ?? 0);
$type = $_GET['type'] ?? 'Late Entry';
if (!in_array($type, ['Late Entry', 'Early Leaving'], true)) {
    $type = 'Late Entry';
}

$pass = clgp_find_pass($passId);
if (!$pass || $pass['status'] !== 'Active') {
    $_SESSION['clgp_mess'] = 'Invalid or inactive gate pass.';
    header('Location: attendance.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log = [
        'type' => $_POST['entry_type'] ?? $type,
        'date' => date('Y-m-d'),
        'reason' => trim($_POST['reason'] ?? ''),
        'sig_workman' => trim($_POST['sig_workman'] ?? ''),
        'sig_superior' => trim($_POST['sig_superior'] ?? ''),
        'sig_hod' => trim($_POST['sig_hod'] ?? ''),
        'recorded_by' => clgp_role_label($_SESSION['clgp_role']),
        'recorded_on' => date('Y-m-d H:i:s'),
    ];
    clgp_add_attendance_log($passId, $log);

    foreach ($_SESSION['clgp_passes'] as &$p) {
        if ((int)$p['id'] !== $passId) {
            continue;
        }
        if ($log['type'] === 'Late Entry') {
            $p['late_in'] = true;
        } else {
            $p['early_out'] = true;
        }
        break;
    }
    unset($p);

    $_SESSION['clgp_mess'] = $log['type'] . ' form saved with signatures.';
    header('Location: attendance.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-2">Early Out / Late Entry Form</h2>
<p class="text-muted mb-4">Capture reason and signature names when a workman arrives late or leaves early.</p>

<?php $pass = clgp_normalize_pass($pass); include __DIR__ . '/../includes/pass_detail_card.php'; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold">Entry Details</div>
    <div class="card-body">
        <form method="post">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Entry Type *</label>
                    <select name="entry_type" class="form-control" required>
                        <option <?= $type === 'Late Entry' ? 'selected' : '' ?>>Late Entry</option>
                        <option <?= $type === 'Early Leaving' ? 'selected' : '' ?>>Early Leaving</option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label>Shift</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($pass['shift']) ?>" readonly>
                </div>
            </div>

            <div class="form-group">
                <label>Reason for Early Leaving / Late Entry *</label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Enter reason..."></textarea>
            </div>

            <h6 class="text-success mt-3 mb-3">Signature of (Name)</h6>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Workman *</label>
                    <input type="text" name="sig_workman" class="form-control" value="<?= htmlspecialchars($pass['workman']) ?>" required>
                    <small class="text-muted">Printed name of workman</small>
                </div>
                <div class="form-group col-md-4">
                    <label>Reporting Superior (N+1) *</label>
                    <input type="text" name="sig_superior" class="form-control" placeholder="Superior name" required>
                </div>
                <div class="form-group col-md-4">
                    <label>HOD *</label>
                    <input type="text" name="sig_hod" class="form-control" placeholder="HOD name" required>
                </div>
            </div>

            <div class="row text-center mb-4">
                <?php foreach (['Workman', 'Reporting Superior', 'HOD'] as $label): ?>
                <div class="col-md-4">
                    <div class="border rounded p-3 bg-light" style="min-height:100px;">
                        <small class="text-muted"><?= $label ?> — Signature</small>
                        <div class="mt-4 border-top border-dark mx-3"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-clgp">Save & Submit</button>
            <a href="attendance.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
