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
$amsHint = $plant !== '' ? lieo_list_ams_departments($plant) : [];
$overviewRows = lieo_list_all_plant_departments($filterPlant !== '' ? $filterPlant : null);

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Department Master</h2>
<p class="text-muted mb-4">
    Add extra departments plant-wise when an employee’s AMS department is missing — for example before assigning
    <strong>Time Office</strong> in the Approval Matrix. AMS departments are always included automatically;
    entries here are add-ons only.
</p>

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
    Select a plant above to view AMS departments and add extras for that plant.
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
                    <th>Status</th>
                    <th class="text-nowrap">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$overviewRows): ?>
                <tr><td colspan="4" class="text-muted text-center py-4">No extra departments saved yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($overviewRows as $r): ?>
                <?php
                    $rowPlant = lieo_ams_canonical_plant((string) ($r['plant'] ?? '')) ?: (string) ($r['plant'] ?? '');
                    $deptId = (int) ($r['dept_id'] ?? 0);
                ?>
                <tr>
                    <td class="font-weight-bold"><?= htmlspecialchars($rowPlant) ?></td>
                    <td><?= htmlspecialchars($r['department_name'] ?? '') ?></td>
                    <td><?= lieo_status_badge($r['status'] ?? '') ?></td>
                    <td class="text-nowrap">
                        <a href="<?= htmlspecialchars(lieo_admin_departments_url($rowPlant, $filterPlant, $deptId)) ?>"
                           class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (($r['status'] ?? '') === 'Active'): ?>
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
