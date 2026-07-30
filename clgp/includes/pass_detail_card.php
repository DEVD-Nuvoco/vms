<?php
/** Reusable Gate Pass detail block. Expects $pass (normalized array). */
$pass = clgp_normalize_pass($pass);
$showSignatures = $showSignatures ?? false;
?>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white font-weight-bold text-success">Gate Pass Details</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th width="45%">Workman Name</th><td><?= htmlspecialchars($pass['workman']) ?></td></tr>
                    <tr><th>Workman ID Code</th><td><?= htmlspecialchars($pass['workman_id_code'] ?: '—') ?></td></tr>
                    <tr><th>Name of Contractor</th><td><?= htmlspecialchars($pass['contractor']) ?></td></tr>
                    <tr><th>Department</th><td><?= htmlspecialchars($pass['department']) ?></td></tr>
                    <tr><th>Shift</th><td><?= htmlspecialchars($pass['shift'] ?: '—') ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th width="45%">Pass ID</th><td>#<?= (int)$pass['id'] ?></td></tr>
                    <tr><th>Plant</th><td><?= htmlspecialchars($pass['plant']) ?></td></tr>
                    <tr><th>Vendor Type</th><td><?= htmlspecialchars($pass['vendor_type']) ?></td></tr>
                    <tr><th>Pool</th><td><?= htmlspecialchars($pass['pool']) ?></td></tr>
                    <tr><th>Status</th><td><?= clgp_status_badge($pass['status']) ?></td></tr>
                </table>
            </div>
        </div>

        <?php if ($showSignatures && !empty($pass['attendance_logs'])):
            $lastLog = end($pass['attendance_logs']);
        ?>
        <hr>
        <h6 class="text-success"><?= htmlspecialchars($lastLog['type']) ?> — Signatures</h6>
        <div class="row text-center small">
            <div class="col-md-4">
                <div class="border rounded p-3 bg-light">
                    <div class="text-muted mb-1">Workman</div>
                    <div class="font-weight-bold"><?= htmlspecialchars($lastLog['sig_workman']) ?></div>
                    <div class="signature-line mt-3 border-top pt-2">Signature</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 bg-light">
                    <div class="text-muted mb-1">Reporting Superior</div>
                    <div class="font-weight-bold"><?= htmlspecialchars($lastLog['sig_superior']) ?></div>
                    <div class="signature-line mt-3 border-top pt-2">Signature</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 bg-light">
                    <div class="text-muted mb-1">HOD</div>
                    <div class="font-weight-bold"><?= htmlspecialchars($lastLog['sig_hod']) ?></div>
                    <div class="signature-line mt-3 border-top pt-2">Signature</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
