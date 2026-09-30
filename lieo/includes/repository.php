<?php
/**
 * LIEO Phase 1 — data access for Late Coming / Early Going.
 */
require_once __DIR__ . '/db.php';
if (!function_exists('lieo_is_local_dev')) {
    require_once __DIR__ . '/env.php';
    lieo_load_dotenv();
}

function lieo_db(): mysqli
{
    global $lieoDb;
    return $lieoDb;
}

function lieo_esc(string $s): string
{
    return lieo_db()->real_escape_string($s);
}

// ---------------------------------------------------------------------------
// Auth / users
// ---------------------------------------------------------------------------

/**
 * Ensure LIEO-only auth schema (password on tbl_lieo_user; no unique login_id).
 * Safe to call repeatedly — used so server deploys self-heal without a manual SQL step.
 */
function lieo_ensure_lieo_auth_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $db = lieo_db();
    mysqli_report(MYSQLI_REPORT_OFF);

    try {
        $tbl = $db->query("SHOW TABLES LIKE 'tbl_lieo_user'");
        if (!$tbl || $tbl->num_rows === 0) {
            error_log('lieo_ensure_lieo_auth_schema: tbl_lieo_user missing — run php database/run_lieo_rename_clgp_tables.php');
            return;
        }
        $col = $db->query("SHOW COLUMNS FROM tbl_lieo_user LIKE 'password'");
        if (!$col || $col->num_rows === 0) {
            $db->query("ALTER TABLE tbl_lieo_user ADD COLUMN `password` VARCHAR(100) NOT NULL DEFAULT '' AFTER `email`");
        }

        $idx = $db->query("SHOW INDEX FROM tbl_lieo_user WHERE Key_name = 'uk_login_id'");
        if ($idx && $idx->num_rows > 0) {
            $db->query('ALTER TABLE tbl_lieo_user DROP INDEX uk_login_id');
        }

        $loginCol = $db->query("SHOW COLUMNS FROM tbl_lieo_user LIKE 'login_id'");
        $loginMeta = $loginCol ? $loginCol->fetch_assoc() : null;
        if ($loginMeta && strtoupper((string) ($loginMeta['Null'] ?? '')) !== 'YES') {
            $db->query('ALTER TABLE tbl_lieo_user MODIFY login_id INT NULL DEFAULT NULL');
        }

        $db->query('UPDATE tbl_lieo_user SET login_id = NULL WHERE login_id = 0');

        $emailIdx = $db->query("SHOW INDEX FROM tbl_lieo_user WHERE Key_name = 'uq_lieo_user_email'");
        if (!$emailIdx || $emailIdx->num_rows === 0) {
            // Ignore failure if duplicate emails already exist.
            @$db->query('ALTER TABLE tbl_lieo_user ADD UNIQUE KEY uq_lieo_user_email (email)');
        }
    } catch (Throwable $e) {
        error_log('lieo_ensure_lieo_auth_schema: ' . $e->getMessage());
    } finally {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
}

function lieo_find_user_by_login(string $email, string $password): ?array
{
    $email = trim($email);

    lieo_ensure_lieo_auth_schema();
    $db = lieo_db();
    $stmt = $db->prepare(
        "SELECT *
         FROM tbl_lieo_user
         WHERE email = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row && lieo_is_local_dev()) {
        $row = lieo_local_login_from_matrix($email);
    }
    if (!$row) {
        return null;
    }
    if (($row['status'] ?? '') !== 'Active') {
        return null;
    }
    if (lieo_is_local_dev()) {
        return $row;
    }
    if ((string) ($row['password'] ?? '') === '' || (string) ($row['password'] ?? '') !== $password) {
        return null;
    }
    return $row;
}

/**
 * Local WAMP only (LIEO_LOCAL_DEV=1 + localhost): an imported DB has matrix rows but no
 * login rows, so create the missing login on the fly from the Approval Matrix so any
 * assigned user can be signed in by email alone. Never runs on a live server.
 */
function lieo_local_login_from_matrix(string $email): ?array
{
    if (!lieo_is_local_dev()) {
        return null;
    }
    $stmt = lieo_db()->prepare(
        "SELECT plant, department, approval_step, emp_code, emp_name, emp_email
         FROM tbl_lieo_approval_matrix WHERE emp_email = ? AND status = 'Active' ORDER BY matrix_id LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $m = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$m) {
        return null;
    }
    $made = lieo_create_user([
        'full_name' => $m['emp_name'],
        'email' => $m['emp_email'],
        'role' => $m['approval_step'],
        'emp_code' => $m['emp_code'],
        'plant' => $m['plant'],
        'department' => $m['department'] === 'All' ? '' : $m['department'],
    ], lieo_generate_password());
    return $made['ok'] ? lieo_get_user((int) $made['lieo_user_id']) : null;
}

/**
 * Diagnose login failure without revealing too much in UI by default.
 * @return 'missing'|'inactive'|'bad_password'|'ok'
 */
function lieo_login_diagnose(string $email, string $password): string
{
    $email = trim($email);

    lieo_ensure_lieo_auth_schema();
    $db = lieo_db();
    $stmt = $db->prepare('SELECT status, password FROM tbl_lieo_user WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 'missing';
    }
    if (($row['status'] ?? '') !== 'Active') {
        return 'inactive';
    }
    if (lieo_is_local_dev()) {
        return 'ok';
    }
    $stored = (string) ($row['password'] ?? '');
    if ($stored === '' || $stored !== $password) {
        return 'bad_password';
    }
    return 'ok';
}

