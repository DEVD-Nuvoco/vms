<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['timeoffice']);

$pageTitle = 'Contractor Master';
$activeNav = 'contractors';
global $LIEO_CONTRACTOR_TYPES;

$plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = $action === 'edit' ? (int) ($_POST['id'] ?? 0) : null;
        // Always force plant from logged-in Time Office session (never from client).
        $_POST['plant'] = $plant;
        $result = lieo_save_contractor($_POST, $id);
        if (!empty($result['ok'])) {
            $_SESSION['lieo_mess'] = 'Contractor saved for plant ' . ($result['plant'] ?? $plant) . '.';
            $_SESSION['lieo_mess_type'] = 'success';
        } else {
            $_SESSION['lieo_mess'] = $result['message'] ?? 'Save failed.';
            $_SESSION['lieo_mess_type'] = 'danger';
            $_SESSION['lieo_alert'] = [
                'title' => 'Cannot save contractor',
                'message' => $result['message'] ?? 'Save failed.',
                'variant' => 'danger',
            ];
        }
    } elseif ($action === 'deactivate') {
        lieo_deactivate_contractor((int) ($_POST['id'] ?? 0), trim($_POST['reason'] ?? ''));
        $_SESSION['lieo_mess'] = 'Contractor deactivated.';
    } elseif ($action === 'request_reactivation') {
        lieo_request_reactivation((int) ($_POST['id'] ?? 0));
        $_SESSION['lieo_mess'] = 'Reactivation requested — pending HR Head approval.';
    }
    header('Location: contractors.php');
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = $editId ? lieo_get_contractor($editId) : null;
if ($editRow && $plant !== '') {
    $editPlant = lieo_ams_canonical_plant($editRow['plant'] ?? '');
    if ($editPlant !== '' && $editPlant !== $plant) {
        $_SESSION['lieo_mess'] = 'That contractor belongs to another plant.';
        $_SESSION['lieo_mess_type'] = 'danger';
        header('Location: contractors.php');
        exit;
    }
}

$contractors = $plant !== '' ? lieo_list_contractors(null, $plant) : [];
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Contractor Master — <?= htmlspecialchars($plant !== '' ? $plant : 'No plant') ?></h2>
<p class="text-muted mb-4">
    Plant is taken from your Time Office login
    (<strong><?= htmlspecialchars($plant !== '' ? $plant : 'not set') ?></strong>)
    and stored with every contractor.
</p>

<?php if ($plant === ''): ?>
<div class="alert alert-warning">
    Your Time Office account has no plant assigned, so contractors cannot be listed or saved.
    Ask Admin to set your plant in the Approval Matrix.
