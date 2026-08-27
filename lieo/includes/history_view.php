<?php
/**
 * Shared My History view — expects $historyRows (array of app-related rows with application_id).
 * Optional $historyMode: 'approver' | 'timeoffice' | 'security'
 */
$historyMode = $historyMode ?? 'approver';
$historyRows = $historyRows ?? [];
$trailStore = [];
?>

<?php
lieo_page_header(
    'My History',
    'Paginated list of your actions. Use View to open the full remark trail from Time Office through gate.'
);
?>

<?php if (!$historyRows): ?>
    <?php lieo_panel_open('Activity'); ?>
        <?php lieo_empty_state('No history yet', 'Your approvals, attestations, or gate actions will appear here.'); ?>
    <?php lieo_panel_close(); ?>
<?php else: ?>
<?php lieo_panel_open('Activity', count($historyRows)); ?>
        <div class="lieo-history-table-wrap">
            <table class="table lieo-table lieo-history-table mb-0">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Application</th>
                        <th>Type</th>
                        <th>Workman</th>
                        <th>Plant / Dept</th>
                        <th>My Action</th>
                        <th>Remark preview</th>
                        <th>Status</th>
                        <th class="text-center" style="width:100px;">Trail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historyRows as $idx => $row): ?>
                    <?php
                        $appId = (int) ($row['application_id'] ?? 0);
                        $trail = lieo_application_remark_trail($appId, $row);
                        $trailId = 'lieo-trail-' . ($row['approval_id'] ?? $idx) . '-' . $appId;
                        $trailStore[$trailId] = lieo_render_remark_trail_html($trail);
                        $myActionLabel = '—';
                        if ($historyMode === 'approver') {
                            $myActionLabel = (($row['action'] ?? '') === 'approve') ? 'Approved' : 'Rejected';
                            $myActionLabel .= ' (' . lieo_step_label($row['step'] ?? '') . ')';
                        } elseif ($historyMode === 'timeoffice') {
                            $myActionLabel = (($row['my_action'] ?? '') === 'attest') ? 'Attested' : 'Created';
                        } elseif ($historyMode === 'security') {
                            $myActionLabel = !empty($row['gate_in_at']) ? 'Gate IN' : 'Gate OUT';
                        }
                    ?>
                    <tr>
                        <td class="text-nowrap small"><?= htmlspecialchars($row['acted_at'] ?? '—') ?></td>
                        <td class="small font-weight-bold"><?= htmlspecialchars($row['application_no'] ?? '—') ?></td>
                        <td class="small"><?= htmlspecialchars(lieo_application_type_label($row['application_type'] ?? '—')) ?></td>
                        <td class="small">
                            <?= htmlspecialchars($row['workman_name'] ?? '—') ?>
                            <span class="text-muted d-block"><?= htmlspecialchars($row['workman_code'] ?? '') ?></span>
                        </td>
                        <td class="small"><?= htmlspecialchars(($row['plant'] ?? '') . ' / ' . ($row['department'] ?? '')) ?></td>
                        <td class="small">
                            <?php if (stripos($myActionLabel, 'Reject') !== false): ?>
                            <span class="badge badge-danger"><?= htmlspecialchars($myActionLabel) ?></span>
                            <?php elseif (stripos($myActionLabel, 'Approved') !== false || stripos($myActionLabel, 'Created') !== false || stripos($myActionLabel, 'Attest') !== false || stripos($myActionLabel, 'Gate') !== false): ?>
                            <span class="badge badge-success"><?= htmlspecialchars($myActionLabel) ?></span>
                            <?php else: ?>
                            <?= htmlspecialchars($myActionLabel) ?>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars(lieo_remark_trail_summary($trail)) ?></td>
                        <td class="small"><?= lieo_status_badge($row['app_status'] ?? '') ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary lieo-trail-open"
                                    data-app-no="<?= htmlspecialchars($row['application_no'] ?? '') ?>"
                                    data-target-id="<?= htmlspecialchars($trailId) ?>">
                                View
                            </button>
                            <div class="d-none lieo-trail-source" id="<?= htmlspecialchars($trailId) ?>"><?= $trailStore[$trailId] ?? '' ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php lieo_panel_close(); ?>

<div class="modal fade" id="lieoTrailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title lieo-title mb-0">Remark trail — <span id="lieoTrailModalAppNo"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="lieoTrailModalBody"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
