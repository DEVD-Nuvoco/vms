<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);

$pageTitle = 'Department Master';
$activeNav = 'departments';

$plant = lieo_ams_canonical_plant($_GET['plant'] ?? $_POST['plant'] ?? '');
$filterPlant = lieo_ams_canonical_plant($_GET['filter_plant'] ?? '');
$plantsWithDeptData = lieo_list_plants_with_department_data();
if ($filterPlant !== '' && !in_array($filterPlant, $plantsWithDeptData, true)) {
    $filterPlant = '';
}

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
        $deptName = (string) ($_POST['department_name'] ?? '');
        $result = lieo_submit_department_request($postPlant, $deptName, $id, !empty($_POST['is_hr']), (string) ($_POST['ams_department_name'] ?? ''));
        if (!empty($result['ok'])) {
            $_SESSION['lieo_mess'] = (string) ($result['message'] ?? 'Request submitted.');
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
        $deptId = (int) ($_POST['id'] ?? 0);
        $deptRow = lieo_find_plant_department_by_id($deptId, $postPlant);
        lieo_delete_plant_department($deptId, $postPlant);
        if ($deptRow) {
            lieo_notify_department_changed($postPlant, (string) $deptRow['department_name'], 'deleted');
        }
        $_SESSION['lieo_mess'] = 'Department deleted.';
    } elseif ($action === 'deactivate') {
        $deptId = (int) ($_POST['id'] ?? 0);
        $deptRow = lieo_find_plant_department_by_id($deptId, $postPlant);
        lieo_set_plant_department_status($deptId, $postPlant, 'Inactive');
        if ($deptRow) {
            lieo_notify_department_changed($postPlant, (string) $deptRow['department_name'], 'deactivated');
        }
        $_SESSION['lieo_mess'] = 'Department deactivated.';
    } elseif ($action === 'activate') {
        $deptId = (int) ($_POST['id'] ?? 0);
        $deptRow = lieo_find_plant_department_by_id($deptId, $postPlant);
        lieo_set_plant_department_status($deptId, $postPlant, 'Active');
        if ($deptRow) {
            lieo_notify_department_changed($postPlant, (string) $deptRow['department_name'], 'activated');
        }
        $_SESSION['lieo_mess'] = 'Department activated.';
    }

    header('Location: ' . lieo_admin_departments_url($postPlant, $postFilter));
    exit;
}

$reqPlant = $plant !== '' ? $plant : $filterPlant;
$pendingDeptRequests = $reqPlant !== '' ? lieo_list_department_requests($reqPlant, 'Pending') : [];
$pendingDeptTargets = lieo_list_pending_department_target_requests();

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    foreach (lieo_list_all_plant_departments(null) as $r) {
        if ((int) ($r['dept_id'] ?? 0) === $editId) {
            $editRow = $r;
            break;
        }
    }
}
if ($editRow && $plant === '') {
    $plant = lieo_ams_canonical_plant((string) ($editRow['plant'] ?? ''));
}
$overviewRows = lieo_list_all_plant_departments($filterPlant !== '' ? $filterPlant : null);

$overviewCombined = [];
foreach ($overviewRows as $r) {
    $overviewCombined[] = [
        'plant' => lieo_ams_canonical_plant((string) ($r['plant'] ?? '')) ?: (string) ($r['plant'] ?? ''),
        'department_name' => (string) ($r['department_name'] ?? ''),
        'source' => 'extra',
        'is_hr' => $r['is_hr'] ?? 'f',
        'status' => $r['status'] ?? '',
        'dept_id' => (int) ($r['dept_id'] ?? 0),
    ];
}
usort($overviewCombined, function ($a, $b) {
    return $a['plant'] === $b['plant']
        ? strcasecmp($a['department_name'], $b['department_name'])
        : strcasecmp($a['plant'], $b['plant']);
});

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Department Master</h2>
<p class="text-muted mb-4">
    Departments are maintained manually here, plant-wise — only departments added here appear in LIEO Users and applications.
    Adding or editing a department needs the HR department HOD's approval before it takes effect.
</p>

