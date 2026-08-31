<?php
require_once __DIR__ . '/config.php';

if (isset($_GET['switch'])) {
    unset(
        $_SESSION['lieo_role'],
        $_SESSION['lieo_user_email'],
        $_SESSION['lieo_user_name'],
        $_SESSION['lieo_user_id'],
        $_SESSION['lieo_login_id'],
        $_SESSION['lieo_emp_code'],
        $_SESSION['lieo_plant'],
        $_SESSION['lieo_department'],
        $_SESSION['lieo_must_change_password'],
        $_SESSION['lieo_mess']
    );
}

if (lieo_is_logged_in()) {
    header('Location: ' . lieo_dashboard_url($_SESSION['lieo_role']));
    exit;
}

$error = '';
$lieoLocalDev = lieo_is_local_dev();
$lieoTestAccounts = $lieoLocalDev ? lieo_local_test_accounts() : [];
$postedEmail = trim($_POST['email'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '') {
        $error = 'Please enter email.';
    } elseif (!lieo_is_local_dev() && $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $user = lieo_find_user_by_login($email, $password);
        if (!$user) {
            $why = lieo_login_diagnose($email, $password);
            if ($why === 'missing') {
                $error = lieo_is_local_dev()
                    ? 'No LIEO account for this email. Use a @local.test address from the list above, or a user from your imported DB.'
                    : 'No LIEO account for this email. Assign the user in Approval Matrix first.';
            } elseif ($why === 'inactive') {
                $error = 'This LIEO account is inactive. Ask Admin to reactivate it.';
            } else {
                $error = 'Invalid LIEO password. Use the password emailed when the role was assigned (not your VMS password).';
            }
        } else {
            $_SESSION['lieo_role'] = $user['role'];
            $_SESSION['lieo_user_email'] = $user['email'];
            $_SESSION['lieo_user_name'] = $user['full_name'];
            $_SESSION['lieo_user_id'] = (int) $user['lieo_user_id'];
            $_SESSION['lieo_login_id'] = (int) $user['lieo_user_id']; // legacy key; LIEO no longer uses tbl_logindetail
            $_SESSION['lieo_emp_code'] = $user['emp_code'] ?? '';
            $_SESSION['lieo_plant'] = lieo_ams_canonical_plant($user['plant'] ?? '');
            $_SESSION['lieo_department'] = $user['department'] ?? '';
            $_SESSION['lieo_must_change_password'] = lieo_is_local_dev()
                ? false
                : (($user['must_change_password'] ?? 'f') === 't');
            if ($_SESSION['lieo_must_change_password']) {
                header('Location: change_password.php');
            } else {
                header('Location: ' . lieo_dashboard_url($user['role']));
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(LIEO_APP_SHORT) ?> — Sign In</title>
    <link href="../lib/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../lib/typicons.font/typicons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/azia.css">
    <style>
        body { background: #f4f5f8; }
        .lieo-login-card { max-width: 480px; margin: 60px auto; }
        .lieo-brand { color: #42bb52; font-weight: 700; }
        .btn-lieo { background: #42bb52; border-color: #42bb52; color: #fff; }
        .btn-lieo:hover { background: #38a644; border-color: #38a644; color: #fff; }
    </style>
</head>
<body class="az-body">
<div class="container lieo-login-card">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <img src="../images/nuvoco-ori.png" width="100" alt="Nuvoco">
                <h4 class="mt-3 lieo-brand">Access control for Contract Workman Entry/Exit Pass</h4>
                <h6 class="mt-3"><?= htmlspecialchars(LIEO_APP_NAME) ?></h6>
                <p class="text-muted small mt-2 mb-0">Workman Late IN / Early Out access control</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($lieoLocalDev): ?>
                <div class="alert alert-warning small mb-3">
                    <strong>Local testing</strong> — pick a test account below. Password is not required.
                    These logins are built into the code and work even after a live DB dump.
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off"<?= $lieoLocalDev ? ' novalidate' : '' ?> id="lieoLoginForm">
                <div class="form-group">
                    <label>Email (Login ID)</label>
                    <?php if ($lieoLocalDev): ?>
                        <?php
                        $postedIsTest = false;
                        foreach ($lieoTestAccounts as $acc) {
                            if (strcasecmp($acc['email'], $postedEmail) === 0) {
                                $postedIsTest = true;
                                break;
                            }
                        }
                        $useOther = $postedEmail !== '' && !$postedIsTest;
                        ?>
                        <select id="lieoEmailSelect" class="form-control"<?= $useOther ? '' : ' name="email"' ?> required>
                            <option value="">— Choose test account —</option>
                            <?php foreach ($lieoTestAccounts as $acc): ?>
                            <option value="<?= htmlspecialchars($acc['email']) ?>"
                                <?= strcasecmp($acc['email'], $postedEmail) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['email']) ?> — <?= htmlspecialchars($acc['label']) ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="__other__" <?= $useOther ? 'selected' : '' ?>>Other — email from imported DB</option>
                        </select>
                        <input type="email" id="lieoEmailOther" class="form-control mt-2<?= $useOther ? '' : ' d-none' ?>"
                               <?= $useOther ? 'name="email" required' : '' ?>
                               placeholder="Enter email from imported database"
                               value="<?= $useOther ? htmlspecialchars($postedEmail) : '' ?>">
                        <small class="text-muted d-block mt-1">Select a role to switch users quickly — no copy/paste needed.</small>
                    <?php else: ?>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($postedEmail) ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Password<?= $lieoLocalDev ? ' (optional on local)' : '' ?></label>
                    <input type="password" name="password" class="form-control"
                           <?= $lieoLocalDev ? '' : 'required' ?> autocomplete="current-password">
                    <small class="text-muted d-block text-center mt-2">
                        <?= $lieoLocalDev
                            ? 'Local WAMP: leave password blank for test accounts.'
                            : 'LIEO password is separate from VMS. <br> Use the password from the credentials email.' ?>
                    </small>
                </div>
                <button type="submit" class="btn btn-lieo btn-block">Sign In</button>
            </form>

            <hr>

            <p class="text-center mt-3 mb-0">
                <a href="../signup.php" class="small">← Back to VMS Visitor Login</a>
            </p>
        </div>
    </div>
</div>
<?php if ($lieoLocalDev): ?>
<script>
(function () {
    var form = document.getElementById('lieoLoginForm');
    var select = document.getElementById('lieoEmailSelect');
    var other = document.getElementById('lieoEmailOther');
    if (!form || !select || !other) return;

    function syncEmailField() {
        var isOther = select.value === '__other__';
        if (isOther) {
            other.classList.remove('d-none');
            other.setAttribute('name', 'email');
            other.setAttribute('required', 'required');
            select.removeAttribute('name');
        } else {
            other.classList.add('d-none');
            other.removeAttribute('name');
            other.removeAttribute('required');
            if (select.value && select.value !== '__other__') {
                select.setAttribute('name', 'email');
            } else {
                select.removeAttribute('name');
            }
        }
    }

    select.addEventListener('change', syncEmailField);
    syncEmailField();

    form.addEventListener('submit', function (e) {
        syncEmailField();
        if (select.value === '__other__') {
            if (!other.value.trim()) {
                e.preventDefault();
                other.focus();
            }
            return;
        }
        if (!select.value) {
            e.preventDefault();
            select.focus();
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
