<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);

$pageTitle = 'Shift Master';
$activeNav = 'shifts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
        $result = lieo_save_shift($_POST, $id);
        $_SESSION['lieo_mess'] = $result['ok'] ? 'Shift saved.' : ($result['message'] ?? 'Could not save shift.');
        if (empty($result['ok'])) {
            $_SESSION['lieo_mess_type'] = 'danger';
        }
    } elseif ($action === 'deactivate') {
        lieo_set_shift_status((int) ($_POST['id'] ?? 0), 'Inactive');
        $_SESSION['lieo_mess'] = 'Shift deactivated.';
    } elseif ($action === 'activate') {
        lieo_set_shift_status((int) ($_POST['id'] ?? 0), 'Active');
        $_SESSION['lieo_mess'] = 'Shift activated.';
    } elseif ($action === 'delete') {
        lieo_delete_shift((int) ($_POST['id'] ?? 0));
        $_SESSION['lieo_mess'] = 'Shift deleted.';
    }
    header('Location: shifts.php');
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId ? lieo_get_shift($editId) : null;
$shifts = lieo_list_all_shifts();
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Shift Master</h2>
<p class="text-muted mb-4">
    Shifts listed here populate the Shift dropdown on Create Application and Create Gate Pass.
    Deactivate a shift to hide it from those forms without losing history on past applications.
</p>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold"><?= $editRow ? 'Edit Shift' : 'Add Shift' ?></div>
    <div class="card-body">
        <form method="post" id="shiftForm">
            <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int) $editRow['shift_id'] ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Shift Name *</label>
                    <input type="text" name="shift_name" class="form-control" required placeholder="e.g. Shift M (09:00–18:00)"
                           value="<?= htmlspecialchars($editRow['shift_name'] ?? '') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Start Time *</label>
                    <input type="time" name="start_time" class="form-control" required
                           value="<?= htmlspecialchars($editRow['start_time'] ?? '') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>End Time *</label>
                    <input type="time" name="end_time" class="form-control" required
                           value="<?= htmlspecialchars($editRow['end_time'] ?? '') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Late Grace (min)</label>
                    <input type="number" name="late_grace_minutes" class="form-control" min="0"
                           value="<?= htmlspecialchars((string) ($editRow['late_grace_minutes'] ?? 15)) ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Early Grace (min)</label>
                    <input type="number" name="early_grace_minutes" class="form-control" min="0"
                           value="<?= htmlspecialchars((string) ($editRow['early_grace_minutes'] ?? 0)) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-lieo"><?= $editRow ? 'Update Shift' : 'Save Shift' ?></button>
            <?php if ($editRow): ?><a href="shifts.php" class="btn btn-link">Cancel edit</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold">Shifts</div>
    <div class="card-body p-0">
        <table class="table mb-0 lieo-datatable">
            <thead>
                <tr>
                    <th>Shift Name</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Late Grace</th>
                    <th>Early Grace</th>
                    <th>Status</th>
                    <th class="text-nowrap">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$shifts): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">No shifts yet. Add one above.</td></tr>
            <?php endif; ?>
            <?php foreach ($shifts as $s): ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($s['shift_name']) ?></td>
                    <td><?= htmlspecialchars(substr((string) $s['start_time'], 0, 5)) ?></td>
                    <td><?= htmlspecialchars(substr((string) $s['end_time'], 0, 5)) ?></td>
                    <td><?= (int) $s['late_grace_minutes'] ?> min</td>
                    <td><?= (int) $s['early_grace_minutes'] ?> min</td>
                    <td><?= lieo_status_badge($s['status']) ?></td>
                    <td class="text-nowrap">
                        <a href="?edit=<?= (int) $s['shift_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if ($s['status'] === 'Active'): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Deactivate this shift? It will no longer appear on Create Application / Create Gate Pass."
                              data-lieo-confirm-title="Deactivate shift"
                              data-lieo-confirm-ok="Deactivate">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="id" value="<?= (int) $s['shift_id'] ?>">
                            <button class="btn btn-sm btn-outline-warning">Deactivate</button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="id" value="<?= (int) $s['shift_id'] ?>">
                            <button class="btn btn-sm btn-outline-success">Activate</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Delete this shift permanently? Past applications keep their stored shift text, but this entry will no longer be selectable."
                              data-lieo-confirm-title="Delete shift"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Delete">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $s['shift_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('shiftForm').addEventListener('submit', function (e) {
    var start = this.start_time.value, end = this.end_time.value;
    if (start && end && start === end) {
        e.preventDefault();
        if (window.lieoAlert) lieoAlert({ title: 'Invalid times', message: 'Start and end time cannot be the same.' });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