<?php if ($pendingDeptRequests): ?>
<div class="alert alert-warning">
    <strong><?= count($pendingDeptRequests) ?> department request<?= count($pendingDeptRequests) === 1 ? '' : 's' ?></strong>
    awaiting HR department HOD approval for <?= htmlspecialchars($reqPlant) ?>:
    <ul class="mb-0 mt-1 pl-3">
        <?php foreach ($pendingDeptRequests as $r): ?>
        <li><?= $r['request_type'] === 'edit' ? 'Update' : 'Add' ?> — <?= htmlspecialchars((string) $r['department_name']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4 lieo-dept-plant-pick">
    <div class="card-header bg-white font-weight-bold">Select plant</div>
    <div class="card-body">
        <form method="get" class="lieo-dept-plant-form" id="plantPickForm" autocomplete="off">
            <?php if ($filterPlant !== ''): ?>
                <input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>">
            <?php endif; ?>
            <?php if ($plant !== ''): ?>
                <p class="small text-muted mb-2">
                    Currently viewing: <strong class="text-success"><?= htmlspecialchars($plant) ?></strong>
                    <span class="text-muted">— type below to switch plant</span>
                </p>
            <?php endif; ?>
            <div class="lieo-dept-plant-search-wrap">
                <label class="d-block mb-1">Plant <small class="text-muted">(search AMS — type at least 2 letters)</small></label>
                <div class="d-flex flex-wrap align-items-stretch" style="gap:.5rem;">
                    <div class="lieo-dept-plant-input-wrap flex-grow-1">
                        <input type="text" id="plantSearch" class="form-control"
                               placeholder="<?= $plant !== '' ? 'Search another plant…' : 'Type plant e.g. RCP' ?>"
                               value="<?= $plant !== '' ? '' : htmlspecialchars($plant) ?>"
                               autocomplete="off">
                        <input type="hidden" name="plant" id="plant" value="<?= htmlspecialchars($plant) ?>">
                        <div id="plantResults" class="list-group lieo-plant-results" style="display:none;"></div>
                    </div>
                    <div class="d-flex align-items-end">
                        <button class="btn btn-lieo" type="submit" id="plantOpenBtn" <?= $plant === '' ? 'disabled' : '' ?>>Open</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.lieo-ams-row { background: #f0f9ff; }
.lieo-dept-plant-pick .card-body { overflow: visible; }
.lieo-dept-plant-input-wrap {
    position: relative;
    min-width: 240px;
}
.lieo-plant-results {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 1050;
    max-height: 220px;
    overflow: auto;
    background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .14);
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.lieo-plant-results .list-group-item {
    cursor: pointer;
    padding: .45rem .75rem;
    font-size: .875rem;
    border: 0;
    border-bottom: 1px solid #f1f5f9;
}
.lieo-plant-results .list-group-item:last-child { border-bottom: 0; }
.lieo-plant-results .list-group-item:hover {
    background: #ecfdf3;
}
</style>

<?php if ($plant === ''): ?>
<div class="alert alert-light border">
    Select a plant above to add departments for that plant.
</div>
<?php else: ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold">Add department —<?= htmlspecialchars($plant) ?></div>
    <div class="card-body">
        <form method="post" class="form-inline flex-wrap">
            <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int) $editRow['dept_id'] ?>"><?php endif; ?>
            <label class="mr-2 mb-2">Department name</label>
            <input type="text" name="department_name" id="deptNameInput" class="form-control mr-2 mb-2" required
                   value="<?= htmlspecialchars($editRow['department_name'] ?? '') ?>">
            <div class="custom-control custom-checkbox mr-2 mb-2">
                <input type="checkbox" class="custom-control-input" id="isHrCheck" name="is_hr" value="1"
                       <?= !empty($editRow['is_hr']) && $editRow['is_hr'] === 't' ? 'checked' : '' ?>>
                <label class="custom-control-label" for="isHrCheck">This is the HR department</label>
            </div>
            <button class="btn btn-lieo mb-2"><?= $editRow ? 'Update' : 'Add' ?></button>
            <?php if ($editRow): ?>
                <a href="<?= htmlspecialchars(lieo_admin_departments_url($plant, $filterPlant)) ?>" class="btn btn-link mb-2">Cancel</a>
            <?php endif; ?>
            <small class="text-muted d-block w-100 mt-1">
                Only one department per plant can be marked HR — checking it here unchecks any other.
                This flags which department unlocks the HOD's "User Approval" / "Plant Users" tabs, and Time Office /
                Security can only be assigned to employees whose AMS Department has this same name.
            </small>
        </form>
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
            <?php if ($editId > 0): ?>
                <input type="hidden" name="edit" value="<?= (int) $editId ?>">
            <?php endif; ?>
            <label class="mr-2 mb-0 small">Filter plant</label>
            <select name="filter_plant" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All plants with data</option>
                <?php foreach ($plantsWithDeptData as $pl): ?>
                    <option value="<?= htmlspecialchars($pl) ?>" <?= $filterPlant === $pl ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pl) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$plantsWithDeptData): ?>
                <span class="small text-muted">No extra departments saved yet.</span>
            <?php endif; ?>
            <noscript><button class="btn btn-sm btn-lieo" type="submit">Filter</button></noscript>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0 lieo-datatable lieo-dept-overview-table">
            <thead>
                <tr>
                    <th>Plant</th>
                    <th>Department</th>
                    <th>HR?</th>
                    <th>Status</th>
                    <th class="text-nowrap">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$overviewCombined): ?>
                <tr><td colspan="5" class="text-muted text-center py-4">No departments to show yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($overviewCombined as $r): ?>
                <?php
                    $rowPlant = $r['plant'];
                    $deptId = $r['dept_id'];
                ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($rowPlant) ?></td>
                    <td><?= htmlspecialchars($r['department_name']) ?></td>
                    <td><?= $r['is_hr'] === 't' ? '<span class="badge badge-success">HR</span>' : '' ?></td>
                    <td>
                        <?= lieo_status_badge($r['status']) ?>
                        <?php if (isset($pendingDeptTargets[$deptId])): ?>
                        <span class="badge badge-warning" title="Edit pending HR department HOD approval">Edit pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a href="<?= htmlspecialchars(lieo_admin_departments_url($rowPlant, $filterPlant, $deptId)) ?>"
                           class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if ($r['status'] === 'Active'): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Deactivate this department?"
                              data-lieo-confirm-title="Deactivate department"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Deactivate">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="plant" value="<?= htmlspecialchars($rowPlant) ?>">
                            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
                            <input type="hidden" name="id" value="<?= $deptId ?>">
                            <button class="btn btn-sm btn-outline-warning">Deactivate</button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="plant" value="<?= htmlspecialchars($rowPlant) ?>">
                            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
                            <input type="hidden" name="id" value="<?= $deptId ?>">
                            <button class="btn btn-sm btn-outline-success">Activate</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Delete this department permanently?"
                              data-lieo-confirm-title="Delete department"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Delete">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plant" value="<?= htmlspecialchars($rowPlant) ?>">
                            <?php if ($filterPlant !== ''): ?><input type="hidden" name="filter_plant" value="<?= htmlspecialchars($filterPlant) ?>"><?php endif; ?>
                            <input type="hidden" name="id" value="<?= $deptId ?>">
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
(function () {
    var $search = document.getElementById('plantSearch');
    var $plant = document.getElementById('plant');
    var $box = document.getElementById('plantResults');
    var $form = document.getElementById('plantPickForm');
    var $openBtn = document.getElementById('plantOpenBtn');
    if (!$search || !$plant || !$box || !$form) return;

    var timer = null;
    var minChars = 2;

    function hideResults() {
        $box.style.display = 'none';
    }

    function showResults() {
        $box.style.display = 'block';
    }

    function syncOpenBtn() {
        if ($openBtn) {
            $openBtn.disabled = !$plant.value;
        }
    }

    function pickPlant(code) {
        $search.value = '';
        $plant.value = code;
        hideResults();
        syncOpenBtn();
        $form.submit();
    }

    function runSearch(q) {
        q = (q || '').trim();
        if (q.length < minChars) {
            hideResults();
            $box.innerHTML = '';
            return;
        }
        var url = '../api/ams_lookup.php?type=plants&q=' + encodeURIComponent(q);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                $box.innerHTML = '';
                if (!rows || !rows.length) {
                    $box.innerHTML = '<div class="list-group-item small text-muted">No plants found</div>';
                } else {
                    rows.slice(0, 40).forEach(function (p) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.textContent = p;
                        btn.addEventListener('click', function () { pickPlant(p); });
                        $box.appendChild(btn);
                    });
                }
                showResults();
            })
            .catch(function () {
                $box.innerHTML = '<div class="list-group-item small text-danger">Search failed</div>';
                showResults();
            });
    }

    $search.addEventListener('input', function () {
        clearTimeout(timer);
        var v = this.value.trim();
        timer = setTimeout(function () { runSearch(v); }, 220);
    });

    $search.addEventListener('focus', function () {
        var v = this.value.trim();
        if (v.length >= minChars) {
            runSearch(v);
        } else {
            hideResults();
        }
    });

    $form.addEventListener('submit', function (e) {
        if (!$plant.value) {
            e.preventDefault();
            if (window.lieoAlert) {
                lieoAlert({ title: 'Select plant', message: 'Type at least 2 letters, pick a plant from the list, then click Open.' });
            }
            $search.focus();
        }
    });

    document.addEventListener('click', function (e) {
        var wrap = $search.closest('.lieo-dept-plant-input-wrap');
        if (wrap && !wrap.contains(e.target)) {
            hideResults();
        }
    });

    syncOpenBtn();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
