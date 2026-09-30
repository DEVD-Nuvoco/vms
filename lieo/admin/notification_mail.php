<?php
/** Per-plant, per-context CC lists for LIEO notification mail (Contractor Reactivation, Approval Matrix, Department Master). */
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);

$LIEO_NOTIFY_CONTEXTS = [
    'reactivation' => 'Contractor Reactivation',
    'approval_matrix' => 'LIEO Users',
    'department' => 'Department Master',
];

$pageTitle = 'Notification Configuration';
$activeNav = 'notify';

$plant = lieo_ams_canonical_plant($_GET['plant'] ?? $_POST['plant'] ?? '');

function lieo_admin_notify_url(string $plant = ''): string
{
    $q = [];
    if ($plant !== '') {
        $q['plant'] = $plant;
    }
    return 'notification_mail.php?' . http_build_query($q);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postPlant = lieo_ams_canonical_plant($_POST['plant'] ?? $plant);
    if ($postPlant === '') {
        $_SESSION['lieo_mess'] = 'Select a plant first.';
        $_SESSION['lieo_mess_type'] = 'danger';
    } elseif ($action === 'add') {
        $ctxs = array_values(array_intersect((array) ($_POST['contexts'] ?? []), array_keys($LIEO_NOTIFY_CONTEXTS)));
        $emailsIn = array_filter(array_map('trim', (array) ($_POST['emails'] ?? [])));
        $errs = [];
        if (!$ctxs || !$emailsIn) {
            $errs[] = 'Select at least one notification and one email.';
        }
        foreach ($ctxs as $c) {
            foreach ($emailsIn as $em) {
                $result = lieo_add_plant_notify_email($postPlant, $em, $c);
                if (empty($result['ok'])) {
                    $errs[] = $em . ': ' . ($result['message'] ?? 'Could not add.');
                }
            }
        }
        $_SESSION['lieo_mess'] = $errs ? implode(' ', $errs) : 'Email(s) added.';
        if ($errs) {
            $_SESSION['lieo_mess_type'] = 'danger';
            $_SESSION['lieo_alert'] = ['title' => 'Cannot add email', 'message' => implode(' ', $errs), 'variant' => 'danger'];
        }
    } elseif (in_array($action, ['toggle', 'edit', 'delete'], true)) {
        // One table row = one email; its notifications live in one DB row each. Act on the whole group.
        $oldEmail = strtolower(trim((string) ($_POST['old_email'] ?? '')));
        $group = [];
        foreach (array_keys($LIEO_NOTIFY_CONTEXTS) as $ck) {
            foreach (lieo_list_plant_notify_rows($postPlant, $ck) as $r) {
                if (strtolower(trim((string) $r['email'])) === $oldEmail) {
                    $group[$ck] = (int) $r['notify_id'];
                }
            }
        }
        if ($action === 'toggle') {
            $st = ($_POST['status'] ?? '') === 'Active' ? 'Active' : 'Inactive';
            foreach ($group as $id) {
                lieo_set_plant_notify_status($id, $postPlant, $st);
            }
            $_SESSION['lieo_mess'] = 'Status updated.';
        } elseif ($action === 'delete') {
            foreach ($group as $id) {
                lieo_delete_plant_notify_email($id, $postPlant);
            }
            $_SESSION['lieo_mess'] = 'Email removed.';
        } else {
            $newEmail = strtolower(trim((string) (((array) ($_POST['emails'] ?? []))[0] ?? '')));
            $want = array_intersect((array) ($_POST['contexts'] ?? []), array_keys($LIEO_NOTIFY_CONTEXTS));
            $result = ['ok' => true];
            if (!$want) {
                $result = ['ok' => false, 'message' => 'Select at least one notification.'];
            }
            foreach ($group as $ck => $id) {
                if (empty($result['ok'])) {
                    break;
                }
                if (!in_array($ck, $want, true)) {
                    lieo_delete_plant_notify_email($id, $postPlant);
                } elseif ($newEmail !== $oldEmail) {
                    $result = lieo_update_plant_notify_email($id, $postPlant, $newEmail);
                }
            }
            foreach ($want as $ck) {
                if (!empty($result['ok']) && !isset($group[$ck])) {
                    $result = lieo_add_plant_notify_email($postPlant, $newEmail, $ck);
                }
            }
            $_SESSION['lieo_mess'] = $result['ok'] ? 'Updated.' : ($result['message'] ?? 'Could not update.');
            if (empty($result['ok'])) {
                $_SESSION['lieo_mess_type'] = 'danger';
            }
        }
    }
    header('Location: ' . lieo_admin_notify_url($postPlant));
    exit;
}