function lieo_get_user(int $lieoUserId): ?array
{
    $stmt = lieo_db()->prepare("SELECT * FROM tbl_lieo_user WHERE lieo_user_id = ? LIMIT 1");
    $stmt->bind_param('i', $lieoUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_list_users(): array
{
    $res = lieo_db()->query("SELECT * FROM tbl_lieo_user ORDER BY lieo_user_id DESC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function lieo_create_user(array $data, string $plainPassword): array
{
    try {
        lieo_ensure_lieo_auth_schema();
        $db = lieo_db();
        $email = trim($data['email'] ?? '');
        $name = trim($data['full_name'] ?? '');
        $role = $data['role'] ?? '';
        $empCode = trim($data['emp_code'] ?? '');
        $plant = lieo_ams_canonical_plant($data['plant'] ?? '');
        $dept = trim($data['department'] ?? '');

        // LIEO login is separate from VMS — uniqueness is only within tbl_lieo_user.
        $check = $db->prepare("SELECT lieo_user_id FROM tbl_lieo_user WHERE email = ? LIMIT 1");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            return ['ok' => false, 'message' => 'LIEO login email already exists.'];
        }
        $check->close();

        $createdBy = (int) ($_SESSION['lieo_user_id'] ?? 0);
        // login_id is legacy/unused — LIEO auth uses tbl_lieo_user.password only.
        $stmt2 = $db->prepare(
            "INSERT INTO tbl_lieo_user
             (login_id, full_name, email, password, role, emp_code, plant, department, must_change_password, status, created_by)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 't', 'Active', ?)"
        );
        if (!$stmt2) {
            return ['ok' => false, 'message' => 'User prepare failed: ' . $db->error];
        }
        $stmt2->bind_param('sssssssi', $name, $email, $plainPassword, $role, $empCode, $plant, $dept, $createdBy);
        if (!$stmt2->execute()) {
            $err = $stmt2->error;
            $stmt2->close();
            return ['ok' => false, 'message' => 'User insert failed: ' . $err];
        }
        $userId = (int) $stmt2->insert_id;
        $stmt2->close();
        return ['ok' => true, 'lieo_user_id' => $userId, 'password' => $plainPassword, 'email' => $email, 'name' => $name];
    } catch (Throwable $e) {
        error_log('lieo_create_user: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'User create failed: ' . $e->getMessage()];
    }
}

/**
 * Update LIEO password by lieo_user_id (not VMS login_id).
 */
function lieo_set_password(int $lieoUserId, string $newPassword, bool $clearMustChange = true): bool
{
    $db = lieo_db();
    if ($clearMustChange) {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_user
             SET password = ?, must_change_password = 'f'
             WHERE lieo_user_id = ?"
        );
    } else {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_user
             SET password = ?
             WHERE lieo_user_id = ?"
        );
    }
    $stmt->bind_param('si', $newPassword, $lieoUserId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lieo_find_user_by_email(string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }
    $stmt = lieo_db()->prepare("SELECT * FROM tbl_lieo_user WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Admin role renames → [role_key => label]. Empty if the table isn't migrated yet. */
function lieo_list_role_label_overrides(): array
{
    try {
        $res = lieo_db()->query('SELECT role_key, label FROM tbl_lieo_role_label');
        return $res ? array_column($res->fetch_all(MYSQLI_ASSOC), 'label', 'role_key') : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Save display names only — role keys are never changed. Blank label = back to default.
 * @param array<string,string> $labels role_key => label
 * @param array<string,string> $defaults role_key => default label
 */
function lieo_save_role_labels(array $labels, array $defaults): array
{
    $clean = [];
    foreach ($defaults as $key => $default) {
        $label = trim(preg_replace('/\s+/u', ' ', (string) ($labels[$key] ?? '')));
        if (mb_strlen($label) > 40) {
            return ['ok' => false, 'message' => 'Role name must be 40 characters or less.'];
        }
        $clean[$key] = $label === '' ? $default : $label;
    }
    $lower = array_map('mb_strtolower', $clean);
    if (count(array_unique($lower)) !== count($lower)) {
        return ['ok' => false, 'message' => 'Two roles cannot have the same name.'];
    }

    $db = lieo_db();
    $by = (int) ($_SESSION['lieo_user_id'] ?? 0);
    try {
        foreach ($clean as $key => $label) {
            if ($label === $defaults[$key]) {
                $stmt = $db->prepare('DELETE FROM tbl_lieo_role_label WHERE role_key = ?');
                $stmt->bind_param('s', $key);
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO tbl_lieo_role_label (role_key, label, updated_by) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE label = VALUES(label), updated_by = VALUES(updated_by)'
                );
                $stmt->bind_param('ssi', $key, $label, $by);
            }
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Could not save — run php database/run_lieo_role_labels.php on the server.'];
    }
    return ['ok' => true];
}

/** Active matrix people sharing one email → [emp_code => emp_name]. */
function lieo_matrix_people_by_email(string $email): array
{
    $stmt = lieo_db()->prepare(
        "SELECT emp_code, MAX(emp_name) AS emp_name FROM tbl_lieo_approval_matrix
         WHERE emp_email = ? AND status = 'Active' AND emp_code <> '' GROUP BY emp_code ORDER BY emp_name"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_column($rows, 'emp_name', 'emp_code');
}

/**
 * Create or update LIEO login from Approval Matrix assignment.
 */
function lieo_provision_matrix_user(
    string $role,
    string $plant,
    string $department,
    string $empCode,
    string $empName,
    string $empEmail,
    bool $sendCredentials
): array {
    $empEmail = trim($empEmail);
    $empName = trim($empName);
    $empCode = trim($empCode);
    $plant = lieo_ams_canonical_plant($plant);
    $department = trim($department);

    if ($empEmail === '' || $empName === '' || $empCode === '') {
        return ['ok' => false, 'message' => 'Employee email, name and code are required for login.'];
    }

    $cc = $plant !== '' ? lieo_list_plant_notify_emails($plant, 'approval_matrix') : [];

    $existing = lieo_find_user_by_email($empEmail);
    if ($existing) {
        if ($existing['role'] !== $role) {
            // N-1 and Security are allowed to be the same person (N-1 creates
            // the application, Security closes it at the gate) — the login
            // keeps its original primary role and profile untouched; the
            // matrix row already saved by the caller grants the other
            // capability (see lieo_user_secondary_role(), checked at login).
            $crossRolePair = ['n1', 'security'];
            if (!in_array($existing['role'], $crossRolePair, true) || !in_array($role, $crossRolePair, true)) {
                return ['ok' => false, 'message' => 'Email already used for role ' . lieo_role_label($existing['role']) . '.'];
            }
            if ($sendCredentials) {
                lieo_send_role_assigned_email($empEmail, $empName, $role, $plant, $department, $cc);
            }
            return ['ok' => true, 'provisioned' => 'updated', 'email' => $empEmail];
        }
        $uid = (int) $existing['lieo_user_id'];
        $stmt = lieo_db()->prepare(
            "UPDATE tbl_lieo_user
             SET full_name=?, emp_code=?, plant=?, department=?, status='Active'
             WHERE lieo_user_id=?"
        );
        $stmt->bind_param('ssssi', $empName, $empCode, $plant, $department, $uid);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Could not update user profile.'];
        }

        // If LIEO password was never set, create one now (separate from VMS).
        if (trim((string) ($existing['password'] ?? '')) === '') {
            if (!$sendCredentials) {
                return ['ok' => false, 'message' => 'User exists but has no LIEO password.'];
            }
            $pass = lieo_generate_password();
            if (!lieo_set_password($uid, $pass, false)) {
                return ['ok' => false, 'message' => 'Could not set LIEO password.'];
            }
            $must = lieo_db()->prepare("UPDATE tbl_lieo_user SET must_change_password = 't' WHERE lieo_user_id = ?");
            $must->bind_param('i', $uid);
            $must->execute();
            $must->close();
            lieo_send_credentials_email($empEmail, $empName, $pass, false, $role, $cc);
            return [
                'ok' => true,
                'provisioned' => 'created',
                'email' => $empEmail,
                'password' => $pass,
            ];
        }

        // Existing login with password — still notify on assign (popup / SMTP).
        if ($sendCredentials) {
            lieo_send_role_assigned_email($empEmail, $empName, $role, $plant, $department, $cc);
        }
        return ['ok' => true, 'provisioned' => 'updated', 'email' => $empEmail];
    }

    if (!$sendCredentials) {
        return ['ok' => false, 'message' => 'No existing login for this email; cannot update credentials.'];
    }

    $pass = lieo_generate_password();
    $created = lieo_create_user([
        'full_name' => $empName,
        'email' => $empEmail,
        'role' => $role,
        'emp_code' => $empCode,
        'plant' => $plant,
        'department' => $department,
    ], $pass);
    if (!$created['ok']) {
        return $created;
    }
    lieo_send_credentials_email($created['email'], $created['name'], $created['password'], false, $role, $cc);
    return [
        'ok' => true,
        'provisioned' => 'created',
        'email' => $created['email'],
        'password' => $created['password'],
    ];
}

function lieo_set_user_status(int $userId, string $status): bool
{
    $stmt = lieo_db()->prepare("UPDATE tbl_lieo_user SET status = ? WHERE lieo_user_id = ?");
    $stmt->bind_param('si', $status, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ---------------------------------------------------------------------------
// Contractors
// ---------------------------------------------------------------------------

function lieo_list_contractors(?string $status = null, ?string $plant = null): array
{
    $where = ['1=1'];
    if ($status) {
        $where[] = "status = '" . lieo_esc($status) . "'";
    }
    $plant = $plant !== null ? lieo_ams_canonical_plant($plant) : '';
    if ($plant !== '') {
        // Match canonical short code and exact stored value (legacy rows).
        $p = lieo_esc($plant);
        $where[] = '(' . lieo_sql_canonical_plant('plant') . " = '{$p}' OR TRIM(plant) = '{$p}')";
    }
    $sql = "SELECT * FROM tbl_lieo_contractor WHERE " . implode(' AND ', $where) . " ORDER BY contractor_id DESC";
    $res = lieo_db()->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** contractor_id => supervisor_name, for any status so historic applications still resolve. */
function lieo_contractor_supervisor_map(?string $plant = null): array
{
    return array_column(lieo_list_contractors(null, $plant), 'supervisor_name', 'contractor_id');
}

function lieo_get_contractor(int $id): ?array
{
    $stmt = lieo_db()->prepare("SELECT * FROM tbl_lieo_contractor WHERE contractor_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_save_contractor(array $data, ?int $id = null): array
{
    $db = lieo_db();
    $cname = trim($data['contractor_name'] ?? '');
    $vtype = $data['contractor_type'] ?? ($data['vendor_type'] ?? 'Temporary');
    $sup = trim($data['supervisor_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $cmob = preg_replace('/\D+/', '', trim($data['contractor_mobile'] ?? '')) ?? '';
    $smob = preg_replace('/\D+/', '', trim($data['supervisor_mobile'] ?? '')) ?? '';

    // Prefer explicit plant, else logged-in user's plant (Time Office / SI).
    $plant = lieo_ams_canonical_plant($data['plant'] ?? '');
    if ($plant === '') {
        $plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
    }

    $allowed = ['Supply', 'Temporary', 'Measurement'];
    if (!in_array($vtype, $allowed, true)) {
        return ['ok' => false, 'message' => 'Invalid contractor type.'];
    }
    if ($cname === '' || $sup === '' || $email === '' || $cmob === '' || $smob === '') {
        return ['ok' => false, 'message' => 'All contractor fields are mandatory.'];
    }
    if (!preg_match('/^\d{10}$/', $cmob)) {
        return ['ok' => false, 'message' => 'Contractor mobile must be a 10-digit number.'];
    }
    if (!preg_match('/^\d{10}$/', $smob)) {
        return ['ok' => false, 'message' => 'Supervisor mobile must be a 10-digit number.'];
    }
    if ($plant === '') {
        return ['ok' => false, 'message' => 'Plant is required. Your login must have a plant assigned.'];
    }

    // Time Office may only maintain contractors for their own plant.
    $role = (string) ($_SESSION['lieo_role'] ?? '');
    if ($role === 'timeoffice') {
        $sessionPlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
        if ($sessionPlant === '') {
            return ['ok' => false, 'message' => 'Your Time Office login has no plant. Re-assign plant in LIEO Users.'];
        }
        $plant = $sessionPlant;
        if ($id) {
            $existing = lieo_get_contractor($id);
            if ($existing) {
                $existingPlant = lieo_ams_canonical_plant($existing['plant'] ?? '');
                if ($existingPlant !== '' && $existingPlant !== $sessionPlant) {
                    return ['ok' => false, 'message' => 'You cannot edit a contractor from another plant.'];
                }
            }
        }
    }

    if ($id) {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_contractor
             SET contractor_name=?, contractor_type=?, supervisor_name=?, email=?, contractor_mobile=?, supervisor_mobile=?, plant=?
             WHERE contractor_id=?"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Update prepare failed: ' . $db->error];
        }
        $stmt->bind_param('sssssssi', $cname, $vtype, $sup, $email, $cmob, $smob, $plant, $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        return $ok ? ['ok' => true, 'contractor_id' => $id, 'plant' => $plant]
            : ['ok' => false, 'message' => $err ?: 'Update failed.'];
    }

    $createdBy = (int) ($_SESSION['lieo_user_id'] ?? 0);
    $stmt = $db->prepare(
        "INSERT INTO tbl_lieo_contractor
         (contractor_name, contractor_type, supervisor_name, email, contractor_mobile, supervisor_mobile, plant, status, created_by)
         VALUES (?,?,?,?,?,?,?,'Active',?)"
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Insert prepare failed: ' . $db->error];
    }
    $stmt->bind_param('sssssssi', $cname, $vtype, $sup, $email, $cmob, $smob, $plant, $createdBy);
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $err = $stmt->error;
    $stmt->close();
    return $ok
        ? ['ok' => true, 'contractor_id' => $newId, 'plant' => $plant]
        : ['ok' => false, 'message' => $err ?: 'Insert failed.'];
}

function lieo_deactivate_contractor(int $id, string $reason = ''): bool
{
    $stmt = lieo_db()->prepare(
        "UPDATE tbl_lieo_contractor SET status='Inactive', deactivation_reason=?, deactivated_at=NOW(), reactivation_requested='f' WHERE contractor_id=?"
    );
    $stmt->bind_param('si', $reason, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lieo_request_reactivation(int $id): bool
{
    $stmt = lieo_db()->prepare(
        "UPDATE tbl_lieo_contractor SET reactivation_requested='t' WHERE contractor_id=? AND status='Inactive'"
    );
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        $c = lieo_get_contractor($id);
        if ($c) {
            lieo_notify_reactivation_requested($c);
        }
    }
    return $ok;
}

function lieo_approve_reactivation(int $id, int $hrUserId): bool
{
    $stmt = lieo_db()->prepare(
        "UPDATE tbl_lieo_contractor
         SET status='Active', reactivation_requested='f', reactivation_approved_by=?, reactivation_approved_at=NOW(),
             deactivation_reason=NULL, deactivated_at=NULL
         WHERE contractor_id=? AND reactivation_requested='t'"
    );
    $stmt->bind_param('ii', $hrUserId, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        $c = lieo_get_contractor($id);
        if ($c) {
            lieo_notify_reactivation_decided($c, 'approve');
        }
    }
    return $ok;
}

function lieo_reject_reactivation(int $id): bool
{
    $c = lieo_get_contractor($id);
    $stmt = lieo_db()->prepare(
        "UPDATE tbl_lieo_contractor SET reactivation_requested='f' WHERE contractor_id=? AND reactivation_requested='t'"
    );
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $c) {
        lieo_notify_reactivation_decided($c, 'reject');
    }
    return $ok;
}

function lieo_list_reactivation_requests(?string $plant = null): array
{
    $sql = "SELECT * FROM tbl_lieo_contractor WHERE reactivation_requested='t' AND status='Inactive'";
    $plant = $plant !== null ? lieo_ams_canonical_plant($plant) : '';
    if ($plant !== '') {
        $sql .= " AND " . lieo_sql_canonical_plant('plant') . " = '" . lieo_esc($plant) . "'";
    }
    $sql .= " ORDER BY deactivated_at DESC";
    $res = lieo_db()->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// ---------------------------------------------------------------------------
// Workmen
// ---------------------------------------------------------------------------

function lieo_list_workmen(?string $status = 'Active'): array
{
    $sql = "SELECT w.*, c.contractor_name AS vendor_name, c.contractor_name
            FROM tbl_lieo_workman w
            INNER JOIN tbl_lieo_contractor c ON c.contractor_id = w.contractor_id";
    if ($status) {
        $sql .= " WHERE w.status = '" . lieo_esc($status) . "' AND c.status = 'Active'";
    }
    $sql .= " ORDER BY w.workman_name";
    $res = lieo_db()->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function lieo_get_workman(int $id): ?array
{
    $stmt = lieo_db()->prepare(
        "SELECT w.*, c.contractor_name AS vendor_name, c.contractor_name AS contractor_display
         FROM tbl_lieo_workman w
         INNER JOIN tbl_lieo_contractor c ON c.contractor_id = w.contractor_id
         WHERE w.workman_id = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_find_workman_by_code(string $code): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }
    $stmt = lieo_db()->prepare(
        "SELECT w.*, c.contractor_name AS vendor_name
         FROM tbl_lieo_workman w
         LEFT JOIN tbl_lieo_contractor c ON c.contractor_id = w.contractor_id
         WHERE w.workman_code = ? LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_save_workman(array $data, ?int $id = null): array
{
    $db = lieo_db();
    $code = trim($data['workman_code'] ?? '');
    $name = trim($data['workman_name'] ?? '');
    $cid = (int) ($data['contractor_id'] ?? 0);
    $plant = lieo_ams_canonical_plant($data['plant'] ?? '');
    $dept = trim($data['department'] ?? '');
    $shift = trim($data['shift'] ?? '');
    if ($code === '' || $name === '' || $cid < 1 || $plant === '' || $dept === '') {
        return ['ok' => false, 'message' => 'Workman code, name, contractor, plant and department are required.'];
    }
    if ($id) {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_workman SET workman_code=?, workman_name=?, contractor_id=?, plant=?, department=?, shift=? WHERE workman_id=?"
        );
        $stmt->bind_param('ssisssi', $code, $name, $cid, $plant, $dept, $shift, $id);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO tbl_lieo_workman (workman_code, workman_name, contractor_id, plant, department, shift, status)
             VALUES (?,?,?,?,?,?,'Active')"
        );
        $stmt->bind_param('ssisss', $code, $name, $cid, $plant, $dept, $shift);
    }
    $ok = $stmt->execute();
    $newId = $id ?: (int) $stmt->insert_id;
    $err = $stmt->error;
    $stmt->close();
    return $ok ? ['ok' => true, 'workman_id' => $newId] : ['ok' => false, 'message' => $err ?: 'Save failed.'];
}

// ---------------------------------------------------------------------------
// Approval matrix
// ---------------------------------------------------------------------------

function lieo_list_matrix(): array
{
    lieo_ensure_lieo_auth_schema();
    $res = lieo_db()->query(
        "SELECT m.*,
                u.lieo_user_id,
                u.must_change_password,
                u.status AS user_status
         FROM tbl_lieo_approval_matrix m
         LEFT JOIN tbl_lieo_user u ON u.email = m.emp_email
         WHERE m.status = 'Active'
         ORDER BY m.plant, m.department, m.approval_step"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Resend LIEO credentials email for a user, by lieo_user_id directly.
 * Unless $force (admin password reset), only allowed while the user has not changed their first password yet.
 */
function lieo_resend_user_credentials(int $userId, bool $force = false): array
{
    $user = lieo_get_user($userId);
    if (!$user) {
        return ['ok' => false, 'message' => 'User not found.'];
    }
    $email = trim($user['email'] ?? '');
    $name = trim($user['full_name'] ?? '');
    if ($email === '') {
        return ['ok' => false, 'message' => 'No email on this account.'];
    }
    if (($user['status'] ?? '') !== 'Active') {
        return ['ok' => false, 'message' => 'LIEO account is inactive.'];
    }
    if (!$force && ($user['must_change_password'] ?? 'f') !== 't') {
        return ['ok' => false, 'message' => 'Password already changed — credentials cannot be resent.'];
    }

    $pass = lieo_generate_password();
    if (!lieo_set_password($userId, $pass, false)) {
        return ['ok' => false, 'message' => 'Could not reset temporary password.'];
    }
    $db = lieo_db();
    $must = $db->prepare("UPDATE tbl_lieo_user SET must_change_password = 't' WHERE lieo_user_id = ?");
    $must->bind_param('i', $userId);
    $must->execute();
    $must->close();

    lieo_send_credentials_email($email, $name !== '' ? $name : $email, $pass, true, (string) ($user['role'] ?? ''));
    return [
        'ok' => true,
        'message' => 'Password reset — new temporary password emailed to ' . $email . ' (password: ' . $pass . ').',
        'password' => $pass,
        'email' => $email,
    ];
}

/**
 * Resend LIEO credentials email for a matrix assignment (thin wrapper over
 * lieo_resend_user_credentials() — kept for the existing Approval Matrix UI
 * call site, which only has a matrix_id, not the linked lieo_user_id).
 * Only allowed while the linked user has not changed their first password yet.
 */
function lieo_resend_matrix_credentials(int $matrixId, bool $force = false): array
{
    lieo_ensure_lieo_auth_schema();
    $db = lieo_db();
    $stmt = $db->prepare(
        "SELECT m.*, u.lieo_user_id
         FROM tbl_lieo_approval_matrix m
         LEFT JOIN tbl_lieo_user u ON u.email = m.emp_email
         WHERE m.matrix_id = ? AND m.status = 'Active'
         LIMIT 1"
    );
    $stmt->bind_param('i', $matrixId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'message' => 'Assignment not found.'];
    }
    if (empty($row['lieo_user_id'])) {
        if (!$force) {
            return ['ok' => false, 'message' => 'No LIEO login linked. Save the assignment again to create login.'];
        }
        // Reset on an assignment that never got a login: create it and email the credentials.
        $prov = lieo_provision_matrix_user(
            (string) $row['approval_step'], (string) $row['plant'], (string) $row['department'],
            (string) $row['emp_code'], (string) $row['emp_name'], (string) $row['emp_email'], true
        );
        if (!$prov['ok']) {
            return $prov;
        }
        $prov['message'] = 'No login existed — created one and emailed credentials to ' . $row['emp_email']
            . (!empty($prov['password']) ? ' (password: ' . $prov['password'] . ')' : '') . '.';
        return $prov;
    }
    return lieo_resend_user_credentials((int) $row['lieo_user_id'], $force);
}

/** Single-assignee steps: at most one active row per (plant, department). 'n1' is exempt (multiple allowed). */
function lieo_matrix_single_assignee_steps(): array
{
    return ['timeoffice', 'hod', 'security'];
}

/**
 * True if an active row already exists for this (plant, department, step)
 * slot, assigned to someone OTHER than $empCode. Only meaningful for
 * single-assignee steps — the widened unique key no longer blocks this by
 * itself once 'n1' needs multiple rows per slot.
 */
function lieo_matrix_slot_taken_by_other(string $plant, string $dept, string $step, string $empCode, ?int $excludeMatrixId = null): ?array
{
    $plant = lieo_ams_canonical_plant($plant);
    $dept = lieo_matrix_needs_department($step) ? trim($dept) : 'All';
    $canon = lieo_sql_canonical_plant('plant');
    $sql = "SELECT * FROM tbl_lieo_approval_matrix
            WHERE $canon = ? AND department = ? AND approval_step = ? AND status = 'Active' AND emp_code <> ?";
    if ($excludeMatrixId) {
        $sql .= ' AND matrix_id <> ' . (int) $excludeMatrixId;
    }
    $sql .= ' LIMIT 1';
    $stmt = lieo_db()->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ssss', $plant, $dept, $step, $empCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Core insert/update of an approval-matrix row + login provisioning, shared by
 * the (admin, still-live-save) matrix page and lieo_decide_user_request()'s
 * approval path — so provisioning logic isn't duplicated between them.
 */
function lieo_apply_matrix_rule(array $data, ?int $id, int $actorUserId): array
{
    try {
        lieo_ensure_lieo_auth_schema();
        $db = lieo_db();
        $plant = lieo_ams_canonical_plant($data['plant'] ?? '');
        $dept = trim($data['department'] ?? '');
        $step = $data['approval_step'] ?? '';
        $empCode = trim($data['emp_code'] ?? '');
        $empName = trim($data['emp_name'] ?? '');
        $empEmail = trim($data['emp_email'] ?? '');

        if (!in_array($step, ['timeoffice', 'n1', 'hod', 'security'], true) || $plant === '' || $empCode === '' || $empName === '') {
            return ['ok' => false, 'message' => 'Plant, role and employee are required.'];
        }
        if (lieo_matrix_needs_department($step) && $dept === '') {
            return ['ok' => false, 'message' => 'Department is required for this role.'];
        }
        if (!lieo_matrix_needs_department($step)) {
            $dept = 'All';
        }
        if ($empEmail === '') {
            return ['ok' => false, 'message' => 'Employee business email is required (used as login ID).'];
        }
        if ($step === 'timeoffice' || $step === 'security') {
            if (!lieo_ams_department_is_hr($plant, $empEmail, $empCode)) {
                return ['ok' => false, 'message' => lieo_role_label($step) . ' must be an HR department employee.'];
            }
        }
        if (in_array($step, lieo_matrix_single_assignee_steps(), true)) {
            $conflict = lieo_matrix_slot_taken_by_other($plant, $dept, $step, $empCode, $id);
            if ($conflict) {
                return [
                    'ok' => false,
                    'code' => 'slot_taken',
                    'message' => ($dept === 'All' ? $plant : $dept) . ' already has an active ' . lieo_role_label($step)
                        . ' (' . $conflict['emp_name'] . '). Use "Replace" to transfer instead of adding a new one.',
                    'existing_matrix_id' => (int) $conflict['matrix_id'],
                ];
            }
        }

        if ($id) {
            $stmt = $db->prepare(
                "UPDATE tbl_lieo_approval_matrix
                 SET plant=?, department=?, approval_step=?, emp_code=?, emp_name=?, emp_email=?, status='Active'
                 WHERE matrix_id=?"
            );
            $stmt->bind_param('ssssssi', $plant, $dept, $step, $empCode, $empName, $empEmail, $id);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO tbl_lieo_approval_matrix
                 (plant, department, approval_step, emp_code, emp_name, emp_email, status, created_by)
                 VALUES (?,?,?,?,?,?,'Active',?)
                 ON DUPLICATE KEY UPDATE emp_code=VALUES(emp_code), emp_name=VALUES(emp_name),
                   emp_email=VALUES(emp_email), status='Active', updated_at=NOW()"
            );
            $stmt->bind_param('ssssssi', $plant, $dept, $step, $empCode, $empName, $empEmail, $actorUserId);
        }
        $ok = $stmt->execute();
        $newId = $id ?: (int) $stmt->insert_id;
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => $err ?: 'Save failed.'];
        }

        $provision = lieo_provision_matrix_user(
            $step,
            $plant,
            $dept,
            $empCode,
            $empName,
            $empEmail,
            true
        );
        if (!$provision['ok']) {
            return ['ok' => false, 'message' => 'Rule saved but login setup failed: ' . ($provision['message'] ?? '')];
        }

        $msg = 'Role assignment saved.';
        if (($provision['provisioned'] ?? '') === 'created') {
            $msg .= ' Login created — credentials emailed';
            if (!empty($provision['password'])) {
                $msg .= ' (password: ' . $provision['password'] . ')';
            }
            $msg .= '. User must change password on first login.';
        } elseif (($provision['provisioned'] ?? '') === 'updated') {
            $msg .= ' Linked user profile updated — notification emailed.';
        }
        if (function_exists('lieo_is_test_mail_mode') && lieo_is_test_mail_mode()
            && !empty($_SESSION['lieo_test_mails'])) {
            $msg .= ' Test mail preview will open on this page.';
        }

        return ['ok' => true, 'matrix_id' => $newId, 'message' => $msg];
    } catch (Throwable $e) {
        error_log('lieo_apply_matrix_rule: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()];
    }
}

/**
 * Admin-only matrix save (Time Office no longer maintains the matrix — it's
 * view-only per the redesign). Still a direct/live save; routing this through
 * the HR-department-HOD user-request approval gate is a separate step
 * (see lieo_submit_user_request()) layered on top by the admin UI.
 */
function lieo_save_matrix_rule(array $data, ?int $id = null): array
{
    global $LIEO_ADMIN_MATRIX_STEPS;
    $actorRole = $_SESSION['lieo_role'] ?? '';
    if ($actorRole !== 'admin') {
        return ['ok' => false, 'message' => 'You cannot maintain the approval matrix.'];
    }
    $step = $data['approval_step'] ?? '';
    if (!in_array($step, $LIEO_ADMIN_MATRIX_STEPS, true)) {
        return ['ok' => false, 'message' => 'Plant, role and employee are required.'];
    }
    $actorUserId = (int) ($_SESSION['lieo_user_id'] ?? 0);
    return lieo_apply_matrix_rule($data, $id, $actorUserId);
}

function lieo_delete_matrix_rule(int $id): bool
{
    $stmt = lieo_db()->prepare("UPDATE tbl_lieo_approval_matrix SET status='Inactive' WHERE matrix_id=?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/** Notify the removed assignee (CC'd by the approval_matrix notify list) that their matrix role was removed. */
function lieo_notify_matrix_removed(array $row): void
{
    $email = trim((string) ($row['emp_email'] ?? ''));
    if ($email === '') {
        return;
    }
    $name = trim((string) ($row['emp_name'] ?? '')) ?: $email;
    $plant = lieo_ams_canonical_plant((string) ($row['plant'] ?? ''));
    $department = trim((string) ($row['department'] ?? ''));
    $roleLabel = lieo_role_label((string) ($row['approval_step'] ?? ''));
    $cc = $plant !== '' ? lieo_list_plant_notify_emails($plant, 'approval_matrix') : [];
    $plantDept = trim($plant . ($department !== '' && $department !== 'All' ? ' / ' . $department : ''));

    $subject = LIEO_APP_SHORT . ' :: Role removed — ' . $roleLabel;
    $body = 'Dear ' . htmlspecialchars($name) . ',<br><br>'
        . 'Your <strong>' . htmlspecialchars($roleLabel) . '</strong> assignment in '
        . '<strong>' . htmlspecialchars(LIEO_APP_NAME) . '</strong> has been removed.';
    lieo_send_mail($email, $name, $subject, $body, $cc, [
        'context' => 'Role Assignment',
        'headline' => 'Role removed: ' . $roleLabel,
        'subhead' => 'Approval matrix assignment removed.',
        'to_role' => $roleLabel,
        'cards' => [
            ['label' => 'Employee', 'value' => $name],
            ['label' => 'Role', 'value' => $roleLabel],
            ['label' => 'Plant / Dept', 'value' => $plantDept !== '' ? $plantDept : '—'],
        ],
    ]);
}

/** Notify plant Time Office that Admin changed the Department Master (CC'd by the department notify list). */
function lieo_notify_department_changed(string $plant, string $deptName, string $action): void
{
    $plant = lieo_ams_canonical_plant($plant);
    $deptName = trim($deptName);
    if ($plant === '' || $deptName === '') {
        return;
    }
    $to = lieo_list_role_notify_recipients_for_plant('timeoffice', $plant);
    if (!$to) {
        return;
    }
    $cc = lieo_list_plant_notify_emails($plant, 'department');
    $actionLabel = ucfirst($action);
    $subject = LIEO_APP_SHORT . ' :: Department ' . $actionLabel . ' — ' . $deptName;
    foreach ($to as $recipient) {
        $body = 'Dear ' . htmlspecialchars($recipient['name']) . ',<br><br>'
            . 'Department <strong>' . htmlspecialchars($deptName) . '</strong> was <strong>'
            . htmlspecialchars(strtolower($actionLabel)) . '</strong> for plant '
            . '<strong>' . htmlspecialchars($plant) . '</strong> in Department Master.';
        lieo_send_mail($recipient['email'], $recipient['name'], $subject, $body, $cc, [
            'context' => 'Department Master',
            'headline' => 'Department ' . $actionLabel,
            'subhead' => 'Department Master updated.',
            'to_role' => 'Time Office',
            'cards' => [
                ['label' => 'Department', 'value' => $deptName],
                ['label' => 'Plant', 'value' => $plant],
                ['label' => 'Action', 'value' => $actionLabel],
            ],
        ]);
    }
}

function lieo_get_matrix_approver(string $plant, string $dept, string $step): ?array
{
    $plant = lieo_ams_canonical_plant($plant);
    if (!lieo_matrix_needs_department($step)) {
        $dept = 'All';
    }
    $canon = lieo_sql_canonical_plant('plant');
    $stmt = lieo_db()->prepare(
        "SELECT * FROM tbl_lieo_approval_matrix
         WHERE $canon = ? AND department=? AND approval_step=? AND status='Active' LIMIT 1"
    );
    $stmt->bind_param('sss', $plant, $dept, $step);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_list_matrix_by_plant(): array
{
    $grouped = [];
    foreach (lieo_list_matrix() as $row) {
        $plant = lieo_ams_canonical_plant($row['plant'] ?? '');
        if ($plant === '') {
            continue;
        }
        if (!isset($grouped[$plant])) {
            $grouped[$plant] = [];
        }
        $grouped[$plant][] = $row;
    }
    ksort($grouped);
    return $grouped;
}

/** All LIEO login accounts for a plant (for HR-department HOD's "see all users of plant" tab). */
function lieo_list_users_for_plant(string $plant): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }
    $canon = lieo_sql_canonical_plant('plant');
    $stmt = lieo_db()->prepare(
        "SELECT * FROM tbl_lieo_user WHERE $canon = ? ORDER BY role, full_name"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Active LIEO login(s) for a role, scoped to one plant. @return list<array{email:string,name:string}> */
function lieo_list_role_notify_recipients_for_plant(string $role, string $plant): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }
    $canon = lieo_sql_canonical_plant('plant');
    $stmt = lieo_db()->prepare(
        "SELECT full_name, email FROM tbl_lieo_user WHERE role=? AND status='Active' AND email<>'' AND $canon = ?"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ss', $role, $plant);
    $stmt->execute();
    $out = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $email = trim($row['email'] ?? '');
        if ($email !== '') {
            $out[$email] = ['email' => $email, 'name' => trim($row['full_name'] ?? '') ?: $email];
        }
    }
    $stmt->close();
    return array_values($out);
}

/**
 * Replace the HOD on an existing matrix row with a new person, logging the
 * change (tbl_lieo_hod_transfer_log) and provisioning the new person's login.
 * The old HOD's own login is only deactivated if they hold no other active
 * matrix rows (they may still be HOD/N-1/etc elsewhere). Updating the matrix
 * row in place (not deleting + re-inserting) is what makes any application
 * currently Pending_hod for this department immediately actionable by the new
 * HOD with zero data loss — lieo_advance_application() re-resolves the
 * approver live from this table on every action, it never caches it.
 */
function lieo_transfer_hod(int $matrixId, array $newEmp, int $actorUserId): array
{
    $db = lieo_db();
    $stmt = $db->prepare("SELECT * FROM tbl_lieo_approval_matrix WHERE matrix_id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param('i', $matrixId);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$old) {
        return ['ok' => false, 'message' => 'HOD assignment not found.'];
    }
    if (($old['approval_step'] ?? '') !== 'hod') {
        return ['ok' => false, 'message' => 'This action is only for HOD assignments.'];
    }

    $newCode = trim((string) ($newEmp['emp_code'] ?? ''));
    $newName = trim((string) ($newEmp['emp_name'] ?? ''));
    $newEmail = trim((string) ($newEmp['emp_email'] ?? ''));
    if ($newCode === '' || $newName === '' || $newEmail === '') {
        return ['ok' => false, 'message' => 'New HOD employee code, name and email are required.'];
    }

    $apply = lieo_apply_matrix_rule([
        'plant' => $old['plant'],
        'department' => $old['department'],
        'approval_step' => 'hod',
        'emp_code' => $newCode,
        'emp_name' => $newName,
        'emp_email' => $newEmail,
    ], $matrixId, $actorUserId);
    if (!$apply['ok']) {
        return $apply;
    }

    $stmt = $db->prepare(
        "INSERT INTO tbl_lieo_hod_transfer_log
         (matrix_id, plant, department, old_emp_code, old_emp_name, old_emp_email, new_emp_code, new_emp_name, new_emp_email, transferred_by)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
        'issssssssi',
        $matrixId,
        $old['plant'],
        $old['department'],
        $old['emp_code'],
        $old['emp_name'],
        $old['emp_email'],
        $newCode,
        $newName,
        $newEmail,
        $actorUserId
    );
    $stmt->execute();
    $stmt->close();

    // Deactivate the outgoing HOD's login only if they hold no other active
    // matrix row anywhere (still HOD of another department, or another role).
    $oldUser = lieo_find_user_by_email((string) $old['emp_email']);
    if ($oldUser) {
        $check = $db->prepare("SELECT COUNT(*) c FROM tbl_lieo_approval_matrix WHERE emp_code = ? AND status = 'Active'");
        $check->bind_param('s', $old['emp_code']);
        $check->execute();
        $remaining = (int) ($check->get_result()->fetch_assoc()['c'] ?? 0);
        $check->close();
        if ($remaining === 0) {
            lieo_set_user_status((int) $oldUser['lieo_user_id'], 'Inactive');
        }
    }

    return ['ok' => true, 'message' => 'HOD transferred to ' . $newName . '.'];
}

// ---------------------------------------------------------------------------
// User requests (Admin create/update/delete of HOD/N-1/Security/Time Office
// assignments, pending the HR-department HOD's approval)
// ---------------------------------------------------------------------------

function lieo_submit_user_request(array $data): array
{
    $plant = lieo_ams_canonical_plant($data['plant'] ?? '');
    $step = $data['approval_step'] ?? '';
    $dept = lieo_matrix_needs_department($step) ? trim($data['department'] ?? '') : 'All';
    $empCode = trim($data['emp_code'] ?? '');
    $empName = trim($data['emp_name'] ?? '');
    $empEmail = trim($data['emp_email'] ?? '');
    $type = in_array($data['request_type'] ?? '', ['create', 'update', 'delete'], true) ? $data['request_type'] : 'create';
    $targetMatrixId = !empty($data['target_matrix_id']) ? (int) $data['target_matrix_id'] : null;

    if (!in_array($step, ['timeoffice', 'n1', 'hod', 'security'], true) || $plant === '' || $empCode === '' || $empName === '' || $empEmail === '') {
        return ['ok' => false, 'message' => 'Plant, role and employee are required.'];
    }
    if (lieo_matrix_needs_department($step) && $dept === '') {
        return ['ok' => false, 'message' => 'Department is required for this role.'];
    }
    if (($step === 'timeoffice' || $step === 'security') && !lieo_ams_department_is_hr($plant, $empEmail, $empCode)) {
        return ['ok' => false, 'message' => lieo_role_label($step) . ' must be an HR department employee.'];
    }

    // Editing a row and saving it with the same plant/department/role/employee
    // it already has isn't a change — don't queue a no-op HR-HOD approval.
    // Department is only compared for roles that use one — Time Office/Security
    // are always forced to 'All' above regardless of what's on the row (some
    // legacy rows still have a real department name stored), so a stale
    // department there must never make an otherwise-identical row look changed.
    if ($type === 'update' && $targetMatrixId) {
        $current = lieo_get_matrix_row($targetMatrixId);
        $deptUnchanged = !lieo_matrix_needs_department($step)
            || lieo_dept_names_equal((string) ($current['department'] ?? ''), $dept);
        if ($current
            && lieo_ams_canonical_plant((string) $current['plant']) === $plant
            && $deptUnchanged
            && (string) $current['approval_step'] === $step
            && strcasecmp((string) $current['emp_code'], $empCode) === 0
            && strcasecmp((string) $current['emp_email'], $empEmail) === 0
        ) {
            return ['ok' => true, 'message' => 'No changes to submit — this assignment is already set that way.'];
        }
    }

    $requestedBy = (int) ($_SESSION['lieo_user_id'] ?? 0);
    $stmt = lieo_db()->prepare(
        "INSERT INTO tbl_lieo_user_request
         (request_type, plant, department, approval_step, emp_code, emp_name, emp_email, target_matrix_id, status, requested_by)
         VALUES (?,?,?,?,?,?,?,?,'Pending',?)"
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Prepare failed: ' . lieo_db()->error];
    }
    $stmt->bind_param('sssssssii', $type, $plant, $dept, $step, $empCode, $empName, $empEmail, $targetMatrixId, $requestedBy);
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => $err ?: 'Could not submit request.'];
    }

    $request = lieo_get_user_request($newId);
    if ($request) {
        lieo_notify_user_request_submitted($request);
    }

    $message = 'Request submitted for HR department HOD approval.';
    $hrDept = lieo_hr_department_name($plant);
    $approver = $hrDept !== null ? lieo_matrix_notify_recipient($plant, $hrDept, 'hod') : null;
    if ($approver) {
        $message = 'Request submitted for HR department HOD approval (' . $approver['name'] . ' – ' . $approver['email'] . ').';
    }

    return ['ok' => true, 'request_id' => $newId, 'message' => $message];
}

function lieo_get_user_request(int $requestId): ?array
{
    $stmt = lieo_db()->prepare("SELECT * FROM tbl_lieo_user_request WHERE request_id = ?");
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function lieo_list_user_requests(string $plant, string $status = 'Pending'): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }
    $canon = lieo_sql_canonical_plant('plant');
    $sql = "SELECT * FROM tbl_lieo_user_request WHERE $canon = ?";
    $types = 's';
    $params = [$plant];
    if ($status !== '') {
        $sql .= ' AND status = ?';
        $types .= 's';
        $params[] = $status;
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = lieo_db()->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function lieo_get_matrix_row(int $matrixId): ?array
{
    $stmt = lieo_db()->prepare("SELECT * FROM tbl_lieo_approval_matrix WHERE matrix_id = ? LIMIT 1");
    $stmt->bind_param('i', $matrixId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Pending update/delete requests indexed by the existing matrix row they
 * target, so a matrix listing can flag "this row has a request queued"
 * without one query per row.
 * @return array<int,array<string,mixed>>
 */
function lieo_list_pending_target_requests(): array
{
    $rows = lieo_db()->query(
        "SELECT * FROM tbl_lieo_user_request WHERE status = 'Pending' AND target_matrix_id IS NOT NULL"
    )->fetch_all(MYSQLI_ASSOC);
    $byTarget = [];
    foreach ($rows as $r) {
        $byTarget[(int) $r['target_matrix_id']] = $r;
    }
    return $byTarget;
}

function lieo_decide_user_request(int $requestId, string $decision, int $hrHodUserId, string $remark): array
{
    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        return ['ok' => false, 'message' => 'Invalid decision.'];
    }
    $request = lieo_get_user_request($requestId);
    if (!$request || ($request['status'] ?? '') !== 'Pending') {
        return ['ok' => false, 'message' => 'Request not found or already decided.'];
    }

    if ($decision === 'Approved') {
        $targetId = !empty($request['target_matrix_id']) ? (int) $request['target_matrix_id'] : null;
        $type = $request['request_type'] ?? 'create';
        if ($type === 'delete') {
            if (!$targetId || !lieo_delete_matrix_rule($targetId)) {
                return ['ok' => false, 'message' => 'Could not delete the assignment.'];
            }
        } elseif ($type === 'update' && $targetId && ($request['approval_step'] ?? '') === 'hod') {
            // Replacing an existing HOD — go through lieo_transfer_hod() so the
            // change is logged to tbl_lieo_hod_transfer_log, not just applied.
            $transfer = lieo_transfer_hod($targetId, [
                'emp_code' => $request['emp_code'],
                'emp_name' => $request['emp_name'],
                'emp_email' => $request['emp_email'],
            ], $hrHodUserId);
            if (!$transfer['ok']) {
                return $transfer;
            }
        } else {
            $apply = lieo_apply_matrix_rule([
                'plant' => $request['plant'],
                'department' => $request['department'],
                'approval_step' => $request['approval_step'],
                'emp_code' => $request['emp_code'],
                'emp_name' => $request['emp_name'],
                'emp_email' => $request['emp_email'],
            ], $targetId, $hrHodUserId);
            if (!$apply['ok']) {
                return $apply;
            }
        }
    }

    $stmt = lieo_db()->prepare(
        "UPDATE tbl_lieo_user_request SET status=?, decided_by=?, decision_remark=?, decided_at=NOW() WHERE request_id=?"
    );
    $stmt->bind_param('sisi', $decision, $hrHodUserId, $remark, $requestId);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => 'Could not record decision.'];
    }

    $request['status'] = $decision;
    lieo_notify_user_request_decided($request, $decision);
    return ['ok' => true, 'message' => 'Request ' . strtolower($decision) . '.'];
}

/**
 * Whether this email also holds an active matrix assignment for the other
 * role of the N-1/Security pair — the one combination where a single person
 * is allowed both roles (N-1 creates the application, Security closes it at
 * the gate). Used at login to grant the second capability without a second
 * login row (tbl_lieo_user has one role column and a unique email).
 */
function lieo_user_secondary_role(string $primaryRole, string $email): ?string
{
    $pairOther = ['n1' => 'security', 'security' => 'n1'][$primaryRole] ?? null;
    $email = trim($email);
    if ($pairOther === null || $email === '') {
        return null;
    }
    $stmt = lieo_db()->prepare(
        "SELECT 1 FROM tbl_lieo_approval_matrix WHERE emp_email = ? AND approval_step = ? AND status = 'Active' LIMIT 1"
    );
    $stmt->bind_param('ss', $email, $pairOther);
    $stmt->execute();
    $has = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $has ? $pairOther : null;
}

/**
 * The logged-in N-1's department, for scoping application creation/listing.
 * Normally just $_SESSION['lieo_department'], but a person whose login was
 * first provisioned as Security has 'All' stored there (Security is plant-
 * only) even after also being granted N-1 — fall back to the matrix, which
 * always has the real department.
 */
function lieo_session_n1_department(): string
{
    $dept = trim($_SESSION['lieo_department'] ?? '');
    if ($dept !== '' && $dept !== 'All') {
        return $dept;
    }
    $plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
    $empCode = trim($_SESSION['lieo_emp_code'] ?? '');
    if ($plant === '' || $empCode === '') {
        return $dept;
    }
    $depts = lieo_list_matrix_departments_for_user($empCode, $plant, 'n1');
    return $depts[0] ?? $dept;
}

/**
 * @param bool $plantWideForHrHod Widen from "HOD's own department(s)" to "whole plant"
 *   for the HR department's HOD — same plant-wide oversight they already get for User
 *   Approval / Plant Users / Reactivation. Only pass true for read-only tracking/listing
 *   pages: lieo_advance_application() independently re-checks department authority on
 *   approve/reject, so widening the *actionable* pending queue would just show items the
 *   HR HOD cannot actually act on — confusing, not unsafe, but still wrong to show there.
 */
function lieo_apply_session_plant_scope(array $filters, bool $plantWideForHrHod = false): array
{
    $role = $_SESSION['lieo_role'] ?? '';
    if ($role === 'admin') {
        return $filters;
    }
    $plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
    if ($plant !== '' && in_array($role, ['timeoffice', 'security', 'n1', 'hod'], true)) {
        $filters['plant'] = $plant;
    }
    if ($role === 'hod') {
        // HOD is per-department (possibly several) — authority lives in the
        // matrix, not $_SESSION['lieo_department'], which only ever holds one
        // value and would silently hide a second department's applications.
        $empCode = trim($_SESSION['lieo_emp_code'] ?? '');
        $isHrHod = $plantWideForHrHod && $empCode !== '' && $plant !== '' && lieo_is_hr_hod($empCode, $plant);
        if ($empCode !== '' && $plant !== '' && !$isHrHod) {
            $filters['department_in'] = lieo_list_matrix_departments_for_user($empCode, $plant, 'hod');
        }
    } elseif ($role === 'n1' || ($_SESSION['lieo_secondary_role'] ?? null) === 'n1') {
        $dept = lieo_session_n1_department();
        if ($dept !== '' && $dept !== 'All') {
            $filters['department'] = $dept;
        }
    }
    return $filters;
}

/**
 * Distinct active departments a person holds for a given plant/step —
 * e.g. all departments a HOD is currently assigned to (may be >1).
 * @return list<string>
 */
function lieo_list_matrix_departments_for_user(string $empCode, string $plant, string $step): array
{
    $empCode = trim($empCode);
    $plant = lieo_ams_canonical_plant($plant);
    if ($empCode === '' || $plant === '') {
        return [];
    }
    $canon = lieo_sql_canonical_plant('plant');
    $stmt = lieo_db()->prepare(
        "SELECT DISTINCT department FROM tbl_lieo_approval_matrix
         WHERE $canon = ? AND approval_step = ? AND emp_code = ? AND status = 'Active'
         ORDER BY department"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('sss', $plant, $step, $empCode);
    $stmt->execute();
    $out = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $d = trim((string) ($row['department'] ?? ''));
        if ($d !== '') {
            $out[] = $d;
        }
    }
    $stmt->close();
    return $out;
}

/**
 * Whether $empCode is the active assignee for (plant, department, step).
 * Unlike lieo_get_matrix_approver() (LIMIT 1, wrong for multi-assignee steps
 * like n1), this checks a specific person and works for any step.
 */
function lieo_is_active_matrix_assignee(string $plant, string $dept, string $step, string $empCode): bool
{
    $empCode = trim($empCode);
    $plant = lieo_ams_canonical_plant($plant);
    $dept = lieo_matrix_needs_department($step) ? trim($dept) : 'All';
    if ($empCode === '' || $plant === '' || $dept === '') {
        return false;
    }
    $canon = lieo_sql_canonical_plant('plant');
    $stmt = lieo_db()->prepare(
        "SELECT 1 FROM tbl_lieo_approval_matrix
         WHERE $canon = ? AND department = ? AND approval_step = ? AND emp_code = ? AND status = 'Active' LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssss', $plant, $dept, $step, $empCode);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $ok;
}

/**
 * AMS employee master used by Approval Matrix.
 * Prefer live AMS table — tbl_nuvo_employee_lieo remaps Department incorrectly
 * (e.g. Information Management → Infrastructure).
 */
function lieo_ams_employee_table(): string
{
    return 'tbl_nuvo_employee';
}

/**
 * Product lines allowed in LIEO AMS lookup (Cement family + Nu Vista).
 */
function lieo_ams_product_line_sql(string $alias = ''): string
{
    $col = ($alias !== '' ? $alias . '.' : '') . 'empProductLine';
    return "($col LIKE '%Cement%' OR $col LIKE '%Nu Vista%')";
}

/** @deprecated Prefer lieo_ams_product_line_sql(); kept for older callers. */
function lieo_ams_product_line(): string
{
    return '70000000-Cement';
}

/**
 * Canonical plant code for LIEO.
 * Collapses AMS variants to one plant, e.g.:
 *   "87000003-NVL-C-RCP", "NVCL_RCP", "NVL-C-RCP", "RCP" → "RCP"
 *   "77000099-Mumbai" → "Mumbai"
 * Uses the last token after "-" or "_".
 */
function lieo_ams_canonical_plant(?string $plantOrLocation): string
{
    $value = trim((string) $plantOrLocation);
    if ($value === '') {
        return '';
    }
    $normalized = str_replace('_', '-', $value);
    $pos = strrpos($normalized, '-');
    return $pos === false ? $normalized : substr($normalized, $pos + 1);
}

/**
 * @deprecated Prefer lieo_ams_canonical_plant()
 */
function lieo_ams_plant_short(?string $workLocation): string
{
    return lieo_ams_canonical_plant($workLocation);
}

/**
 * Full AMS plant token after first hyphen (kept for legacy callers; not used in plant list).
 */
function lieo_ams_plant_full_short(?string $workLocation): string
{
    $workLocation = trim((string) $workLocation);
    if ($workLocation === '') {
        return '';
    }
    $pos = strpos($workLocation, '-');
    return $pos === false ? $workLocation : substr($workLocation, $pos + 1);
}

/**
 * SQL: match selected plant code against AMS work location last token.
 * Binds the plant parameter once (canonical short code, e.g. RCP).
 */
function lieo_ams_plant_match_sql(string $alias = ''): string
{
    $col = ($alias !== '' ? $alias . '.' : '') . 'empWorkLocation';
    return "SUBSTRING_INDEX(REPLACE($col, '_', '-'), '-', -1) = ?";
}

/**
 * SQL expression that returns the canonical plant short code for a stored plant column.
 */
function lieo_sql_canonical_plant(string $column = 'plant'): string
{
    return "SUBSTRING_INDEX(REPLACE($column, '_', '-'), '-', -1)";
}

function lieo_ams_bind_plant(string &$types, array &$params, string $plant): void
{
    $types .= 's';
    $params[] = lieo_ams_canonical_plant($plant);
}

/**
 * Distinct plants from AMS as canonical short codes only (one plant = one code).
 * @return list<string>
 */
function lieo_list_ams_plants(?string $q = null): array
{
    $db = lieo_db();
    $table = lieo_ams_employee_table();
    $productSql = lieo_ams_product_line_sql();
    $sql = "SELECT DISTINCT empWorkLocation
            FROM `$table`
            WHERE empStatus = 'Active'
              AND $productSql
              AND empWorkLocation IS NOT NULL
              AND empWorkLocation != ''";
    $res = $db->query($sql);
    if (!$res) {
        return [];
    }
    $plants = [];
    $qNorm = $q !== null ? strtolower(trim($q)) : '';
    while ($row = $res->fetch_assoc()) {
        $code = lieo_ams_canonical_plant($row['empWorkLocation'] ?? '');
        if ($code === '') {
            continue;
        }
        if ($qNorm !== '' && strpos(strtolower($code), $qNorm) === false) {
            continue;
        }
        $plants[strtoupper($code)] = $code;
    }
    $list = array_values($plants);
    natcasesort($list);
    return array_values($list);
}

/**
 * Departments configured for a plant in Department Master (active only). AMS departments are not merged in.
 * @return list<string>
 */
function lieo_list_departments_for_plant(string $plant): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }

    $merged = [];
    $seen = [];

    foreach (lieo_list_plant_departments($plant, true) as $row) {
        $dept = lieo_normalize_dept_name((string) ($row['department_name'] ?? ''));
        if ($dept === '') {
            continue;
        }
        $key = strtolower($dept);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $merged[] = $dept;
    }

    natcasesort($merged);
    return array_values($merged);
}

/** @return list<array<string,mixed>> */
function lieo_list_plant_departments(string $plant, bool $activeOnly = false): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $sql = "SELECT * FROM tbl_lieo_plant_department WHERE plant = ?";
    if ($activeOnly) {
        $sql .= " AND status = 'Active'";
    }
    $sql .= ' ORDER BY department_name';
    $stmt = lieo_db()->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * All plant master department rows, optionally filtered by plant.
 * @return list<array<string,mixed>>
 */
function lieo_list_all_plant_departments(?string $plant = null): array
{
    if ($plant !== null && $plant !== '') {
        return lieo_list_plant_departments(lieo_ams_canonical_plant($plant), false);
    }
    $res = lieo_db()->query(
        'SELECT * FROM tbl_lieo_plant_department ORDER BY plant, department_name'
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Plants that have at least one row in plant department master.
 * @return list<string>
 */
function lieo_list_plants_with_department_data(): array
{
    $res = lieo_db()->query(
        "SELECT DISTINCT plant FROM tbl_lieo_plant_department
         WHERE plant IS NOT NULL AND TRIM(plant) != ''"
    );
    if (!$res) {
        return [];
    }
    $plants = [];
    while ($row = $res->fetch_assoc()) {
        $code = lieo_ams_canonical_plant((string) ($row['plant'] ?? ''));
        if ($code === '') {
            continue;
        }
        $plants[strtoupper($code)] = $code;
    }
    $list = array_values($plants);
    natcasesort($list);
    return array_values($list);
}

/**
 * Active Time Office matrix rows for a plant (for department master reference).
 * @return list<array<string,mixed>>
 */
function lieo_list_timeoffice_matrix_for_plant(string $plant): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }
    $canon = lieo_sql_canonical_plant('m.plant');
    $stmt = lieo_db()->prepare(
        "SELECT m.*
         FROM tbl_lieo_approval_matrix m
         WHERE m.status = 'Active'
           AND m.approval_step = 'timeoffice'
           AND $canon = ?
         ORDER BY m.department, m.emp_name"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Normalize department name for strict compare (trim + collapse spaces). */
function lieo_normalize_dept_name(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    return $name;
}

function lieo_dept_names_equal(string $a, string $b): bool
{
    return strcasecmp(lieo_normalize_dept_name($a), lieo_normalize_dept_name($b)) === 0;
}

/**
 * The plant's designated HR department name (is_hr='t'), or null if none set
 * yet. This is the single source of truth for "is this the HR department" —
 * see lieo_hod_workflow_redesign.sql's is_hr column comment for why name
 * matching (e.g. against a hardcoded 'HR' string) isn't used instead.
 */
function lieo_hr_department_name(string $plant): ?string
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return null;
    }
    $stmt = lieo_db()->prepare(
        "SELECT department_name FROM tbl_lieo_plant_department WHERE plant = ? AND is_hr = 't' AND status = 'Active' LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? trim((string) $row['department_name']) : null;
}

/**
 * Whether a HOD holds the plant's flagged HR department among their active
 * HOD assignments — this is what unlocks the "User Approval" / plant-users
 * sidebar tabs, not a name comparison.
 */
function lieo_is_hr_hod(string $empCode, string $plant): bool
{
    $hrDept = lieo_hr_department_name($plant);
    if ($hrDept === null) {
        return false;
    }
    foreach (lieo_list_matrix_departments_for_user($empCode, $plant, 'hod') as $dept) {
        if (lieo_dept_names_equal($dept, $hrDept)) {
            return true;
        }
    }
    return false;
}

/**
 * Whether an AMS employee's own (coarse) AMS Department resolves to the
 * plant's HR department — used to gate Time Office/Security assignment to HR
 * department people only. AMS's coarse "Department" field uses a different,
 * broader vocabulary than the LIEO department master (AMS says "Human
 * Resources" for everyone from HR/Security/Medical/..., LIEO's master has a
 * granular 'HR' row) — they don't string-match each other, so this compares
 * against the is_hr row's separately recorded ams_department_name, not its
 * department_name.
 */
function lieo_ams_department_is_hr(string $plant, string $empEmail, string $empCode = ''): bool
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return false;
    }
    $stmt = lieo_db()->prepare(
        "SELECT COALESCE(NULLIF(TRIM(ams_department_name), ''), department_name) AS ams_department_name
         FROM tbl_lieo_plant_department WHERE plant = ? AND is_hr = 't' AND status = 'Active' LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $amsHrDept = trim((string) ($row['ams_department_name'] ?? ''));
    if ($amsHrDept === '') {
        return false;
    }
    // Security may log in with a shared/custom mailbox that is not the employee's own AMS
    // email, so when the employee code is known, judge the HR department by that code.
    $empCode = trim($empCode);
    if ($empCode !== '') {
        $table = lieo_ams_employee_table();
        $stmt = lieo_db()->prepare(
            "SELECT TRIM(Department) AS dept FROM `$table`
             WHERE empStatus = 'Active' AND " . lieo_ams_product_line_sql() . ' AND ' . lieo_ams_plant_match_sql() . '
               AND empCode = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $plant, $empCode);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r ? lieo_dept_names_equal((string) $r['dept'], $amsHrDept) : false;
    }
    foreach (lieo_list_ams_emails_for_plant($plant) as $r) {
        if (strcasecmp($r['email'], trim($empEmail)) === 0) {
            return lieo_dept_names_equal((string) $r['department'], $amsHrDept);
        }
    }
    return false;
}

// ---------------------------------------------------------------------------
// Department requests (Admin add/edit of Department Master rows, pending the
// HR-department HOD's approval — same pattern as user requests above.
// Deactivate/activate/delete stay immediate; only add/edit are gated.)
// ---------------------------------------------------------------------------

function lieo_submit_department_request(string $plant, string $name, ?int $id = null, bool $isHr = false, string $amsDeptName = ''): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $name = lieo_normalize_dept_name($name);
    if ($plant === '' || $name === '') {
        return ['ok' => false, 'message' => 'Plant and department name are required.'];
    }
    $amsDeptName = trim($amsDeptName);
    foreach (lieo_list_plant_departments($plant) as $row) {
        if ($id !== null && (int) ($row['dept_id'] ?? 0) === $id) {
            continue;
        }
        if (lieo_dept_names_equal($name, (string) ($row['department_name'] ?? ''))) {
            return ['ok' => false, 'code' => 'master_duplicate', 'message' => 'Department already exists for this plant.'];
        }
    }

    $type = $id ? 'edit' : 'add';
    $requestedBy = (int) ($_SESSION['lieo_user_id'] ?? 0);
    $isHrInt = $isHr ? 1 : 0;
    $stmt = lieo_db()->prepare(
        "INSERT INTO tbl_lieo_department_request
         (request_type, plant, department_name, is_hr, ams_department_name, target_dept_id, status, requested_by)
         VALUES (?,?,?,?,?,?,'Pending',?)"
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Prepare failed: ' . lieo_db()->error];
    }
    $stmt->bind_param('sssisii', $type, $plant, $name, $isHrInt, $amsDeptName, $id, $requestedBy);
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => $err ?: 'Could not submit request.'];
    }

    $request = lieo_get_department_request($newId);
    if ($request) {
        lieo_notify_department_request_submitted($request);
    }
    $message = 'Request submitted for HR department HOD approval.';
    $hrDept = lieo_hr_department_name($plant);
    $approver = $hrDept !== null ? lieo_matrix_notify_recipient($plant, $hrDept, 'hod') : null;
    if ($approver) {
        $message = 'Request submitted for HR department HOD approval (' . $approver['name'] . ' – ' . $approver['email'] . ').';
    }
    return ['ok' => true, 'request_id' => $newId, 'message' => $message];
}

function lieo_get_department_request(int $requestId): ?array
{
    $stmt = lieo_db()->prepare('SELECT * FROM tbl_lieo_department_request WHERE request_id = ?');
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function lieo_list_department_requests(string $plant, string $status = 'Pending'): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }
    $sql = 'SELECT * FROM tbl_lieo_department_request WHERE plant = ?';
    $types = 's';
    $params = [$plant];
    if ($status !== '') {
        $sql .= ' AND status = ?';
        $types .= 's';
        $params[] = $status;
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = lieo_db()->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Pending requests indexed by the existing dept_id they target (edits only — adds have no target yet). */
function lieo_list_pending_department_target_requests(): array
{
    $rows = lieo_db()->query(
        "SELECT * FROM tbl_lieo_department_request WHERE status = 'Pending' AND target_dept_id IS NOT NULL"
    )->fetch_all(MYSQLI_ASSOC);
    $byTarget = [];
    foreach ($rows as $r) {
        $byTarget[(int) $r['target_dept_id']] = $r;
    }
    return $byTarget;
}

function lieo_decide_department_request(int $requestId, string $decision, int $hrHodUserId, string $remark): array
{
    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        return ['ok' => false, 'message' => 'Invalid decision.'];
    }
    $request = lieo_get_department_request($requestId);
    if (!$request || ($request['status'] ?? '') !== 'Pending') {
        return ['ok' => false, 'message' => 'Request not found or already decided.'];
    }

    if ($decision === 'Approved') {
        $targetId = !empty($request['target_dept_id']) ? (int) $request['target_dept_id'] : null;
        $apply = lieo_save_plant_department(
            (string) $request['plant'],
            (string) $request['department_name'],
            $targetId,
            (bool) $request['is_hr'],
            (string) $request['ams_department_name']
        );
        if (!$apply['ok']) {
            return $apply;
        }
        lieo_notify_department_changed((string) $request['plant'], (string) $request['department_name'], $targetId ? 'updated' : 'added');
    }

    $stmt = lieo_db()->prepare(
        'UPDATE tbl_lieo_department_request SET status=?, decided_by=?, decision_remark=?, decided_at=NOW() WHERE request_id=?'
    );
    $stmt->bind_param('sisi', $decision, $hrHodUserId, $remark, $requestId);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => 'Could not record decision.'];
    }

    $request['status'] = $decision;
    $request['decision_remark'] = $remark;
    lieo_notify_department_request_decided($request, $decision);
    return ['ok' => true, 'message' => 'Request ' . strtolower($decision) . '.'];
}

function lieo_save_plant_department(string $plant, string $name, ?int $id = null, bool $isHr = false, string $amsDeptName = ''): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $name = lieo_normalize_dept_name($name);
    if ($plant === '' || $name === '') {
        return ['ok' => false, 'message' => 'Plant and department name are required.'];
    }

    $amsDeptName = trim($amsDeptName);

    // Department Master is manual-only — block duplicates already in this plant master.
    foreach (lieo_list_plant_departments($plant) as $row) {
        if ($id !== null && (int) ($row['dept_id'] ?? 0) === $id) {
            continue;
        }
        if (lieo_dept_names_equal($name, (string) ($row['department_name'] ?? ''))) {
            return [
                'ok' => false,
                'code' => 'master_duplicate',
                'message' => 'Department already exists for this plant.',
            ];
        }
    }

    $db = lieo_db();
    $by = (int) ($_SESSION['lieo_user_id'] ?? 0);
    $isHrVal = $isHr ? 't' : 'f';

    // Only one department per plant may be flagged HR — unset any other first.
    if ($isHr) {
        $unset = $db->prepare("UPDATE tbl_lieo_plant_department SET is_hr='f' WHERE plant=? AND dept_id<>?");
        $excludeId = $id ?? 0;
        $unset->bind_param('si', $plant, $excludeId);
        $unset->execute();
        $unset->close();
    }

    $amsDeptVal = $isHr ? $amsDeptName : null;

    if ($id) {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_plant_department SET department_name=?, is_hr=?, ams_department_name=?, status='Active' WHERE dept_id=? AND plant=?"
        );
        $stmt->bind_param('sssis', $name, $isHrVal, $amsDeptVal, $id, $plant);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO tbl_lieo_plant_department (plant, department_name, is_hr, ams_department_name, status, created_by)
             VALUES (?,?,?,?,'Active',?)"
        );
        $stmt->bind_param('ssssi', $plant, $name, $isHrVal, $amsDeptVal, $by);
    }
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'message' => $err ?: 'Save failed (duplicate?).'];
}

/**
 * Add one or more extra plant departments (skips AMS duplicates and existing master rows).
 *
 * @param list<string> $names
 * @return array{ok:bool,added:list<string>,skipped:list<array{name:string,reason:string}>,errors:list<array{name:string,reason:string}>,departments:list<string>,message?:string}
 */
function lieo_add_plant_departments_bulk(string $plant, array $names): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return ['ok' => false, 'added' => [], 'skipped' => [], 'errors' => [], 'departments' => [], 'message' => 'Plant is required.'];
    }

    $added = [];
    $skipped = [];
    $errors = [];
    $seenInput = [];

    foreach ($names as $name) {
        $name = lieo_normalize_dept_name((string) $name);
        if ($name === '') {
            continue;
        }
        $key = strtolower($name);
        if (isset($seenInput[$key])) {
            continue;
        }
        $seenInput[$key] = true;

        $result = lieo_save_plant_department($plant, $name);
        if (!empty($result['ok'])) {
            $added[] = $name;
            continue;
        }
        $code = (string) ($result['code'] ?? '');
        $reason = (string) ($result['message'] ?? 'Could not add.');
        if ($code === 'ams_duplicate' || $code === 'master_duplicate') {
            $skipped[] = ['name' => $name, 'reason' => $reason];
        } else {
            $errors[] = ['name' => $name, 'reason' => $reason];
        }
    }

    if (!$added && !$skipped && !$errors) {
        return [
            'ok' => false,
            'added' => [],
            'skipped' => [],
            'errors' => [],
            'departments' => lieo_list_departments_for_plant($plant),
            'message' => 'Enter at least one department name.',
        ];
    }

    $message = '';
    if ($added) {
        $message = count($added) === 1
            ? 'Added 1 department.'
            : ('Added ' . count($added) . ' departments.');
    }
    if ($skipped && !$added && !$errors) {
        $message = 'All entered names already exist in the plant master.';
    } elseif ($skipped && $message !== '') {
        $message .= ' ' . count($skipped) . ' skipped (already exist).';
    }
    if ($errors) {
        $message = ($message !== '' ? $message . ' ' : '') . count($errors) . ' could not be added.';
    }

    return [
        'ok' => count($added) > 0 || (count($skipped) > 0 && count($errors) === 0),
        'added' => $added,
        'skipped' => $skipped,
        'errors' => $errors,
        'departments' => lieo_list_departments_for_plant($plant),
        'message' => $message,
    ];
}

function lieo_find_plant_department_by_id(int $id, string $plant): ?array
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare('SELECT * FROM tbl_lieo_plant_department WHERE dept_id=? AND plant=?');
    $stmt->bind_param('is', $id, $plant);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_delete_plant_department(int $id, string $plant): bool
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare('DELETE FROM tbl_lieo_plant_department WHERE dept_id=? AND plant=?');
    $stmt->bind_param('is', $id, $plant);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lieo_set_plant_department_status(int $id, string $plant, string $status): bool
{
    $plant = lieo_ams_canonical_plant($plant);
    $status = $status === 'Active' ? 'Active' : 'Inactive';
    $stmt = lieo_db()->prepare(
        "UPDATE tbl_lieo_plant_department SET status=? WHERE dept_id=? AND plant=?"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sis', $status, $id, $plant);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/** @return list<string> */
function lieo_list_plant_notify_emails(string $plant, string $context = 'reactivation'): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare(
        "SELECT email FROM tbl_lieo_plant_notify_email WHERE plant=? AND context=? AND status='Active' ORDER BY email"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ss', $plant, $context);
    $stmt->execute();
    $out = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $e = trim($row['email'] ?? '');
        if ($e !== '') {
            $out[] = $e;
        }
    }
    $stmt->close();
    return $out;
}

function lieo_add_plant_notify_email(string $plant, string $email, string $context = 'reactivation'): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $email = strtolower(trim($email));
    if ($plant === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Valid plant and email are required.'];
    }
    if (!lieo_ams_email_in_plant($plant, $email)) {
        return ['ok' => false, 'message' => 'Email must belong to an AMS employee at this plant.'];
    }
    $by = (int) ($_SESSION['lieo_user_id'] ?? 0);
    $stmt = lieo_db()->prepare(
        "INSERT INTO tbl_lieo_plant_notify_email (plant, context, email, status, created_by) VALUES (?,?,?,'Active',?)
         ON DUPLICATE KEY UPDATE status='Active'"
    );
    $stmt->bind_param('sssi', $plant, $context, $email, $by);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'message' => $err ?: 'Could not add email.'];
}

function lieo_delete_plant_notify_email(int $id, string $plant): bool
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare('DELETE FROM tbl_lieo_plant_notify_email WHERE notify_id=? AND plant=?');
    $stmt->bind_param('is', $id, $plant);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lieo_set_plant_notify_status(int $id, string $plant, string $status): bool
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare('UPDATE tbl_lieo_plant_notify_email SET status=? WHERE notify_id=? AND plant=?');
    $stmt->bind_param('sis', $status, $id, $plant);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lieo_update_plant_notify_email(int $id, string $plant, string $email): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !lieo_ams_email_in_plant($plant, $email)) {
        return ['ok' => false, 'message' => 'Email must belong to an AMS employee at this plant.'];
    }
    $stmt = lieo_db()->prepare('UPDATE tbl_lieo_plant_notify_email SET email=? WHERE notify_id=? AND plant=?');
    $stmt->bind_param('sis', $email, $id, $plant);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'message' => str_contains($err, 'Duplicate') ? 'That email already exists for this notification.' : ($err ?: 'Could not update.')];
}

