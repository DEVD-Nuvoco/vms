<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['hod']);

$plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
$empCode = (string) ($_SESSION['lieo_emp_code'] ?? '');
if (!lieo_is_hr_hod($empCode, $plant)) {
    http_response_code(403);
    die('Access denied — this page is only for the HR department HOD.');
}

$pageTitle = 'Plant Users';
$activeNav = 'plant_users';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    $result = lieo_resend_user_credentials((int) ($_POST['user_id'] ?? 0));
    $_SESSION['lieo_mess'] = $result['message'] ?? ($result['ok'] ? 'Credentials resent.' : 'Resend failed.');
    if (empty($result['ok'])) {
        $_SESSION['lieo_mess_type'] = 'danger';
    }
    header('Location: plant_users.php');
    exit;
}

$users = lieo_list_users_for_plant($plant);

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Plant Users</h2>
<p class="text-muted mb-4">All LIEO logins for plant <strong><?= htmlspecialchars($plant) ?></strong> (HR department HOD view).</p>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table mb-0 lieo-datatable">
            <thead>
                <tr><th>Role</th><th>Name</th><th>Emp Code</th><th>Email</th><th>Department</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">No users for this plant.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
                <?php
                    $canResend = ($u['status'] ?? '') === 'Active' && ($u['must_change_password'] ?? 'f') === 't';
                ?>
                <tr>
                    <td><span class="badge badge-info"><?= htmlspecialchars(lieo_role_label((string) $u['role'])) ?></span></td>
                    <td><?= htmlspecialchars((string) $u['full_name']) ?></td>
                    <td><?= htmlspecialchars((string) $u['emp_code']) ?></td>
                    <td><?= htmlspecialchars((string) $u['email']) ?></td>
                    <td><?= htmlspecialchars((string) ($u['department'] ?: '—')) ?></td>
                    <td><?= lieo_status_badge((string) $u['status']) ?></td>
                    <td>
                        <?php if ($canResend): ?>
                        <form method="post" class="d-inline"
                              data-lieo-confirm="Resend login credentials email to <?= htmlspecialchars((string) $u['email'], ENT_QUOTES) ?>?"
                              data-lieo-confirm-title="Resend credentials"
                              data-lieo-confirm-ok="Resend">
                            <input type="hidden" name="action" value="resend">
                            <input type="hidden" name="user_id" value="<?= (int) $u['lieo_user_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success">Resend</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
