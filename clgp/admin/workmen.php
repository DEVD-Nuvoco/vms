<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);

$pageTitle = 'Workmen';
$activeNav = 'workmen';
global $CLGP_PLANTS, $CLGP_DEPARTMENTS, $CLGP_SHIFTS;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $result = clgp_save_workman($_POST);
    $_SESSION['clgp_mess'] = $result['ok'] ? 'Workman added.' : $result['message'];
    header('Location: workmen.php');
    exit;
}

$workmen = clgp_list_workmen(null);
$contractors = clgp_list_contractors('Active');
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-4">Workman Master</h2>
<p class="text-muted">Time Office selects workmen from this list when creating Late IN / Early Out applications.</p>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold">Add Workman</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Workman ID Code *</label>
                        <input type="text" name="workman_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Workman Name *</label>
                        <input type="text" name="workman_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Contractor *</label>
                        <select name="contractor_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach ($contractors as $c): ?>
                            <option value="<?= (int)$c['contractor_id'] ?>"><?= htmlspecialchars($c['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plant *</label>
                        <select name="plant" class="form-control" required>
                            <?php foreach ($CLGP_PLANTS as $pl): ?><option><?= htmlspecialchars($pl) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department" class="form-control" required>
                            <?php foreach ($CLGP_DEPARTMENTS as $d): ?><option><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Shift</label>
                        <select name="shift" class="form-control">
                            <?php foreach ($CLGP_SHIFTS as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-clgp btn-block">Save</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-bordered clgp-datatable">
                    <thead>
                        <tr><th>Code</th><th>Name</th><th>Vendor</th><th>Plant</th><th>Dept</th><th>Shift</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workmen as $w): ?>
                        <tr>
                            <td><?= htmlspecialchars($w['workman_code']) ?></td>
                            <td><?= htmlspecialchars($w['workman_name']) ?></td>
                            <td><?= htmlspecialchars($w['vendor_name']) ?></td>
                            <td><?= htmlspecialchars($w['plant']) ?></td>
                            <td><?= htmlspecialchars($w['department']) ?></td>
                            <td><?= htmlspecialchars($w['shift']) ?></td>
                            <td><?= clgp_status_badge($w['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
