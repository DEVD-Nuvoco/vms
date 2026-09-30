<?php
require_once __DIR__ . '/../config.php';
lieo_require_role(['admin']);
global $LIEO_ROLES, $LIEO_ROLE_DEFAULT_LABELS;

$pageTitle = 'Role Names';
$activeNav = 'roles';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = lieo_save_role_labels((array) ($_POST['label'] ?? []), $LIEO_ROLE_DEFAULT_LABELS);
    $_SESSION['lieo_mess'] = $result['ok'] ? 'Role names saved.' : $result['message'];
    if (!$result['ok']) {
        $_SESSION['lieo_mess_type'] = 'danger';
    }
    header('Location: roles.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2 class="lieo-title mb-2">Role Names</h2>
<p class="text-muted mb-4">
    Rename how each role is shown across LIEO (menus, LIEO Users, applications, emails).
    Only the display name changes — permissions, the approval flow and existing assignments stay exactly the same.
    Leave a box blank to go back to the default name.
</p>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post">
            <table class="table table-bordered mb-3">
                <thead>
                    <tr><th style="width: 220px;">Default name</th><th>Display name</th></tr>
                </thead>
                <tbody>
                <?php foreach ($LIEO_ROLE_DEFAULT_LABELS as $key => $default): ?>
                    <tr>
                        <td class="font-weight-bold align-middle"><?= htmlspecialchars($default) ?></td>
                        <td>
                            <input type="text" name="label[<?= htmlspecialchars($key) ?>]" class="form-control" maxlength="40"
                                   placeholder="<?= htmlspecialchars($default) ?>"
                                   value="<?= $LIEO_ROLES[$key]['label'] !== $default ? htmlspecialchars($LIEO_ROLES[$key]['label']) : '' ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-lieo">Save</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
