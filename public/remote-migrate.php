<?php

/**
 * Emergency / deploy hook: runs `php artisan migrate --force` when called with a valid token.
 *
 * SECURITY:
 * - Set MIGRATE_WEBHOOK_TOKEN in .env to a long random string (never commit it).
 * - Use only over HTTPS. Prefer removing this file after migrations or protecting by IP at the server.
 * - Many hosts disable shell_exec — if output is empty, check PHP disable_functions.
 *
 * URL example:
 *   https://fleetiq.absolutebyte.co.uk/remote-migrate.php?token=YOUR_MIGRATE_WEBHOOK_TOKEN
 *
 * Under PHP-FPM, {@see PHP_BINARY} often points to php-fpm, not the CLI. Set PHP_CLI_BINARY in
 * .env to your PHP CLI, e.g. /usr/bin/php8.3
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

/**
 * FPM and CGI SAPIs set PHP_BINARY to the php-fpm/php-cgi binary. That binary cannot run artisan.
 */
function remote_migrate_php_cli_binary(): string
{
    $fromEnv = (string) ($_ENV['PHP_CLI_BINARY'] ?? getenv('PHP_CLI_BINARY') ?: '');
    if ($fromEnv !== '') {
        if (is_executable($fromEnv) || (strpbrk($fromEnv, '/\\') === false)) {
            return $fromEnv;
        }
    }

    $binary = PHP_BINARY;
    if ($binary !== '' && is_executable($binary) && ! preg_match('/(php-fpm|php-cgi|lsphp)(\.exe)?$/i', basename($binary))) {
        return $binary;
    }

    $ver = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $candidates = [
        '/usr/bin/php' . $ver,
        '/usr/bin/php',
        '/usr/local/bin/php' . $ver,
        '/usr/local/bin/php',
    ];

    foreach ($candidates as $path) {
        if (is_file($path) && is_executable($path) && ! preg_match('/(fpm|cgi)/i', basename($path))) {
            return $path;
        }
    }

    return 'php';
}

if (! is_readable($basePath . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bootstrap not found.';
    exit;
}

require $basePath . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

$secret = $_ENV['MIGRATE_WEBHOOK_TOKEN'] ?? getenv('MIGRATE_WEBHOOK_TOKEN') ?: '';
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($secret === '' || $token === '' || ! hash_equals($secret, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

chdir($basePath);

$phpBinary = remote_migrate_php_cli_binary();
$artisan = $basePath . DIRECTORY_SEPARATOR . 'artisan';
$cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisan) . ' migrate --force 2>&1';

$output = shell_exec($cmd);

header('Content-Type: text/html; charset=utf-8');
echo '<pre>' . htmlspecialchars((string) $output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
