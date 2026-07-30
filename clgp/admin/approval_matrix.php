<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);

$pageTitle = 'Approval Matrix';
$activeNav = 'matrix';
global $CLGP_APPROVAL_STEPS, $CLGP_MATRIX_PLANT_ROLES;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
        $result = clgp_save_matrix_rule([
            'plant' => $_POST['plant'] ?? '',
            'department' => $_POST['department'] ?? '',
            'approval_step' => $_POST['approval_step'] ?? '',
            'emp_code' => $_POST['emp_code'] ?? '',
            'emp_name' => $_POST['emp_name'] ?? '',
            'emp_email' => $_POST['emp_email'] ?? '',
        ], $id);
        $_SESSION['clgp_mess'] = $result['ok'] ? ($result['message'] ?? 'Approval rule saved.') : $result['message'];
    } elseif ($action === 'delete') {
        clgp_delete_matrix_rule((int) ($_POST['id'] ?? 0));
        $_SESSION['clgp_mess'] = 'Rule deactivated.';
    }
    header('Location: approval_matrix.php');
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$allMatrix = clgp_list_matrix();
$plantsInMatrix = array_values(array_unique(array_column($allMatrix, 'plant')));
sort($plantsInMatrix);
$viewPlant = $_GET['plant'] ?? '';
if ($viewPlant !== '' && !in_array($viewPlant, $plantsInMatrix, true)) {
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
$editDept = $editRow['department'] ?? '';
$editDepts = $editPlant !== '' ? clgp_list_ams_departments($editPlant) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-2">Approval Matrix</h2>
<p class="text-muted mb-4">
    Assign <strong>Time Office, Supervisor, N-1, HOD, Security, HR Head</strong> per plant (and department where required).
    Saving creates or updates the CLGP login — default password is emailed; user must change password on first sign-in.
    LC/EG flow: Time Office <strong>creates</strong> → <strong>Supervisor → N-1 → HOD</strong> approve → Security <strong>closes at gate</strong>.
    <strong>Security</strong> and <strong>HR Head</strong> are assigned <em>once per plant</em> (not per department) — add a separate row for each plant.
</p>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white font-weight-bold text-success"><?= $editRow ? 'Edit Assignment' : 'Add Role Assignment' ?></div>
            <div class="card-body">
                <form method="post" id="matrixForm" autocomplete="off">
                    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
                    <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['matrix_id'] ?>"><?php endif; ?>

                    <div class="form-group">
                        <label>Plant * <small class="text-muted">(search AMS)</small></label>
                        <input type="text" id="plantSearch" class="form-control" placeholder="Type plant e.g. JCP"
                               value="<?= htmlspecialchars($editPlant) ?>" autocomplete="off" required>
                        <input type="hidden" name="plant" id="plant" value="<?= htmlspecialchars($editPlant) ?>" required>
                        <div id="plantResults" class="list-group mt-1" style="max-height:180px;overflow:auto;display:none;position:relative;z-index:30;"></div>
                    </div>

                    <div class="form-group" id="departmentGroup">
                        <label>Department *</label>
                        <select name="department" id="department" class="form-control" required <?= $editPlant === '' ? 'disabled' : '' ?>>
                            <option value="">— Select plant first —</option>
                            <?php foreach ($editDepts as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $editDept === $d ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="department" id="departmentAll" value="All" disabled>
                    </div>

                    <div class="form-group">
                        <label>Role *</label>
                        <select name="approval_step" id="approval_step" class="form-control" required>
                            <?php foreach ($CLGP_APPROVAL_STEPS as $key => $label): ?>
                            <option value="<?= $key ?>" <?= (($editRow['approval_step'] ?? '') === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" id="roleHint">Security and HR Head: pick plant only — one assignee per plant.</small>
                    </div>

                    <div class="form-group">
                        <label>Search Employee (AMS) *</label>
                        <input type="text" id="empSearch" class="form-control" placeholder="Type name / code / email" autocomplete="off"
                               value="<?= htmlspecialchars($editRow['emp_name'] ?? '') ?>"
                               <?= ($editPlant === '' || $editDept === '') ? 'disabled' : '' ?>>
                        <div id="empResults" class="list-group mt-1" style="max-height:180px;overflow:auto;display:none;position:relative;z-index:20;"></div>
                    </div>
                    <div class="form-group">
                        <label>Employee Code</label>
                        <input type="text" name="emp_code" id="emp_code" class="form-control" readonly required
                               value="<?= htmlspecialchars($editRow['emp_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Employee Name</label>
                        <input type="text" name="emp_name" id="emp_name" class="form-control" readonly required
                               value="<?= htmlspecialchars($editRow['emp_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Employee Email</label>
                        <input type="email" name="emp_email" id="emp_email" class="form-control" readonly
                               value="<?= htmlspecialchars($editRow['emp_email'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-clgp btn-block">Save &amp; Provision Login</button>
                    <?php if ($editRow): ?><a href="approval_matrix.php" class="btn btn-link btn-block">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 font-weight-bold">Configured by plant</h5>
            <?php if ($plantsInMatrix): ?>
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
        <div class="alert alert-light border">No assignments yet. Add a role on the left.</div>
        <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-bordered clgp-datatable mb-0">
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
                        <tr>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['plant']) ?></td>
                            <td><?= htmlspecialchars($row['department'] === 'All' ? 'All departments' : $row['department']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars(clgp_step_label($row['approval_step'])) ?></span></td>
                            <td><?= htmlspecialchars($row['emp_code']) ?></td>
                            <td><?= htmlspecialchars($row['emp_name']) ?></td>
                            <td><?= htmlspecialchars($row['emp_email']) ?></td>
                            <td class="text-nowrap">
                                <a href="?edit=<?= (int)$row['matrix_id'] ?><?= $viewPlant ? '&plant=' . urlencode($viewPlant) : '' ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Remove this assignment?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$row['matrix_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">×</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var plantOnlyRoles = <?= json_encode(array_values($CLGP_MATRIX_PLANT_ROLES)) ?>;
    var plantTimer = null, empTimer = null;
    var $plantSearch = document.getElementById('plantSearch');
    var $plant = document.getElementById('plant');
    var $plantBox = document.getElementById('plantResults');
    var $dept = document.getElementById('department');
    var $deptAll = document.getElementById('departmentAll');
    var $deptGroup = document.getElementById('departmentGroup');
    var $role = document.getElementById('approval_step');
    var $empSearch = document.getElementById('empSearch');
    var $empBox = document.getElementById('empResults');

    function isPlantOnlyRole() {
        return plantOnlyRoles.indexOf($role.value) >= 0;
    }

    function syncDepartmentField() {
        var plantOnly = isPlantOnlyRole();
        if (plantOnly) {
            $deptGroup.style.display = 'none';
            $dept.disabled = true;
            $dept.removeAttribute('name');
            $deptAll.disabled = false;
            $deptAll.setAttribute('name', 'department');
            $empSearch.disabled = !$plant.value;
        } else {
            $deptGroup.style.display = '';
            $deptAll.disabled = true;
            $deptAll.removeAttribute('name');
            $dept.setAttribute('name', 'department');
            $empSearch.disabled = !($dept.value && $plant.value);
        }
    }

    $role.addEventListener('change', function () {
        syncDepartmentField();
        clearEmployee();
    });
    syncDepartmentField();

    function clearEmployee() {
        $empSearch.value = '';
        document.getElementById('emp_code').value = '';
        document.getElementById('emp_name').value = '';
        document.getElementById('emp_email').value = '';
        $empBox.style.display = 'none';
    }

    function loadDepartments(plant, selected) {
        $dept.innerHTML = '<option value="">Loading…</option>';
        $dept.disabled = true;
        $empSearch.disabled = true;
        clearEmployee();
        if (!plant) {
            $dept.innerHTML = '<option value="">— Select plant first —</option>';
            return;
        }
        fetch('../api/ams_lookup.php?type=departments&plant=' + encodeURIComponent(plant))
            .then(function (r) { return r.json(); })
            .then(function (rows) {
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
            })
            .catch(function () {
                $dept.innerHTML = '<option value="">Failed to load</option>';
            });
    }

    $plantSearch.addEventListener('input', function () {
        clearTimeout(plantTimer);
        $plant.value = '';
        $dept.innerHTML = '<option value="">— Select plant first —</option>';
        $dept.disabled = true;
        $empSearch.disabled = true;
        clearEmployee();
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
        clearEmployee();
        syncDepartmentField();
    });

    $empSearch.addEventListener('input', function () {
        clearTimeout(empTimer);
        var v = this.value.trim();
        var plant = $plant.value;
        var dept = isPlantOnlyRole() ? '' : $dept.value;
        if (!plant) {
            $empBox.innerHTML = '<div class="list-group-item small text-muted">Select plant first</div>';
            $empBox.style.display = 'block';
            return;
        }
        if (!isPlantOnlyRole() && !dept) {
            $empBox.innerHTML = '<div class="list-group-item small text-muted">Select plant and department first</div>';
            $empBox.style.display = 'block';
            return;
        }
        if (v.length < 2) { $empBox.style.display = 'none'; return; }
        empTimer = setTimeout(function () {
            var url = '../api/search_employee.php?q=' + encodeURIComponent(v)
                + '&plant=' + encodeURIComponent(plant);
            if (dept) {
                url += '&department=' + encodeURIComponent(dept);
            }
            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    $empBox.innerHTML = '';
                    if (!rows.length) {
                        $empBox.innerHTML = '<div class="list-group-item small text-muted">No matches</div>';
                    } else {
                        rows.forEach(function (e) {
                            var a = document.createElement('button');
                            a.type = 'button';
                            a.className = 'list-group-item list-group-item-action small';
                            a.textContent = (e.empCode || '') + ' — ' + (e.empName || '') + (e.empBusiEmail ? ' (' + e.empBusiEmail + ')' : '');
                            a.addEventListener('click', function () {
                                document.getElementById('emp_code').value = e.empCode || '';
                                document.getElementById('emp_name').value = e.empName || '';
                                document.getElementById('emp_email').value = e.empBusiEmail || '';
                                $empSearch.value = (e.empCode || '') + ' - ' + (e.empName || '');
                                $empBox.style.display = 'none';
                            });
                            $empBox.appendChild(a);
                        });
                    }
                    $empBox.style.display = 'block';
                });
        }, 300);
    });

    document.getElementById('matrixForm').addEventListener('submit', function (ev) {
        if (!$plant.value || !document.getElementById('emp_code').value) {
            ev.preventDefault();
            alert('Please select Plant and Employee from the AMS lists.');
            return;
        }
        if (!isPlantOnlyRole() && !$dept.value) {
            ev.preventDefault();
            alert('Please select Department.');
        }
    });

    document.addEventListener('click', function (ev) {
        if (!$plantBox.contains(ev.target) && ev.target !== $plantSearch) $plantBox.style.display = 'none';
        if (!$empBox.contains(ev.target) && ev.target !== $empSearch) $empBox.style.display = 'none';
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
