<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['contractor']);

$pageTitle = 'Create Gate Pass';
$activeNav = 'create';

global $LIEO_PLANTS, $LIEO_DEPARTMENTS;
$LIEO_SHIFTS = array_column(lieo_list_shifts(), 'shift_name');

$contractorName = 'ABC Contractors Pvt Ltd';
$vendorType = 'Outsider';
foreach ($_SESSION['lieo_contractors'] as $c) {
    if (strtolower($c['email']) === strtolower($_SESSION['lieo_user_email'])) {
        $contractorName = $c['name'];
        $vendorType = $c['type'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = max(array_column($_SESSION['lieo_passes'], 'id')) + 1;
    $firstStep = $vendorType === 'Outsider' ? 'n1' : 'hod';
    $_SESSION['lieo_passes'][] = [
        'id' => $id,
        'workman' => trim($_POST['workman_name'] ?? ''),
        'workman_id_code' => trim($_POST['workman_id_code'] ?? ''),
        'contractor' => $contractorName,
        'vendor_type' => $vendorType,
        'plant' => $_POST['plant'] ?? '',
        'department' => $_POST['department'] ?? '',
        'shift' => $_POST['shift'] ?? '',
        'pool' => $_POST['pool'] ?? 'Temporary',
        'status' => 'Pending_' . $firstStep,
        'current_step' => $firstStep,
        'created' => date('Y-m-d'),
        'expiry' => '',
        'aadhaar' => trim($_POST['aadhaar'] ?? ''),
        'documents' => ['Aadhaar', 'PAN', 'Form 21', 'ESIC/WC', 'Photo'],
        'attendance_logs' => [],
    ];
    $_SESSION['lieo_mess'] = 'Gate pass request #' . $id . ' submitted. Awaiting ' . lieo_step_label($firstStep) . '.';
    header('Location: my_passes.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-4">Create Gate Pass Request</h2>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="alert alert-info small">
                        Contractor: <strong><?= htmlspecialchars($contractorName) ?></strong> |
                        Vendor type: <strong><?= htmlspecialchars($vendorType) ?></strong>
                        <?php if ($vendorType === 'Outsider'): ?>
                        — requires <strong>N+1 approval</strong> first (configured by Admin).
                        <?php endif; ?>
                    </div>

                    <h6 class="text-success">Gate Pass Details</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Workman Name *</label>
                            <input type="text" name="workman_name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Workman ID Code *</label>
                            <input type="text" name="workman_id_code" class="form-control" placeholder="e.g. WM-NIM-2026-001" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Name of Contractor</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($contractorName) ?>" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Aadhaar Number *</label>
                            <input type="text" name="aadhaar" class="form-control" maxlength="12" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Department *</label>
                            <select name="department" class="form-control" required>
                                <?php foreach ($LIEO_DEPARTMENTS as $d): ?>
                                <option><?= htmlspecialchars($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Shift *</label>
                            <select name="shift" class="form-control" required>
                                <?php foreach ($LIEO_SHIFTS as $sh): ?>
                                <option><?= htmlspecialchars($sh) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Plant *</label>
                            <select name="plant" class="form-control" required>
                                <?php foreach ($LIEO_PLANTS as $pl): ?>
                                <option><?= htmlspecialchars($pl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Pool Type *</label>
                            <select name="pool" class="form-control">
                                <option>Regular</option>
                                <option selected>Temporary</option>
                                <option>Service Engineer</option>
                                <option>Project</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-success mt-3">Supporting Documents (Insider / Outsider)</h6>
                    <div class="form-row">
                        <?php foreach (['Aadhaar Card','PAN Card','Bank Passbook','Form 21 Medical','ESIC/WC','UAN Card','Recent Photo'] as $doc): ?>
                        <div class="form-group col-md-6">
                            <label><?= htmlspecialchars($doc) ?></label>
                            <input type="file" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-lieo">Submit for Approval</button>
                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold">Approval Path</div>
            <div class="card-body small">
                <?php
                $chain = $vendorType === 'Outsider'
                    ? ['n1','hod','safety','hr','security','medical','timeoffice']
                    : ['hod','safety','hr','security','medical','timeoffice'];
                foreach ($chain as $i => $step):
                ?>
                <div class="mb-1"><?= ($i+1) ?>. <?= lieo_step_label($step) ?></div>
                <?php endforeach; ?>
                <hr>
                <p class="mb-0 text-muted">Pass validity after issue: <strong>6 months</strong></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
