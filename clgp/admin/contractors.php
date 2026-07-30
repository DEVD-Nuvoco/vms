<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);

$pageTitle = 'Contractors';
$activeNav = 'contractors';
global $CLGP_VENDOR_TYPES;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
        $result = clgp_save_contractor($_POST, $id);
        $_SESSION['clgp_mess'] = $result['ok'] ? 'Contractor saved.' : $result['message'];
    } elseif ($action === 'deactivate') {
        clgp_deactivate_contractor((int) ($_POST['id'] ?? 0), trim($_POST['reason'] ?? ''));
        $_SESSION['clgp_mess'] = 'Contractor deactivated.';
    } elseif ($action === 'request_reactivation') {
        clgp_request_reactivation((int) ($_POST['id'] ?? 0));
        $_SESSION['clgp_mess'] = 'Reactivation requested — pending HR Head approval.';
    }
    header('Location: contractors.php');
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId ? clgp_get_contractor($editId) : null;
$contractors = clgp_list_contractors();
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-4">Contractor Master</h2>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold"><?= $editRow ? 'Edit Contractor' : 'Add Contractor' ?></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
                    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['contractor_id'] ?>"><?php endif; ?>
                    <div class="form-group">
                        <label>Vendor Name *</label>
                        <input type="text" name="vendor_name" class="form-control" required
                               value="<?= htmlspecialchars($editRow['vendor_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contractor Name *</label>
                        <input type="text" name="contractor_name" class="form-control" required
                               value="<?= htmlspecialchars($editRow['contractor_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Vendor Type *</label>
                        <select name="vendor_type" class="form-control" required>
                            <?php foreach ($CLGP_VENDOR_TYPES as $t): ?>
                            <option <?= (($editRow['vendor_type'] ?? '') === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Supervisor (Contact Person) *</label>
                        <input type="text" name="supervisor_name" class="form-control" required
                               value="<?= htmlspecialchars($editRow['supervisor_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email ID *</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($editRow['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contractor Mobile *</label>
                        <input type="text" name="contractor_mobile" class="form-control" required
                               value="<?= htmlspecialchars($editRow['contractor_mobile'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Supervisor Mobile *</label>
                        <input type="text" name="supervisor_mobile" class="form-control" required
                               value="<?= htmlspecialchars($editRow['supervisor_mobile'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-clgp btn-block">Save</button>
                    <?php if ($editRow): ?><a href="contractors.php" class="btn btn-link btn-block">Cancel edit</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-bordered clgp-datatable">
                    <thead>
                        <tr>
                            <th>Vendor</th><th>Contractor</th><th>Type</th><th>Supervisor</th>
                            <th>Email</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contractors as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['vendor_name']) ?></td>
                            <td><?= htmlspecialchars($c['contractor_name']) ?></td>
                            <td><?= htmlspecialchars($c['vendor_type']) ?></td>
                            <td><?= htmlspecialchars($c['supervisor_name']) ?></td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td>
                                <?= clgp_status_badge($c['status']) ?>
                                <?php if ($c['reactivation_requested'] === 't'): ?>
                                    <span class="badge badge-info">Reactivation pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <a href="?edit=<?= (int)$c['contractor_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <?php if ($c['status'] === 'Active'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Deactivate this contractor?')">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="id" value="<?= (int)$c['contractor_id'] ?>">
                                    <input type="hidden" name="reason" value="Admin deactivated">
                                    <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                                </form>
                                <?php elseif ($c['reactivation_requested'] !== 't'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="request_reactivation">
                                    <input type="hidden" name="id" value="<?= (int)$c['contractor_id'] ?>">
                                    <button class="btn btn-sm btn-outline-success">Request Reactivation</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
