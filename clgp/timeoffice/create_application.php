<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['timeoffice']);

$pageTitle = 'Create Application';
$activeNav = 'create';

global $CLGP_SHIFTS;

$contractors = clgp_list_contractors('Active');
$userPlant = clgp_ams_canonical_plant($_SESSION['clgp_plant'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = clgp_create_application([
        'workman_name' => $_POST['workman_name'] ?? '',
        'workman_code' => $_POST['workman_code'] ?? '',
        'contractor_id' => (int) ($_POST['contractor_id'] ?? 0),
        'plant' => $_POST['plant'] ?? '',
        'department' => $_POST['department'] ?? '',
        'shift' => $_POST['shift'] ?? '',
        'application_type' => $_POST['application_type'] ?? '',
        'reason' => $_POST['reason'] ?? '',
        'created_by' => (int) $_SESSION['clgp_user_id'],
    ]);
    if ($result['ok']) {
        $_SESSION['clgp_mess'] = 'Success! Application ' . $result['application_no']
            . ' submitted. It is now pending Supervisor approval.';
        $_SESSION['clgp_mess_type'] = 'success';
    } else {
        $_SESSION['clgp_mess'] = $result['message'] ?: 'Could not create application.';
        $_SESSION['clgp_mess_type'] = 'danger';
    }
    header('Location: create_application.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-2">Create Late IN / Early Out</h2>
<p class="text-muted mb-4">Enter workman details and submit. Approval: Supervisor → N-1 → HOD, then Security closes at gate.</p>

<div class="card shadow-sm" style="max-width:720px;">
    <div class="card-body">
        <form method="post" id="createAppForm" autocomplete="off">
            <h6 class="text-success mb-3">Workman details</h6>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Workman Name *</label>
                    <input type="text" name="workman_name" class="form-control" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Workman ID Code *</label>
                    <input type="text" name="workman_code" class="form-control" required placeholder="e.g. WM-RCP-001">
                </div>
            </div>
            <div class="form-group">
                <label>Contractor *</label>
                <select name="contractor_id" class="form-control" required>
                    <option value="">— Select contractor —</option>
                    <?php foreach ($contractors as $c): ?>
                    <option value="<?= (int) $c['contractor_id'] ?>">
                        <?= htmlspecialchars($c['contractor_name']) ?>
                        (<?= htmlspecialchars($c['contractor_type'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Plant * <small class="text-muted">(search AMS)</small></label>
                    <input type="text" id="plantSearch" class="form-control" placeholder="Type plant e.g. RCP"
                           value="<?= htmlspecialchars($userPlant) ?>" autocomplete="off" required>
                    <input type="hidden" name="plant" id="plant" value="<?= htmlspecialchars($userPlant) ?>" required>
                    <div id="plantResults" class="list-group mt-1" style="max-height:180px;overflow:auto;display:none;position:relative;z-index:30;"></div>
                </div>
                <div class="form-group col-md-6">
                    <label>Department *</label>
                    <select name="department" id="department" class="form-control" required <?= $userPlant === '' ? 'disabled' : '' ?>>
                        <option value=""><?= $userPlant === '' ? '— Select plant first —' : '— Select department —' ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Shift</label>
                <select name="shift" class="form-control">
                    <?php foreach ($CLGP_SHIFTS as $s): ?>
                    <option><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <hr>
            <h6 class="text-success mb-3">Application</h6>
            <div class="form-group">
                <label>Application Type *</label>
                <select name="application_type" class="form-control" required>
                    <option value="Late Coming">Late IN (Entry)</option>
                    <option value="Early Going">Early Out (Exit)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason *</label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Reason for late coming / early going"></textarea>
            </div>
            <button type="submit" class="btn btn-clgp">Submit for Approval</button>
        </form>
    </div>
</div>

<script>
(function () {
    var plantTimer = null;
    var $plantSearch = document.getElementById('plantSearch');
    var $plant = document.getElementById('plant');
    var $plantBox = document.getElementById('plantResults');
    var $dept = document.getElementById('department');
    var initialPlant = <?= json_encode($userPlant) ?>;

    function resetDepartments(placeholder) {
        $dept.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder || '— Select plant first —';
        $dept.appendChild(opt);
        $dept.disabled = true;
    }

    function loadDepartments(plant, selected) {
        resetDepartments('Loading…');
        if (!plant) {
            resetDepartments('— Select plant first —');
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
            })
            .catch(function () {
                resetDepartments('Failed to load');
            });
    }

    $plantSearch.addEventListener('input', function () {
        clearTimeout(plantTimer);
        $plant.value = '';
        resetDepartments('— Select plant first —');
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
                                loadDepartments(p);
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

    document.addEventListener('click', function (ev) {
        if (!$plantBox.contains(ev.target) && ev.target !== $plantSearch) {
            $plantBox.style.display = 'none';
        }
    });

    document.getElementById('createAppForm').addEventListener('submit', function (ev) {
        if (!$plant.value || !$dept.value) {
            ev.preventDefault();
            alert('Please select Plant and Department from the AMS lists.');
        }
    });

    if (initialPlant) {
        loadDepartments(initialPlant);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
