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

$secret = $_ENV['MIGRATE_WEBHOOK_TOKEN'] ?? getenv('MIGRATE_WEBHOOK_TOKEN') ?: '';
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($secret === '' || $token === '' || ! hash_equals($secret, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

chdir($basePath);

$phpBinary = PHP_BINARY && PHP_BINARY !== '' ? PHP_BINARY : 'php';
$artisan = $basePath . DIRECTORY_SEPARATOR . 'artisan';
$cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisan) . ' migrate';

$output = shell_exec($cmd);

header('Content-Type: text/html; charset=utf-8');
echo '<pre>' . htmlspecialchars((string) $output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