$rows = [];
if ($plant !== '') {
    foreach ($LIEO_NOTIFY_CONTEXTS as $ck => $cl) {
        foreach (lieo_list_plant_notify_rows($plant, $ck) as $r) {
            $r['context_label'] = $cl;
            $r['context_key'] = $ck;
            $rows[] = $r;
        }
    }
}
$groups = [];
foreach ($rows as $r) {
    $k = strtolower(trim((string) $r['email']));
    $groups[$k]['email'] = $r['email'];
    $groups[$k]['ctx'][$r['context_key']] = $r['context_label'];
    $groups[$k]['active'] = ($groups[$k]['active'] ?? false) || ($r['status'] ?? '') === 'Active';
}
$amsEmails = $plant !== '' ? lieo_list_ams_emails_for_plant($plant) : [];
$amsByEmail = [];
foreach ($amsEmails as $ae) {
    $amsByEmail[strtolower((string) $ae['email'])] = $ae;
}
require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Notification Configuration</h2>
<p class="text-muted mb-4">
    Select a plant, the notification(s) and the CC email(s) to add. CC lists are kept separate per notification. Whoever the mail already goes <strong>TO</strong>
    for that event (HR department HOD for reactivation, the assigned employee for LIEO Users, Time Office for
    Department Master) also gets these addresses in <strong>CC</strong>. Choose from AMS emails for that plant only.
</p>


<div class="card shadow-sm mb-4">
    <div class="card-header bg-white font-weight-bold">Select plant</div>
    <div class="card-body">
        <form method="get" class="form-inline" id="plantPickForm" autocomplete="off">
            <div class="position-relative mr-2" style="min-width:260px;">
                <input type="text" id="plantSearch" class="form-control"
                       placeholder="Type plant e.g. RCP" value="<?= htmlspecialchars($plant) ?>" autocomplete="off">
                <input type="hidden" name="plant" id="plant" value="<?= htmlspecialchars($plant) ?>">
                <div id="plantResults" class="list-group" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:50;max-height:220px;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.14);"></div>
            </div>
            <button class="btn btn-lieo" type="submit" id="plantOpenBtn" <?= $plant === '' ? 'disabled' : '' ?>>Open</button>
        </form>
    </div>
</div>

<?php if ($plant === ''): ?>
<div class="alert alert-light border">Select a plant above to view/manage its notification CC list.</div>
<?php else: ?>

