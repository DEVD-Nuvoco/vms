<?php
/** Contractor-reactivation CC list — moved from Time Office to Admin (view-only now). */
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);

$pageTitle = 'Contractor Reactivation Notification';
$activeNav = 'notify';

$plant = lieo_ams_canonical_plant($_GET['plant'] ?? $_POST['plant'] ?? '');

function lieo_admin_notify_url(string $plant = ''): string
{
    return 'notification_mail.php' . ($plant !== '' ? ('?plant=' . rawurlencode($plant)) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postPlant = lieo_ams_canonical_plant($_POST['plant'] ?? $plant);
    if ($postPlant === '') {
        $_SESSION['lieo_mess'] = 'Select a plant first.';
        $_SESSION['lieo_mess_type'] = 'danger';
    } elseif ($action === 'add') {
        $result = lieo_add_plant_notify_email($postPlant, $_POST['email'] ?? '');
        $_SESSION['lieo_mess'] = $result['ok'] ? 'Email added.' : ($result['message'] ?? 'Could not add.');
        if (empty($result['ok'])) {
            $_SESSION['lieo_mess_type'] = 'danger';
            $_SESSION['lieo_alert'] = [
                'title' => 'Cannot add email',
                'message' => (string) ($result['message'] ?? 'Could not add.'),
                'variant' => 'danger',
            ];
        }
    } elseif ($action === 'delete') {
        lieo_delete_plant_notify_email((int) ($_POST['id'] ?? 0), $postPlant);
        $_SESSION['lieo_mess'] = 'Email removed.';
    }
    header('Location: ' . lieo_admin_notify_url($postPlant));
    exit;
}

$rows = $plant !== '' ? lieo_list_plant_notify_rows($plant) : [];
$amsEmails = $plant !== '' ? lieo_list_ams_emails_for_plant($plant) : [];
$amsByEmail = [];
foreach ($amsEmails as $ae) {
    $amsByEmail[strtolower((string) $ae['email'])] = $ae;
}
$already = [];
foreach ($rows as $r) {
    if (($r['status'] ?? '') === 'Active') {
        $already[strtolower(trim((string) $r['email']))] = true;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Contractor Reactivation Notification</h2>
<p class="text-muted mb-4">
    Multiple CC emails per plant. When Time Office requests contractor reactivation, mail goes <strong>TO</strong> the HR
    department HOD and <strong>CC</strong> these addresses until they approve or reject. Choose from AMS emails for that
    plant only.
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
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
            <input type="hidden" name="email" id="notifyEmail" value="">
            <div class="lieo-notify-form-row">
                <div class="lieo-email-picker" id="notifyEmailPicker">
                    <label for="notifyEmailSearch">CC email (AMS — <?= htmlspecialchars($plant) ?>)</label>
                    <div class="lieo-email-picker-controls">
                        <div class="lieo-email-search-wrap">
                            <input type="text" id="notifyEmailSearch" class="form-control"
                                   placeholder="Search by email, name or code…" autocomplete="off"
                                   <?= $amsEmails ? '' : 'disabled' ?>>
                            <div id="notifyEmailResults" class="list-group" style="display:none;" role="listbox"></div>
                        </div>
                        <button class="btn btn-lieo" type="submit" id="notifyEmailAddBtn" disabled>Add</button>
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
                    <tr><th>Emp Code</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-muted text-center py-4">No CC emails yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $em = strtolower(trim((string) ($r['email'] ?? '')));
                        $ams = $amsByEmail[$em] ?? null;
                        $empCode = $ams['emp_code'] ?? '—';
                        $empName = $ams['emp_name'] ?? '—';
                        $dept = $ams['department'] ?? '—';
                    ?>
                    <tr>
                        <td class="lieo-notify-code"><?= htmlspecialchars((string) $empCode) ?></td>
                        <td><?= htmlspecialchars((string) $empName) ?></td>
                        <td><?= htmlspecialchars((string) ($r['email'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) $dept) ?></td>
                        <td><?= lieo_status_badge($r['status'] ?? '') ?></td>
                        <td class="text-nowrap text-right">
                            <form method="post" class="d-inline"
                                  data-lieo-confirm="Remove this notification email?"
                                  data-lieo-confirm-title="Remove email"
                                  data-lieo-danger="1"
                                  data-lieo-confirm-ok="Remove">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="plant" value="<?= htmlspecialchars($plant) ?>">
                                <input type="hidden" name="id" value="<?= (int) $r['notify_id'] ?>">
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
    var already = <?= json_encode($already) ?>;
    var $esearch = document.getElementById('notifyEmailSearch');
    var $hidden = document.getElementById('notifyEmail');
    var $ebox = document.getElementById('notifyEmailResults');
    var $btn = document.getElementById('notifyEmailAddBtn');
    var $eform = document.getElementById('notifyEmailForm');
    var $picker = document.getElementById('notifyEmailPicker');
    if ($esearch && $hidden && $ebox && $picker) {
        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function clearSelection() { $hidden.value = ''; $btn.disabled = true; }
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
                var used = !!already[email.toLowerCase()];
                html += '<button type="button" class="list-group-item list-group-item-action' + (used ? ' email-used' : '') + '" data-email="' + esc(email) + '"' + (used ? ' disabled title="Already added"' : '') + '>'
                    + '<div class="email-main">' + esc(email) + (used ? ' <span class="text-muted">(already added)</span>' : '') + '</div>'
                    + '<div class="email-meta">' + (code ? esc(code) + ' · ' : '') + esc(name || '—') + (dept ? ' · ' + esc(dept) : '') + '</div></button>';
                shown += 1;
                if (shown >= 80) break;
            }
            $ebox.innerHTML = shown ? html : '<div class="list-group-item text-muted">No matching plant email.</div>';
            $ebox.style.display = 'block';
        }
        $esearch.addEventListener('focus', function () { render($esearch.value); });
        $esearch.addEventListener('input', function () { clearSelection(); render($esearch.value); });
        $ebox.addEventListener('click', function (e) {
            var b = e.target.closest('[data-email]');
            if (!b || b.disabled) return;
            var email = b.getAttribute('data-email') || '';
            $hidden.value = email;
            $esearch.value = email;
            $btn.disabled = !email;
            $ebox.style.display = 'none';
        });
        document.addEventListener('click', function (e) {
            if (!$picker.contains(e.target)) $ebox.style.display = 'none';
        });
        $eform.addEventListener('submit', function (e) {
            if (!$hidden.value) {
                e.preventDefault();
                if (window.lieoAlert) lieoAlert({ title: 'Select an email', message: 'Pick a plant AMS email from the list.' });
                render($esearch.value);
                $esearch.focus();
            }
        });
    }
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
