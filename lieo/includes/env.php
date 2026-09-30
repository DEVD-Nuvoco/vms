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

function lieo_is_local_test_user_id(int $userId): bool
{
    return $userId < 0;
}
