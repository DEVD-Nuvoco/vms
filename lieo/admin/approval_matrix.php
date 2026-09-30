<?php
require_once __DIR__ . '/../config.php';
if (!defined('LIEO_MATRIX_PAGE')) {
    lieo_require_role(['admin']);
    define('LIEO_MATRIX_PAGE', 1);
    global $LIEO_ADMIN_MATRIX_STEPS;
    $lieoMatrixStepKeys = $LIEO_ADMIN_MATRIX_STEPS;
    $lieoMatrixLockPlant = false;
}

$pageTitle = 'LIEO Users';
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
    global $LIEO_ADMIN_REQUEST_GATED_STEPS;
    try {
        if ($action === 'add' || $action === 'edit') {
            $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
            $step = $_POST['approval_step'] ?? '';
            if (in_array($step, $LIEO_ADMIN_REQUEST_GATED_STEPS, true)) {
                // HOD/N-1/Security need HR-department-HOD sign-off before they
                // take effect — queue a request instead of saving live.
                $result = lieo_submit_user_request([
                    'request_type' => $id ? 'update' : 'create',
                    'target_matrix_id' => $id,
                    'plant' => $_POST['plant'] ?? '',
                    'department' => $_POST['department'] ?? '',
                    'approval_step' => $step,
                    'emp_code' => $_POST['emp_code'] ?? '',
                    'emp_name' => $_POST['emp_name'] ?? '',
                    'emp_email' => $_POST['emp_email'] ?? '',
                ]);
            } else {
                // Time Office: direct/live save (not HR-HOD-approval-gated).
                $result = lieo_save_matrix_rule([
                    'plant' => $_POST['plant'] ?? '',
                    'department' => $_POST['department'] ?? '',
                    'approval_step' => $step,
                    'emp_code' => $_POST['emp_code'] ?? '',
                    'emp_name' => $_POST['emp_name'] ?? '',
                    'emp_email' => $_POST['emp_email'] ?? '',
                ], $id);
            }
            $flashMsg = trim((string) ($result['message'] ?? ''));
            $_SESSION['lieo_mess'] = $result['ok']
                ? ($flashMsg !== '' ? $flashMsg : 'Approval rule saved.')
                : ($flashMsg !== '' ? $flashMsg : 'Could not save approval rule.');
            if (!$result['ok']) {
                $_SESSION['lieo_mess_type'] = 'danger';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = null;
            foreach (lieo_list_matrix() as $r) {
                if ((int) $r['matrix_id'] === $id) {
                    $row = $r;
                    break;
                }
            }
            $step = $row['approval_step'] ?? '';
            if ($row && in_array($step, $LIEO_ADMIN_REQUEST_GATED_STEPS, true)) {
                lieo_submit_user_request([
                    'request_type' => 'delete',
                    'target_matrix_id' => $id,
                    'plant' => $row['plant'],
                    'department' => $row['department'],
                    'approval_step' => $step,
                    'emp_code' => $row['emp_code'],
                    'emp_name' => $row['emp_name'],
                    'emp_email' => $row['emp_email'],
                ]);
                $_SESSION['lieo_mess'] = 'Removal request submitted for HR department HOD approval.';
            } else {
                lieo_delete_matrix_rule($id);
                if ($row) {
                    lieo_notify_matrix_removed($row);
                }
                $_SESSION['lieo_mess'] = 'Rule deactivated.';
            }
        } elseif ($action === 'transfer_hod') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = null;
            foreach (lieo_list_matrix() as $r) {
                if ((int) $r['matrix_id'] === $id) {
                    $row = $r;
                    break;
                }
            }
            if ($row && trim((string) ($_POST['emp_code'] ?? '')) !== '') {
                $result = lieo_submit_user_request([
                    'request_type' => 'update',
                    'target_matrix_id' => $id,
                    'plant' => $row['plant'],
                    'department' => $row['department'],
                    'approval_step' => 'hod',
                    'emp_code' => $_POST['emp_code'] ?? '',
                    'emp_name' => $_POST['emp_name'] ?? '',
                    'emp_email' => $_POST['emp_email'] ?? '',
                ]);
                $_SESSION['lieo_mess'] = $result['ok']
                    ? 'HOD replacement request submitted for HR department HOD approval.'
                    : (string) ($result['message'] ?? 'Could not submit replacement request.');
                if (empty($result['ok'])) {
                    $_SESSION['lieo_mess_type'] = 'danger';
                }
            } else {
                $_SESSION['lieo_mess'] = $row ? 'Pick the new HOD before submitting.' : 'Assignment not found.';
                $_SESSION['lieo_mess_type'] = 'danger';
            }
        } elseif ($action === 'resend') {
            $result = lieo_resend_matrix_credentials((int) ($_POST['id'] ?? 0), true);
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

$transferId = (int) ($_GET['transfer'] ?? 0);
$editId = $transferId ?: (int) ($_GET['edit'] ?? 0);
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
$pendingByMatrixId = lieo_list_pending_target_requests();
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

// Replace mode: same form, but for picking a NEW person — clear the
// pre-filled employee, and only ever HOD (the only step with a "Replace" button).
if ($transferId && $editRow) {
    $editRow['emp_code'] = '';
    $editRow['emp_name'] = '';
    $editRow['emp_email'] = '';
}

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

<h2 class="lieo-title mb-2">LIEO Users</h2>
<p class="text-muted mb-4">
    Admin assigns <strong>HOD</strong> and <strong>N-1</strong> by department (one HOD per department; N-1 can be multiple per department),
    and <strong>Security</strong> once per plant (must be an HR department employee, same as Time Office).
    Flow: <strong>N-1 creates</strong> the application → <strong>HOD approves</strong> → <strong>Security</strong> closes at the gate with a remark.
    <strong>HOD, N-1 and Security</strong> assignments need the HR department HOD's approval before they take effect — you'll see them queued
    until approved. <strong>Time Office</strong> saves immediately (view-only role; not part of the approval chain).
    Once a request is approved, the LIEO login is created/updated and credentials are emailed; the user must change password on first sign-in.
</p>

<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-lieo" id="matrixAddBtn">+ Add Role Assignment</button>
</div>

<div class="modal fade" id="matrixModal" tabindex="-1" role="dialog" aria-hidden="true" data-open="<?= $editRow ? '1' : '0' ?>" data-list-url="approval_matrix.php<?= $viewPlant !== '' ? ('?plant=' . rawurlencode($viewPlant)) : '' ?>">
<div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header py-2">
        <h5 class="modal-title font-weight-bold text-success">
        <?= $transferId ? 'Replace HOD — ' . htmlspecialchars($editDept) . ' (' . htmlspecialchars($editPlant) . ')' : ($editRow ? 'Edit Assignment' : 'Add Role Assignment') ?>
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
    </div>
    <div class="modal-body">
        <?php if ($transferId): ?>
        <p class="small text-muted">Pick the new HOD below. The outgoing HOD's record is kept — see the transfer log — and any pending application for this department becomes actionable by the new HOD immediately.</p>
        <?php endif; ?>
        <form method="post" id="matrixForm" autocomplete="off">
            <input type="hidden" name="action" value="<?= $transferId ? 'transfer_hod' : ($editRow ? 'edit' : 'add') ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['matrix_id'] ?>"><?php endif; ?>
            <?php if ($viewPlant !== ''): ?><input type="hidden" name="view_plant" value="<?= htmlspecialchars($viewPlant) ?>"><?php endif; ?>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Plant * <small class="text-muted">(search AMS)</small></label>
                    <input type="text" id="plantSearch" class="form-control" placeholder="Type plant e.g. JCP"
                           value="<?= htmlspecialchars($editPlant) ?>" autocomplete="off" required
                           <?= ($lieoMatrixLockPlant || $transferId) ? 'readonly' : '' ?>>
                    <input type="hidden" name="plant" id="plant" value="<?= htmlspecialchars($editPlant) ?>" required>
                    <div id="plantResults" class="list-group mt-1" style="max-height:180px;overflow:auto;display:none;position:relative;z-index:30;"></div>
                </div>
                <div class="form-group col-md-6">
                    <label>Role *</label>
                    <select name="approval_step" id="approval_step" class="form-control" required <?= $transferId ? 'disabled' : '' ?>>
                        <?php foreach ($lieoMatrixSteps as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (($editRow['approval_step'] ?? '') === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted" id="roleHint">Time Office and Security: pick plant only — one assignee per plant (HR department employee only). HOD: one per department (N-1: multiple allowed).</small>
                </div>
                <div class="form-group col-md-12" id="departmentGroup">
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
                    <select name="department" id="department" class="form-control" required <?= ($editPlant === '' || $transferId) ? 'disabled' : '' ?>>
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
                        Departments come from Department Master. <a href="<?= htmlspecialchars($lieoDeptMasterBase) ?>" id="lieoDeptMasterLink">Manage in Department Master</a>
                    </small>
                    <input type="hidden" name="department" id="departmentAll" value="All" disabled>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-12">
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
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-danger px-1" id="empClearBtn" title="Clear selection">Clear</button>
                        </div>
                        <button type="button" class="lieo-emp-open-btn" id="empBrowseBtn">
                            <span class="lieo-emp-open-icon" aria-hidden="true"><i class="typcn typcn-zoom-outline"></i></span>
                            <span class="lieo-emp-open-text" id="empBrowseLabel">
                                <?= !empty($editRow['emp_code']) ? 'Change employee' : 'Click here to search &amp; select employee' ?>
                            </span>
                        </button>
                        <div id="empExistingNote" class="alert alert-warning py-2 px-3 mt-2 mb-0 small" style="display:none;"></div>
                        <small class="text-muted d-block mt-1" id="empCountHint">Select plant first, then pick an employee (all AMS departments for that plant).</small>
                    </div>
                    <input type="hidden" name="emp_code" id="emp_code" required value="<?= htmlspecialchars($editRow['emp_code'] ?? '') ?>">
                    <input type="hidden" name="emp_name" id="emp_name" required value="<?= htmlspecialchars($editRow['emp_name'] ?? '') ?>">
                    <div id="loginEmailGroup" class="mt-2" style="display:none;">
                        <label class="small font-weight-bold mb-1">Login email <span class="text-muted font-weight-normal">(Security only — employee code and name stay the same; the login email can be changed)</span></label>
                        <input type="email" name="emp_email" id="emp_email" class="form-control" placeholder="e.g. maingate.plant@nuvoco.com"
                               value="<?= htmlspecialchars($editRow['emp_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group col-md-12 mb-0">
                    <div class="d-flex justify-content-end align-items-center">
                        <button type="submit" class="btn btn-lieo px-4"><?= $transferId ? 'Submit Replacement' : 'Save & Provision Login' ?></button>
                        <?php if ($editRow): ?><a href="approval_matrix.php<?= $viewPlant !== '' ? ('?plant=' . rawurlencode($viewPlant)) : '' ?>" class="btn btn-link ml-2">Cancel</a><?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div></div>
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
                    // Reset is offered on every row; with no login yet it creates one.
                    $canResend = empty($row['lieo_user_id']) || ($row['user_status'] ?? '') === 'Active';
                ?>
                <?php
                    $pendingReq = $pendingByMatrixId[(int) $row['matrix_id']] ?? null;
                    $pendingReqTitle = '';
                    if ($pendingReq) {
                        if (($pendingReq['request_type'] ?? '') === 'delete') {
                            $pendingReqTitle = 'Removal requested';
                        } else {
                            $changes = [];
                            if ((string) $pendingReq['approval_step'] !== (string) $row['approval_step']) {
                                $changes[] = 'Role: ' . lieo_step_label($row['approval_step']) . ' → ' . lieo_step_label($pendingReq['approval_step']);
                            }
                            if (!lieo_dept_names_equal((string) $pendingReq['department'], (string) $row['department'])) {
                                $changes[] = 'Dept: ' . $row['department'] . ' → ' . $pendingReq['department'];
                            }
                            if (strcasecmp((string) $pendingReq['emp_email'], (string) $row['emp_email']) !== 0) {
                                $changes[] = 'Employee: ' . $row['emp_name'] . ' → ' . $pendingReq['emp_name'];
                            }
                            $pendingReqTitle = $changes ? implode('; ', $changes) : 'Update requested';
                        }
                    }
                ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($row['plant']) ?></td>
                    <td><?= $row['department'] === 'All' ? '<span class="text-muted">—</span>' : htmlspecialchars($row['department']) ?></td>
                    <td>
                        <span class="badge badge-info"><?= htmlspecialchars(lieo_step_label($row['approval_step'])) ?></span>
                        <?php if ($pendingReq): ?>
                        <span class="badge badge-warning" title="<?= htmlspecialchars($pendingReqTitle) ?>">Pending: <?= htmlspecialchars($pendingReqTitle) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['emp_code']) ?></td>
                    <td><?= htmlspecialchars($row['emp_name']) ?></td>
                    <td><?= htmlspecialchars($row['emp_email']) ?></td>
                    <td class="text-nowrap">
                        <a href="?edit=<?= (int)$row['matrix_id'] ?><?= $viewPlant ? '&plant=' . urlencode($viewPlant) : '' ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (($row['approval_step'] ?? '') === 'hod'): ?>
                        <a href="?transfer=<?= (int)$row['matrix_id'] ?><?= $viewPlant ? '&plant=' . urlencode($viewPlant) : '' ?>" class="btn btn-sm btn-outline-warning">Replace</a>
                        <?php endif; ?>
                        <?php if ($canResend): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Reset the password for <?= htmlspecialchars($row['emp_email'], ENT_QUOTES) ?>? Their current password stops working and a new temporary password is emailed; they must change it at next sign-in."
                              data-lieo-confirm-title="Reset password"
                              data-lieo-confirm-ok="Reset">
                            <input type="hidden" name="action" value="resend">
                            <input type="hidden" name="id" value="<?= (int)$row['matrix_id'] ?>">
                            <?php if ($viewPlant !== ''): ?><input type="hidden" name="view_plant" value="<?= htmlspecialchars($viewPlant) ?>"><?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Reset password and email a new temporary one">Reset</button>
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
#lieoEmpBrowseModal { z-index: 2100 !important; }
#lieoEmpBrowseModal .modal-dialog,
#lieoEmpBrowseModal .modal-content {
    pointer-events: auto;
    position: relative;
    z-index: 2101;
}
#matrixModal { z-index: 2000 !important; }
#matrixModal #department:disabled { background-color: #fff; pointer-events: none; }
#matrixModal .modal-dialog,
#matrixModal .modal-content {
    pointer-events: auto;
    position: relative;
    z-index: 2001;
}
#lieoAddDeptModal { z-index: 2100 !important; }
#lieoAddDeptModal .modal-dialog,
#lieoAddDeptModal .modal-content {
    pointer-events: auto;
    position: relative;
    z-index: 2101;
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
                <p class="lieo-add-dept-hint mb-2">
                    Departments listed in the dropdown come only from Department Master. Add new names here, or manage them (edit/deactivate) in <a href="<?= htmlspecialchars($lieoDeptMasterBase) ?>" id="lieoAddDeptMasterLink">Department Master</a>.
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
                                <th style="width:90px;"></th>
                            </tr>
                        </thead>
                        <tbody id="empModalBody">
                            <tr><td colspan="3" class="text-muted text-center py-4">Loading employees…</td></tr>
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
        if (!document.querySelector('#matrixModal.show')) {
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
        } else {
            document.body.classList.add('modal-open');
        }
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

    function showAddDeptModal() {
        syncDeptMasterLinks();
        var plant = $plant.value || '';
        document.getElementById('lieoAddDeptPlantLabel').textContent = plant || '—';
        document.getElementById('lieoAddDeptResult').style.display = 'none';
        if ($addDeptModalEl) {
            $addDeptModalEl.style.zIndex = '2000';
        }
        if ($ && $.fn.modal && $addDeptModal && $addDeptModal.length) {
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
        } else {
            document.body.classList.add('modal-open');
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

    // One assignee per plant (Time Office/Security) or per department (HOD): show who holds it now.
    function updateExistingNote() {
        var $n = document.getElementById('empExistingNote');
        var plant = ($plant.value || '').trim();
        var role = $role.value;
        var dept = isPlantOnlyRole() ? 'All' : ($dept.value || '').trim();
        var found = [];
        if (plant && role !== 'n1' && (isPlantOnlyRole() || dept)) {
            matrixAssignments.forEach(function (r) {
                if (String(r.plant).toUpperCase() !== plant.toUpperCase() || r.approval_step !== role || r.matrix_id === editMatrixId) return;
                // Plant-level roles are one per plant whatever department value was stored.
                if (isPlantOnlyRole() || r.department === dept) found.push(r);
            });
        }
        if (!found.length) { $n.style.display = 'none'; return; }
        var label = roleLabels[role] || role;
        $n.innerHTML = '';
        var head = document.createElement('div');
        head.textContent = 'Current ' + label + ' for ' + plant + (isPlantOnlyRole() ? '' : ' · ' + dept) + ' (' + found.length + '):';
        $n.appendChild(head);
        found.forEach(function (r) {
            var line = document.createElement('div');
            line.className = 'font-weight-bold';
            line.textContent = r.emp_name + ' (' + r.emp_code + ')' + (r.emp_email ? ' · ' + r.emp_email : '');
            $n.appendChild(line);
        });
        var tail = document.createElement('div');
        tail.textContent = 'Pick another employee to replace.';
        $n.appendChild(tail);
        $n.style.display = '';
    }

    function syncDepartmentField() {
        updateExistingNote();
        document.getElementById('loginEmailGroup').style.display = $role.value === 'security' ? '' : 'none';
        var plantOnly = isPlantOnlyRole();
        if (plantOnly) {
            $deptGroup.style.display = 'none';
            $dept.disabled = true;
            $dept.removeAttribute('name');
            $deptAll.disabled = false;
            $deptAll.setAttribute('name', 'department');
        } else {
            $deptGroup.style.display = '';
            $dept.disabled = !$plant.value;
            $deptAll.disabled = true;
            $deptAll.removeAttribute('name');
            $dept.setAttribute('name', 'department');
        }
        var hasPlant = canLoadEmployees();
        if (hasPlant) {
            setBrowseLabel(document.getElementById('emp_code').value ? 'Change employee' : 'Click here to search & select employee');
            $countHint.textContent = 'Employees load by plant (all AMS departments). Choose LIEO department before save.';
            preloadEmployees(false, true);
        } else {
            empCache = [];
            empLoadedFor = '';
            setBrowseLabel('Click here to search & select employee');
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
            setBrowseLabel('Click here to search & select employee');
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
            var hay = ((e.empCode || '') + ' ' + (e.empName || '') + ' ' + (e.empBusiEmail || '')).toLowerCase();
            return hay.indexOf(q) !== -1;
        });
        $modalBody.innerHTML = '';
        if (!matched.length) {
            $modalBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">'
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
                '<td class="text-right"><button type="button" class="btn btn-sm btn-lieo">Select</button></td>';
            tr.querySelector('.lieo-emp-row-name').textContent = e.empName || '';
            tr.querySelector('.lieo-emp-row-email').textContent = e.empBusiEmail || '—';
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
                        if (!document.getElementById('emp_email').value) document.getElementById('emp_email').value = found.empBusiEmail || '';
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
        $modalScope.textContent = $plant.value;
        $modalSearch.value = '';
        $modalBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Loading employees…</td></tr>';
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
        // Employee list only depends on Plant, not Role — keep the picked
        // employee (syncDepartmentField()'s preload re-applies it from
        // editEmpCode). Eligibility (e.g. Security/Time Office must be an
        // HR department employee) is still enforced server-side on save.
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
        updateExistingNote();
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

    function findReplaceConflict() {
        var plant = ($plant.value || '').trim();
        var role = $role.value;
        var dept = isPlantOnlyRole() ? 'All' : ($dept.value || '').trim();
        var empCode = String(document.getElementById('emp_code').value || '').trim();
        // N-1 allows multiple approvers per department, so picking an
        // employee already assigned there is never a "replace" — unlike
        // HOD (one per department) and Time Office/Security (one per plant).
        if (role === 'n1') {
            return null;
        }
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

    // Departments are per plant: tell the user instead of a dead-looking control.
    $deptGroup.addEventListener('click', function (ev) {
        if ($plant.value || ev.target.closest('button,a,label')) return;
        if (window.lieoAlert) lieoAlert({ title: 'Select plant first', message: 'Departments are configured per plant. Choose the plant, then pick the department.' });
        $plantSearch.focus();
    });

    document.addEventListener('click', function (ev) {
        if (!$plantBox.contains(ev.target) && ev.target !== $plantSearch) $plantBox.style.display = 'none';
    });

    updatePickerUI();
    syncDepartmentField();
    syncDeptMasterLinks();

    var $matrixModalEl = document.getElementById('matrixModal');
    var $matrixModal = $('#matrixModal').appendTo('body');
    function openMatrixModal() { $matrixModal.modal({ backdrop: 'static', keyboard: true, show: true }); }
    document.getElementById('matrixAddBtn').addEventListener('click', openMatrixModal);
    // Edit/Replace arrive as ?edit=/?transfer= — closing the modal returns to the plain list.
    if ($matrixModalEl.getAttribute('data-open') === '1') {
        $matrixModal.on('hidden.bs.modal', function () { location.href = $matrixModalEl.getAttribute('data-list-url'); });
        openMatrixModal();
    }

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
