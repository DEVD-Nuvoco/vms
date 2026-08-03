<?php
require_once __DIR__ . '/../config.php';
clgp_require_role(['admin']);

$pageTitle = 'Workmen';
$activeNav = 'workmen';
global $CLGP_SHIFTS;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $result = clgp_save_workman($_POST);
    $_SESSION['clgp_mess'] = $result['ok'] ? 'Workman added.' : $result['message'];
    header('Location: workmen.php');
    exit;
}

$workmen = clgp_list_workmen(null);
$contractors = clgp_list_contractors('Active');
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="clgp-title mb-4">Workman Master</h2>
<p class="text-muted">Time Office selects workmen from this list when creating Late IN / Early Out applications.</p>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white font-weight-bold">Add Workman</div>
            <div class="card-body">
                <form method="post" id="workmanForm" autocomplete="off">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Workman ID Code *</label>
                        <input type="text" name="workman_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Workman Name *</label>
                        <input type="text" name="workman_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Contractor *</label>
                        <select name="contractor_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach ($contractors as $c): ?>
                            <option value="<?= (int)$c['contractor_id'] ?>"><?= htmlspecialchars($c['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plant * <small class="text-muted">(search AMS)</small></label>
                        <input type="text" id="plantSearch" class="form-control" placeholder="Type plant e.g. RCP" autocomplete="off" required>
                        <input type="hidden" name="plant" id="plant" value="" required>
                        <div id="plantResults" class="list-group mt-1" style="max-height:180px;overflow:auto;display:none;position:relative;z-index:30;"></div>
                    </div>
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department" id="department" class="form-control" required disabled>
                            <option value="">— Select plant first —</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Shift</label>
                        <select name="shift" class="form-control">
                            <?php foreach ($CLGP_SHIFTS as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-clgp btn-block">Save</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-bordered clgp-datatable">
                    <thead>
                        <tr><th>Code</th><th>Name</th><th>Vendor</th><th>Plant</th><th>Dept</th><th>Shift</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workmen as $w): ?>
                        <tr>
                            <td><?= htmlspecialchars($w['workman_code']) ?></td>
                            <td><?= htmlspecialchars($w['workman_name']) ?></td>
                            <td><?= htmlspecialchars($w['vendor_name']) ?></td>
                            <td><?= htmlspecialchars($w['plant']) ?></td>
                            <td><?= htmlspecialchars($w['department']) ?></td>
                            <td><?= htmlspecialchars($w['shift']) ?></td>
                            <td><?= clgp_status_badge($w['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var plantTimer = null;
    var $plantSearch = document.getElementById('plantSearch');
    var $plant = document.getElementById('plant');
    var $plantBox = document.getElementById('plantResults');
    var $dept = document.getElementById('department');

    function resetDepartments(placeholder) {
        $dept.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder || '— Select plant first —';
        $dept.appendChild(opt);
        $dept.disabled = true;
    }

    function loadDepartments(plant) {
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

    document.getElementById('workmanForm').addEventListener('submit', function (ev) {
        if (!$plant.value || !$dept.value) {
            ev.preventDefault();
            alert('Please select Plant and Department from the AMS lists.');
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
