<?php

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    exit('Faltan dependencias de Bee. Ejecuta composer install dentro de backend/app.');
}

require_once $autoload;

Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

$requiredEnvironment = ['DB_ENGINE', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET', 'AUTH_SALT', 'NONCE_SALT'];
$missingEnvironment = array_filter($requiredEnvironment, static fn(string $key): bool => empty($_ENV[$key]));
if ($missingEnvironment) {
    http_response_code(503);
    exit('Configura las variables requeridas en backend/.env antes de iniciar Bee.');
}

if (str_contains($_ENV['DB_PASS'], 'replace-this') ||
    str_contains($_ENV['AUTH_SALT'], 'replace-this') ||
    str_contains($_ENV['NONCE_SALT'], 'replace-this')) {
    http_response_code(503);
    exit('Reemplaza los valores de ejemplo en backend/.env antes de iniciar Bee.');
}

if (strlen($_ENV['AUTH_SALT']) < 32 || strlen($_ENV['NONCE_SALT']) < 32) {
    http_response_code(503);
    exit('Los secretos AUTH_SALT y NONCE_SALT deben tener al menos 32 caracteres.');
}

define('IS_LOCAL', in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));
define('DEV_PATH', '/');
define('LIVE_PATH', '/');
define('BASEPATH', IS_LOCAL ? DEV_PATH : LIVE_PATH);
define('IS_DEMO', false);
define('SANDBOX', true);

define('DB_ENGINE', $_ENV['DB_ENGINE']);
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_CHARSET', $_ENV['DB_CHARSET']);
