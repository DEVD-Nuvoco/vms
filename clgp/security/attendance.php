<?php

require_once __DIR__ . '/../config.php';

clgp_require_role(['security']);



$pageTitle = 'Gate Attendance';

$activeNav = 'attendance';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $appId = (int) ($_POST['application_id'] ?? 0);

    $action = $_POST['action'] ?? '';

    $remark = trim($_POST['remark'] ?? '');

    $app = clgp_get_application($appId);

    if ($app && $action === 'close') {

        $action = $app['access_type'] === 'Entry' ? 'in' : 'out';

    }

    $result = clgp_gate_action($appId, $action, (int) $_SESSION['clgp_user_id'], $remark);

    $_SESSION['clgp_mess'] = $result['ok']

        ? 'Application closed at gate (' . ($action === 'in' ? 'IN' : 'OUT') . ').'

        : $result['message'];

    $_SESSION['clgp_mess_type'] = $result['ok'] ? 'success' : 'danger';

    header('Location: attendance.php');

    exit;

}



$plantScope = clgp_apply_session_plant_scope([]);

$applications = clgp_list_applications($plantScope);

$readyCount = 0;

foreach ($applications as $row) {

    if (clgp_application_ready_for_gate($row)) {

        $readyCount++;

    }

}



require_once __DIR__ . '/../includes/header.php';



$userPlant = trim($_SESSION['clgp_plant'] ?? '');

clgp_page_header(

    'Gate Attendance',

    'Early IN / Early Out (LIEO) — all applications for your plant'

        . ($userPlant !== '' ? ' (' . $userPlant . ')' : '')

        . '. After HOD approval, close at gate with a mandatory remark.'

);

?>



<?php clgp_panel_open('Plant applications', count($applications), $readyCount . ' ready to close at gate'); ?>

    <?php if (!$applications): ?>

        <?php clgp_empty_state('No applications for your plant', 'LC/EG requests appear here once Time Office creates them.'); ?>

    <?php else: ?>

    <div class="clgp-table-wrap">

        <table class="table clgp-table clgp-datatable">

            <thead>

                <tr>

                    <th>Date</th>

                    <th>No</th>

                    <th>Type</th>

                    <th>Workman</th>

                    <th>Dept</th>

                    <th>Access</th>

                    <th>Status</th>

                    <th>Gate IN</th>

                    <th>Gate OUT</th>

                    <th class="text-center">Action</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($applications as $p): ?>

                <?php

                    $canClose = clgp_application_ready_for_gate($p);

                    $closeLabel = $p['access_type'] === 'Entry' ? 'Close (Gate IN)' : 'Close (Gate OUT)';

                ?>

                <tr>

                    <td class="text-nowrap small"><?= htmlspecialchars($p['application_date'] ?? '—') ?></td>

                    <td class="font-weight-bold"><?= htmlspecialchars($p['application_no']) ?></td>

                    <td><?= htmlspecialchars(clgp_application_type_label($p['application_type'] ?? '')) ?></td>

                    <td>

                        <?= htmlspecialchars($p['workman_name']) ?>

                        <span class="text-muted d-block small"><?= htmlspecialchars($p['workman_code']) ?></span>

                    </td>

                    <td><?= htmlspecialchars($p['department']) ?></td>

                    <td><?= htmlspecialchars($p['access_type']) ?></td>

                    <td><?= clgp_status_badge($p['status']) ?></td>

                    <td class="text-nowrap small"><?= htmlspecialchars($p['gate_in_at'] ?: '—') ?></td>

                    <td class="text-nowrap small"><?= htmlspecialchars($p['gate_out_at'] ?: '—') ?></td>

                    <td class="text-center">

                        <?php if ($canClose): ?>

                        <button type="button" class="btn btn-sm btn-clgp clgp-gate-close-open"

                                data-app-id="<?= (int) $p['application_id'] ?>"

                                data-app-no="<?= htmlspecialchars($p['application_no']) ?>"

                                data-close-label="<?= htmlspecialchars($closeLabel) ?>">

                            <?= htmlspecialchars($closeLabel) ?>

                        </button>

                        <?php else: ?>

                        <span class="text-muted small">—</span>

                        <?php endif; ?>

                    </td>

                </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <?php endif; ?>

<?php clgp_panel_close(); ?>



<div class="modal fade" id="clgpGateCloseModal" tabindex="-1" role="dialog" aria-hidden="true">

    <div class="modal-dialog" role="document">

        <div class="modal-content">

            <form method="post" id="clgpGateCloseForm">

                <div class="modal-header py-2">

                    <h5 class="modal-title clgp-page-title mb-0" style="font-size:1rem;">

                        Close application — <span id="clgpGateCloseAppNo"></span>

                    </h5>

                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>

                </div>

                <div class="modal-body">

                    <input type="hidden" name="application_id" id="clgpGateCloseAppId" value="">

                    <input type="hidden" name="action" value="close">

                    <p class="small text-muted mb-2" id="clgpGateCloseHint"></p>

                    <div class="form-group mb-0">

                        <label class="small font-weight-bold">Remark <span class="text-danger">*</span></label>

                        <textarea name="remark" id="clgpGateCloseRemark" class="form-control" rows="3" required

                                  maxlength="500" placeholder="Reason / observation at gate (required)"></textarea>

                    </div>

                </div>

                <div class="modal-footer py-2">

                    <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Cancel</button>

                    <button type="submit" class="btn btn-sm btn-clgp" id="clgpGateCloseSubmit">Confirm close</button>

                </div>

            </form>

        </div>

    </div>

</div>



<script>

$(function () {

    var $modal = $('#clgpGateCloseModal');

    if ($modal.length && !$modal.parent().is('body')) {

        $modal.appendTo('body');

    }

    $(document).on('click', '.clgp-gate-close-open', function () {

        var appId = this.getAttribute('data-app-id');

        var appNo = this.getAttribute('data-app-no') || '';

        var label = this.getAttribute('data-close-label') || 'Close application';

        $('#clgpGateCloseAppId').val(appId);

        $('#clgpGateCloseAppNo').text(appNo);

        $('#clgpGateCloseHint').text(label + ' — remark is mandatory for LIEO (Late IN / Early Out).');

        $('#clgpGateCloseRemark').val('');

        if ($.fn.modal) {

            $modal.modal('show');

        }

        setTimeout(function () { $('#clgpGateCloseRemark').trigger('focus'); }, 300);

    });

});

</script>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>