function lieo_list_plant_notify_rows(string $plant, string $context = 'reactivation'): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare(
        "SELECT * FROM tbl_lieo_plant_notify_email WHERE plant=? AND context=? ORDER BY email"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ss', $plant, $context);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Distinct AMS business emails for a plant (active employees only).
 * @return list<array{email:string,emp_name:string,emp_code:string,department:string}>
 */
function lieo_list_ams_emails_for_plant(string $plant): array
{
    $plant = lieo_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }
    $db = lieo_db();
    $table = lieo_ams_employee_table();
    $productSql = lieo_ams_product_line_sql();
    $plantSql = lieo_ams_plant_match_sql();
    $stmt = $db->prepare(
        "SELECT empCode, empName, empBusiEmail, TRIM(Department) AS dept
         FROM `$table`
         WHERE empStatus = 'Active'
           AND $productSql
           AND $plantSql
           AND empBusiEmail IS NOT NULL
           AND TRIM(empBusiEmail) != ''
         ORDER BY empName"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $res = $stmt->get_result();
    $seen = [];
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $email = strtolower(trim((string) ($row['empBusiEmail'] ?? '')));
        if ($email === '' || isset($seen[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $seen[$email] = true;
        $out[] = [
            'email' => $email,
            'emp_name' => trim((string) ($row['empName'] ?? '')),
            'emp_code' => trim((string) ($row['empCode'] ?? '')),
            'department' => trim((string) ($row['dept'] ?? '')),
        ];
    }
    $stmt->close();
    usort($out, static function ($a, $b) {
        return strcasecmp($a['email'], $b['email']);
    });
    return $out;
}

/**
 * True if email belongs to an active AMS employee at the plant.
 */
function lieo_ams_email_in_plant(string $plant, string $email): bool
{
    $plant = lieo_ams_canonical_plant($plant);
    $email = strtolower(trim($email));
    if ($plant === '' || $email === '') {
        return false;
    }
    $db = lieo_db();
    $table = lieo_ams_employee_table();
    $productSql = lieo_ams_product_line_sql();
    $plantSql = lieo_ams_plant_match_sql();
    $stmt = $db->prepare(
        "SELECT 1
         FROM `$table`
         WHERE empStatus = 'Active'
           AND $productSql
           AND $plantSql
           AND LOWER(TRIM(empBusiEmail)) = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $plant, $email);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $ok;
}

/**
 * List / search AMS employees.
 * Pass empty $q to load everyone for plant (+ optional department) into a dropdown.
 *
 * @return list<array<string,mixed>>
 */
function lieo_search_employees(string $q, int $limit = 20, ?string $plant = null, ?string $department = null): array
{
    $db = lieo_db();
    $table = lieo_ams_employee_table();
    $productSql = lieo_ams_product_line_sql();
    $plant = $plant !== null ? trim($plant) : '';
    $department = $department !== null ? trim($department) : '';
    $q = trim($q);
    $limit = max(1, min(2000, $limit));

    $sql = "SELECT empCode, empName, empBusiEmail, empDepartment, Department, empPlant, empWorkLocation, empProductLine
            FROM `$table`
            WHERE empStatus = 'Active'
              AND $productSql";
    $types = '';
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= ' AND (empName LIKE ? OR CAST(empCode AS CHAR) LIKE ? OR IFNULL(empBusiEmail,\'\') LIKE ? OR IFNULL(searchIndex,\'\') LIKE ?)';
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }
    if ($plant !== '') {
        $sql .= ' AND ' . lieo_ams_plant_match_sql();
        lieo_ams_bind_plant($types, $params, $plant);
    }
    if ($department !== '') {
        // Match official Department; also tolerate coded empDepartment strings.
        $sql .= ' AND (TRIM(Department) = ? OR empDepartment = ? OR empDepartment LIKE ? OR empDepartment LIKE ?)';
        $types .= 'ssss';
        $params[] = $department;
        $params[] = $department;
        $params[] = '%-' . $department . '_%';
        $params[] = '%-' . $department;
    }
    $sql .= ' ORDER BY empName LIMIT ?';
    $types .= 'i';
    $params[] = $limit;

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $sql = "SELECT empCode, empName, empBusiEmail, empDepartment, Department, empPlant, empWorkLocation, empProductLine
                FROM `$table`
                WHERE empStatus = 'Active'
                  AND $productSql";
        $types = '';
        $params = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' AND (empName LIKE ? OR CAST(empCode AS CHAR) LIKE ? OR IFNULL(empBusiEmail,\'\') LIKE ?)';
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }
        if ($plant !== '') {
            $sql .= ' AND ' . lieo_ams_plant_match_sql();
            lieo_ams_bind_plant($types, $params, $plant);
        }
        if ($department !== '') {
            $sql .= ' AND TRIM(Department) = ?';
            $types .= 's';
            $params[] = $department;
        }
        $sql .= ' ORDER BY empName LIMIT ?';
        $types .= 'i';
        $params[] = $limit;
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ---------------------------------------------------------------------------
// Applications
// ---------------------------------------------------------------------------

function lieo_next_application_no(): string
{
    $prefix = 'LIEO-' . date('Ymd') . '-';
    $res = lieo_db()->query(
        "SELECT application_no FROM tbl_lieo_application
         WHERE application_no LIKE '" . lieo_esc($prefix) . "%'
         ORDER BY application_id DESC LIMIT 1"
    );
    $seq = 1;
    if ($res && ($row = $res->fetch_assoc())) {
        $parts = explode('-', $row['application_no']);
        $seq = ((int) end($parts)) + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function lieo_create_application(array $data): array
{
    $type = $data['application_type'] ?? '';
    $reason = trim($data['reason'] ?? '');
    $createdBy = (int) ($data['created_by'] ?? 0);

    $workmanName = trim($data['workman_name'] ?? '');
    $workmanCode = trim($data['workman_code'] ?? '');
    $contractorId = (int) ($data['contractor_id'] ?? 0);
    $plant = lieo_ams_canonical_plant($data['plant'] ?? '');
    $department = trim($data['department'] ?? '');
    $shift = trim($data['shift'] ?? '');

    // Legacy: create from existing workman master id
    $workmanId = (int) ($data['workman_id'] ?? 0);
    if ($workmanId > 0 && $workmanName === '') {
        $w = lieo_get_workman($workmanId);
        if (!$w || $w['status'] !== 'Active') {
            return ['ok' => false, 'message' => 'Workman not found or inactive.'];
        }
        $workmanName = $w['workman_name'];
        $workmanCode = $w['workman_code'];
        $contractorId = (int) $w['contractor_id'];
        $plant = lieo_ams_canonical_plant($w['plant'] ?? '');
        $department = trim($w['department'] ?? '');
        $shift = trim($w['shift'] ?? '');
    }

    if (!in_array($type, ['Late Coming', 'Early Going'], true) || $reason === '') {
        return ['ok' => false, 'message' => 'Application type and reason are required.'];
    }
    if ($workmanName === '' || $workmanCode === '' || $contractorId < 1 || $plant === '' || $department === '' || $shift === '') {
        return ['ok' => false, 'message' => 'Workman name, ID code, contractor, shift, plant and department are required.'];
    }

    $contractor = lieo_get_contractor($contractorId);
    if (!$contractor || ($contractor['status'] ?? '') !== 'Active') {
        return ['ok' => false, 'message' => 'Contractor not found or inactive.'];
    }
    $cName = $contractor['contractor_name'] ?? ($contractor['vendor_name'] ?? '');

    // N-1 creates the application directly — must be an active N-1 for this
    // plant/department, and that department must have a HOD to approve it.
    $actorCode = trim($data['actor_emp_code'] ?? '');
    if (!lieo_is_active_matrix_assignee($plant, $department, 'n1', $actorCode)) {
        return ['ok' => false, 'message' => 'You are not an assigned N-1 for ' . $plant . ' / ' . $department . '.'];
    }
    $hod = lieo_get_matrix_approver($plant, $department, 'hod');
    if (!$hod) {
        return ['ok' => false, 'message' => $department . ' department HOD is not created please contact to Admin'];
    }

    // Optional: keep workman master in sync for reporting (find-or-create by code)
    if ($workmanId < 1) {
        $existing = lieo_find_workman_by_code($workmanCode);
        if ($existing) {
            $workmanId = (int) $existing['workman_id'];
            lieo_save_workman([
                'workman_code' => $workmanCode,
                'workman_name' => $workmanName,
                'contractor_id' => $contractorId,
                'plant' => $plant,
                'department' => $department,
                'shift' => $shift,
            ], $workmanId);
        } else {
            $saved = lieo_save_workman([
                'workman_code' => $workmanCode,
                'workman_name' => $workmanName,
                'contractor_id' => $contractorId,
                'plant' => $plant,
                'department' => $department,
                'shift' => $shift,
            ]);
            $workmanId = $saved['ok'] ? (int) ($saved['workman_id'] ?? 0) : 0;
        }
    }

    $access = $type === 'Late Coming' ? 'Entry' : 'Exit';
    $appNo = lieo_next_application_no();
    $date = date('Y-m-d');

    $stmt = lieo_db()->prepare(
        "INSERT INTO tbl_lieo_application
         (application_no, application_type, access_type, workman_id, workman_code, workman_name,
          contractor_id, contractor_name, plant, department, shift, reason, application_date,
          status, current_step, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending_hod','hod',?)"
    );
    $stmt->bind_param(
        'sssississssssi',
        $appNo,
        $type,
        $access,
        $workmanId,
        $workmanCode,
        $workmanName,
        $contractorId,
        $cName,
        $plant,
        $department,
        $shift,
        $reason,
        $date,
        $createdBy
    );
    $ok = $stmt->execute();
    $id = (int) $stmt->insert_id;
    $err = $stmt->error;
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => $err ?: 'Create failed.'];
    }
    $app = lieo_get_application($id);
    if ($app) {
        lieo_notify_application_created($app);
    }
    return ['ok' => true, 'application_id' => $id, 'application_no' => $appNo];
}

function lieo_get_application(int $id): ?array
{
    $stmt = lieo_db()->prepare("SELECT * FROM tbl_lieo_application WHERE application_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_list_applications(array $filters = []): array
{
    $where = ['1=1'];
    if (!empty($filters['status'])) {
        $where[] = "status = '" . lieo_esc($filters['status']) . "'";
    }
    if (!empty($filters['current_step'])) {
        $where[] = "current_step = '" . lieo_esc($filters['current_step']) . "'";
    }
    if (!empty($filters['access_type'])) {
        $where[] = "access_type = '" . lieo_esc($filters['access_type']) . "'";
    }
    if (!empty($filters['date'])) {
        $where[] = "application_date = '" . lieo_esc($filters['date']) . "'";
    }
    if (!empty($filters['date_from'])) {
        $where[] = "application_date >= '" . lieo_esc($filters['date_from']) . "'";
    }
    if (!empty($filters['plant'])) {
        $canonPlant = lieo_ams_canonical_plant($filters['plant']);
        $where[] = lieo_sql_canonical_plant('plant') . " = '" . lieo_esc($canonPlant) . "'";
    }
    if (!empty($filters['department'])) {
        $where[] = "department = '" . lieo_esc($filters['department']) . "'";
    }
    if (!empty($filters['department_in']) && is_array($filters['department_in'])) {
        $depts = array_filter(array_map('trim', $filters['department_in']), static fn($d) => $d !== '');
        if ($depts) {
            $quoted = array_map(static fn($d) => "'" . lieo_esc($d) . "'", $depts);
            $where[] = 'department IN (' . implode(',', $quoted) . ')';
        } else {
            // HOD with no active department assignment sees nothing, not everything.
            $where[] = '1=0';
        }
    }
    if (!empty($filters['created_by'])) {
        $where[] = "created_by = " . (int) $filters['created_by'];
    }
    if (!empty($filters['contractor_id'])) {
        $where[] = "contractor_id = " . (int) $filters['contractor_id'];
    }
    if (!empty($filters['workman_id'])) {
        $where[] = "workman_id = " . (int) $filters['workman_id'];
    }
    if (!empty($filters['shift'])) {
        $where[] = "shift = '" . lieo_esc($filters['shift']) . "'";
    }
    if (!empty($filters['date_to'])) {
        $where[] = "application_date <= '" . lieo_esc($filters['date_to']) . "'";
    }
    if (!empty($filters['pending_for_role'])) {
        // 'hod' is the only step in the current chain; timeoffice/n1 entries are
        // kept so any pre-redesign application still mid-flight in a legacy
        // status can still be found/tracked, not silently orphaned.
        $role = $filters['pending_for_role'];
        $map = [
            'timeoffice' => 'Pending_timeoffice',
            'n1' => 'Pending_n1',
            'hod' => 'Pending_hod',
        ];
        if (isset($map[$role])) {
            $where[] = "status = '" . $map[$role] . "' AND current_step = '" . lieo_esc($role) . "'";
        }
    }
    $sql = "SELECT * FROM tbl_lieo_application WHERE " . implode(' AND ', $where) . " ORDER BY application_id DESC";
    $res = lieo_db()->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function lieo_advance_application(int $appId, string $role, string $action, string $remark, array $actor): array
{
    $app = lieo_get_application($appId);
    if (!$app) {
        return ['ok' => false, 'message' => 'Application not found.'];
    }
    if ($app['current_step'] !== $role || strpos($app['status'], 'Pending_') !== 0) {
        return ['ok' => false, 'message' => 'Not pending for your step.'];
    }

    $matrix = lieo_get_matrix_approver($app['plant'], $app['department'], $role);
    $actorCode = trim($actor['emp_code'] ?? '');
    if (!$matrix || $matrix['emp_code'] !== $actorCode) {
        return ['ok' => false, 'message' => 'You are not the assigned approver for this plant / department.'];
    }
    if (!in_array($action, ['approve', 'reject'], true)) {
        return ['ok' => false, 'message' => 'Invalid action.'];
    }

    $db = lieo_db();
    $userId = (int) ($actor['lieo_user_id'] ?? 0);
    $empCode = $actor['emp_code'] ?? '';
    $name = $actor['full_name'] ?? '';

    $stmt = $db->prepare(
        "INSERT INTO tbl_lieo_application_approval
         (application_id, step, approver_user_id, approver_emp_code, approver_name, action, remark)
         VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('isissss', $appId, $role, $userId, $empCode, $name, $action, $remark);
    $stmt->execute();
    $stmt->close();

    if ($action === 'reject') {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_application
             SET status='Rejected', current_step='rejected', reject_reason=?, rejected_by_step=?
             WHERE application_id=?"
        );
        $stmt->bind_param('ssi', $remark, $role, $appId);
        $stmt->execute();
        $stmt->close();
        $result = ['ok' => true, 'status' => 'Rejected'];
        lieo_notify_application_action($app, $role, $action, $remark, $result);
        return $result;
    }

    // Only 'hod' remains in the live chain (N-1 creates, HOD approves, then
    // gate). 'timeoffice'/'n1' entries stay mapped so a pre-redesign
    // application still mid-flight in a legacy pending state doesn't break.
    $chain = ['timeoffice' => 'n1', 'n1' => 'hod', 'hod' => null];
    $next = $chain[$role] ?? null;
    if ($next === null) {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_application SET status='Approved', current_step='gate' WHERE application_id=?"
        );
        $stmt->bind_param('i', $appId);
        $stmt->execute();
        $stmt->close();
        $result = ['ok' => true, 'status' => 'Approved'];
        lieo_notify_application_action($app, $role, $action, $remark, $result);
        return $result;
    }

    $status = 'Pending_' . $next;
    $stmt = $db->prepare(
        "UPDATE tbl_lieo_application SET status=?, current_step=? WHERE application_id=?"
    );
    $stmt->bind_param('ssi', $status, $next, $appId);
    $stmt->execute();
    $stmt->close();
    $result = ['ok' => true, 'status' => $status];
    lieo_notify_application_action($app, $role, $action, $remark, $result);
    return $result;
}

function lieo_application_ready_for_gate(?array $app): bool
{
    if (!$app) {
        return false;
    }
    $status = $app['status'] ?? '';
    return $status === 'Approved' || $status === 'Attested';
}

function lieo_attest_application(int $appId, int $userId): array
{
    return ['ok' => false, 'message' => 'Attestation is no longer required. Security closes applications after HOD approval.'];
}

function lieo_gate_action(int $appId, string $action, int $securityUserId, string $remark, string $gateAt = ''): array
{
    $remark = trim($remark);
    if ($remark === '') {
        return ['ok' => false, 'message' => 'Please enter a remark when closing the application.'];
    }
    if (mb_strlen($remark) > 500) {
        return ['ok' => false, 'message' => 'Remark must be 500 characters or less.'];
    }

    $gateAt = trim($gateAt);
    if ($gateAt === '') {
        return ['ok' => false, 'message' => 'Please enter the gate date and time (when the workman entered or left).'];
    }
    // Accept HTML datetime-local (Y-m-d\TH:i) or Y-m-d H:i[:s]
    $gateAtNorm = str_replace('T', ' ', $gateAt);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $gateAtNorm)) {
        $gateAtNorm .= ':00';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $gateAtNorm);
    $errors = DateTime::getLastErrors();
    if (!$dt || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
        return ['ok' => false, 'message' => 'Invalid gate date/time.'];
    }
    $gateAtSql = $dt->format('Y-m-d H:i:s');

    $app = lieo_get_application($appId);
    if (!lieo_application_ready_for_gate($app)) {
        return ['ok' => false, 'message' => 'Only HOD-approved applications (not yet closed at gate) can be completed here.'];
    }

    $secUser = lieo_get_user($securityUserId);
    $secName = $secUser['full_name'] ?? 'Security';
    $secCode = $secUser['emp_code'] ?? '';
    // Shared gate login: record the person picked at sign-in, not the login owner.
    if ($securityUserId === (int) ($_SESSION['lieo_user_id'] ?? 0) && !empty($_SESSION['lieo_emp_code'])) {
        $secName = (string) ($_SESSION['lieo_user_name'] ?? $secName);
        $secCode = (string) $_SESSION['lieo_emp_code'];
    }

    $db = lieo_db();
    if ($action === 'in') {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_application
             SET gate_in_at=?, security_by=?, security_at=?, gate_remark=?,
                 status='Gate_completed', current_step='done'
             WHERE application_id=?"
        );
    } elseif ($action === 'out') {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_application
             SET gate_out_at=?, security_by=?, security_at=?, gate_remark=?,
                 status='Gate_completed', current_step='done'
             WHERE application_id=?"
        );
    } else {
        return ['ok' => false, 'message' => 'Invalid gate action.'];
    }
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Database error. Run php database/run_lieo_gate_remark.php on the server.'];
    }
    $stmt->bind_param('sissi', $gateAtSql, $securityUserId, $gateAtSql, $remark, $appId);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => 'Gate update failed.'];
    }

    $gateStep = 'gate';
    $gateAction = $action === 'in' ? 'gate_in' : 'gate_out';
    $trailRemark = $remark . ' | Gate time: ' . $dt->format('d M Y, H:i');
    $stmt2 = $db->prepare(
        "INSERT INTO tbl_lieo_application_approval
         (application_id, step, approver_user_id, approver_emp_code, approver_name, action, remark)
         VALUES (?,?,?,?,?,?,?)"
    );
    if ($stmt2) {
        $stmt2->bind_param('isissss', $appId, $gateStep, $securityUserId, $secCode, $secName, $gateAction, $trailRemark);
        $stmt2->execute();
        $stmt2->close();
    }

    lieo_notify_gate_closed($app, $action, $remark);
    return ['ok' => true];
}

