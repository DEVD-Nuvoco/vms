<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);

$pageTitle = 'Department Master';
$activeNav = 'departments';
$plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
        $result = lieo_save_plant_department($plant, $_POST['department_name'] ?? '', $id);
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
        lieo_delete_plant_department((int) ($_POST['id'] ?? 0), $plant);
        $_SESSION['lieo_mess'] = 'Department deleted.';
    } elseif ($action === 'deactivate') {
        lieo_set_plant_department_status((int) ($_POST['id'] ?? 0), $plant, 'Inactive');
        $_SESSION['lieo_mess'] = 'Department deactivated.';
    } elseif ($action === 'activate') {
        lieo_set_plant_department_status((int) ($_POST['id'] ?? 0), $plant, 'Active');
        $_SESSION['lieo_mess'] = 'Department activated.';
    }
    header('Location: departments.php');
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$rows = lieo_list_plant_departments($plant);
$editRow = null;
foreach ($rows as $r) {
    if ((int) $r['dept_id'] === $editId) {
        $editRow = $r;
        break;
    }
}
$amsHint = lieo_list_ams_departments($plant);

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Department Master — <?= htmlspecialchars($plant) ?></h2>
<p class="text-muted mb-3">
    If this plant has departments here, they are used in the matrix and applications.
    If the list is empty, LIEO falls back to AMS departments
    (<?= count($amsHint) ?> AMS departments currently).
</p>

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
.lieo-ams-dept-chips {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
}
.lieo-ams-dept-chip {
    display: inline-block;
    padding: .28rem .7rem;
    border-radius: 999px;
    font-size: .8125rem;
    font-weight: 600;
    color: #166534;
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
    line-height: 1.3;
}
.lieo-ams-dept-ref .hint {
    font-size: .75rem;
    color: #94a3b8;
    margin: .55rem 0 0;
}
</style>
<div class="lieo-ams-dept-ref">
    <h6>AMS departments already on <?= htmlspecialchars($plant) ?> (reference)</h6>
    <div class="lieo-ams-dept-chips">
        <?php foreach ($amsHint as $amsDept): ?>
            <span class="lieo-ams-dept-chip"><?= htmlspecialchars($amsDept) ?></span>
        <?php endforeach; ?>
    </div>
    <p class="hint mb-0">These come from AMS for this plant. Add only extra departments here that are not already in AMS.</p>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="post" class="form-inline">
            <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int) $editRow['dept_id'] ?>"><?php endif; ?>
            <label class="mr-2">Department name</label>
            <input type="text" name="department_name" class="form-control mr-2" required
                   value="<?= htmlspecialchars($editRow['department_name'] ?? '') ?>">
            <button class="btn btn-lieo"><?= $editRow ? 'Update' : 'Add' ?></button>
            <?php if ($editRow): ?><a href="departments.php" class="btn btn-link">Cancel</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm py-3">
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Department</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="3" class="text-muted text-center">No plant departments yet — AMS list is used.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['department_name']) ?></td>
                    <td><?= lieo_status_badge($r['status']) ?></td>
                    <td class="text-nowrap">
                        <a href="?edit=<?= (int) $r['dept_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (($r['status'] ?? '') === 'Active'): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Deactivate this department? It will no longer be available for matrix and applications."
                              data-lieo-confirm-title="Deactivate department"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Deactivate">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="id" value="<?= (int) $r['dept_id'] ?>">
                            <button class="btn btn-sm btn-outline-warning">Deactivate</button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="id" value="<?= (int) $r['dept_id'] ?>">
                            <button class="btn btn-sm btn-outline-success">Activate</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Delete this department permanently from the list?"
                              data-lieo-confirm-title="Delete department"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Delete">
                            <input type="hidden" name="action" value="delete">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
