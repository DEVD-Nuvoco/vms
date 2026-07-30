<?php
require_once __DIR__ . '/config.php';
clgp_require_login();

$pageTitle = 'Change Password';
$activeNav = 'account';
$forced = !empty($_SESSION['clgp_must_change_password']);
$userEmail = $_SESSION['clgp_user_email'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = trim($_POST['current_password'] ?? '');
    $new = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (!$forced && $current === '') {
        $error = 'Please enter your current password.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif (!$forced && !clgp_find_user_by_login($userEmail, $current)) {
        $error = 'Current password is incorrect.';
    } else {
        $loginId = (int) ($_SESSION['clgp_login_id'] ?? 0);
        if ($loginId > 0 && clgp_set_password($loginId, $new, true)) {
            $_SESSION['clgp_must_change_password'] = false;
            $_SESSION['clgp_mess'] = 'Password updated successfully.';
            $_SESSION['clgp_mess_type'] = 'success';
            header('Location: ' . clgp_dashboard_url($_SESSION['clgp_role']));
            exit;
        }
        $error = 'Could not update password. Please try again.';
    }
}

require_once __DIR__ . '/includes/header.php';

$lead = 'Signed in as ' . ($_SESSION['clgp_user_name'] ?? '') . ' (' . $userEmail . ').';
if ($forced) {
    $lead = 'You must set a new password before using the application.';
}
clgp_page_header('Change Password', $lead);
?>

<?php clgp_panel_open('Update password'); ?>
    <div class="clgp-panel-body padded" style="max-width: 440px;">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <?php if (!$forced): ?>
            <div class="form-group">
                <label class="small font-weight-bold text-secondary">Current password</label>
                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label class="small font-weight-bold text-secondary">New password</label>
                <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                <small class="text-muted">Minimum 6 characters.</small>
            </div>
            <div class="form-group mb-4">
                <label class="small font-weight-bold text-secondary">Confirm new password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-clgp">Save password</button>
            <?php if (!$forced): ?>
            <a href="<?= htmlspecialchars(clgp_dashboard_url($_SESSION['clgp_role'])) ?>" class="btn btn-outline-secondary ml-2">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
<?php clgp_panel_close(); ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
