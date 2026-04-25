<?php

/**
 * Secure one-off: downloads a database dump when called with a valid token.
 *
 * SECURITY:
 * - Set DB_BACKUP_WEBHOOK_TOKEN in .env to a long random string (never commit it).
 * - Use only over HTTPS. Remove this file when not needed; restrict by IP on the server if possible.
 * - Dumps are sensitive; anyone with the token can exfiltrate the DB.
 *
 * URL:
 *   https://fleetiq.absolutebyte.co.uk/remote-db-backup.php?token=YOUR_DB_BACKUP_WEBHOOK_TOKEN
 *
 * Supported DB_CONNECTION: mysql, mariadb, pgsql, sqlite (sqlsrv: not supported here).
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

if (! is_readable($basePath . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bootstrap not found.';
    exit;
}

require $basePath . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

$secret = $_ENV['DB_BACKUP_WEBHOOK_TOKEN'] ?? getenv('DB_BACKUP_WEBHOOK_TOKEN') ?: '';
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($secret === '' || $token === '' || ! hash_equals($secret, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

$driver = (string) ($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'sqlite');
$appSlug = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($_ENV['APP_NAME'] ?? 'backup')) ?: 'backup';
$stamp = gmdate('Y-m-d\THis\Z');
$filenameBase = 'db-backup-' . $appSlug . '-' . $stamp;

/**
 * @return array{0: int, 1: string}
 */
function runDumpCommand(string $command, string $basePath): array
{
    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($command, $desc, $pipes, $basePath, null);
    if (! is_resource($process)) {
        return [1, 'Could not start dump command. Check that mysqldump/pg_dump is installed, on PATH, and not blocked.'];
    }
    fclose($pipes[0]);
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return [$code, $code === 0 ? $out : (trim($err) !== '' ? $err : $out)];
}

if (in_array($driver, ['mysql', 'mariadb'], true)) {
    $dbHost = (string) ($_ENV['DB_HOST'] ?? '127.0.0.1');
    $dbPort = (string) ($_ENV['DB_PORT'] ?? '3306');
    $dbName = (string) ($_ENV['DB_DATABASE'] ?? '');
    $dbUser = (string) ($_ENV['DB_USERNAME'] ?? '');
    $dbPass = (string) ($_ENV['DB_PASSWORD'] ?? '');
    $socket = (string) ($_ENV['DB_SOCKET'] ?? '');

    if ($dbName === '' || $dbUser === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Missing DB_DATABASE or DB_USERNAME in environment.';
        exit;
    }

    $args = [
        'mysqldump',
        '--single-transaction',
        '--routines',
        '--triggers',
        '--default-character-set=utf8mb4',
    ];
    if ($socket !== '') {
        $args[] = '--socket=' . $socket;
    } else {
        $args[] = '-h';
        $args[] = $dbHost;
        $args[] = '-P';
        $args[] = $dbPort;
    }
    $args[] = '-u';
    $args[] = $dbUser;
    $args[] = '--password=' . $dbPass;
    $args[] = $dbName;

    $command = implode(' ', array_map('escapeshellarg', $args));
    [$code, $output] = runDumpCommand($command, $basePath);
    if ($code !== 0) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $output;
        exit;
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.sql"');
    echo $output;
    exit;
}

if ($driver === 'pgsql') {
    $dbHost = (string) ($_ENV['DB_HOST'] ?? '127.0.0.1');
    $dbPort = (string) ($_ENV['DB_PORT'] ?? '5432');
    $dbName = (string) ($_ENV['DB_DATABASE'] ?? '');
    $dbUser = (string) ($_ENV['DB_USERNAME'] ?? '');
    $dbPass = (string) ($_ENV['DB_PASSWORD'] ?? '');

    if ($dbName === '' || $dbUser === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Missing DB_DATABASE or DB_USERNAME in environment.';
        exit;
    }

    $dsn = sprintf(
        'postgresql://%s:%s@%s:%s/%s',
        rawurlencode($dbUser),
        rawurlencode($dbPass),
        $dbHost,
        $dbPort,
        rawurlencode($dbName)
    );
    $args = [
        'pg_dump',
        $dsn,
        '--no-owner',
    ];
    $command = implode(' ', array_map('escapeshellarg', $args));
    [$code, $body] = runDumpCommand($command, $basePath);
    if ($code !== 0) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.sql"');
    echo $body;
    exit;
}

if ($driver === 'sqlite') {
    $dbFile = (string) ($_ENV['DB_DATABASE'] ?? 'database/database.sqlite');
    if ($dbFile === '' || $dbFile === ':memory:') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid SQLite database path.';
        exit;
    }
    if (! str_starts_with($dbFile, '/')) {
        $dbFile = $basePath . '/' . ltrim($dbFile, '/');
    }
    if (! is_readable($dbFile)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'SQLite file not found or not readable: ' . $dbFile;
        exit;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.sqlite"');
    header('Content-Length: ' . (string) filesize($dbFile));
    readfile($dbFile);
    exit;
}

http_response_code(501);
header('Content-Type: text/plain; charset=utf-8');
echo 'Unsupported DB_CONNECTION for this script: ' . $driver;
