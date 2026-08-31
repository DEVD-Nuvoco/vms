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
    if (lieo_is_local_dev()) {
        $localUser = lieo_find_local_test_user_by_email($email);
        if ($localUser) {
            return $localUser;
        }
    }

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
 * Diagnose login failure without revealing too much in UI by default.
 * @return 'missing'|'inactive'|'bad_password'|'ok'
 */
function lieo_login_diagnose(string $email, string $password): string
{
    $email = trim($email);
    if (lieo_is_local_dev() && lieo_find_local_test_user_by_email($email)) {
        return 'ok';
    }

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

    $existing = lieo_find_user_by_email($empEmail);
    if ($existing) {
        if ($existing['role'] !== $role) {
            return ['ok' => false, 'message' => 'Email already used for role ' . lieo_role_label($existing['role']) . '.'];
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
            lieo_send_credentials_email($empEmail, $empName, $pass, false, $role);
            return [
                'ok' => true,
                'provisioned' => 'created',
                'email' => $empEmail,
                'password' => $pass,
            ];
        }

        // Existing login with password — still notify on assign (popup / SMTP).
        if ($sendCredentials) {
            lieo_send_role_assigned_email($empEmail, $empName, $role, $plant, $department);
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
    lieo_send_credentials_email($created['email'], $created['name'], $created['password'], false, $role);
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
            return ['ok' => false, 'message' => 'Your Time Office login has no plant. Re-assign plant in Approval Matrix.'];
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
 * Resend LIEO credentials email for a matrix assignment.
 * Only allowed while the linked user has not changed their first password yet.
 */
function lieo_resend_matrix_credentials(int $matrixId): array
{
    lieo_ensure_lieo_auth_schema();
    $db = lieo_db();
    $stmt = $db->prepare(
        "SELECT m.*, u.lieo_user_id, u.must_change_password, u.status AS user_status
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
    $email = trim($row['emp_email'] ?? '');
    $name = trim($row['emp_name'] ?? '');
    if ($email === '') {
        return ['ok' => false, 'message' => 'No email on this assignment.'];
    }
    if (empty($row['lieo_user_id'])) {
        return ['ok' => false, 'message' => 'No LIEO login linked. Save the assignment again to create login.'];
    }
    if (($row['user_status'] ?? '') !== 'Active') {
        return ['ok' => false, 'message' => 'LIEO account is inactive.'];
    }
    if (($row['must_change_password'] ?? 'f') !== 't') {
        return ['ok' => false, 'message' => 'Password already changed — credentials cannot be resent.'];
    }

    $uid = (int) $row['lieo_user_id'];
    $pass = lieo_generate_password();
    if (!lieo_set_password($uid, $pass, false)) {
        return ['ok' => false, 'message' => 'Could not reset temporary password.'];
    }
    $must = $db->prepare("UPDATE tbl_lieo_user SET must_change_password = 't' WHERE lieo_user_id = ?");
    $must->bind_param('i', $uid);
    $must->execute();
    $must->close();

    lieo_send_credentials_email($email, $name !== '' ? $name : $email, $pass, true, (string) ($row['approval_step'] ?? ''));
    return [
        'ok' => true,
        'message' => 'Credentials resent to ' . $email . ' (password: ' . $pass . ').',
        'password' => $pass,
        'email' => $email,
    ];
}

function lieo_save_matrix_rule(array $data, ?int $id = null): array
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
        $actorRole = $_SESSION['lieo_role'] ?? '';
        global $LIEO_ADMIN_MATRIX_STEPS, $LIEO_TO_MATRIX_STEPS;
        if ($actorRole === 'admin') {
            $allowed = $LIEO_ADMIN_MATRIX_STEPS;
        } elseif ($actorRole === 'timeoffice') {
            $allowed = $LIEO_TO_MATRIX_STEPS;
            $sessionPlant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
            if ($sessionPlant !== '') {
                $plant = $sessionPlant;
            }
        } else {
            return ['ok' => false, 'message' => 'You cannot maintain the approval matrix.'];
        }
        if (!in_array($step, $allowed, true) || $plant === '' || $empCode === '' || $empName === '') {
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
        $createdBy = (int) ($_SESSION['lieo_user_id'] ?? 0);

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
            $stmt->bind_param('ssssssi', $plant, $dept, $step, $empCode, $empName, $empEmail, $createdBy);
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
        error_log('lieo_save_matrix_rule: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()];
    }
}

function lieo_delete_matrix_rule(int $id): bool
{
    $stmt = lieo_db()->prepare("UPDATE tbl_lieo_approval_matrix SET status='Inactive' WHERE matrix_id=?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
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

function lieo_apply_session_plant_scope(array $filters): array
{
    $role = $_SESSION['lieo_role'] ?? '';
    if ($role === 'admin') {
        return $filters;
    }
    $plant = lieo_ams_canonical_plant($_SESSION['lieo_plant'] ?? '');
    if ($plant !== '' && in_array($role, ['section_incharge', 'timeoffice', 'security', 'n1', 'hod', 'hr'], true)) {
        $filters['plant'] = $plant;
    }
    $dept = trim($_SESSION['lieo_department'] ?? '');
    if ($dept !== '' && $dept !== 'All' && in_array($role, ['section_incharge', 'timeoffice', 'n1'], true)) {
        $filters['department'] = $dept;
    }
    return $filters;
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
 * Distinct AMS departments for a plant (uses real Department column).
 * @return list<string>
 */
function lieo_list_ams_departments(string $plant): array
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
        "SELECT DISTINCT TRIM(Department) AS dept
         FROM `$table`
         WHERE empStatus = 'Active'
           AND $productSql
           AND $plantSql
           AND Department IS NOT NULL
           AND TRIM(Department) != ''
         ORDER BY dept"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $plant);
    $stmt->execute();
    $res = $stmt->get_result();
    $deps = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['dept'])) {
            $deps[] = $row['dept'];
        }
    }
    $stmt->close();
    return $deps;
}

/**
 * Departments for a plant: AMS list plus any extra plant master rows (merged, deduped).
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

    foreach (lieo_list_ams_departments($plant) as $dept) {
        $dept = lieo_normalize_dept_name((string) $dept);
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

function lieo_save_plant_department(string $plant, string $name, ?int $id = null): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $name = lieo_normalize_dept_name($name);
    if ($plant === '' || $name === '') {
        return ['ok' => false, 'message' => 'Plant and department name are required.'];
    }

    // Strict AMS check: "Sales " / "sales" must match existing AMS "Sales".
    foreach (lieo_list_ams_departments($plant) as $amsDept) {
        if (lieo_dept_names_equal($name, (string) $amsDept)) {
            return [
                'ok' => false,
                'code' => 'ams_duplicate',
                'message' => 'Department already mentioned in the AMS portal',
            ];
        }
    }

    // Also block duplicates already in this plant master.
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
    if ($id) {
        $stmt = $db->prepare(
            "UPDATE tbl_lieo_plant_department SET department_name=?, status='Active' WHERE dept_id=? AND plant=?"
        );
        $stmt->bind_param('sis', $name, $id, $plant);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO tbl_lieo_plant_department (plant, department_name, status, created_by)
             VALUES (?,?,'Active',?)"
        );
        $stmt->bind_param('ssi', $plant, $name, $by);
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
        $message = 'All entered names already exist (AMS or plant master).';
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
function lieo_list_plant_notify_emails(string $plant): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare(
        "SELECT email FROM tbl_lieo_plant_notify_email WHERE plant=? AND status='Active' ORDER BY email"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $plant);
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

function lieo_add_plant_notify_email(string $plant, string $email): array
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
        "INSERT INTO tbl_lieo_plant_notify_email (plant, email, status, created_by) VALUES (?,?,'Active',?)
         ON DUPLICATE KEY UPDATE status='Active'"
    );
    $stmt->bind_param('ssi', $plant, $email, $by);
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

function lieo_list_plant_notify_rows(string $plant): array
{
    $plant = lieo_ams_canonical_plant($plant);
    $stmt = lieo_db()->prepare(
        "SELECT * FROM tbl_lieo_plant_notify_email WHERE plant=? ORDER BY email"
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
    if ($workmanName === '' || $workmanCode === '' || $contractorId < 1 || $plant === '' || $department === '') {
        return ['ok' => false, 'message' => 'Workman name, ID code, contractor, plant and department are required.'];
    }

    $contractor = lieo_get_contractor($contractorId);
    if (!$contractor || ($contractor['status'] ?? '') !== 'Active') {
        return ['ok' => false, 'message' => 'Contractor not found or inactive.'];
    }
    $cName = $contractor['contractor_name'] ?? ($contractor['vendor_name'] ?? '');

    $to = lieo_get_matrix_approver($plant, $department, 'timeoffice');
    if (!$to) {
        return ['ok' => false, 'message' => 'No Time Office configured in Approval Matrix for ' . $plant . ' / ' . $department . '.'];
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
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending_timeoffice','timeoffice',?)"
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
    if (!empty($filters['created_by'])) {
        $where[] = "created_by = " . (int) $filters['created_by'];
    }
    if (!empty($filters['contractor_id'])) {
        $where[] = "contractor_id = " . (int) $filters['contractor_id'];
    }
    if (!empty($filters['workman'])) {
        $q = lieo_esc($filters['workman']);
        $where[] = "(workman_name LIKE '%$q%' OR workman_code LIKE '%$q%')";
    }
    if (!empty($filters['date_to'])) {
        $where[] = "application_date <= '" . lieo_esc($filters['date_to']) . "'";
    }
    if (!empty($filters['pending_for_role'])) {
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
