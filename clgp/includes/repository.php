<?php
/**
 * CLGP Phase 1 — data access for Late Coming / Early Going.
 */
require_once __DIR__ . '/db.php';

function clgp_db(): mysqli
{
    global $clgpDb;
    return $clgpDb;
}

function clgp_esc(string $s): string
{
    return clgp_db()->real_escape_string($s);
}

// ---------------------------------------------------------------------------
// Auth / users
// ---------------------------------------------------------------------------

function clgp_find_user_by_login(string $email, string $password): ?array
{
    $db = clgp_db();
    $email = trim($email);
    $stmt = $db->prepare(
        "SELECT *
         FROM tbl_clgp_user
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
    if ((string) ($row['password'] ?? '') === '' || (string) ($row['password'] ?? '') !== $password) {
        return null;
    }
    return $row;
}

/**
 * Diagnose login failure without revealing too much in UI by default.
 * @return 'missing'|'inactive'|'bad_password'|'ok'
 */
function clgp_login_diagnose(string $email, string $password): string
{
    $db = clgp_db();
    $email = trim($email);
    $stmt = $db->prepare('SELECT status, password FROM tbl_clgp_user WHERE email = ? LIMIT 1');
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
    $stored = (string) ($row['password'] ?? '');
    if ($stored === '' || $stored !== $password) {
        return 'bad_password';
    }
    return 'ok';
}

function clgp_get_user(int $clgpUserId): ?array
{
    $stmt = clgp_db()->prepare("SELECT * FROM tbl_clgp_user WHERE clgp_user_id = ? LIMIT 1");
    $stmt->bind_param('i', $clgpUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function clgp_list_users(): array
{
    $res = clgp_db()->query("SELECT * FROM tbl_clgp_user ORDER BY clgp_user_id DESC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function clgp_create_user(array $data, string $plainPassword): array
{
    $db = clgp_db();
    $email = trim($data['email'] ?? '');
    $name = trim($data['full_name'] ?? '');
    $role = $data['role'] ?? '';
    $empCode = trim($data['emp_code'] ?? '');
    $plant = clgp_ams_canonical_plant($data['plant'] ?? '');
    $dept = trim($data['department'] ?? '');

    // LIEO login is separate from VMS — uniqueness is only within tbl_clgp_user.
    $check = $db->prepare("SELECT clgp_user_id FROM tbl_clgp_user WHERE email = ? LIMIT 1");
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        return ['ok' => false, 'message' => 'LIEO login email already exists.'];
    }
    $check->close();

    $createdBy = (int) ($_SESSION['clgp_user_id'] ?? 0);
    // login_id is legacy/unused — LIEO auth uses tbl_clgp_user.password only.
    $stmt2 = $db->prepare(
        "INSERT INTO tbl_clgp_user
         (login_id, full_name, email, password, role, emp_code, plant, department, must_change_password, status, created_by)
         VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 't', 'Active', ?)"
    );
    $stmt2->bind_param('sssssssi', $name, $email, $plainPassword, $role, $empCode, $plant, $dept, $createdBy);
    if (!$stmt2->execute()) {
        $err = $stmt2->error;
        $stmt2->close();
        return ['ok' => false, 'message' => 'User insert failed: ' . $err];
    }
    $userId = (int) $stmt2->insert_id;
    $stmt2->close();
    return ['ok' => true, 'clgp_user_id' => $userId, 'password' => $plainPassword, 'email' => $email, 'name' => $name];
}

/**
 * Update LIEO password by clgp_user_id (not VMS login_id).
 */
function clgp_set_password(int $clgpUserId, string $newPassword, bool $clearMustChange = true): bool
{
    $db = clgp_db();
    if ($clearMustChange) {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_user
             SET password = ?, must_change_password = 'f'
             WHERE clgp_user_id = ?"
        );
    } else {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_user
             SET password = ?
             WHERE clgp_user_id = ?"
        );
    }
    $stmt->bind_param('si', $newPassword, $clgpUserId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function clgp_find_user_by_email(string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }
    $stmt = clgp_db()->prepare("SELECT * FROM tbl_clgp_user WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Create or update CLGP login from Approval Matrix assignment.
 */
function clgp_provision_matrix_user(
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
    $plant = clgp_ams_canonical_plant($plant);
    $department = trim($department);

    if ($empEmail === '' || $empName === '' || $empCode === '') {
        return ['ok' => false, 'message' => 'Employee email, name and code are required for login.'];
    }

    $existing = clgp_find_user_by_email($empEmail);
    if ($existing) {
        if ($existing['role'] !== $role) {
            return ['ok' => false, 'message' => 'Email already used for role ' . clgp_role_label($existing['role']) . '.'];
        }
        $uid = (int) $existing['clgp_user_id'];
        $stmt = clgp_db()->prepare(
            "UPDATE tbl_clgp_user
             SET full_name=?, emp_code=?, plant=?, department=?, status='Active'
             WHERE clgp_user_id=?"
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
            $pass = clgp_generate_password();
            if (!clgp_set_password($uid, $pass, false)) {
                return ['ok' => false, 'message' => 'Could not set LIEO password.'];
            }
            $must = clgp_db()->prepare("UPDATE tbl_clgp_user SET must_change_password = 't' WHERE clgp_user_id = ?");
            $must->bind_param('i', $uid);
            $must->execute();
            $must->close();
            clgp_send_credentials_email($empEmail, $empName, $pass);
            return [
                'ok' => true,
                'provisioned' => 'created',
                'email' => $empEmail,
                'password' => $pass,
            ];
        }

        return ['ok' => true, 'provisioned' => 'updated', 'email' => $empEmail];
    }

    if (!$sendCredentials) {
        return ['ok' => false, 'message' => 'No existing login for this email; cannot update credentials.'];
    }

    $pass = clgp_generate_password();
    $created = clgp_create_user([
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
    clgp_send_credentials_email($created['email'], $created['name'], $created['password']);
    return [
        'ok' => true,
        'provisioned' => 'created',
        'email' => $created['email'],
        'password' => $created['password'],
    ];
}

function clgp_set_user_status(int $userId, string $status): bool
{
    $stmt = clgp_db()->prepare("UPDATE tbl_clgp_user SET status = ? WHERE clgp_user_id = ?");
    $stmt->bind_param('si', $status, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ---------------------------------------------------------------------------
// Contractors
// ---------------------------------------------------------------------------

function clgp_list_contractors(?string $status = null): array
{
    $sql = "SELECT * FROM tbl_clgp_contractor";
    if ($status) {
        $sql .= " WHERE status = '" . clgp_esc($status) . "'";
    }
    $sql .= " ORDER BY contractor_id DESC";
    $res = clgp_db()->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function clgp_get_contractor(int $id): ?array
{
    $stmt = clgp_db()->prepare("SELECT * FROM tbl_clgp_contractor WHERE contractor_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function clgp_save_contractor(array $data, ?int $id = null): array
{
    $db = clgp_db();
    $vendor = trim($data['vendor_name'] ?? '');
    $cname = trim($data['contractor_name'] ?? '');
    $vtype = $data['vendor_type'] ?? 'Temporary';
    $sup = trim($data['supervisor_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $cmob = trim($data['contractor_mobile'] ?? '');
    $smob = trim($data['supervisor_mobile'] ?? '');
    $allowed = ['Supply', 'Temporary', 'Measurement'];
    if (!in_array($vtype, $allowed, true)) {
        return ['ok' => false, 'message' => 'Invalid vendor type.'];
    }
    if ($vendor === '' || $cname === '' || $sup === '' || $email === '' || $cmob === '' || $smob === '') {
        return ['ok' => false, 'message' => 'All contractor fields are mandatory.'];
    }

    if ($id) {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_contractor SET vendor_name=?, contractor_name=?, vendor_type=?, supervisor_name=?, email=?, contractor_mobile=?, supervisor_mobile=?
             WHERE contractor_id=?"
        );
        $stmt->bind_param('sssssssi', $vendor, $cname, $vtype, $sup, $email, $cmob, $smob, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ['ok' => true, 'contractor_id' => $id] : ['ok' => false, 'message' => 'Update failed.'];
    }

    $createdBy = (int) ($_SESSION['clgp_user_id'] ?? 0);
    $stmt = $db->prepare(
        "INSERT INTO tbl_clgp_contractor
         (vendor_name, contractor_name, vendor_type, supervisor_name, email, contractor_mobile, supervisor_mobile, status, created_by)
         VALUES (?,?,?,?,?,?,?,'Active',?)"
    );
    $stmt->bind_param('sssssssi', $vendor, $cname, $vtype, $sup, $email, $cmob, $smob, $createdBy);
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();
    return $ok ? ['ok' => true, 'contractor_id' => $newId] : ['ok' => false, 'message' => 'Insert failed.'];
}

function clgp_deactivate_contractor(int $id, string $reason = ''): bool
{
    $stmt = clgp_db()->prepare(
        "UPDATE tbl_clgp_contractor SET status='Inactive', deactivation_reason=?, deactivated_at=NOW(), reactivation_requested='f' WHERE contractor_id=?"
    );
    $stmt->bind_param('si', $reason, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function clgp_request_reactivation(int $id): bool
{
    $stmt = clgp_db()->prepare(
        "UPDATE tbl_clgp_contractor SET reactivation_requested='t' WHERE contractor_id=? AND status='Inactive'"
    );
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        $c = clgp_get_contractor($id);
        if ($c) {
            clgp_notify_reactivation_requested($c);
        }
    }
    return $ok;
}

function clgp_approve_reactivation(int $id, int $hrUserId): bool
{
    $stmt = clgp_db()->prepare(
        "UPDATE tbl_clgp_contractor
         SET status='Active', reactivation_requested='f', reactivation_approved_by=?, reactivation_approved_at=NOW(),
             deactivation_reason=NULL, deactivated_at=NULL
         WHERE contractor_id=? AND reactivation_requested='t'"
    );
    $stmt->bind_param('ii', $hrUserId, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function clgp_list_reactivation_requests(): array
{
    $res = clgp_db()->query(
        "SELECT * FROM tbl_clgp_contractor WHERE reactivation_requested='t' AND status='Inactive' ORDER BY deactivated_at DESC"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// ---------------------------------------------------------------------------
// Workmen
// ---------------------------------------------------------------------------

function clgp_list_workmen(?string $status = 'Active'): array
{
    $sql = "SELECT w.*, c.vendor_name
            FROM tbl_clgp_workman w
            INNER JOIN tbl_clgp_contractor c ON c.contractor_id = w.contractor_id";
    if ($status) {
        $sql .= " WHERE w.status = '" . clgp_esc($status) . "' AND c.status = 'Active'";
    }
    $sql .= " ORDER BY w.workman_name";
    $res = clgp_db()->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function clgp_get_workman(int $id): ?array
{
    $stmt = clgp_db()->prepare(
        "SELECT w.*, c.vendor_name, c.contractor_name AS contractor_display
         FROM tbl_clgp_workman w
         INNER JOIN tbl_clgp_contractor c ON c.contractor_id = w.contractor_id
         WHERE w.workman_id = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function clgp_save_workman(array $data, ?int $id = null): array
{
    $db = clgp_db();
    $code = trim($data['workman_code'] ?? '');
    $name = trim($data['workman_name'] ?? '');
    $cid = (int) ($data['contractor_id'] ?? 0);
    $plant = clgp_ams_canonical_plant($data['plant'] ?? '');
    $dept = trim($data['department'] ?? '');
    $shift = trim($data['shift'] ?? '');
    if ($code === '' || $name === '' || $cid < 1 || $plant === '' || $dept === '') {
        return ['ok' => false, 'message' => 'Workman code, name, contractor, plant and department are required.'];
    }
    if ($id) {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_workman SET workman_code=?, workman_name=?, contractor_id=?, plant=?, department=?, shift=? WHERE workman_id=?"
        );
        $stmt->bind_param('ssisssi', $code, $name, $cid, $plant, $dept, $shift, $id);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO tbl_clgp_workman (workman_code, workman_name, contractor_id, plant, department, shift, status)
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

function clgp_list_matrix(): array
{
    $res = clgp_db()->query(
        "SELECT * FROM tbl_clgp_approval_matrix WHERE status='Active' ORDER BY plant, department, approval_step"
    );
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function clgp_save_matrix_rule(array $data, ?int $id = null): array
{
    $db = clgp_db();
    $plant = clgp_ams_canonical_plant($data['plant'] ?? '');
    $dept = trim($data['department'] ?? '');
    $step = $data['approval_step'] ?? '';
    $empCode = trim($data['emp_code'] ?? '');
    $empName = trim($data['emp_name'] ?? '');
    $empEmail = trim($data['emp_email'] ?? '');
    $allowed = ['timeoffice', 'supervisor', 'n1', 'hod', 'security', 'hr'];
    if (!in_array($step, $allowed, true) || $plant === '' || $empCode === '' || $empName === '') {
        return ['ok' => false, 'message' => 'Plant, role and employee are required.'];
    }
    if (clgp_matrix_needs_department($step) && $dept === '') {
        return ['ok' => false, 'message' => 'Department is required for this role.'];
    }
    if (!clgp_matrix_needs_department($step)) {
        $dept = 'All';
    }
    if ($empEmail === '') {
        return ['ok' => false, 'message' => 'Employee business email is required (used as login ID).'];
    }
    $createdBy = (int) ($_SESSION['clgp_user_id'] ?? 0);

    if ($id) {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_approval_matrix
             SET plant=?, department=?, approval_step=?, emp_code=?, emp_name=?, emp_email=?, status='Active'
             WHERE matrix_id=?"
        );
        $stmt->bind_param('ssssssi', $plant, $dept, $step, $empCode, $empName, $empEmail, $id);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO tbl_clgp_approval_matrix
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

    $provision = clgp_provision_matrix_user(
        $step,
        $plant,
        $dept,
        $empCode,
        $empName,
        $empEmail,
        $id === null
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
        $msg .= ' Linked user profile updated.';
    }

    return ['ok' => true, 'matrix_id' => $newId, 'message' => $msg];
}

function clgp_delete_matrix_rule(int $id): bool
{
    $stmt = clgp_db()->prepare("UPDATE tbl_clgp_approval_matrix SET status='Inactive' WHERE matrix_id=?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function clgp_get_matrix_approver(string $plant, string $dept, string $step): ?array
{
    $plant = clgp_ams_canonical_plant($plant);
    if (!clgp_matrix_needs_department($step)) {
        $dept = 'All';
    }
    $canon = clgp_sql_canonical_plant('plant');
    $stmt = clgp_db()->prepare(
        "SELECT * FROM tbl_clgp_approval_matrix
         WHERE $canon = ? AND department=? AND approval_step=? AND status='Active' LIMIT 1"
    );
    $stmt->bind_param('sss', $plant, $dept, $step);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function clgp_list_matrix_by_plant(): array
{
    $grouped = [];
    foreach (clgp_list_matrix() as $row) {
        $plant = clgp_ams_canonical_plant($row['plant'] ?? '');
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

function clgp_apply_session_plant_scope(array $filters): array
{
    $role = $_SESSION['clgp_role'] ?? '';
    if ($role === 'admin') {
        return $filters;
    }
    $plant = clgp_ams_canonical_plant($_SESSION['clgp_plant'] ?? '');
    if ($plant !== '' && in_array($role, ['timeoffice', 'security', 'supervisor', 'n1', 'hod', 'hr'], true)) {
        $filters['plant'] = $plant;
    }
    return $filters;
}

/**
 * AMS employee master used by Approval Matrix.
 * Prefer live AMS table — tbl_nuvo_employee_clgp remaps Department incorrectly
 * (e.g. Information Management → Infrastructure).
 */
function clgp_ams_employee_table(): string
{
    return 'tbl_nuvo_employee';
}

/**
 * Product lines allowed in LIEO AMS lookup (Cement family + Nu Vista).
 */
function clgp_ams_product_line_sql(string $alias = ''): string
{
    $col = ($alias !== '' ? $alias . '.' : '') . 'empProductLine';
    return "($col LIKE '%Cement%' OR $col LIKE '%Nu Vista%')";
}

/** @deprecated Prefer clgp_ams_product_line_sql(); kept for older callers. */
function clgp_ams_product_line(): string
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
function clgp_ams_canonical_plant(?string $plantOrLocation): string
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
 * @deprecated Prefer clgp_ams_canonical_plant()
 */
function clgp_ams_plant_short(?string $workLocation): string
{
    return clgp_ams_canonical_plant($workLocation);
}

/**
 * Full AMS plant token after first hyphen (kept for legacy callers; not used in plant list).
 */
function clgp_ams_plant_full_short(?string $workLocation): string
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
function clgp_ams_plant_match_sql(string $alias = ''): string
{
    $col = ($alias !== '' ? $alias . '.' : '') . 'empWorkLocation';
    return "SUBSTRING_INDEX(REPLACE($col, '_', '-'), '-', -1) = ?";
}

/**
 * SQL expression that returns the canonical plant short code for a stored plant column.
 */
function clgp_sql_canonical_plant(string $column = 'plant'): string
{
    return "SUBSTRING_INDEX(REPLACE($column, '_', '-'), '-', -1)";
}

function clgp_ams_bind_plant(string &$types, array &$params, string $plant): void
{
    $types .= 's';
    $params[] = clgp_ams_canonical_plant($plant);
}

/**
 * Distinct plants from AMS as canonical short codes only (one plant = one code).
 * @return list<string>
 */
function clgp_list_ams_plants(?string $q = null): array
{
    $db = clgp_db();
    $table = clgp_ams_employee_table();
    $productSql = clgp_ams_product_line_sql();
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
        $code = clgp_ams_canonical_plant($row['empWorkLocation'] ?? '');
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
function clgp_list_ams_departments(string $plant): array
{
    $plant = clgp_ams_canonical_plant($plant);
    if ($plant === '') {
        return [];
    }
    $db = clgp_db();
    $table = clgp_ams_employee_table();
    $productSql = clgp_ams_product_line_sql();
    $plantSql = clgp_ams_plant_match_sql();
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
 * List / search AMS employees.
 * Pass empty $q to load everyone for plant (+ optional department) into a dropdown.
 *
 * @return list<array<string,mixed>>
 */
function clgp_search_employees(string $q, int $limit = 20, ?string $plant = null, ?string $department = null): array
{
    $db = clgp_db();
    $table = clgp_ams_employee_table();
    $productSql = clgp_ams_product_line_sql();
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
        $sql .= ' AND ' . clgp_ams_plant_match_sql();
        clgp_ams_bind_plant($types, $params, $plant);
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
            $sql .= ' AND ' . clgp_ams_plant_match_sql();
            clgp_ams_bind_plant($types, $params, $plant);
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

function clgp_next_application_no(): string
{
    $prefix = 'LIEO-' . date('Ymd') . '-';
    $res = clgp_db()->query(
        "SELECT application_no FROM tbl_clgp_application
         WHERE application_no LIKE '" . clgp_esc($prefix) . "%'
         ORDER BY application_id DESC LIMIT 1"
    );
    $seq = 1;
    if ($res && ($row = $res->fetch_assoc())) {
        $parts = explode('-', $row['application_no']);
        $seq = ((int) end($parts)) + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function clgp_create_application(array $data): array
{
    $workmanId = (int) ($data['workman_id'] ?? 0);
    $type = $data['application_type'] ?? '';
    $reason = trim($data['reason'] ?? '');
    $createdBy = (int) ($data['created_by'] ?? 0);

    if (!in_array($type, ['Late Coming', 'Early Going'], true) || $workmanId < 1 || $reason === '') {
        return ['ok' => false, 'message' => 'Workman, type and reason are required.'];
    }

    $w = clgp_get_workman($workmanId);
    if (!$w || $w['status'] !== 'Active') {
        return ['ok' => false, 'message' => 'Workman not found or inactive.'];
    }

    // Must have Supervisor matrix rule for plant/dept (first approver)
    $sup = clgp_get_matrix_approver($w['plant'], $w['department'], 'supervisor');
    if (!$sup) {
        return ['ok' => false, 'message' => 'No Supervisor configured in Approval Matrix for ' . $w['plant'] . ' / ' . $w['department'] . '.'];
    }

    $access = $type === 'Late Coming' ? 'Entry' : 'Exit';
    $appNo = clgp_next_application_no();
    $date = date('Y-m-d');
    $cName = $w['vendor_name'] ?? '';
    $cid = (int) $w['contractor_id'];

    $stmt = clgp_db()->prepare(
        "INSERT INTO tbl_clgp_application
         (application_no, application_type, access_type, workman_id, workman_code, workman_name,
          contractor_id, contractor_name, plant, department, shift, reason, application_date,
          status, current_step, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending_supervisor','supervisor',?)"
    );
    $stmt->bind_param(
        'sssississssssi',
        $appNo,
        $type,
        $access,
        $workmanId,
        $w['workman_code'],
        $w['workman_name'],
        $cid,
        $cName,
        $w['plant'],
        $w['department'],
        $w['shift'],
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
    $app = clgp_get_application($id);
    if ($app) {
        clgp_notify_application_created($app);
    }
    return ['ok' => true, 'application_id' => $id, 'application_no' => $appNo];
}

function clgp_get_application(int $id): ?array
{
    $stmt = clgp_db()->prepare("SELECT * FROM tbl_clgp_application WHERE application_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function clgp_list_applications(array $filters = []): array
{
    $where = ['1=1'];
    if (!empty($filters['status'])) {
        $where[] = "status = '" . clgp_esc($filters['status']) . "'";
    }
    if (!empty($filters['current_step'])) {
        $where[] = "current_step = '" . clgp_esc($filters['current_step']) . "'";
    }
    if (!empty($filters['access_type'])) {
        $where[] = "access_type = '" . clgp_esc($filters['access_type']) . "'";
    }
    if (!empty($filters['date'])) {
        $where[] = "application_date = '" . clgp_esc($filters['date']) . "'";
    }
    if (!empty($filters['date_from'])) {
        $where[] = "application_date >= '" . clgp_esc($filters['date_from']) . "'";
    }
    if (!empty($filters['plant'])) {
        $canonPlant = clgp_ams_canonical_plant($filters['plant']);
        $where[] = clgp_sql_canonical_plant('plant') . " = '" . clgp_esc($canonPlant) . "'";
    }
    if (!empty($filters['department'])) {
        $where[] = "department = '" . clgp_esc($filters['department']) . "'";
    }
    if (!empty($filters['date_to'])) {
        $where[] = "application_date <= '" . clgp_esc($filters['date_to']) . "'";
    }
    if (!empty($filters['pending_for_role'])) {
        $role = $filters['pending_for_role'];
        $map = [
            'supervisor' => 'Pending_supervisor',
            'n1' => 'Pending_n1',
            'hod' => 'Pending_hod',
        ];
        if (isset($map[$role])) {
            $where[] = "status = '" . $map[$role] . "' AND current_step = '" . clgp_esc($role) . "'";
        }
        $dept = trim($_SESSION['clgp_department'] ?? '');
        if ($dept !== '' && $dept !== 'All' && in_array($role, ['supervisor', 'n1', 'hod'], true)) {
            $where[] = "department = '" . clgp_esc($dept) . "'";
        }
    }
    $sql = "SELECT * FROM tbl_clgp_application WHERE " . implode(' AND ', $where) . " ORDER BY application_id DESC";
    $res = clgp_db()->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function clgp_advance_application(int $appId, string $role, string $action, string $remark, array $actor): array
{
    $app = clgp_get_application($appId);
    if (!$app) {
        return ['ok' => false, 'message' => 'Application not found.'];
    }
    if ($app['current_step'] !== $role || strpos($app['status'], 'Pending_') !== 0) {
        return ['ok' => false, 'message' => 'Not pending for your step.'];
    }

    $matrix = clgp_get_matrix_approver($app['plant'], $app['department'], $role);
    $actorCode = trim($actor['emp_code'] ?? '');
    if (!$matrix || $matrix['emp_code'] !== $actorCode) {
        return ['ok' => false, 'message' => 'You are not the assigned approver for this plant / department.'];
    }
    if (!in_array($action, ['approve', 'reject'], true)) {
        return ['ok' => false, 'message' => 'Invalid action.'];
    }

    $db = clgp_db();
    $userId = (int) ($actor['clgp_user_id'] ?? 0);
    $empCode = $actor['emp_code'] ?? '';
    $name = $actor['full_name'] ?? '';

    $stmt = $db->prepare(
        "INSERT INTO tbl_clgp_application_approval
         (application_id, step, approver_user_id, approver_emp_code, approver_name, action, remark)
         VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('isissss', $appId, $role, $userId, $empCode, $name, $action, $remark);
    $stmt->execute();
    $stmt->close();

    if ($action === 'reject') {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_application
             SET status='Rejected', current_step='rejected', reject_reason=?, rejected_by_step=?
             WHERE application_id=?"
        );
        $stmt->bind_param('ssi', $remark, $role, $appId);
        $stmt->execute();
        $stmt->close();
        $result = ['ok' => true, 'status' => 'Rejected'];
        clgp_notify_application_action($app, $role, $action, $remark, $result);
        return $result;
    }

    $chain = ['supervisor' => 'n1', 'n1' => 'hod', 'hod' => null];
    $next = $chain[$role] ?? null;
    if ($next === null) {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_application SET status='Approved', current_step='gate' WHERE application_id=?"
        );
        $stmt->bind_param('i', $appId);
        $stmt->execute();
        $stmt->close();
        $result = ['ok' => true, 'status' => 'Approved'];
        clgp_notify_application_action($app, $role, $action, $remark, $result);
        return $result;
    }

    $status = 'Pending_' . $next;
    $stmt = $db->prepare(
        "UPDATE tbl_clgp_application SET status=?, current_step=? WHERE application_id=?"
    );
    $stmt->bind_param('ssi', $status, $next, $appId);
    $stmt->execute();
    $stmt->close();
    $result = ['ok' => true, 'status' => $status];
    clgp_notify_application_action($app, $role, $action, $remark, $result);
    return $result;
}

function clgp_application_ready_for_gate(?array $app): bool
{
    if (!$app) {
        return false;
    }
    $status = $app['status'] ?? '';
    return $status === 'Approved' || $status === 'Attested';
}

function clgp_attest_application(int $appId, int $userId): array
{
    return ['ok' => false, 'message' => 'Attestation is no longer required. Security closes applications after HOD approval.'];
}

function clgp_gate_action(int $appId, string $action, int $securityUserId, string $remark): array
{
    $remark = trim($remark);
    if ($remark === '') {
        return ['ok' => false, 'message' => 'Please enter a remark when closing the application.'];
    }
    if (mb_strlen($remark) > 500) {
        return ['ok' => false, 'message' => 'Remark must be 500 characters or less.'];
    }

    $app = clgp_get_application($appId);
    if (!clgp_application_ready_for_gate($app)) {
        return ['ok' => false, 'message' => 'Only HOD-approved applications (not yet closed at gate) can be completed here.'];
    }

    $secUser = clgp_get_user($securityUserId);
    $secName = $secUser['full_name'] ?? 'Security';
    $secCode = $secUser['emp_code'] ?? '';

    $db = clgp_db();
    if ($action === 'in') {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_application
             SET gate_in_at=NOW(), security_by=?, security_at=NOW(), gate_remark=?,
                 status='Gate_completed', current_step='done'
             WHERE application_id=?"
        );
    } elseif ($action === 'out') {
        $stmt = $db->prepare(
            "UPDATE tbl_clgp_application
             SET gate_out_at=NOW(), security_by=?, security_at=NOW(), gate_remark=?,
                 status='Gate_completed', current_step='done'
             WHERE application_id=?"
        );
    } else {
        return ['ok' => false, 'message' => 'Invalid gate action.'];
    }
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Database error. Run php database/run_clgp_gate_remark.php on the server.'];
    }
    $stmt->bind_param('isi', $securityUserId, $remark, $appId);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'message' => 'Gate update failed.'];
    }

    $gateStep = 'gate';
    $gateAction = $action === 'in' ? 'gate_in' : 'gate_out';
    $stmt2 = $db->prepare(
        "INSERT INTO tbl_clgp_application_approval
         (application_id, step, approver_user_id, approver_emp_code, approver_name, action, remark)
         VALUES (?,?,?,?,?,?,?)"
    );
    if ($stmt2) {
        $stmt2->bind_param('isissss', $appId, $gateStep, $securityUserId, $secCode, $secName, $gateAction, $remark);
        $stmt2->execute();
        $stmt2->close();
    }

    clgp_notify_gate_closed($app, $action, $remark);
    return ['ok' => true];
}

function clgp_dashboard_counts(string $date): array
{
    $db = clgp_db();
    $d = clgp_esc($date);
    $counts = [
        'contractors' => 0,
        'matrix_rules' => 0,
        'pending' => 0,
        'in_today' => 0,
        'out_today' => 0,
        'completed_today' => 0,
    ];
    $r = $db->query("SELECT COUNT(*) c FROM tbl_clgp_contractor WHERE status='Active'");
    $counts['contractors'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_clgp_approval_matrix WHERE status='Active'");
    $counts['matrix_rules'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_clgp_application WHERE status LIKE 'Pending_%'");
    $counts['pending'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_clgp_application WHERE access_type='Entry' AND application_date='$d'");
    $counts['in_today'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_clgp_application WHERE access_type='Exit' AND application_date='$d'");
    $counts['out_today'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r = $db->query("SELECT COUNT(*) c FROM tbl_clgp_application WHERE status='Gate_completed' AND application_date='$d'");
    $counts['completed_today'] = (int) ($r->fetch_assoc()['c'] ?? 0);
    return $counts;
}

function clgp_list_shifts(): array
{
    $res = clgp_db()->query("SELECT * FROM tbl_clgp_shift_master WHERE status='Active' ORDER BY shift_id");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function clgp_get_application_approvals(int $appId): array
{
    $stmt = clgp_db()->prepare(
        "SELECT * FROM tbl_clgp_application_approval WHERE application_id=? ORDER BY approval_id"
    );
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Actions taken by a specific approver (approve / reject history). */
function clgp_list_approver_history(int $userId, int $limit = 200): array
{
    if ($userId < 1) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $stmt = clgp_db()->prepare(
        "SELECT a.approval_id, a.application_id, a.step, a.action, a.remark, a.acted_at,
                a.approver_name, a.approver_emp_code,
                app.application_no, app.application_type, app.workman_name, app.workman_code,
                app.contractor_name, app.plant, app.department, app.status AS app_status,
                app.reason, app.created_at, app.created_by, app.reject_reason,
                app.attested_at, app.attested_by, app.gate_in_at, app.gate_out_at, app.security_by,
                cu.full_name AS creator_name
         FROM tbl_clgp_application_approval a
         INNER JOIN tbl_clgp_application app ON app.application_id = a.application_id
         LEFT JOIN tbl_clgp_user cu ON cu.clgp_user_id = app.created_by
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
function clgp_list_timeoffice_history(int $userId, int $limit = 200): array
{
    if ($userId < 1) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $stmt = clgp_db()->prepare(
        "SELECT app.application_id, app.application_no, app.application_type, app.workman_name, app.workman_code,
                app.contractor_name, app.plant, app.department, app.status AS app_status,
                app.reason, app.created_at, app.created_by, app.reject_reason,
                app.attested_at, app.attested_by, app.gate_in_at, app.gate_out_at, app.security_by,
                cu.full_name AS creator_name,
                CASE WHEN app.attested_by = ? THEN app.attested_at ELSE app.created_at END AS acted_at,
                CASE WHEN app.attested_by = ? THEN 'attest' ELSE 'create' END AS my_action
         FROM tbl_clgp_application app
         LEFT JOIN tbl_clgp_user cu ON cu.clgp_user_id = app.created_by
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
function clgp_list_security_history(int $userId, int $limit = 200): array
{
    if ($userId < 1) {
        return [];
    }
    $limit = max(1, min(500, $limit));
    $stmt = clgp_db()->prepare(
        "SELECT app.application_id, app.application_no, app.application_type, app.workman_name, app.workman_code,
                app.contractor_name, app.plant, app.department, app.status AS app_status,
                app.reason, app.created_at, app.created_by, app.reject_reason,
                app.attested_at, app.attested_by, app.gate_in_at, app.gate_out_at, app.security_by,
                cu.full_name AS creator_name,
                COALESCE(app.security_at, app.gate_in_at, app.gate_out_at) AS acted_at
         FROM tbl_clgp_application app
         LEFT JOIN tbl_clgp_user cu ON cu.clgp_user_id = app.created_by
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
function clgp_application_remark_trail(int $appId, ?array $app = null): array
{
    if ($app === null) {
        $app = clgp_get_application($appId);
    }
    if (!$app) {
        return [];
    }

    $trail = [];
    $creatorName = $app['creator_name'] ?? '';
    if ($creatorName === '' && !empty($app['created_by'])) {
        $u = clgp_get_user((int) $app['created_by']);
        $creatorName = $u['full_name'] ?? '';
    }

    $trail[] = [
        'label' => 'Time Office',
        'by' => $creatorName !== '' ? $creatorName : 'Time Office',
        'action' => 'Created',
        'remark' => trim((string) ($app['reason'] ?? '')),
        'at' => $app['created_at'] ?? '',
    ];

    $hasGateInTrail = false;
    foreach (clgp_get_application_approvals($appId) as $a) {
        $act = $a['action'] ?? '';
        if ($act === 'gate_in') {
            $actionLabel = 'Gate IN';
            $hasGateInTrail = true;
        } elseif ($act === 'gate_out') {
            $actionLabel = 'Gate OUT';
            $hasGateInTrail = true;
        } elseif ($act === 'reject') {
            $actionLabel = 'Rejected';
        } else {
            $actionLabel = 'Approved';
        }
        $trail[] = [
            'label' => clgp_step_label($a['step']),
            'by' => $a['approver_name'] ?: '—',
            'action' => $actionLabel,
            'remark' => trim((string) ($a['remark'] ?? '')),
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
            $u = clgp_get_user((int) $app['attested_by']);
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

    if (!$hasGateInTrail && (!empty($app['gate_in_at']) || !empty($app['gate_out_at']))) {
        $name = 'Security';
        if (!empty($app['security_by'])) {
            $u = clgp_get_user((int) $app['security_by']);
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

function clgp_render_remark_trail_html(array $trail): string
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
function clgp_remark_trail_summary(array $trail, int $maxLen = 55): string
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
