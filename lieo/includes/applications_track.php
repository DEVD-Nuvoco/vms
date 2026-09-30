<?php
$LIEO_SHIFTS = array_column(lieo_list_shifts(), 'shift_name');
$list = $list ?? [];
$filters = $filters ?? [];
$contractors = $contractors ?? lieo_list_contractors('Active', $_SESSION['lieo_plant'] ?? null);
$contractorMap = $contractorMap ?? lieo_contractor_supervisor_map($_SESSION['lieo_plant'] ?? null);
if (!isset($workmenList)) {
    $workmenList = lieo_list_workmen('Active');
    $scopePlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
    if ($scopePlant !== '') {
        $workmenList = array_filter($workmenList, fn($w) => lieo_ams_canonical_plant($w['plant'] ?? '') === $scopePlant);
    }
}
?>
<h2 class="lieo-title mb-3">Application Tracking</h2>
<form method="get" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-2">
                <label class="small">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group col-md-2">
                <label class="small">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
            </div>
            <div class="form-group col-md-2">
                <label class="small">Status</label>
                <input type="text" name="status" class="form-control form-control-sm" placeholder="e.g. Pending_n1"
                       value="<?= htmlspecialchars($filters['status'] ?? '') ?>">
            </div>
            <div class="form-group col-md-3">
                <label class="small">Workman</label>
                <select name="workman_id" class="form-control form-control-sm">
                    <option value="">All</option>
                    <?php foreach ($workmenList as $w): ?>
                    <option value="<?= (int) $w['workman_id'] ?>" <?= ((int) ($filters['workman_id'] ?? 0) === (int) $w['workman_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($w['workman_name']) ?> (<?= htmlspecialchars($w['workman_code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label class="small">Contractor</label>
                <select name="contractor_id" class="form-control form-control-sm">
                    <option value="">All</option>
                    <?php foreach ($contractors as $c): ?>
                    <option value="<?= (int) $c['contractor_id'] ?>" <?= ((int) ($filters['contractor_id'] ?? 0) === (int) $c['contractor_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['contractor_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2">
                <label class="small">Shift</label>
                <select name="shift" class="form-control form-control-sm">
                    <option value="">All</option>
                    <?php foreach ($LIEO_SHIFTS as $s): ?>
                    <option <?= ($filters['shift'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn btn-sm btn-lieo">Filter</button>
        <a class="btn btn-sm btn-outline-success" href="export_applications.php?<?= htmlspecialchars(http_build_query(array_filter($filters))) ?>">
            <i class="typcn-download"></i> Export to Excel
        </a>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-bordered lieo-datatable">
            <thead>
                <tr>
                    <th>No</th><th>Type</th><th>Workman</th><th>Code</th><th>Shift</th>
                    <th>Contractor</th><th>Supervisor</th>
                    <th>Plant</th><th>Dept</th><th>Date</th><th>Time</th><th>Status</th><th>Step</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['application_no']) ?></td>
                    <td><?= htmlspecialchars(lieo_application_type_label($a['application_type'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($a['workman_name']) ?></td>
                    <td><?= htmlspecialchars($a['workman_code']) ?></td>
                    <td><?= htmlspecialchars($a['shift']) ?></td>
                    <td><?= htmlspecialchars($a['contractor_name']) ?></td>
                    <td><?= htmlspecialchars($contractorMap[(int) $a['contractor_id']] ?? '') ?></td>
                    <td><?= htmlspecialchars($a['plant']) ?></td>
                    <td><?= htmlspecialchars($a['department']) ?></td>
                    <td><?= htmlspecialchars($a['application_date']) ?></td>
                    <td><?= htmlspecialchars($a['created_at'] ? date('H:i', strtotime($a['created_at'])) : '') ?></td>
                    <td><?= lieo_status_badge($a['status']) ?></td>
                    <td><?= htmlspecialchars(lieo_step_label($a['current_step'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