<style>
.lieo-notify-card { overflow: visible; }
.lieo-notify-form-row { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .75rem 1rem; }
.lieo-email-picker { position: relative; flex: 1 1 360px; min-width: 280px; max-width: 560px; }
.lieo-email-picker > label { display: block; font-weight: 600; margin-bottom: .35rem; color: #0f172a; }
.lieo-email-picker-controls { display: flex; align-items: stretch; gap: .5rem; }
.lieo-email-search-wrap { position: relative; flex: 1 1 auto; min-width: 0; }
.lieo-email-picker-controls .btn-lieo { flex: 0 0 auto; align-self: stretch; padding-left: 1.25rem; padding-right: 1.25rem; white-space: nowrap; }
.lieo-email-search-wrap .list-group { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 50; max-height: 280px; overflow: auto; background: #fff; box-shadow: 0 12px 32px rgba(15, 23, 42, .14); border: 1px solid #e2e8f0; border-radius: 8px; }
.lieo-email-picker .list-group-item { cursor: pointer; border: 0; border-bottom: 1px solid #f1f5f9; padding: .6rem .9rem; text-align: left; background: #fff; width: 100%; }
.lieo-email-picker .list-group-item:last-child { border-bottom: 0; }
.lieo-email-picker .list-group-item:hover:not(:disabled), .lieo-email-picker .list-group-item.active { background: #ecfdf3; }
.lieo-email-picker .email-main { font-weight: 600; color: #0f172a; font-size: .9rem; }
.lieo-email-picker .email-meta { font-size: .75rem; color: #64748b; margin-top: 2px; }
.lieo-email-picker .email-used { opacity: .55; cursor: not-allowed; }
.lieo-notify-hint { font-size: .8rem; color: #64748b; margin-top: .45rem; }
.lieo-notify-table th { white-space: nowrap; }
.lieo-notify-table td { vertical-align: middle; }
.lieo-notify-code { font-weight: 600; color: #b91c1c; font-variant-numeric: tabular-nums; }
</style>

<div class="card shadow-sm mb-4 lieo-notify-card">
    <div class="card-body">
        <form method="post" id="notifyEmailForm" autocomplete="off">
            <input type="hidden" name="action" value="add" id="notifyAction">
            <input type="hidden" name="old_email" value="" id="notifyEditId" disabled>
            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
            <div id="notifyEmailInputs"></div>
            <div class="lieo-notify-form-row">
                <div class="lieo-email-picker" style="flex:0 1 260px;min-width:220px;">
                    <label>Notification</label>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-left" type="button" id="ctxBtn" data-toggle="dropdown">Select notification(s)</button>
                        <div class="dropdown-menu p-2" style="min-width:100%;" onclick="event.stopPropagation()">
                            <?php foreach ($LIEO_NOTIFY_CONTEXTS as $ck => $cl): ?>
                            <label class="d-block mb-1"><input type="checkbox" class="ctx-cb" name="contexts[]" value="<?= $ck ?>"> <?= htmlspecialchars($cl) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="lieo-email-picker" id="notifyEmailPicker">
                    <label for="notifyEmailSearch">CC email (AMS — <?= htmlspecialchars($plant) ?>)</label>
                    <div id="notifyChips" class="mb-1"></div>
                    <div class="lieo-email-picker-controls">
                        <div class="lieo-email-search-wrap">
                            <input type="text" id="notifyEmailSearch" class="form-control"
                                   placeholder="Search by email, name or code…" autocomplete="off"
                                   <?= $amsEmails ? '' : 'disabled' ?>>
                            <div id="notifyEmailResults" class="list-group" style="display:none;" role="listbox"></div>
                        </div>
                        <button class="btn btn-lieo" type="submit" id="notifyEmailAddBtn" disabled>Add</button>
                        <button class="btn btn-outline-secondary" type="button" id="notifyCancelEdit" style="display:none;">Cancel</button>
                    </div>
                    <div class="lieo-notify-hint">
                        <?= count($amsEmails) ?> plant email<?= count($amsEmails) === 1 ? '' : 's' ?> available from AMS.
                        Select from the list, then click Add.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm py-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 lieo-notify-table">
                <thead>
                    <tr><th>Plant</th><th>Notification</th><th>Emp Code</th><th>Name</th><th>Email</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (!$groups): ?>
                    <tr><td colspan="7" class="text-muted text-center py-4">No CC emails yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($groups as $em => $g): ?>
                    <?php
                        $ams = $amsByEmail[$em] ?? null;
                        $status = $g['active'] ? 'Active' : 'Inactive';
                        $old = htmlspecialchars((string) $g['email']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($plant) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $g['ctx'])) ?></td>
                        <td class="lieo-notify-code"><?= htmlspecialchars((string) ($ams['emp_code'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($ams['emp_name'] ?? '—')) ?></td>
                        <td><?= $old ?></td>
                        <td><?= lieo_status_badge($status) ?></td>
                        <td class="text-nowrap text-right">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
                                <input type="hidden" name="old_email" value="<?= $old ?>">
                                <input type="hidden" name="status" value="<?= $status === 'Active' ? 'Inactive' : 'Active' ?>">
                                <button class="btn btn-sm btn-outline-secondary"><?= $status === 'Active' ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-primary lieo-edit-btn"
                                    data-ctx="<?= htmlspecialchars(implode(',', array_keys($g['ctx']))) ?>" data-email="<?= $old ?>">Edit</button>
                            <form method="post" class="d-inline"
                                  data-lieo-confirm="Remove this notification email?"
                                  data-lieo-confirm-title="Remove email"
                                  data-lieo-danger="1"
                                  data-lieo-confirm-ok="Remove">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
                                <input type="hidden" name="old_email" value="<?= $old ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var $search = document.getElementById('plantSearch');
    var $plant = document.getElementById('plant');
    var $box = document.getElementById('plantResults');
    var $form = document.getElementById('plantPickForm');
    var $openBtn = document.getElementById('plantOpenBtn');
    if ($search && $plant && $box && $form) {
        var timer = null;
        function hideResults() { $box.style.display = 'none'; }
        function pickPlant(code) {
            $search.value = code;
            $plant.value = code;
            hideResults();
            if ($openBtn) $openBtn.disabled = false;
            $form.submit();
        }
        function runSearch(q) {
            q = (q || '').trim();
            if (q.length < 2) { hideResults(); return; }
            fetch('../api/ams_lookup.php?type=plants&q=' + encodeURIComponent(q))
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
                    $box.style.display = 'block';
                });
        }
        $search.addEventListener('input', function () {
            clearTimeout(timer);
            var v = this.value;
            timer = setTimeout(function () { runSearch(v); }, 220);
        });
        document.addEventListener('click', function (e) {
            if (!$search.closest('.card-body').contains(e.target)) hideResults();
        });
    }

    <?php if ($plant !== ''): ?>
    var emails = <?= json_encode(array_values($amsEmails), JSON_UNESCAPED_UNICODE) ?>;
    var $esearch = document.getElementById('notifyEmailSearch');
    var selected = [];
    var $inputs = document.getElementById('notifyEmailInputs');
    var $chips = document.getElementById('notifyChips');
    var $ebox = document.getElementById('notifyEmailResults');
    var $btn = document.getElementById('notifyEmailAddBtn');
    var $eform = document.getElementById('notifyEmailForm');
    var $picker = document.getElementById('notifyEmailPicker');
    var $ctxBtn = document.getElementById('ctxBtn');
    document.querySelectorAll('.ctx-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var n = [].map.call(document.querySelectorAll('.ctx-cb:checked'), function (x) { return x.parentNode.textContent.trim(); });
            $ctxBtn.textContent = n.length ? n.join(', ') : 'Select notification(s)';
        });
    });
    var editing = false;
    if ($esearch && $ebox && $picker) {
        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function sync() {
            $inputs.innerHTML = ''; $chips.innerHTML = '';
            selected.forEach(function (em, i) {
                $inputs.insertAdjacentHTML('beforeend', '<input type="hidden" name="emails[]" value="' + esc(em) + '">');
                $chips.insertAdjacentHTML('beforeend', '<span class="badge badge-secondary mr-1 p-2">' + esc(em) + ' <a href="#" data-rm="' + i + '" class="text-white">&times;</a></span>');
            });
            $btn.disabled = !selected.length;
        }
        function setEdit(on, ctxs, email) {
            editing = on;
            document.getElementById('notifyAction').value = on ? 'edit' : 'add';
            var $id = document.getElementById('notifyEditId');
            $id.disabled = !on; $id.value = on ? email : '';
            var list = on ? ctxs.split(',') : [];
            document.querySelectorAll('.ctx-cb').forEach(function (cb) { cb.checked = list.indexOf(cb.value) >= 0; });
            $ctxBtn.textContent = on ? [].map.call(document.querySelectorAll('.ctx-cb:checked'), function (x) { return x.parentNode.textContent.trim(); }).join(', ') : 'Select notification(s)';
            selected = on ? [email] : [];
            $btn.textContent = on ? 'Save' : 'Add';
            document.getElementById('notifyCancelEdit').style.display = on ? '' : 'none';
            sync();
            if (on) { $eform.scrollIntoView({ behavior: 'smooth', block: 'center' }); $esearch.focus(); }
        }
        document.querySelectorAll('.lieo-edit-btn').forEach(function (b) {
            b.addEventListener('click', function () { setEdit(true, b.getAttribute('data-ctx'), b.getAttribute('data-email')); });
        });
        document.getElementById('notifyCancelEdit').addEventListener('click', function () { setEdit(false); });
        $chips.addEventListener('click', function (e) {
            var r = e.target.closest('[data-rm]');
            if (!r) return;
            e.preventDefault();
            selected.splice(+r.getAttribute('data-rm'), 1);
            sync();
        });
        function render(q) {
            q = (q || '').trim().toLowerCase();
            var html = '';
            var shown = 0;
            for (var i = 0; i < emails.length; i++) {
                var row = emails[i];
                var email = String(row.email || '');
                var name = String(row.emp_name || '');
                var code = String(row.emp_code || '');
                var dept = String(row.department || '');
                var hay = (email + ' ' + name + ' ' + code + ' ' + dept).toLowerCase();
                if (q && hay.indexOf(q) < 0) continue;
                var used = selected.indexOf(email) >= 0;
                html += '<button type="button" class="list-group-item list-group-item-action' + (used ? ' email-used' : '') + '" data-email="' + esc(email) + '"' + (used ? ' disabled title="Selected"' : '') + '>'
                    + '<div class="email-main">' + esc(email) + (used ? ' <span class="text-muted">(selected)</span>' : '') + '</div>'
                    + '<div class="email-meta">' + (code ? esc(code) + ' · ' : '') + esc(name || '—') + (dept ? ' · ' + esc(dept) : '') + '</div></button>';
                shown += 1;
                if (shown >= 80) break;
            }
            $ebox.innerHTML = shown ? html : '<div class="list-group-item text-muted">No matching plant email.</div>';
            $ebox.style.display = 'block';
        }
        $esearch.addEventListener('focus', function () { render($esearch.value); });
        $esearch.addEventListener('input', function () { render($esearch.value); });
        $ebox.addEventListener('click', function (e) {
            var b = e.target.closest('[data-email]');
            if (!b || b.disabled) return;
            var email = b.getAttribute('data-email') || '';
            if (editing) selected = [email]; else if (email && selected.indexOf(email) < 0) selected.push(email);
            sync();
            $esearch.value = '';
            $ebox.style.display = 'none';
        });
        document.addEventListener('click', function (e) {
            if (!$picker.contains(e.target)) $ebox.style.display = 'none';
        });
        $eform.addEventListener('submit', function (e) {
            if (!selected.length || !document.querySelector('.ctx-cb:checked')) {
                e.preventDefault();
                if (window.lieoAlert) lieoAlert({ title: 'Incomplete', message: 'Select at least one notification and one email.' });
            }
        });
    }
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