</div>
<?php else: ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold text-success"><?= $editRow ? 'Edit Contractor' : 'Add Contractor' ?></div>
    <div class="card-body">
        <form method="post" id="lieoContractorForm" autocomplete="off" novalidate>
            <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['contractor_id'] ?>"><?php endif; ?>

            <h6 class="text-success font-weight-bold text-uppercase mb-3" style="font-size:.875rem;letter-spacing:.04em;">Plant</h6>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="lieoPlant">Plant</label>
                    <input type="text" id="lieoPlant" class="form-control" value="<?= htmlspecialchars($plant) ?>" readonly>
                </div>
            </div>

            <h6 class="text-success font-weight-bold text-uppercase mb-3 mt-2" style="font-size:.875rem;letter-spacing:.04em;">Contractor</h6>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="lieoContractorName">Contractor Name <span class="text-danger">*</span></label>
                    <input type="text" id="lieoContractorName" name="contractor_name" class="form-control" required
                           value="<?= htmlspecialchars($editRow['contractor_name'] ?? '') ?>">
                    <div class="lieo-field-error" data-error-for="contractor_name"></div>
                </div>
                <div class="form-group col-md-3">
                    <label for="lieoContractorEmail">Contractor Email <span class="text-danger">*</span></label>
                    <input type="email" id="lieoContractorEmail" name="email" class="form-control" required
                           value="<?= htmlspecialchars($editRow['email'] ?? '') ?>">
                    <div class="lieo-field-error" data-error-for="email"></div>
                </div>
                <div class="form-group col-md-3">
                    <label for="lieoContractorMobile">Contractor Mobile Number <span class="text-danger">*</span></label>
                    <input type="text" id="lieoContractorMobile" name="contractor_mobile" class="form-control" required
                           inputmode="numeric" maxlength="10" autocomplete="off"
                           value="<?= htmlspecialchars($editRow['contractor_mobile'] ?? '') ?>">
                    <div class="lieo-field-error" data-error-for="contractor_mobile"></div>
                </div>
                <div class="form-group col-md-3">
                    <label for="lieoContractorType">Contractor Type <span class="text-danger">*</span></label>
                    <select id="lieoContractorType" name="contractor_type" class="form-control" required>
                        <?php foreach ($LIEO_CONTRACTOR_TYPES as $t): ?>
                        <option <?= (($editRow['contractor_type'] ?? '') === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="lieo-field-error" data-error-for="contractor_type"></div>
                </div>
            </div>

            <h6 class="text-success font-weight-bold text-uppercase mb-3 mt-2" style="font-size:.875rem;letter-spacing:.04em;">Supervisor</h6>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="lieoSupervisorName">Supervisor Name <span class="text-danger">*</span></label>
                    <input type="text" id="lieoSupervisorName" name="supervisor_name" class="form-control" required
                           value="<?= htmlspecialchars($editRow['supervisor_name'] ?? '') ?>">
                    <div class="lieo-field-error" data-error-for="supervisor_name"></div>
                </div>
                <div class="form-group col-md-3">
                    <label for="lieoSupervisorMobile">Supervisor Mobile Number <span class="text-danger">*</span></label>
                    <input type="text" id="lieoSupervisorMobile" name="supervisor_mobile" class="form-control" required
                           inputmode="numeric" maxlength="10" autocomplete="off"
                           value="<?= htmlspecialchars($editRow['supervisor_mobile'] ?? '') ?>">
                    <div class="lieo-field-error" data-error-for="supervisor_mobile"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-lieo"><?= $editRow ? 'Update Contractor' : 'Save Contractor' ?></button>
            <?php if ($editRow): ?><a href="contractors.php" class="btn btn-link">Cancel edit</a><?php endif; ?>
        </form>
    </div>
</div>

<style>
#lieoContractorForm .lieo-field-error {
    display: none;
    color: #dc3545;
    font-size: .875rem;
    margin-top: .25rem;
    line-height: 1.3;
}
#lieoContractorForm .lieo-field-error.is-visible {
    display: block;
}
#lieoContractorForm .form-control.is-invalid {
    border-color: #dc3545;
    background-image: none;
}
</style>
<script>
(function () {
    var form = document.getElementById('lieoContractorForm');
    if (!form) return;

    function setError(name, message) {
        var input = form.querySelector('[name="' + name + '"]');
        var box = form.querySelector('[data-error-for="' + name + '"]');
        if (input) {
            if (message) input.classList.add('is-invalid');
            else input.classList.remove('is-invalid');
        }
        if (box) {
            box.textContent = message || '';
            if (message) box.classList.add('is-visible');
            else box.classList.remove('is-visible');
        }
    }

    function digitsOnly(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function validateMobile(name, label) {
        var input = form.querySelector('[name="' + name + '"]');
        if (!input) return true;
        var raw = String(input.value || '');
        var digits = digitsOnly(raw);
        if (raw !== digits) {
            input.value = digits;
        }
        if (digits === '') {
            setError(name, label + ' is required.');
            return false;
        }
        if (!/^\d+$/.test(digits)) {
            setError(name, label + ' must contain numbers only.');
            return false;
        }
        if (digits.length !== 10) {
            setError(name, label + ' must be exactly 10 digits.');
            return false;
        }
        setError(name, '');
        return true;
    }

    function validateRequired(name, label) {
        var input = form.querySelector('[name="' + name + '"]');
        if (!input) return true;
        var v = String(input.value || '').trim();
        if (v === '') {
            setError(name, label + ' is required.');
            return false;
        }
        setError(name, '');
        return true;
    }

    function validateEmail() {
        var input = form.querySelector('[name="email"]');
        if (!input) return true;
        var v = String(input.value || '').trim();
        if (v === '') {
            setError('email', 'Contractor Email is required.');
            return false;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
            setError('email', 'Enter a valid email address.');
            return false;
        }
        setError('email', '');
        return true;
    }

    ['contractor_mobile', 'supervisor_mobile'].forEach(function (name) {
        var input = form.querySelector('[name="' + name + '"]');
        if (!input) return;
        input.addEventListener('input', function () {
            input.value = digitsOnly(input.value).slice(0, 10);
            validateMobile(name, name === 'contractor_mobile' ? 'Contractor Mobile Number' : 'Supervisor Mobile Number');
        });
        input.addEventListener('blur', function () {
            validateMobile(name, name === 'contractor_mobile' ? 'Contractor Mobile Number' : 'Supervisor Mobile Number');
        });
    });

    form.addEventListener('submit', function (e) {
        var ok = true;
        ok = validateRequired('contractor_name', 'Contractor Name') && ok;
        ok = validateEmail() && ok;
        ok = validateMobile('contractor_mobile', 'Contractor Mobile Number') && ok;
        ok = validateRequired('contractor_type', 'Contractor Type') && ok;
        ok = validateRequired('supervisor_name', 'Supervisor Name') && ok;
        ok = validateMobile('supervisor_mobile', 'Supervisor Mobile Number') && ok;
        if (!ok) {
            e.preventDefault();
            var first = form.querySelector('.form-control.is-invalid');
            if (first) first.focus();
        }
    });
})();
</script>

<div class="card shadow-sm">
    <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center">
        <span>Contractor List</span>
        <span class="text-muted small font-weight-normal"><?= count($contractors) ?> contractor(s) for <?= htmlspecialchars($plant) ?></span>
    </div>
    <div class="card-body">
        <table class="table table-bordered lieo-datatable">
            <thead>
                <tr>
                    <th>Plant</th>
                    <th>Contractor</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Type</th>
                    <th>Supervisor</th>
                    <th>Sup. Mobile</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contractors as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(lieo_ams_canonical_plant($c['plant'] ?? '') ?: ($c['plant'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($c['contractor_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($c['email'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(lieo_format_mobile($c['contractor_mobile'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($c['contractor_type'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($c['supervisor_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars(lieo_format_mobile($c['supervisor_mobile'] ?? '')) ?></td>
                    <td>
                        <?= lieo_status_badge($c['status'] ?? '') ?>
                        <?php if (($c['reactivation_requested'] ?? 'f') === 't'): ?>
                            <span class="badge badge-info">Reactivation pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a href="?edit=<?= (int)$c['contractor_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (($c['status'] ?? '') === 'Active'): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Deactivate this contractor? They will no longer be available for new applications."
                              data-lieo-confirm-title="Deactivate contractor"
                              data-lieo-danger="1"
                              data-lieo-confirm-ok="Deactivate">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="id" value="<?= (int)$c['contractor_id'] ?>">
                            <input type="hidden" name="reason" value="Time Office deactivated">
                            <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                        </form>
                        <?php elseif (($c['reactivation_requested'] ?? 'f') !== 't'): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="request_reactivation">
                            <input type="hidden" name="id" value="<?= (int)$c['contractor_id'] ?>">
                            <button class="btn btn-sm btn-outline-success">Request Reactivation</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
