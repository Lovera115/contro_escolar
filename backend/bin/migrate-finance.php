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

if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'development' || ($_ENV['DB_NAME'] ?? '') !== 'ceptri_dev') {
    fwrite(STDERR, "Esta migración solo se permite en APP_ENV=development y DB_NAME=ceptri_dev.\n");
    exit(1);
}

$requiredEnvironment = ['DB_ENGINE', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
foreach ($requiredEnvironment as $key) {
    if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
        fwrite(STDERR, "Configura la variable {$key} en backend/.env.\n");
        exit(1);
    }
}

$migrationFile = $backendRoot . '/database/migrations/005_finance.sql';
$migration = file_get_contents($migrationFile);
if ($migration === false) {
    fwrite(STDERR, "No se pudo leer la migración de Finanzas.\n");
    exit(1);
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
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', trim($migration)) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
} catch (PDOException $exception) {
    fwrite(STDERR, "No se pudo aplicar el esquema de Finanzas: {$exception->getMessage()}\n");
    exit(1);
}

echo "Migración de cuentas cuatrimestrales y pagos aplicada correctamente.\n";
