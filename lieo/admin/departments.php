<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);

$pageTitle = 'Department Master';
$activeNav = 'departments';

$plant = lieo_ams_canonical_plant($_GET['plant'] ?? $_POST['plant'] ?? '');
$filterPlant = lieo_ams_canonical_plant($_GET['filter_plant'] ?? '');
$amsPlants = lieo_list_ams_plants();

function lieo_admin_departments_url(string $plant = '', string $filterPlant = '', int $editId = 0): string
{
    $q = [];
    if ($plant !== '') {
        $q['plant'] = $plant;
    }
    if ($filterPlant !== '') {
        $q['filter_plant'] = $filterPlant;
    }
    if ($editId > 0) {
        $q['edit'] = (string) $editId;
    }
    return 'departments.php' . ($q ? ('?' . http_build_query($q)) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postPlant = lieo_ams_canonical_plant($_POST['plant'] ?? $plant);
    $postFilter = lieo_ams_canonical_plant($_POST['filter_plant'] ?? $filterPlant);

    if ($postPlant === '') {
        $_SESSION['lieo_mess'] = 'Select a plant first.';
        $_SESSION['lieo_mess_type'] = 'danger';
    } elseif ($action === 'add' || $action === 'edit') {
        $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
        $result = lieo_save_plant_department($postPlant, $_POST['department_name'] ?? '', $id);
        if (!empty($result['ok'])) {
            $_SESSION['lieo_mess'] = 'Department saved.';
            $_SESSION['lieo_mess_type'] = 'success';
        } else {
            $msg = (string) ($result['message'] ?? 'Save failed.');
            $_SESSION['lieo_mess'] = $msg;
            $_SESSION['lieo_mess_type'] = 'danger';
            $_SESSION['lieo_alert'] = [
                'title' => 'Cannot add department',
                'message' => $msg,
                'variant' => 'danger',
            ];
        }
    } elseif ($action === 'delete') {
        lieo_delete_plant_department((int) ($_POST['id'] ?? 0), $postPlant);
        $_SESSION['lieo_mess'] = 'Department deleted.';
    } elseif ($action === 'deactivate') {
        lieo_set_plant_department_status((int) ($_POST['id'] ?? 0), $postPlant, 'Inactive');
        $_SESSION['lieo_mess'] = 'Department deactivated.';
    } elseif ($action === 'activate') {
        lieo_set_plant_department_status((int) ($_POST['id'] ?? 0), $postPlant, 'Active');
        $_SESSION['lieo_mess'] = 'Department activated.';
    }

    header('Location: ' . lieo_admin_departments_url($postPlant, $postFilter));
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$rows = $plant !== '' ? lieo_list_plant_departments($plant) : [];
$editRow = null;
foreach ($rows as $r) {
    if ((int) $r['dept_id'] === $editId) {
        $editRow = $r;
        break;
    }
}
$amsHint = $plant !== '' ? lieo_list_ams_departments($plant) : [];
$timeofficeRows = $plant !== '' ? lieo_list_timeoffice_matrix_for_plant($plant) : [];
$overviewRows = lieo_list_all_plant_departments($filterPlant !== '' ? $filterPlant : null);

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Department Master</h2>
<p class="text-muted mb-4">
    Add extra departments plant-wise when an employee’s AMS department is missing — for example before assigning
    <strong>Time Office</strong> in the Approval Matrix. AMS departments are always included automatically;
    entries here are add-ons only.
</p>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold">Select plant</div>
    <div class="card-body">
        <form method="get" class="form-inline flex-wrap" id="plantPickForm">
            <?php if ($filterPlant !== ''): ?>
                <input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>">
            <?php endif; ?>
            <label class="mr-2 mb-2">Plant</label>
            <select name="plant" class="form-control mr-2 mb-2" required onchange="this.form.submit()">
                <option value="">— Choose plant —</option>
                <?php foreach ($amsPlants as $pl): ?>
                    <option value="<?= htmlspecialchars($pl) ?>" <?= $plant === $pl ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pl) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-lieo mb-2" type="submit">Open</button></noscript>
        </form>
    </div>
</div>

<?php if ($plant === ''): ?>
<div class="alert alert-light border">
    Select a plant above to view AMS departments, add extras, and see Time Office assignments for that plant.
</div>
<?php else: ?>

<?php if ($amsHint): ?>
<style>
.lieo-ams-dept-ref {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: .85rem 1rem;
    margin-bottom: 1.25rem;
}
.lieo-ams-dept-ref h6 {
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: #64748b;
    margin: 0 0 .65rem;
}
.lieo-ams-dept-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
.lieo-ams-dept-chip {
    display: inline-block;
    padding: .28rem .7rem;
    border-radius: 999px;
    font-size: .8125rem;
    font-weight: 600;
    color: #166534;
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
}
.lieo-ams-dept-ref .hint { font-size: .75rem; color: #94a3b8; margin: .55rem 0 0; }
</style>
<div class="lieo-ams-dept-ref">
    <h6>AMS departments on <?= htmlspecialchars($plant) ?> (reference)</h6>
    <div class="lieo-ams-dept-chips">
        <?php foreach ($amsHint as $amsDept): ?>
            <span class="lieo-ams-dept-chip"><?= htmlspecialchars($amsDept) ?></span>
        <?php endforeach; ?>
    </div>
    <p class="hint mb-0"><?= count($amsHint) ?> AMS departments. Add only names not already listed here.</p>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold">Add extra department — <?= htmlspecialchars($plant) ?></div>
    <div class="card-body">
        <form method="post" class="form-inline flex-wrap">
            <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int) $editRow['dept_id'] ?>"><?php endif; ?>
            <label class="mr-2 mb-2">Department name</label>
            <input type="text" name="department_name" class="form-control mr-2 mb-2" required
                   value="<?= htmlspecialchars($editRow['department_name'] ?? '') ?>">
            <button class="btn btn-lieo mb-2"><?= $editRow ? 'Update' : 'Add' ?></button>
            <?php if ($editRow): ?>
                <a href="<?= htmlspecialchars(lieo_admin_departments_url($plant, $filterPlant)) ?>" class="btn btn-link mb-2">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center flex-wrap">
        <span>Time Office assigned — <?= htmlspecialchars($plant) ?></span>
        <a href="approval_matrix.php?plant=<?= rawurlencode($plant) ?>" class="btn btn-sm btn-outline-success">
            Assign Time Office
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Emp Code</th>
                    <th>Name</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$timeofficeRows): ?>
                <tr>
                    <td colspan="4" class="text-muted text-center py-3">
                        No Time Office assigned yet for this plant.
                        <a href="approval_matrix.php?plant=<?= rawurlencode($plant) ?>">Assign now</a>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($timeofficeRows as $to): ?>
                <tr>
                    <td><?= htmlspecialchars($to['department'] === 'All' ? 'All departments' : ($to['department'] ?? '')) ?></td>
                    <td class="font-weight-bold text-danger"><?= htmlspecialchars($to['emp_code'] ?? '') ?></td>
                    <td><?= htmlspecialchars($to['emp_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($to['emp_email'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-4 py-3">
    <div class="card-header bg-white font-weight-bold">Extra departments — <?= htmlspecialchars($plant) ?></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Department</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="3" class="text-muted text-center">No extra departments for this plant.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['department_name']) ?></td>
                    <td><?= lieo_status_badge($r['status']) ?></td>
                    <td class="text-nowrap">
                        <a href="<?= htmlspecialchars(lieo_admin_departments_url($plant, $filterPlant, (int) $r['dept_id'])) ?>"
                           class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (($r['status'] ?? '') === 'Active'): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Deactivate this department?"
                              data-lieo-confirm-title="Deactivate department"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Deactivate">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
                            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
                            <input type="hidden" name="id" value="<?= (int) $r['dept_id'] ?>">
                            <button class="btn btn-sm btn-outline-warning">Deactivate</button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
                            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
                            <input type="hidden" name="id" value="<?= (int) $r['dept_id'] ?>">
                            <button class="btn btn-sm btn-outline-success">Activate</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Delete this department permanently?"
                              data-lieo-confirm-title="Delete department"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Delete">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
                            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
                            <input type="hidden" name="id" value="<?= (int) $r['dept_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center flex-wrap">
        <span>All extra departments (overview)</span>
        <form method="get" class="form-inline mt-2 mt-md-0">
            <?php if ($plant !== ''): ?>
                <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
            <?php endif; ?>
            <label class="mr-2 mb-0 small">Filter plant</label>
            <select name="filter_plant" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All plants</option>
                <?php foreach ($amsPlants as $pl): ?>
                    <option value="<?= htmlspecialchars($pl) ?>" <?= $filterPlant === $pl ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pl) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-sm btn-lieo" type="submit">Filter</button></noscript>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0 lieo-datatable">
            <thead>
                <tr>
                    <th>Plant</th>
                    <th>Department</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$overviewRows): ?>
                <tr><td colspan="3" class="text-muted text-center py-4">No extra plant departments recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($overviewRows as $r): ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars(lieo_ams_canonical_plant($r['plant'] ?? '') ?: ($r['plant'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($r['department_name'] ?? '') ?></td>
                    <td><?= lieo_status_badge($r['status'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
