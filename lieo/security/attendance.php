<?php

require_once __DIR__ . '/../config.php';

lieo_require_role(['security']);



$pageTitle = 'Gate Attendance';

$activeNav = 'attendance';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int) ($_POST['application_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remark = trim($_POST['remark'] ?? '');
    $gateAt = trim($_POST['gate_at'] ?? '');
    $app = lieo_get_application($appId);
    if ($app && $action === 'close') {
        $action = $app['access_type'] === 'Entry' ? 'in' : 'out';
    }
    $result = lieo_gate_action($appId, $action, (int) $_SESSION['lieo_user_id'], $remark, $gateAt);
    $_SESSION['lieo_mess'] = $result['ok']
        ? 'Application closed at gate (' . ($action === 'in' ? 'IN' : 'OUT') . ').'
        : $result['message'];
    $_SESSION['lieo_mess_type'] = $result['ok'] ? 'success' : 'danger';
    header('Location: attendance.php');
    exit;
}



$plantScope = lieo_apply_session_plant_scope([]);

$applications = lieo_list_applications($plantScope);

$readyCount = 0;

foreach ($applications as $row) {

    if (lieo_application_ready_for_gate($row)) {

        $readyCount++;

    }

}



require_once __DIR__ . '/../includes/header.php';



$userPlant = trim($_SESSION['lieo_plant'] ?? '');

lieo_page_header(

    'Gate Attendance',

    'Early IN / Early Out (LIEO) — all applications for your plant'

        . ($userPlant !== '' ? ' (' . $userPlant . ')' : '')

        . '. After HOD approval, close at gate with a mandatory remark.'

);

?>



<?php lieo_panel_open('Plant applications', count($applications), $readyCount . ' ready to close at gate'); ?>

    <?php if (!$applications): ?>

        <?php lieo_empty_state('No applications for your plant', 'LC/EG requests appear here once N-1 creates them and HOD approves.'); ?>

    <?php else: ?>

    <div class="lieo-table-wrap">

        <table class="table lieo-table lieo-datatable">

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

                    $canClose = lieo_application_ready_for_gate($p);

                    $closeLabel = $p['access_type'] === 'Entry' ? 'Close (Gate IN)' : 'Close (Gate OUT)';

                ?>

                <tr>

                    <td class="text-nowrap small"><?= htmlspecialchars($p['application_date'] ?? '—') ?></td>

                    <td class="font-weight-bold"><?= htmlspecialchars($p['application_no']) ?></td>

                    <td><?= htmlspecialchars(lieo_application_type_label($p['application_type'] ?? '')) ?></td>

                    <td>

                        <?= htmlspecialchars($p['workman_name']) ?>

                        <span class="text-muted d-block small"><?= htmlspecialchars($p['workman_code']) ?></span>

                    </td>

                    <td><?= htmlspecialchars($p['department']) ?></td>

                    <td><?= htmlspecialchars($p['access_type']) ?></td>

                    <td><?= lieo_status_badge($p['status']) ?></td>

                    <td class="text-nowrap small"><?= htmlspecialchars($p['gate_in_at'] ?: '—') ?></td>

                    <td class="text-nowrap small"><?= htmlspecialchars($p['gate_out_at'] ?: '—') ?></td>

                    <td class="text-center">

                        <?php if ($canClose): ?>

                        <button type="button" class="btn btn-sm btn-lieo lieo-gate-close-open"

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

<?php lieo_panel_close(); ?>

<div class="modal fade" id="lieoGateCloseModal" tabindex="-1" role="dialog" aria-labelledby="lieoGateCloseTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="post" id="lieoGateCloseForm">
                <div class="modal-header py-2">
                    <h5 class="modal-title mb-0" id="lieoGateCloseTitle" style="font-size:1rem;">
                        Close application — <span id="lieoGateCloseAppNo"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="lieoGateCloseAppId" value="">
                    <input type="hidden" name="action" value="close">
                    <p class="small text-muted mb-3" id="lieoGateCloseHint"></p>
                    <div class="form-group">
                        <label class="small font-weight-bold" for="lieoGateCloseAt">
                            Gate date &amp; time <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" name="gate_at" id="lieoGateCloseAt"
                               class="form-control" required>
                        <small class="text-muted">Defaults to now — change if the workman entered/left at a different time.</small>
                        <div class="lieo-field-error" id="lieoGateCloseAtError"></div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold" for="lieoGateCloseRemark">Remark <span class="text-danger">*</span></label>
                        <textarea name="remark" id="lieoGateCloseRemark" class="form-control" rows="3" required
                                  maxlength="500" placeholder="Reason / observation at gate (required)"></textarea>
                        <div class="lieo-field-error" id="lieoGateCloseRemarkError"></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-lieo" id="lieoGateCloseSubmit">Confirm close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
(function () {
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function nowLocalDatetimeValue() {
        var d = new Date();
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function setFieldError(id, message) {
        var input = document.getElementById(id);
        var box = document.getElementById(id + 'Error');
        if (input) {
            if (message) input.classList.add('is-invalid');
            else input.classList.remove('is-invalid');
        }
        if (box) {
            box.textContent = message || '';
            box.style.display = message ? 'block' : 'none';
            box.style.color = '#dc3545';
            box.style.fontSize = '.875rem';
            box.style.marginTop = '.25rem';
        }
    }

    function bindGateClose($) {
        var $modal = $('#lieoGateCloseModal');
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }

        $(document).off('click.lieoGateClose', '.lieo-gate-close-open')
            .on('click.lieoGateClose', '.lieo-gate-close-open', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var appId = this.getAttribute('data-app-id') || '';
                var appNo = this.getAttribute('data-app-no') || '';
                var label = this.getAttribute('data-close-label') || 'Close application';
                $('#lieoGateCloseAppId').val(appId);
                $('#lieoGateCloseAppNo').text(appNo);
                $('#lieoGateCloseHint').text(label + ' — enter actual gate time and a mandatory remark.');
                $('#lieoGateCloseRemark').val('');
                $('#lieoGateCloseAt').val(nowLocalDatetimeValue());
                setFieldError('lieoGateCloseAt', '');
                setFieldError('lieoGateCloseRemark', '');
                if ($modal.length && $.fn.modal) {
                    $modal.modal('show');
                } else if (window.lieoAlert) {
                    lieoAlert({
                        title: 'Cannot open gate form',
                        message: 'Please refresh the page and try again.',
                        variant: 'danger'
                    });
                }
                setTimeout(function () { $('#lieoGateCloseAt').trigger('focus'); }, 350);
            });

        $('#lieoGateCloseForm').off('submit.lieoGateClose').on('submit.lieoGateClose', function (e) {
            var at = String($('#lieoGateCloseAt').val() || '').trim();
            var remark = String($('#lieoGateCloseRemark').val() || '').trim();
            var ok = true;
            if (!at) {
                setFieldError('lieoGateCloseAt', 'Gate date & time is required.');
                ok = false;
            } else {
                setFieldError('lieoGateCloseAt', '');
            }
            if (!remark) {
                setFieldError('lieoGateCloseRemark', 'Remark is required.');
                ok = false;
            } else {
                setFieldError('lieoGateCloseRemark', '');
            }
            if (!ok) {
                e.preventDefault();
            }
        });
    }

    if (window.jQuery) {
        bindGateClose(window.jQuery);
        return;
    }
    var n = 0;
    var t = setInterval(function () {
        n += 1;
        if (window.jQuery) {
            clearInterval(t);
            bindGateClose(window.jQuery);
        } else if (n > 200) {
            clearInterval(t);
        }
    }, 25);
})();
</script>


