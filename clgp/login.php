<?php
require_once __DIR__ . '/config.php';

if (isset($_GET['switch'])) {
    unset(
        $_SESSION['clgp_role'],
        $_SESSION['clgp_user_email'],
        $_SESSION['clgp_user_name'],
        $_SESSION['clgp_user_id'],
        $_SESSION['clgp_login_id'],
        $_SESSION['clgp_emp_code'],
        $_SESSION['clgp_plant'],
        $_SESSION['clgp_department'],
        $_SESSION['clgp_must_change_password'],
        $_SESSION['clgp_mess']
    );
}

if (clgp_is_logged_in()) {
    header('Location: ' . clgp_dashboard_url($_SESSION['clgp_role']));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $user = clgp_find_user_by_login($email, $password);
        if (!$user) {
            $error = 'Invalid credentials or inactive account.';
        } else {
            $_SESSION['clgp_role'] = $user['role'];
            $_SESSION['clgp_user_email'] = $user['email'];
            $_SESSION['clgp_user_name'] = $user['full_name'];
            $_SESSION['clgp_user_id'] = (int) $user['clgp_user_id'];
            $_SESSION['clgp_login_id'] = (int) $user['clgp_user_id']; // legacy key; LIEO no longer uses tbl_logindetail
            $_SESSION['clgp_emp_code'] = $user['emp_code'] ?? '';
            $_SESSION['clgp_plant'] = clgp_ams_canonical_plant($user['plant'] ?? '');
            $_SESSION['clgp_department'] = $user['department'] ?? '';
            $_SESSION['clgp_must_change_password'] = ($user['must_change_password'] ?? 'f') === 't';
            if ($_SESSION['clgp_must_change_password']) {
                header('Location: change_password.php');
            } else {
                header('Location: ' . clgp_dashboard_url($user['role']));
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
    <title><?= htmlspecialchars(CLGP_APP_SHORT) ?> — Sign In</title>
    <link href="../lib/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../lib/typicons.font/typicons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/azia.css">
    <style>
        body { background: #f4f5f8; }
        .clgp-login-card { max-width: 480px; margin: 60px auto; }
        .clgp-brand { color: #42bb52; font-weight: 700; }
        .btn-clgp { background: #42bb52; border-color: #42bb52; color: #fff; }
        .btn-clgp:hover { background: #38a644; border-color: #38a644; color: #fff; }
    </style>
</head>
<body class="az-body">
<div class="container clgp-login-card">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <img src="../images/nuvoco-ori.png" width="100" alt="Nuvoco">
                <h4 class="mt-3 clgp-brand">Access control for Contract Workman Entry/Exit Pass</h4>
                <h6 class="mt-3"><?= htmlspecialchars(CLGP_APP_NAME) ?></h6>
                <p class="text-muted small mt-2 mb-0">Workman Late IN / Early Out access control</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label>Email (Login ID)</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required value="">
                </div>
                <button type="submit" class="btn btn-clgp btn-block">Sign In</button>
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