function lieo_dashboard_counts(string $date): array
{
    $db = lieo_db();
    $d = lieo_esc($date);
    $counts = [
        'contractors' => 0,
        'matrix_rules' => 0,
        'pending' => 0,
        'in_today' => 0,
        'out_today' => 0,
        'completed_today' => 0,
    ];
    $r = $db->query("SELECT COUNT(*) c FROM tbl_lieo_contractor WHERE status='Active'");
    $counts['contractors'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_lieo_approval_matrix WHERE status='Active'");
    $counts['matrix_rules'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_lieo_application WHERE status LIKE 'Pending_%'");
    $counts['pending'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_lieo_application WHERE access_type='Entry' AND application_date='$d'");
    $counts['in_today'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_lieo_application WHERE access_type='Exit' AND application_date='$d'");
    $counts['out_today'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_lieo_application WHERE status='Gate_completed' AND application_date='$d'");
    $counts['completed_today'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    return $counts;
}

/** Role-scoped dashboard numbers for a date (default plant/dept from session). */
function lieo_role_dashboard_stats(string $date, array $extra = []): array
{
    $filters = lieo_apply_session_plant_scope(array_merge(['date' => $date], $extra));
    $rows = lieo_list_applications($filters);
    $stats = [
        'created' => count($rows),
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'gate_completed' => 0,
        'pending_mine' => 0,
    ];
    $role = $_SESSION['lieo_role'] ?? '';
    foreach ($rows as $row) {
        $st = $row['status'] ?? '';
        if (strpos($st, 'Pending_') === 0) {
            $stats['pending']++;
        }
        if ($st === 'Approved') {
            $stats['approved']++;
        }
        if ($st === 'Rejected') {
            $stats['rejected']++;
        }
        if ($st === 'Gate_completed') {
            $stats['gate_completed']++;
        }
        if (($row['current_step'] ?? '') === $role && strpos($st, 'Pending_') === 0) {
            $stats['pending_mine']++;
        }
    }
    return $stats;
}

function lieo_list_shifts(): array
{
    $res = lieo_db()->query("SELECT * FROM tbl_lieo_shift_master WHERE status='Active' ORDER BY shift_id");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** All shifts (any status) for the admin Shift Master page. */
function lieo_list_all_shifts(): array
{
    $res = lieo_db()->query('SELECT * FROM tbl_lieo_shift_master ORDER BY shift_id');
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function lieo_get_shift(int $id): ?array
{
    $stmt = lieo_db()->prepare('SELECT * FROM tbl_lieo_shift_master WHERE shift_id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function lieo_save_shift(array $data, ?int $id = null): array
{
    $name = trim((string) ($data['shift_name'] ?? ''));
    $start = trim((string) ($data['start_time'] ?? ''));
    $end = trim((string) ($data['end_time'] ?? ''));
    $lateGrace = (int) ($data['late_grace_minutes'] ?? 0);
    $earlyGrace = (int) ($data['early_grace_minutes'] ?? 0);

    if ($name === '' || $start === '' || $end === '') {
        return ['ok' => false, 'message' => 'Shift name, start time and end time are required.'];
    }
    if ($lateGrace < 0 || $earlyGrace < 0) {
        return ['ok' => false, 'message' => 'Grace minutes cannot be negative.'];
    }

    $db = lieo_db();
    if ($id) {
        $stmt = $db->prepare(
            'UPDATE tbl_lieo_shift_master
             SET shift_name=?, start_time=?, end_time=?, late_grace_minutes=?, early_grace_minutes=?
             WHERE shift_id=?'
        );
        $stmt->bind_param('sssiii', $name, $start, $end, $lateGrace, $earlyGrace, $id);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO tbl_lieo_shift_master (shift_name, start_time, end_time, late_grace_minutes, early_grace_minutes, status)
             VALUES (?,?,?,?,?,'Active')"
        );
        $stmt->bind_param('sssii', $name, $start, $end, $lateGrace, $earlyGrace);
    }
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'message' => $err ?: 'Save failed.'];
}

function lieo_set_shift_status(int $id, string $status): bool
{
    $stmt = lieo_db()->prepare("UPDATE tbl_lieo_shift_master SET status=? WHERE shift_id=?");
    $stmt->bind_param('si', $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lieo_delete_shift(int $id): bool
{
    $stmt = lieo_db()->prepare('DELETE FROM tbl_lieo_shift_master WHERE shift_id=?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function lieo_get_application_approvals(int $appId): array
{
    $stmt = lieo_db()->prepare(
        "SELECT * FROM tbl_lieo_application_approval WHERE application_id=? ORDER BY approval_id"
    );
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Actions taken by a specific approver (approve / reject history). */
function lieo_list_approver_history(int $userId, int $limit = 200): array
{
    if ($userId < 1) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $stmt = lieo_db()->prepare(
        "SELECT a.approval_id, a.application_id, a.step, a.action, a.remark, a.acted_at,
                a.approver_name, a.approver_emp_code,
                app.application_no, app.application_type, app.workman_name, app.workman_code,
                app.contractor_name, app.plant, app.department, app.status AS app_status,
                app.reason, app.created_at, app.created_by, app.reject_reason,
                app.attested_at, app.attested_by, app.gate_in_at, app.gate_out_at, app.security_by,
                cu.full_name AS creator_name
         FROM tbl_lieo_application_approval a
         INNER JOIN tbl_lieo_application app ON app.application_id = a.application_id
         LEFT JOIN tbl_lieo_user cu ON cu.lieo_user_id = app.created_by
         WHERE a.approver_user_id = ?
         ORDER BY a.acted_at DESC, a.approval_id DESC
         LIMIT ?"
    );
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Time Office: applications created or attested by this user. */
function lieo_list_timeoffice_history(int $userId, int $limit = 200): array
{
    if ($userId < 1) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $stmt = lieo_db()->prepare(
        "SELECT app.application_id, app.application_no, app.application_type, app.workman_name, app.workman_code,
                app.contractor_name, app.plant, app.department, app.status AS app_status,
                app.reason, app.created_at, app.created_by, app.reject_reason,
                app.attested_at, app.attested_by, app.gate_in_at, app.gate_out_at, app.security_by,
                cu.full_name AS creator_name,
                CASE WHEN app.attested_by = ? THEN app.attested_at ELSE app.created_at END AS acted_at,
                CASE WHEN app.attested_by = ? THEN 'attest' ELSE 'create' END AS my_action
         FROM tbl_lieo_application app
         LEFT JOIN tbl_lieo_user cu ON cu.lieo_user_id = app.created_by
         WHERE app.created_by = ? OR app.attested_by = ?
         ORDER BY acted_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('iiiii', $userId, $userId, $userId, $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Security: applications gated by this user. */
function lieo_list_security_history(int $userId, int $limit = 200): array
{
    if ($userId < 1) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $stmt = lieo_db()->prepare(
        "SELECT app.application_id, app.application_no, app.application_type, app.workman_name, app.workman_code,
                app.contractor_name, app.plant, app.department, app.status AS app_status,
                app.reason, app.created_at, app.created_by, app.reject_reason,
                app.attested_at, app.attested_by, app.gate_in_at, app.gate_out_at, app.security_by,
                cu.full_name AS creator_name,
                COALESCE(app.security_at, app.gate_in_at, app.gate_out_at) AS acted_at
         FROM tbl_lieo_application app
         LEFT JOIN tbl_lieo_user cu ON cu.lieo_user_id = app.created_by
         WHERE app.security_by = ?
         ORDER BY acted_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Full remark trail for one application: Time Office reason → each approver → attest → gate.
 */
function lieo_application_remark_trail(int $appId, ?array $app = null): array
{
    // Always load full application so gate_remark / gate times are present
    // (history list rows may omit these columns).
    $full = lieo_get_application($appId);
    if (!$full) {
        return [];
    }
    $app = $full;

    $trail = [];
    $creatorName = $app['creator_name'] ?? '';
    if ($creatorName === '' && !empty($app['created_by'])) {
        $u = lieo_get_user((int) $app['created_by']);
        $creatorName = $u['full_name'] ?? '';
    }

    $trail[] = [
        'label' => 'Time Office',
        'by' => $creatorName !== '' ? $creatorName : 'Time Office',
        'action' => 'Created',
        'remark' => trim((string) ($app['reason'] ?? '')),
        'at' => $app['created_at'] ?? '',
    ];

    $hasGateTrail = false;
    foreach (lieo_get_application_approvals($appId) as $a) {
        $act = (string) ($a['action'] ?? '');
        $step = (string) ($a['step'] ?? '');
        $remark = trim((string) ($a['remark'] ?? ''));

        if ($act === 'gate_in' || ($step === 'gate' && $act === '' && !empty($app['gate_in_at']))) {
            $actionLabel = 'Gate IN';
            $hasGateTrail = true;
            if ($remark === '') {
                $remark = trim((string) ($app['gate_remark'] ?? ''));
            }
        } elseif ($act === 'gate_out' || ($step === 'gate' && $act === '' && !empty($app['gate_out_at']))) {
            $actionLabel = 'Gate OUT';
            $hasGateTrail = true;
            if ($remark === '') {
                $remark = trim((string) ($app['gate_remark'] ?? ''));
            }
        } elseif ($step === 'gate') {
            // Legacy rows where ENUM dropped gate_in/out → empty action.
            $actionLabel = !empty($app['gate_out_at']) && empty($app['gate_in_at']) ? 'Gate OUT' : 'Gate IN';
            $hasGateTrail = true;
            if ($remark === '') {
                $remark = trim((string) ($app['gate_remark'] ?? ''));
            }
        } elseif ($act === 'reject') {
            $actionLabel = 'Rejected';
        } else {
            $actionLabel = 'Approved';
        }

        $trail[] = [
            'label' => lieo_step_label($step),
            'by' => $a['approver_name'] ?: '—',
            'action' => $actionLabel,
            'remark' => $remark,
            'at' => $a['acted_at'] ?? '',
        ];
    }

    if (!empty($app['reject_reason']) && empty(array_filter($trail, static function ($t) {
        return ($t['action'] ?? '') === 'Rejected';
    }))) {
        $trail[] = [
            'label' => 'Rejection',
            'by' => '—',
            'action' => 'Rejected',
            'remark' => trim((string) $app['reject_reason']),
            'at' => $app['updated_at'] ?? '',
        ];
    }

    if (!empty($app['attested_at'])) {
        $name = 'Time Office';
        if (!empty($app['attested_by'])) {
            $u = lieo_get_user((int) $app['attested_by']);
            $name = $u['full_name'] ?? $name;
        }
        $trail[] = [
            'label' => 'Time Office',
            'by' => $name,
            'action' => 'Attested',
            'remark' => '',
            'at' => $app['attested_at'],
        ];
    }

    if (!$hasGateTrail && (!empty($app['gate_in_at']) || !empty($app['gate_out_at']))) {
        $name = 'Security';
        if (!empty($app['security_by'])) {
            $u = lieo_get_user((int) $app['security_by']);
            $name = $u['full_name'] ?? $name;
        }
        $trail[] = [
            'label' => 'Security',
            'by' => $name,
            'action' => !empty($app['gate_in_at']) ? 'Gate IN' : 'Gate OUT',
            'remark' => trim((string) ($app['gate_remark'] ?? '')),
            'at' => $app['gate_in_at'] ?: $app['gate_out_at'],
        ];
    }

    return $trail;
}

function lieo_render_remark_trail_html(array $trail): string
{
    if (!$trail) {
        return '<span class="text-muted">—</span>';
    }
    $html = '<ul class="list-unstyled mb-0 small">';
    foreach ($trail as $t) {
        $html .= '<li class="mb-2 pb-2 border-bottom">';
        $html .= '<strong>' . htmlspecialchars($t['label']) . '</strong>';
        $html .= ' <span class="text-muted">(' . htmlspecialchars($t['by']) . ')</span>';
        $html .= ' — ' . htmlspecialchars($t['action']);
        if (($t['remark'] ?? '') !== '') {
            $html .= '<br><em class="text-dark">' . htmlspecialchars($t['remark']) . '</em>';
        } else {
            $html .= '<br><span class="text-muted">No remark</span>';
        }
        if (($t['at'] ?? '') !== '') {
            $html .= '<br><span class="text-muted">' . htmlspecialchars($t['at']) . '</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/** One-line preview for history table (Time Office reason + step count). */
function lieo_remark_trail_summary(array $trail, int $maxLen = 55): string
{
    if (!$trail) {
        return '—';
    }
    $firstRemark = trim((string) ($trail[0]['remark'] ?? ''));
    if ($firstRemark === '') {
        $firstRemark = 'No remark at create';
    }
    if (strlen($firstRemark) > $maxLen) {
        $firstRemark = substr($firstRemark, 0, $maxLen) . '…';
    }
    $n = count($trail);
    return 'TO: ' . $firstRemark . ' · ' . $n . ' step' . ($n === 1 ? '' : 's');
}
