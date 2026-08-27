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
                $error = 'No LIEO account for this email. Assign the user in Approval Matrix first.';
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

            <?php if (lieo_is_local_dev()): ?>
                <div class="alert alert-warning small">
                    <strong>Local testing</strong> — password is not required.
                    Copy an email below and sign in.
                    <ul class="mb-0 mt-2 pl-3">
                        <?php foreach (lieo_local_test_accounts() as $acc): ?>
                        <li><?= htmlspecialchars($acc['email']) ?> — <?= htmlspecialchars($acc['label']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off"<?= lieo_is_local_dev() ? ' novalidate' : '' ?>>
                <div class="form-group">
                    <label>Email (Login ID)</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password<?= lieo_is_local_dev() ? ' (optional on local)' : '' ?></label>
                    <input type="password" name="password" class="form-control"
                           <?= lieo_is_local_dev() ? '' : 'required' ?> autocomplete="current-password">
                    <small class="text-muted d-block text-center mt-2">
                        <?= lieo_is_local_dev()
                            ? 'Local WAMP: leave password blank.'
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
</body>
</html>
