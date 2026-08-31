<?php
/**
 * Load repo-root .env into getenv()/$_ENV (does not override existing env vars).
 */
function lieo_load_dotenv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
    ];
    foreach ($candidates as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }
            $value = trim($value, "\"'");
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
        break;
    }
}

function lieo_env(string $key, string $default = ''): string
{
    if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return (string) $v;
}

function lieo_env_flag(string $key): bool
{
    $v = strtolower(lieo_env($key, ''));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Local WAMP testing only.
 * Blank-password login: host must be localhost AND LIEO_LOCAL_DEV=1.
 */
function lieo_is_local_dev(): bool
{
    if (PHP_SAPI !== 'cli') {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?: '';
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }
    }
    if (!lieo_env_flag('LIEO_LOCAL_DEV') && !lieo_env_flag('CLGP_LOCAL_DEV')) {
        return false;
    }
    return true;
}

/**
 * Test-mail mode: show popup instead of SMTP.
 * .env local=1 (or LIEO_LOCAL_MAIL=1) AND host is local.
 * On live server this is always false — real mail is sent.
 */
function lieo_is_test_mail_mode(): bool
{
    if (PHP_SAPI !== 'cli') {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?: '';
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        // Also allow machine.local / *.test style WAMP vhosts when LIEO_LOCAL_DEV is on.
        $isLocalHost = in_array($host, $localHosts, true)
            || (lieo_env_flag('LIEO_LOCAL_DEV') && (
                str_ends_with($host, '.local')
                || str_ends_with($host, '.test')
                || str_ends_with($host, '.localhost')
            ));
        if (!$isLocalHost) {
            return false;
        }
    }
    if (lieo_env_flag('LIEO_LOCAL_MAIL') || lieo_env_flag('local')) {
        return true;
    }
    return false;
}

/**
 * Hardcoded LIEO test users for local WAMP only (not stored in DB).
 * Survives live DB dumps — login checks this list before tbl_lieo_user.
 *
 * @return list<array{
 *   lieo_user_id:int,
 *   email:string,
 *   full_name:string,
 *   role:string,
 *   label:string,
 *   emp_code:string,
 *   plant:string,
 *   department:string,
 *   status:string,
 *   must_change_password:string,
 *   password:string,
 *   login_id:null,
 *   local_test:bool
 * }>
 */
function lieo_local_test_users(): array
{
    if (!lieo_is_local_dev()) {
        return [];
    }

    $plant = lieo_env('LIEO_LOCAL_PLANT', 'RCP');
    $dept = lieo_env('LIEO_LOCAL_DEPT', 'Maintenance');

    $defs = [
        ['id' => -1, 'role' => 'admin', 'label' => 'Admin', 'email' => 'lieo.admin@local.test', 'name' => 'LIEO Local Admin', 'code' => 'LOC-ADM', 'plant' => $plant, 'department' => ''],
        ['id' => -2, 'role' => 'section_incharge', 'label' => 'Section Incharge', 'email' => 'lieo.si@local.test', 'name' => 'LIEO Local Section Incharge', 'code' => 'LOC-SI', 'plant' => $plant, 'department' => $dept],
        ['id' => -3, 'role' => 'timeoffice', 'label' => 'Time Office', 'email' => 'lieo.timeoffice@local.test', 'name' => 'LIEO Local Time Office', 'code' => 'LOC-TO', 'plant' => $plant, 'department' => $dept],
        ['id' => -4, 'role' => 'n1', 'label' => 'N-1', 'email' => 'lieo.n1@local.test', 'name' => 'LIEO Local N-1', 'code' => 'LOC-N1', 'plant' => $plant, 'department' => $dept],
        ['id' => -5, 'role' => 'hod', 'label' => 'HOD', 'email' => 'lieo.hod@local.test', 'name' => 'LIEO Local HOD', 'code' => 'LOC-HOD', 'plant' => $plant, 'department' => ''],
        ['id' => -6, 'role' => 'security', 'label' => 'Security', 'email' => 'lieo.security@local.test', 'name' => 'LIEO Local Security', 'code' => 'LOC-SEC', 'plant' => $plant, 'department' => ''],
        ['id' => -7, 'role' => 'hr', 'label' => 'HR Head', 'email' => 'lieo.hr@local.test', 'name' => 'LIEO Local HR Head', 'code' => 'LOC-HR', 'plant' => $plant, 'department' => ''],
    ];

    $users = [];
    foreach ($defs as $d) {
        $users[] = [
            'lieo_user_id' => $d['id'],
            'email' => $d['email'],
            'full_name' => $d['name'],
            'role' => $d['role'],
            'label' => $d['label'],
            'emp_code' => $d['code'],
            'plant' => $d['plant'],
            'department' => $d['department'],
            'status' => 'Active',
            'must_change_password' => 'f',
            'password' => '',
            'login_id' => null,
            'local_test' => true,
        ];
    }

    return $users;
}

/** @return list<array{role:string,label:string,email:string}> */
function lieo_local_test_accounts(): array
{
    $out = [];
    foreach (lieo_local_test_users() as $u) {
        $out[] = [
            'role' => $u['role'],
            'label' => $u['label'],
            'email' => $u['email'],
        ];
    }
    return $out;
}

/** Resolve a hardcoded local test user by email (localhost + LIEO_LOCAL_DEV only). */
function lieo_find_local_test_user_by_email(string $email): ?array
{
    if (!lieo_is_local_dev()) {
        return null;
    }
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }
    foreach (lieo_local_test_users() as $user) {
        if (strtolower($user['email']) === $email) {
            return $user;
        }
    }
    return null;
}

function lieo_is_local_test_user_id(int $userId): bool
{
    return $userId < 0;
}
