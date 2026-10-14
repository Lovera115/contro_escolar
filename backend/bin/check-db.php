<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
$autoload = $backendRoot . '/app/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Faltan dependencias de Bee. Ejecuta composer install dentro de backend/app.\n");
    exit(1);
}

require_once $autoload;
Dotenv\Dotenv::createImmutable($backendRoot)->safeLoad();

$requiredEnvironment = ['DB_ENGINE', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
foreach ($requiredEnvironment as $key) {
    if (empty($_ENV[$key])) {
        fwrite(STDERR, "Configura la variable {$key} en backend/.env.\n");
        exit(1);
    }
}

$dsn = sprintf(
    '%s:host=%s;dbname=%s;charset=%s',
    $_ENV['DB_ENGINE'],
    $_ENV['DB_HOST'],
    $_ENV['DB_NAME'],
    $_ENV['DB_CHARSET']
);

try {
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $result = $pdo->query('SELECT DATABASE() AS database_name, CURRENT_USER() AS user_name, VERSION() AS server_version')->fetch();
} catch (PDOException $exception) {
    fwrite(STDERR, "No se pudo conectar a la base configurada: {$exception->getMessage()}\n");
    exit(1);
}

printf(
    "Conexión OK: base=%s usuario=%s MariaDB/MySQL=%s\n",
    $result['database_name'],
    $result['user_name'],
    $result['server_version']
);
