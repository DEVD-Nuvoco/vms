<?php
require_once __DIR__ . '/../config.php';
if (!defined('LIEO_MATRIX_PAGE')) {
    lieo_require_role(['admin']);
    define('LIEO_MATRIX_PAGE', 1);
    global $LIEO_ADMIN_MATRIX_STEPS;
    $lieoMatrixStepKeys = $LIEO_ADMIN_MATRIX_STEPS;
    $lieoMatrixLockPlant = false;
}

$pageTitle = 'Approval Matrix';
$activeNav = 'matrix';
global $LIEO_APPROVAL_STEPS, $LIEO_MATRIX_PLANT_ROLES;

$lieoMatrixStepKeys = $lieoMatrixStepKeys ?? $LIEO_ADMIN_MATRIX_STEPS;
$lieoMatrixLockPlant = !empty($lieoMatrixLockPlant);
$lieoMatrixSteps = [];
foreach ($lieoMatrixStepKeys as $k) {
    $lieoMatrixSteps[$k] = $LIEO_APPROVAL_STEPS[$k] ?? lieo_step_label($k);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'edit') {
            $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
            $result = lieo_save_matrix_rule([
                'plant' => $_POST['plant'] ?? '',
                'department' => $_POST['department'] ?? '',
                'approval_step' => $_POST['approval_step'] ?? '',
                'emp_code' => $_POST['emp_code'] ?? '',
                'emp_name' => $_POST['emp_name'] ?? '',
                'emp_email' => $_POST['emp_email'] ?? '',
            ], $id);
            $flashMsg = trim((string) ($result['message'] ?? ''));
            $_SESSION['lieo_mess'] = $result['ok']
                ? ($flashMsg !== '' ? $flashMsg : 'Approval rule saved.')
                : ($flashMsg !== '' ? $flashMsg : 'Could not save approval rule.');
            if (!$result['ok']) {
                $_SESSION['lieo_mess_type'] = 'danger';
            }
        } elseif ($action === 'delete') {
            lieo_delete_matrix_rule((int) ($_POST['id'] ?? 0));
            $_SESSION['lieo_mess'] = 'Rule deactivated.';
        } elseif ($action === 'resend') {
            $result = lieo_resend_matrix_credentials((int) ($_POST['id'] ?? 0));
            $_SESSION['lieo_mess'] = $result['message'] ?? ($result['ok'] ? 'Credentials resent.' : 'Resend failed.');
        } elseif ($action === 'email_test') {
            // Local WAMP only — never expose email test on live server.
            if (!lieo_is_test_mail_mode()) {
                $_SESSION['lieo_mess'] = 'Email test is only available in local development.';
                $_SESSION['lieo_mess_type'] = 'danger';
            } else {
                $testId = (int) ($_POST['id'] ?? 0);
                // Re-open last preview when local=1 and no specific row requested.
                if ($testId < 1 && lieo_restore_last_test_mails()) {
                    $_SESSION['lieo_mess'] = 'Showing last LIEO email test preview.';
                } else {
                    $result = lieo_matrix_email_test($testId);
                    $_SESSION['lieo_mess'] = $result['message'] ?? 'Email test done.';
                    if (empty($result['ok'])) {
                        $_SESSION['lieo_mess_type'] = 'danger';
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('approval_matrix POST: ' . $e->getMessage());
        $_SESSION['lieo_mess'] = 'Save failed: ' . $e->getMessage();
    }
    $redir = 'approval_matrix.php';
    $vp = trim((string) ($_POST['view_plant'] ?? $_GET['plant'] ?? ''));
    if ($vp !== '') {
        $redir .= '?plant=' . rawurlencode($vp);
    }
    // Ensure queued test mails are written before redirect (PRG).
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . $redir);
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$allMatrix = lieo_list_matrix();
$allowedSteps = $lieoMatrixStepKeys;
$allMatrix = array_values(array_filter($allMatrix, static function ($r) use ($allowedSteps) {
    return in_array($r['approval_step'] ?? '', $allowedSteps, true);
}));
if ($lieoMatrixLockPlant) {
    $sessionPlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
    $allMatrix = array_values(array_filter($allMatrix, static function ($r) use ($sessionPlant) {
        return lieo_ams_canonical_plant($r['plant'] ?? '') === $sessionPlant;
    }));
}
$plantsInMatrix = array_values(array_unique(array_column($allMatrix, 'plant')));
sort($plantsInMatrix);
$viewPlant = $_GET['plant'] ?? '';
if ($lieoMatrixLockPlant) {
    $viewPlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
}
if ($viewPlant !== '' && !$lieoMatrixLockPlant && !in_array($viewPlant, $plantsInMatrix, true)) {
    $viewPlant = '';
}
$matrixRows = $allMatrix;
if ($viewPlant !== '') {
    $matrixRows = array_values(array_filter($allMatrix, static function ($r) use ($viewPlant) {
        return $r['plant'] === $viewPlant;
    }));
}
$editRow = null;
foreach ($allMatrix as $r) {
    if ((int) $r['matrix_id'] === $editId) {
        $editRow = $r;
        break;
    }
}

$editPlant = $editRow['plant'] ?? '';
if ($lieoMatrixLockPlant) {
    $editPlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? $editPlant);
}
$editDept = $editRow['department'] ?? '';
$editDepts = $editPlant !== '' ? lieo_list_departments_for_plant($editPlant) : [];

$lieoMatrixAssignments = array_map(static function (array $r): array {
    return [
        'matrix_id' => (int) ($r['matrix_id'] ?? 0),
        'plant' => lieo_ams_canonical_plant($r['plant'] ?? ''),
        'department' => (string) ($r['department'] ?? ''),
        'approval_step' => (string) ($r['approval_step'] ?? ''),
        'emp_code' => (string) ($r['emp_code'] ?? ''),
        'emp_name' => (string) ($r['emp_name'] ?? ''),
        'emp_email' => (string) ($r['emp_email'] ?? ''),
    ];
}, $allMatrix);

$lieoDeptMasterBase = $_SESSION['lieo_role'] === 'admin'
    ? lieo_nav_url('admin', 'departments.php')
    : lieo_nav_url('timeoffice', 'departments.php');

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Approval Matrix</h2>
<p class="text-muted mb-4">
    <?php if ($_SESSION['lieo_role'] === 'admin'): ?>
        Admin assigns <strong>Time Office</strong> only (department-wise). Time Office maintains Section Incharge, N-1, HOD, Security and HR Head.
    <?php else: ?>
        Assign <strong>Section Incharge</strong> and <strong>N-1</strong> by department, and <strong>HOD / Security / HR Head</strong> once per plant.
        You cannot assign Time Office. Flow: Section Incharge creates → Time Office → N-1 → HOD → Security at gate.
        <strong>LIEO Department</strong> (for approvals) may differ from the employee&apos;s AMS department — pick the employee by plant, then choose the LIEO department before save.
    <?php endif; ?>
    Saving creates or updates the LIEO login — credentials are emailed; the user must change password on first sign-in.
</p>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold text-success"><?= $editRow ? 'Edit Assignment' : 'Add Role Assignment' ?></div>
    <div class="card-body">
        <form method="post" id="matrixForm" autocomplete="off">
            <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['matrix_id'] ?>"><?php endif; ?>
            <?php if ($viewPlant !== ''): ?><input type="hidden" name="view_plant" value="<?= htmlspecialchars($viewPlant) ?>"><?php endif; ?>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Plant * <small class="text-muted">(search AMS)</small></label>
                    <input type="text" id="plantSearch" class="form-control" placeholder="Type plant e.g. JCP"
                           value="<?= htmlspecialchars($editPlant) ?>" autocomplete="off" required
                           <?= $lieoMatrixLockPlant ? 'readonly' : '' ?>>
                    <input type="hidden" name="plant" id="plant" value="<?= htmlspecialchars($editPlant) ?>" required>
                    <div id="plantResults" class="list-group mt-1" style="max-height:180px;overflow:auto;display:none;position:relative;z-index:30;"></div>
                </div>
                <div class="form-group col-md-4" id="departmentGroup">
                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap">
                        <label class="mb-0">LIEO Department *</label>
                        <span class="text-nowrap">
                            <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="lieoToggleAllDepts">Show all departments</button>
                            <span class="text-muted mx-1">·</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-success font-weight-bold" id="lieoAddDeptOpen">
                                + Add Department(s)
                            </button>
                        </span>
                    </div>
                    <select name="department" id="department" class="form-control" required <?= $editPlant === '' ? 'disabled' : '' ?>>
                        <option value="">— Select plant first —</option>
                        <?php foreach ($editDepts as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $editDept === $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="lieoAllDeptsBox" class="lieo-all-depts-box" style="display:none;">
                        <div class="lieo-all-depts-head">
                            <span>All departments for this plant</span>
                            <span id="lieoAllDeptsCount" class="text-muted"></span>
                        </div>
                        <div id="lieoAllDeptsList" class="lieo-all-depts-list"></div>
                    </div>
                    <small class="text-muted d-block mt-1">
                        AMS departments are included automatically. Use <strong>Add Department(s)</strong> for extras.
                        <a href="<?= htmlspecialchars($lieoDeptMasterBase) ?>" id="lieoDeptMasterLink">Manage in Department Master</a>
                    </small>
                    <input type="hidden" name="department" id="departmentAll" value="All" disabled>
                </div>
                <div class="form-group col-md-4">
                    <label>Role *</label>
                    <select name="approval_step" id="approval_step" class="form-control" required>
                        <?php foreach ($lieoMatrixSteps as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (($editRow['approval_step'] ?? '') === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted" id="roleHint">HOD, Security and HR Head: pick plant only — one assignee per plant.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label>Employee (AMS) *</label>
                    <div id="empPickerCard" class="lieo-emp-picker <?= !empty($editRow['emp_code']) ? 'has-selection' : '' ?>">
                        <div id="empPickerSelected" class="lieo-emp-picker-selected" <?= empty($editRow['emp_code']) ? 'style="display:none"' : '' ?>>
                            <div class="lieo-emp-avatar" aria-hidden="true"><?php
                                $n = trim((string) ($editRow['emp_name'] ?? 'E'));
                                echo htmlspecialchars(strtoupper($n !== '' ? substr($n, 0, 1) : 'E'));
                            ?></div>
                            <div class="lieo-emp-meta">
                                <div class="lieo-emp-name" id="empDisplayName"><?= htmlspecialchars($editRow['emp_name'] ?? '') ?></div>
                                <div class="lieo-emp-code" id="empDisplayCode"><?= htmlspecialchars($editRow['emp_code'] ?? '') ?></div>
                                <div class="lieo-emp-email" id="empDisplayEmail"><?= htmlspecialchars($editRow['emp_email'] ?? '') ?></div>
                                <div class="lieo-emp-ams-dept text-muted small" id="empDisplayAmsDept" style="display:none"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-danger px-1" id="empClearBtn" title="Clear selection">Clear</button>
                        </div>
                        <button type="button" class="lieo-emp-open-btn" id="empBrowseBtn"
                                <?= $editPlant === '' ? 'disabled' : '' ?>>
                            <span class="lieo-emp-open-icon" aria-hidden="true"><i class="typcn typcn-zoom-outline"></i></span>
                            <span class="lieo-emp-open-text" id="empBrowseLabel">
                                <?= !empty($editRow['emp_code']) ? 'Change employee' : 'Click here to search &amp; select employee' ?>
                            </span>
                        </button>
                        <small class="text-muted d-block mt-1" id="empCountHint">Select plant first, then pick an employee (all AMS departments for that plant).</small>
                    </div>
                    <input type="hidden" name="emp_code" id="emp_code" required value="<?= htmlspecialchars($editRow['emp_code'] ?? '') ?>">
                    <input type="hidden" name="emp_name" id="emp_name" required value="<?= htmlspecialchars($editRow['emp_name'] ?? '') ?>">
                    <input type="hidden" name="emp_email" id="emp_email" value="<?= htmlspecialchars($editRow['emp_email'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4 d-flex align-items-end">
                    <div class="w-100">
                        <button type="submit" class="btn btn-lieo btn-block">Save &amp; Provision Login</button>
                        <?php if ($editRow): ?><a href="approval_matrix.php<?= $viewPlant !== '' ? ('?plant=' . rawurlencode($viewPlant)) : '' ?>" class="btn btn-link btn-block">Cancel</a><?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 font-weight-bold">
        <?php if (!empty($lieoMatrixLockPlant) && $viewPlant !== ''): ?>
            Configured for <?= htmlspecialchars($viewPlant) ?>
        <?php else: ?>
            Configured by plant
        <?php endif; ?>
    </h5>
    <?php if ($plantsInMatrix && empty($lieoMatrixLockPlant)): ?>
    <form method="get" class="form-inline">
        <?php if ($editId): ?><input type="hidden" name="edit" value="<?= $editId ?>"><?php endif; ?>
        <label class="mr-2 small text-muted">Plant</label>
        <select name="plant" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="">All plants</option>
            <?php foreach ($plantsInMatrix as $pl): ?>
            <option value="<?= htmlspecialchars($pl) ?>" <?= $viewPlant === $pl ? 'selected' : '' ?>><?= htmlspecialchars($pl) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if (!$matrixRows): ?>
<div class="alert alert-light border">No assignments yet. Add a role above.</div>
<?php if (lieo_is_test_mail_mode()): ?>
<div class="d-flex justify-content-end mt-2">
    <form method="post" class="m-0">
        <input type="hidden" name="action" value="email_test">
        <?php if ($viewPlant !== ''): ?><input type="hidden" name="view_plant" value="<?= htmlspecialchars($viewPlant) ?>"><?php endif; ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Email test</button>
    </form>
</div>
<?php endif; ?>
<?php else: ?>
<div class="card shadow-sm">
    <div class="card-body p-0 pt-3">
        <table class="table table-bordered lieo-datatable mb-0">
            <thead>
                <tr>
                    <th>Plant</th>
                    <th>Dept</th>
                    <th>Role</th>
                    <th>Emp Code</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matrixRows as $row): ?>
                <?php
                    $canResend = !empty($row['lieo_user_id'])
                        && ($row['user_status'] ?? '') === 'Active'
                        && ($row['must_change_password'] ?? 'f') === 't';
                ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($row['plant']) ?></td>
                    <td><?= htmlspecialchars($row['department'] === 'All' ? 'All departments' : $row['department']) ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars(lieo_step_label($row['approval_step'])) ?></span></td>
                    <td><?= htmlspecialchars($row['emp_code']) ?></td>
                    <td><?= htmlspecialchars($row['emp_name']) ?></td>
                    <td><?= htmlspecialchars($row['emp_email']) ?></td>
                    <td class="text-nowrap">
                        <a href="?edit=<?= (int)$row['matrix_id'] ?><?= $viewPlant ? '&plant=' . urlencode($viewPlant) : '' ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if ($canResend): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Resend login credentials email to <?= htmlspecialchars($row['emp_email'], ENT_QUOTES) ?>?"
                              data-lieo-confirm-title="Resend credentials"
                              data-lieo-confirm-ok="Resend">
                            <input type="hidden" name="action" value="resend">
                            <input type="hidden" name="id" value="<?= (int)$row['matrix_id'] ?>">
                            <?php if ($viewPlant !== ''): ?><input type="hidden" name="view_plant" value="<?= htmlspecialchars($viewPlant) ?>"><?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Resend credentials email">Resend</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Remove this user assignment? This cannot be undone from here."
                              data-lieo-confirm-title="Remove user"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Remove">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$row['matrix_id'] ?>">
                            <?php if ($viewPlant !== ''): ?><input type="hidden" name="view_plant" value="<?= htmlspecialchars($viewPlant) ?>"><?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Remove">×</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (lieo_is_test_mail_mode()): ?>
        <div class="d-flex justify-content-end px-3 py-3 border-top bg-white">
            <form method="post" class="m-0">
                <input type="hidden" name="action" value="email_test">
                <?php if ($viewPlant !== ''): ?><input type="hidden" name="view_plant" value="<?= htmlspecialchars($viewPlant) ?>"><?php endif; ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Preview or send a LIEO test email">Email test</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
.lieo-emp-picker {
    border: 1px solid var(--lieo-border, #e2e8f0);
    border-radius: 10px;
    background: #fff;
    padding: 12px;
}
.lieo-emp-picker.has-selection {
    border-color: rgba(66, 187, 82, 0.45);
    background: linear-gradient(180deg, #f7fdf8 0%, #fff 55%);
}
.lieo-emp-picker-selected {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 10px;
}
.lieo-emp-avatar {
    flex: 0 0 40px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--lieo-green, #42bb52);
    color: #fff;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lieo-emp-meta { flex: 1; min-width: 0; }
.lieo-emp-name { font-weight: 700; color: #0f172a; line-height: 1.25; }
.lieo-emp-code { font-size: 12px; color: #64748b; }
.lieo-emp-email { font-size: 12px; color: #64748b; word-break: break-all; }
.lieo-emp-open-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 46px;
    padding: 10px 12px;
    border: 1.5px dashed #94a3b8;
    border-radius: 8px;
    background: #f8fafc;
    color: #334155;
    font-size: 14px;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    transition: border-color .15s, background .15s, box-shadow .15s;
}
.lieo-emp-open-btn:hover:not(:disabled),
.lieo-emp-open-btn:focus:not(:disabled) {
    border-color: var(--lieo-green, #42bb52);
    border-style: solid;
    background: #f0faf2;
    color: #166534;
    box-shadow: 0 0 0 3px rgba(66, 187, 82, 0.15);
    outline: none;
}
.lieo-emp-open-btn:disabled {
    cursor: not-allowed;
    opacity: 0.75;
    color: #94a3b8;
    background: #f1f5f9;
}
.lieo-emp-open-icon {
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.lieo-emp-open-btn:not(:disabled) .lieo-emp-open-icon {
    background: rgba(66, 187, 82, 0.15);
    color: var(--lieo-green-dark, #38a644);
}
.lieo-emp-open-text { flex: 1; line-height: 1.3; }
.lieo-emp-modal-search {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    padding-bottom: 10px;
}
.lieo-emp-table tbody tr {
    cursor: pointer;
}
.lieo-emp-table tbody tr:hover {
    background: #f0faf2;
}
.lieo-emp-table tbody tr.is-selected {
    background: #e6f7ea;
}
.lieo-emp-table td { vertical-align: middle; }
.lieo-emp-row-name { font-weight: 600; color: #0f172a; }
.lieo-emp-row-email { font-size: 12px; color: #64748b; }
.lieo-emp-ams-dept { font-size: 12px; margin-top: 2px; }
.lieo-emp-row-dept { font-size: 12px; color: #475569; }
#lieoEmpBrowseModal { z-index: 2000 !important; }
#lieoEmpBrowseModal .modal-dialog,
#lieoEmpBrowseModal .modal-content {
    pointer-events: auto;
    position: relative;
    z-index: 2001;
}
#lieoAddDeptModal { z-index: 2000 !important; }
#lieoAddDeptModal .modal-dialog,
#lieoAddDeptModal .modal-content {
    pointer-events: auto;
    position: relative;
    z-index: 2001;
}
.lieo-add-dept-hint { font-size: .8125rem; color: #64748b; }
.lieo-add-dept-result { font-size: .8125rem; max-height: 120px; overflow: auto; }
.lieo-add-dept-ams-ref {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: .65rem .75rem;
    margin-bottom: .85rem;
    max-height: 140px;
    overflow: auto;
    cursor: pointer;
}
.lieo-add-dept-ams-ref.is-expanded {
    max-height: none;
    overflow: visible;
}
.lieo-add-dept-ams-ref h6 { cursor: pointer; user-select: none; }
.lieo-all-depts-box {
    margin-top: .5rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    overflow: hidden;
}
.lieo-all-depts-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .4rem .65rem;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .02em;
    color: #64748b;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}
.lieo-all-depts-list {
    max-height: 200px;
    overflow: auto;
    padding: .45rem;
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}
.lieo-all-depts-item {
    border: 1px solid #bbf7d0;
    background: #ecfdf3;
    color: #166534;
    border-radius: 999px;
    padding: .25rem .65rem;
    font-size: .8125rem;
    font-weight: 600;
    cursor: pointer;
}
.lieo-all-depts-item:hover,
.lieo-all-depts-item.is-selected {
    background: #42bb52;
    border-color: #42bb52;
    color: #fff;
}
.lieo-add-dept-ams-ref h6 {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: #64748b;
    margin: 0 0 .5rem;
}
.lieo-add-dept-ams-chips { display: flex; flex-wrap: wrap; gap: .35rem; }
.lieo-add-dept-ams-chip {
    display: inline-block;
    padding: .22rem .6rem;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 600;
    color: #166534;
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
}
</style>

<div class="modal fade" id="lieoAddDeptModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h5 class="modal-title lieo-title mb-0" style="font-size:1.05rem;">Add department(s)</h5>
                    <small class="text-muted">Plant: <strong id="lieoAddDeptPlantLabel">—</strong></small>
                </div>
                <button type="button" class="close" id="lieoAddDeptClose" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="lieo-add-dept-ams-ref" id="lieoAddDeptAmsRef" title="Click to expand/collapse">
                    <h6>AMS departments on this plant <span id="lieoAddDeptAmsCount" class="text-muted font-weight-normal"></span>
                        <small class="text-muted font-weight-normal">(click to show all)</small></h6>
                    <div id="lieoAddDeptAmsChips" class="lieo-add-dept-ams-chips text-muted small">Loading…</div>
                </div>
                <p class="lieo-add-dept-hint mb-2">
                    These AMS names are already in the LIEO dropdown. Add only names that are <strong>not</strong> listed above.
                    Full edit/deactivate is in <a href="<?= htmlspecialchars($lieoDeptMasterBase) ?>" id="lieoAddDeptMasterLink">Department Master</a>.
                </p>
                <label for="lieoAddDeptNames" class="font-weight-bold small">Extra department name(s)</label>
                <textarea id="lieoAddDeptNames" class="form-control" rows="4"
                          placeholder="One name per line, e.g.&#10;Sales&#10;Projects"></textarea>
                <div id="lieoAddDeptResult" class="lieo-add-dept-result mt-2 text-muted" style="display:none;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="lieoAddDeptCancel">Cancel</button>
                <button type="button" class="btn btn-lieo btn-sm" id="lieoAddDeptSave">Add to list</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lieoEmpBrowseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h5 class="modal-title lieo-title mb-0" style="font-size:1.05rem;">Select employee</h5>
                    <small class="text-muted" id="empModalScope">AMS directory</small>
                </div>
                <button type="button" class="close" id="empModalCloseBtn" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="lieo-emp-modal-search">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white">Search</span>
                        </div>
                        <input type="text" id="empModalSearch" class="form-control" placeholder="Filter by name, code or email…" autocomplete="off">
                    </div>
                    <small class="text-muted" id="empModalCount">Loading…</small>
                </div>
                <div class="table-responsive" style="max-height:420px;">
                    <table class="table table-sm table-hover mb-0 lieo-emp-table">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:110px;">Code</th>
                                <th>Name / Email</th>
                                <th style="width:160px;">AMS Dept</th>
                                <th style="width:90px;"></th>
                            </tr>
                        </thead>
                        <tbody id="empModalBody">
                            <tr><td colspan="4" class="text-muted text-center py-4">Loading employees…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="empModalCancelBtn">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function lieoWhenReady(fn) {
        if (window.jQuery) {
            window.jQuery(fn);
            return;
        }
        var tries = 0;
        var t = setInterval(function () {
            tries += 1;
            if (window.jQuery) {
                clearInterval(t);
                window.jQuery(fn);
            } else if (tries > 200) {
                clearInterval(t);
                fn();
            }
        }, 25);
    }

    lieoWhenReady(function () {
    var $ = window.jQuery;
    var plantOnlyRoles = <?= json_encode(array_values($LIEO_MATRIX_PLANT_ROLES)) ?>;
    var roleLabels = <?= json_encode($lieoMatrixSteps) ?>;
    var matrixAssignments = <?= json_encode($lieoMatrixAssignments, JSON_UNESCAPED_UNICODE) ?>;
    var editMatrixId = <?= (int) ($editRow['matrix_id'] ?? 0) ?>;
    var plantTimer = null, empFilterTimer = null;
    var empCache = [];
    var empLoadedFor = '';
    var $plantSearch = document.getElementById('plantSearch');
    var $plant = document.getElementById('plant');
    var $plantBox = document.getElementById('plantResults');
    var $dept = document.getElementById('department');
    var $deptAll = document.getElementById('departmentAll');
    var $deptGroup = document.getElementById('departmentGroup');
    var $role = document.getElementById('approval_step');
    var $browseBtn = document.getElementById('empBrowseBtn');
    var $browseLabel = document.getElementById('empBrowseLabel');
    var $countHint = document.getElementById('empCountHint');
    var $pickerCard = document.getElementById('empPickerCard');
    var $pickerSelected = document.getElementById('empPickerSelected');
    var $modalSearch = document.getElementById('empModalSearch');
    var $modalBody = document.getElementById('empModalBody');
    var $modalCount = document.getElementById('empModalCount');
    var $modalScope = document.getElementById('empModalScope');
    var $modalEl = document.getElementById('lieoEmpBrowseModal');
    var $modal = $ ? $('#lieoEmpBrowseModal') : null;
    var $addDeptModalEl = document.getElementById('lieoAddDeptModal');
    var $addDeptModal = $ ? $('#lieoAddDeptModal') : null;
    var lieoDeptMasterBase = <?= json_encode($lieoDeptMasterBase) ?>;
    var editEmpCode = <?= json_encode((string) ($editRow['emp_code'] ?? '')) ?>;

    if ($ && $addDeptModal && $addDeptModal.length && !$addDeptModal.parent().is('body')) {
        $addDeptModal.appendTo('body');
    } else if ($addDeptModalEl && $addDeptModalEl.parentElement !== document.body) {
        document.body.appendChild($addDeptModalEl);
    }

    if ($ && $modal && $modal.length && !$modal.parent().is('body')) {
        $modal.appendTo('body');
    } else if ($modalEl && $modalEl.parentElement !== document.body) {
        document.body.appendChild($modalEl);
    }

    function setBrowseLabel(text) {
        if ($browseLabel) $browseLabel.textContent = text;
    }

    function showEmpModal() {
        if ($modalEl) {
            $modalEl.style.zIndex = '2000';
            if ($modalEl.parentElement !== document.body) {
                document.body.appendChild($modalEl);
            }
        }
        if ($ && $.fn.modal && $modal && $modal.length) {
            $('.modal-backdrop').remove();
            $modal.modal({ backdrop: true, keyboard: true, show: true });
            setTimeout(function () {
                $('.modal-backdrop').last().css('z-index', 1990);
            }, 10);
            return;
        }
        if (!$modalEl) return;
        $modalEl.classList.add('show');
        $modalEl.style.display = 'block';
        $modalEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop')) {
            var bd = document.createElement('div');
            bd.className = 'modal-backdrop fade show';
            bd.style.zIndex = '1990';
            document.body.appendChild(bd);
        }
    }

    function hideEmpModal() {
        if ($ && $.fn.modal && $modal && $modal.length) {
            $modal.modal('hide');
        }
        if ($modalEl) {
            $modalEl.classList.remove('show');
            $modalEl.style.display = 'none';
            $modalEl.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
    }

    function deptMasterUrl(plant) {
        if (!plant) return lieoDeptMasterBase;
        return lieoDeptMasterBase + (lieoDeptMasterBase.indexOf('?') >= 0 ? '&' : '?') + 'plant=' + encodeURIComponent(plant);
    }

    function syncDeptMasterLinks() {
        var url = deptMasterUrl($plant.value || '');
        ['lieoDeptMasterLink', 'lieoAddDeptMasterLink'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.setAttribute('href', url);
        });
    }

    function loadAmsDeptRef(plant) {
        var $chips = document.getElementById('lieoAddDeptAmsChips');
        var $count = document.getElementById('lieoAddDeptAmsCount');
        if (!$chips) return;
        $chips.innerHTML = '<span class="text-muted">Loading…</span>';
        if ($count) $count.textContent = '';
        if (!plant) {
            $chips.innerHTML = '<span class="text-muted">Select a plant first.</span>';
            return;
        }
        fetch('../api/ams_lookup.php?type=ams_departments&plant=' + encodeURIComponent(plant))
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                rows = rows || [];
                if ($count) {
                    $count.textContent = rows.length ? ('(' + rows.length + ')') : '';
                }
                if (!rows.length) {
                    $chips.innerHTML = '<span class="text-muted">No AMS departments found for this plant.</span>';
                    return;
                }
                $chips.innerHTML = rows.map(function (d) {
                    return '<span class="lieo-add-dept-ams-chip">' + String(d).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
                }).join('');
            })
            .catch(function () {
                $chips.innerHTML = '<span class="text-danger">Could not load AMS departments.</span>';
            });
    }

    function showAddDeptModal() {
        syncDeptMasterLinks();
        var plant = $plant.value || '';
        document.getElementById('lieoAddDeptPlantLabel').textContent = plant || '—';
        document.getElementById('lieoAddDeptResult').style.display = 'none';
        loadAmsDeptRef(plant);
        if ($addDeptModalEl) {
            $addDeptModalEl.style.zIndex = '2000';
        }
        if ($ && $.fn.modal && $addDeptModal && $addDeptModal.length) {
            $('.modal-backdrop').not('.lieo-dialog-backdrop').remove();
            $addDeptModal.modal({ backdrop: true, keyboard: true, show: true });
            setTimeout(function () {
                $('.modal-backdrop').last().css('z-index', 1990);
            }, 10);
            return;
        }
        if (!$addDeptModalEl) return;
        $addDeptModalEl.classList.add('show');
        $addDeptModalEl.style.display = 'block';
        $addDeptModalEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function hideAddDeptModal() {
        if ($ && $.fn.modal && $addDeptModal && $addDeptModal.length) {
            $addDeptModal.modal('hide');
        }
        if ($addDeptModalEl) {
            $addDeptModalEl.classList.remove('show');
            $addDeptModalEl.style.display = 'none';
            $addDeptModalEl.setAttribute('aria-hidden', 'true');
        }
        if (!$('.modal.show').length) {
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
        }
    }

    function fillDepartmentOptions(rows, selected) {
        $dept.innerHTML = '<option value="">— Select department —</option>';
        (rows || []).forEach(function (d) {
            var opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            if (selected && selected === d) opt.selected = true;
            $dept.appendChild(opt);
        });
        $dept.disabled = false;
        syncDepartmentField();
        renderAllDeptsList(rows || [], selected);
    }

    function renderAllDeptsList(rows, selected) {
        var $box = document.getElementById('lieoAllDeptsList');
        var $count = document.getElementById('lieoAllDeptsCount');
        if (!$box) return;
        selected = selected || $dept.value || '';
        if ($count) {
            $count.textContent = rows.length ? ('(' + rows.length + ')') : '';
        }
        if (!rows.length) {
            $box.innerHTML = '<span class="text-muted small px-1">No departments for this plant.</span>';
            return;
        }
        $box.innerHTML = rows.map(function (d) {
            var esc = String(d).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            var sel = d === selected ? ' is-selected' : '';
            return '<button type="button" class="lieo-all-depts-item' + sel + '" data-dept="' + esc + '">' + esc + '</button>';
        }).join('');
    }

    function fetchDepartmentsOnly(plant) {
        if (!plant) return Promise.resolve([]);
        return fetch('../api/ams_lookup.php?type=departments&plant=' + encodeURIComponent(plant))
            .then(function (r) { return r.json(); })
            .then(function (rows) { return rows || []; })
            .catch(function () { return [] });
    }

    function syncSelectOptionsQuietly(rows, selected) {
        selected = selected || $dept.value || '';
        var current = Array.from($dept.options).slice(1).map(function (o) { return o.value; });
        var same = current.length === rows.length && current.every(function (v, i) { return v === rows[i]; });
        if (!same) {
            fillDepartmentOptions(rows, selected);
        } else {
            renderAllDeptsList(rows, selected);
        }
    }

    function toggleAllDeptsBox(forceShow) {
        var $panel = document.getElementById('lieoAllDeptsBox');
        var $toggle = document.getElementById('lieoToggleAllDepts');
        if (!$panel || !$toggle) return;
        var show = typeof forceShow === 'boolean' ? forceShow : ($panel.style.display === 'none');
        if (!$plant.value) {
            if (window.lieoAlert) {
                lieoAlert({ title: 'Select plant', message: 'Choose a plant first to view departments.' });
            }
            return;
        }
        if (show) {
            fetchDepartmentsOnly($plant.value).then(function (rows) {
                syncSelectOptionsQuietly(rows, $dept.value);
                renderAllDeptsList(rows, $dept.value);
                $panel.style.display = 'block';
                $toggle.textContent = 'Hide departments';
            });
        } else {
            $panel.style.display = 'none';
            $toggle.textContent = 'Show all departments';
        }
    }

    function selectDepartment(name) {
        if (!name) return;
        $dept.value = name;
        $dept.dispatchEvent(new Event('change'));
        renderAllDeptsList(Array.from($dept.options).slice(1).map(function (o) { return o.value; }), name);
    }

    function isPlantOnlyRole() {
        return plantOnlyRoles.indexOf($role.value) >= 0;
    }

    function amsDeptLabel(emp) {
        if (!emp) return '—';
        var d = String(emp.Department || emp.department || '').trim();
        if (!d && emp.empDepartment) {
            d = String(emp.empDepartment).trim();
        }
        return d || '—';
    }

    function setAmsDeptDisplay(text) {
        var el = document.getElementById('empDisplayAmsDept');
        if (!el) return;
        var label = (text || '').trim();
        if (label) {
            el.textContent = 'AMS Department: ' + label;
            el.style.display = '';
        } else {
            el.textContent = '';
            el.style.display = 'none';
        }
    }

    function canLoadEmployees() {
        return !!$plant.value;
    }

    function loadKey() {
        return $plant.value || '';
    }

    function initialFromName(name) {
        var t = (name || 'E').trim();
        return t ? t.charAt(0).toUpperCase() : 'E';
    }

    function syncDepartmentField() {
        var plantOnly = isPlantOnlyRole();
        if (plantOnly) {
            $deptGroup.style.display = 'none';
            $dept.disabled = true;
            $dept.removeAttribute('name');
            $deptAll.disabled = false;
            $deptAll.setAttribute('name', 'department');
        } else {
            $deptGroup.style.display = '';
            $deptAll.disabled = true;
            $deptAll.removeAttribute('name');
            $dept.setAttribute('name', 'department');
        }
        var hasPlant = canLoadEmployees();
        $browseBtn.disabled = !hasPlant;
        if (hasPlant) {
            setBrowseLabel(document.getElementById('emp_code').value ? 'Change employee' : 'Click here to search & select employee');
            $countHint.textContent = 'Employees load by plant (all AMS departments). Choose LIEO department before save.';
            preloadEmployees(false, true);
        } else {
            empCache = [];
            empLoadedFor = '';
            setBrowseLabel('Select plant first');
            $countHint.textContent = 'Select plant first, then pick an employee (all AMS departments for that plant).';
        }
    }

    function refreshAmsDeptFromCache() {
        var code = document.getElementById('emp_code').value;
        if (!code || !empCache.length) return;
        var found = empCache.find(function (e) { return String(e.empCode) === String(code); });
        if (found) {
            setAmsDeptDisplay(amsDeptLabel(found));
        }
    }

    function updatePickerUI() {
        var code = document.getElementById('emp_code').value;
        var name = document.getElementById('emp_name').value;
        var email = document.getElementById('emp_email').value;
        if (code) {
            $pickerCard.classList.add('has-selection');
            $pickerSelected.style.display = 'flex';
            document.getElementById('empDisplayName').textContent = name;
            document.getElementById('empDisplayCode').textContent = code;
            document.getElementById('empDisplayEmail').textContent = email || '—';
            $pickerSelected.querySelector('.lieo-emp-avatar').textContent = initialFromName(name);
            refreshAmsDeptFromCache();
            setBrowseLabel('Change employee');
        } else {
            $pickerCard.classList.remove('has-selection');
            $pickerSelected.style.display = 'none';
            setAmsDeptDisplay('');
            setBrowseLabel(canLoadEmployees() ? 'Click here to search & select employee' : 'Select plant first');
        }
    }

    function clearEmployeeFields() {
        document.getElementById('emp_code').value = '';
        document.getElementById('emp_name').value = '';
        document.getElementById('emp_email').value = '';
        editEmpCode = '';
        setAmsDeptDisplay('');
        updatePickerUI();
    }

    function selectEmployee(emp) {
        document.getElementById('emp_code').value = String(emp.empCode || '');
        document.getElementById('emp_name').value = emp.empName || '';
        document.getElementById('emp_email').value = emp.empBusiEmail || '';
        editEmpCode = String(emp.empCode || '');
        setAmsDeptDisplay(amsDeptLabel(emp));
        updatePickerUI();
        hideEmpModal();
    }

    function renderModalList(filterText) {
        var q = (filterText || '').trim().toLowerCase();
        var selected = document.getElementById('emp_code').value;
        var matched = empCache.filter(function (e) {
            if (!q) return true;
            var hay = ((e.empCode || '') + ' ' + (e.empName || '') + ' ' + (e.empBusiEmail || '') + ' ' + amsDeptLabel(e)).toLowerCase();
            return hay.indexOf(q) !== -1;
        });
        $modalBody.innerHTML = '';
        if (!matched.length) {
            $modalBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">'
                + (empCache.length ? 'No matches for your search.' : 'No employees found for this plant.')
                + '</td></tr>';
            $modalCount.textContent = 'Showing 0 of ' + empCache.length;
            return;
        }
        matched.forEach(function (e) {
            var tr = document.createElement('tr');
            var code = String(e.empCode || '');
            if (selected && selected === code) tr.className = 'is-selected';
            tr.innerHTML =
                '<td><code>' + code + '</code></td>' +
                '<td><div class="lieo-emp-row-name"></div><div class="lieo-emp-row-email"></div></td>' +
                '<td><div class="lieo-emp-row-dept"></div></td>' +
                '<td class="text-right"><button type="button" class="btn btn-sm btn-lieo">Select</button></td>';
            tr.querySelector('.lieo-emp-row-name').textContent = e.empName || '';
            tr.querySelector('.lieo-emp-row-email').textContent = e.empBusiEmail || '—';
            tr.querySelector('.lieo-emp-row-dept').textContent = amsDeptLabel(e);
            tr.addEventListener('click', function (ev) {
                if (ev.target.closest('button') || ev.target === tr || tr.contains(ev.target)) {
                    selectEmployee(e);
                }
            });
            $modalBody.appendChild(tr);
        });
        $modalCount.textContent = q
            ? ('Showing ' + matched.length + ' of ' + empCache.length)
            : (empCache.length + ' employees');
    }

    function preloadEmployees(force, autoSelect) {
        if (!canLoadEmployees()) return Promise.resolve([]);
        var key = loadKey();
        if (!force && empLoadedFor === key && empCache.length) {
            return Promise.resolve(empCache);
        }
        var plant = $plant.value;
        var url = '../api/search_employee.php?all=1&plant=' + encodeURIComponent(plant);
        $countHint.textContent = 'Loading employee directory…';
        return fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                empCache = rows || [];
                empLoadedFor = key;
                $countHint.textContent = empCache.length
                    ? (empCache.length + ' employees available for this plant — click above to pick.')
                    : 'No employees found for this plant.';
                if (autoSelect && editEmpCode) {
                    var found = empCache.find(function (e) { return String(e.empCode) === String(editEmpCode); });
                    if (found) {
                        document.getElementById('emp_code').value = String(found.empCode || '');
                        document.getElementById('emp_name').value = found.empName || '';
                        document.getElementById('emp_email').value = found.empBusiEmail || '';
                        setAmsDeptDisplay(amsDeptLabel(found));
                        updatePickerUI();
                    }
                } else {
                    refreshAmsDeptFromCache();
                }
                return empCache;
            })
            .catch(function () {
                empCache = [];
                empLoadedFor = '';
                $countHint.textContent = 'Failed to load employees. Try again.';
                return [];
            });
    }

    function openBrowseModal() {
        if (!canLoadEmployees()) {
            if (window.lieoAlert) {
                lieoAlert({
                    title: 'Almost there',
                    message: 'Select a plant first.'
                });
            }
            return;
        }
        $modalScope.textContent = $plant.value + ' · All AMS departments';
        $modalSearch.value = '';
        $modalBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Loading employees…</td></tr>';
        $modalCount.textContent = 'Loading…';
        showEmpModal();
        preloadEmployees(false, false).then(function () {
            renderModalList('');
            setTimeout(function () { $modalSearch.focus(); }, 250);
        });
    }

    $browseBtn.addEventListener('click', openBrowseModal);
    document.getElementById('empClearBtn').addEventListener('click', function () {
        clearEmployeeFields();
        if (canLoadEmployees()) {
            setBrowseLabel('Click here to search & select employee');
            $countHint.textContent = 'Click the box above to open the searchable employee list.';
        }
    });

    $modalSearch.addEventListener('input', function () {
        clearTimeout(empFilterTimer);
        empFilterTimer = setTimeout(function () {
            renderModalList($modalSearch.value);
        }, 120);
    });

    $role.addEventListener('change', function () {
        clearEmployeeFields();
        syncDepartmentField();
    });

    function loadDepartments(plant, selected, opts) {
        opts = opts || {};
        $dept.innerHTML = '<option value="">Loading…</option>';
        $dept.disabled = true;
        if (!opts.keepEmployee) {
            clearEmployeeFields();
            empCache = [];
            empLoadedFor = '';
        }
        syncDeptMasterLinks();
        if (!plant) {
            $dept.innerHTML = '<option value="">— Select plant first —</option>';
            syncDepartmentField();
            return Promise.resolve([]);
        }
        return fetch('../api/ams_lookup.php?type=departments&plant=' + encodeURIComponent(plant))
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                fillDepartmentOptions(rows, selected);
                return rows || [];
            })
            .catch(function () {
                $dept.innerHTML = '<option value="">Failed to load</option>';
                return [];
            });
    }

    document.getElementById('lieoAddDeptOpen').addEventListener('click', function () {
        if (!$plant.value) {
            if (window.lieoAlert) {
                lieoAlert({ title: 'Select plant', message: 'Choose a plant first, then add department(s).' });
            }
            return;
        }
        document.getElementById('lieoAddDeptNames').value = '';
        showAddDeptModal();
        setTimeout(function () { document.getElementById('lieoAddDeptNames').focus(); }, 200);
    });

    document.getElementById('lieoAddDeptCancel').addEventListener('click', function (e) {
        e.preventDefault();
        hideAddDeptModal();
    });
    document.getElementById('lieoAddDeptClose').addEventListener('click', function (e) {
        e.preventDefault();
        hideAddDeptModal();
    });

    document.getElementById('lieoAddDeptSave').addEventListener('click', function () {
        var plant = ($plant.value || '').trim();
        var raw = (document.getElementById('lieoAddDeptNames').value || '');
        var names = raw.split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
        var $result = document.getElementById('lieoAddDeptResult');
        var $btn = document.getElementById('lieoAddDeptSave');

        if (!plant) {
            if (window.lieoAlert) lieoAlert({ title: 'Select plant', message: 'Choose a plant first.' });
            return;
        }
        if (!names.length) {
            if (window.lieoAlert) lieoAlert({ title: 'Enter names', message: 'Type one or more department names (one per line).' });
            return;
        }

        $btn.disabled = true;
        $result.style.display = 'none';

        fetch('../api/add_plant_departments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plant: plant, departments: names })
        })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                var data = res.data || {};
                var html = '';
                if (data.message) {
                    html += '<div class="' + (data.added && data.added.length ? 'text-success' : 'text-muted') + '">' + data.message + '</div>';
                }
                if (data.skipped && data.skipped.length) {
                    html += '<ul class="mb-0 pl-3">';
                    data.skipped.forEach(function (s) {
                        html += '<li>' + (s.name || '') + ' — ' + (s.reason || 'skipped') + '</li>';
                    });
                    html += '</ul>';
                }
                if (data.errors && data.errors.length) {
                    html += '<ul class="mb-0 pl-3 text-danger">';
                    data.errors.forEach(function (s) {
                        html += '<li>' + (s.name || '') + ' — ' + (s.reason || 'failed') + '</li>';
                    });
                    html += '</ul>';
                }
                if (html) {
                    $result.innerHTML = html;
                    $result.style.display = 'block';
                }

                if (data.departments && data.departments.length) {
                    var pick = (data.added && data.added.length) ? data.added[data.added.length - 1] : $dept.value;
                    fillDepartmentOptions(data.departments, pick);
                } else {
                    loadDepartments(plant, $dept.value);
                }

                if (data.added && data.added.length) {
                    document.getElementById('lieoAddDeptNames').value = '';
                    setTimeout(hideAddDeptModal, 600);
                } else if (!res.ok && window.lieoAlert) {
                    lieoAlert({ title: 'Could not add', message: data.message || 'No departments were added.' });
                }
            })
            .catch(function () {
                if (window.lieoAlert) lieoAlert({ title: 'Error', message: 'Could not save departments. Try again.' });
            })
            .finally(function () {
                $btn.disabled = false;
            });
    });

    $plantSearch.addEventListener('input', function () {
        clearTimeout(plantTimer);
        $plant.value = '';
        $dept.innerHTML = '<option value="">— Select plant first —</option>';
        $dept.disabled = true;
        clearEmployeeFields();
        empCache = [];
        empLoadedFor = '';
        syncDepartmentField();
        var v = this.value.trim();
        plantTimer = setTimeout(function () {
            var url = '../api/ams_lookup.php?type=plants' + (v ? '&q=' + encodeURIComponent(v) : '');
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    $plantBox.innerHTML = '';
                    if (!rows.length) {
                        $plantBox.innerHTML = '<div class="list-group-item small text-muted">No plants found</div>';
                    } else {
                        rows.forEach(function (p) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action small';
                            btn.textContent = p;
                            btn.addEventListener('click', function () {
                                $plantSearch.value = p;
                                $plant.value = p;
                                $plantBox.style.display = 'none';
                                loadDepartments(p, null);
                            });
                            $plantBox.appendChild(btn);
                        });
                    }
                    $plantBox.style.display = 'block';
                });
        }, 250);
    });

    $plantSearch.addEventListener('focus', function () {
        if (!$plant.value) {
            $plantSearch.dispatchEvent(new Event('input'));
        }
    });

    $dept.addEventListener('change', function () {
        if (canLoadEmployees() && document.getElementById('emp_code').value) {
            $countHint.textContent = 'LIEO department is for approvals — may differ from the AMS department shown on the employee.';
        }
        renderAllDeptsList(Array.from($dept.options).slice(1).map(function (o) { return o.value; }), $dept.value);
    });

    document.getElementById('lieoToggleAllDepts').addEventListener('click', function () {
        toggleAllDeptsBox();
    });

    document.getElementById('lieoAllDeptsList').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dept]');
        if (!btn) return;
        selectDepartment(btn.getAttribute('data-dept') || '');
    });

    var $amsRef = document.getElementById('lieoAddDeptAmsRef');
    if ($amsRef) {
        $amsRef.addEventListener('click', function () {
            $amsRef.classList.toggle('is-expanded');
        });
    }

    function findReplaceConflict() {
        var plant = ($plant.value || '').trim();
        var role = $role.value;
        var dept = isPlantOnlyRole() ? 'All' : ($dept.value || '').trim();
        var empCode = String(document.getElementById('emp_code').value || '').trim();
        if (!plant || !role || (!isPlantOnlyRole() && !dept) || !empCode) {
            return null;
        }
        for (var i = 0; i < matrixAssignments.length; i++) {
            var row = matrixAssignments[i];
            if (editMatrixId && row.matrix_id === editMatrixId) {
                continue;
            }
            if (row.plant !== plant || row.department !== dept || row.approval_step !== role) {
                continue;
            }
            if (String(row.emp_code) === empCode) {
                continue;
            }
            return row;
        }
        return null;
    }

    function buildReplaceConfirmMessage(conflict) {
        var roleLabel = roleLabels[$role.value] || $role.value;
        var plant = ($plant.value || '').trim();
        var dept = ($dept.value || '').trim();
        var who = conflict.emp_name || conflict.emp_email || ('Employee ' + conflict.emp_code);
        if (isPlantOnlyRole()) {
            return who + ' is currently ' + roleLabel + ' for ' + plant + '. Do you want to replace with the selected employee?';
        }
        return who + ' is currently ' + roleLabel + ' for ' + plant + ' · ' + dept + '. Do you want to replace with the selected employee?';
    }

    document.getElementById('matrixForm').addEventListener('submit', function (ev) {
        var form = ev.target;

        if (form.getAttribute('data-lieo-replace-confirmed') === '1') {
            form.removeAttribute('data-lieo-replace-confirmed');
            return;
        }

        if (!$plant.value || !document.getElementById('emp_code').value) {
            ev.preventDefault();
            if (window.lieoAlert) {
                lieoAlert({ title: 'Missing details', message: 'Please select Plant and Employee from the AMS lists.' });
            }
            return;
        }
        if (!isPlantOnlyRole() && !$dept.value) {
            ev.preventDefault();
            if (window.lieoAlert) {
                lieoAlert({ title: 'Missing LIEO department', message: 'Please select the LIEO department used for approvals.' });
            }
            return;
        }

        var conflict = findReplaceConflict();
        if (!conflict) {
            return;
        }

        ev.preventDefault();
        var msg = buildReplaceConfirmMessage(conflict);
        if (window.lieoConfirm) {
            window.lieoConfirm({
                title: 'Replace existing assignment?',
                message: msg,
                confirmText: 'Replace',
                variant: 'info'
            }).then(function (ok) {
                if (!ok) {
                    return;
                }
                form.setAttribute('data-lieo-replace-confirmed', '1');
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
            return;
        }
        if (window.confirm(msg)) {
            form.setAttribute('data-lieo-replace-confirmed', '1');
            form.submit();
        }
    });

    document.addEventListener('click', function (ev) {
        if (!$plantBox.contains(ev.target) && ev.target !== $plantSearch) $plantBox.style.display = 'none';
    });

    updatePickerUI();
    syncDepartmentField();
    syncDeptMasterLinks();

    if ($modalEl) {
        var closeBtn = document.getElementById('empModalCloseBtn');
        var cancelBtn = document.getElementById('empModalCancelBtn');
        if (closeBtn) closeBtn.addEventListener('click', function (e) { e.preventDefault(); hideEmpModal(); });
        if (cancelBtn) cancelBtn.addEventListener('click', function (e) { e.preventDefault(); hideEmpModal(); });
        $modalEl.addEventListener('click', function (e) {
            if (e.target === $modalEl) hideEmpModal();
        });
    }
    if ($addDeptModalEl) {
        $addDeptModalEl.addEventListener('click', function (e) {
            if (e.target === $addDeptModalEl) hideAddDeptModal();
        });
    }
    }); // lieoWhenReady
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
