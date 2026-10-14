<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
$autoload = $backendRoot . '/app/vendor/autoload.php';
$migrationPath = $backendRoot . '/database/migrations/001_rbac_schema.sql';

if (!is_file($autoload)) {
    fwrite(STDERR, "Faltan dependencias de Bee. Ejecuta composer install dentro de backend/app.\n");
    exit(1);
}

if (!is_file($migrationPath)) {
    fwrite(STDERR, "No se encontró la migración RBAC del backend.\n");
    exit(1);
}

require_once $autoload;
Dotenv\Dotenv::createImmutable($backendRoot)->safeLoad();

$requiredEnvironment = ['DB_ENGINE', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET', 'AUTH_SALT'];
foreach ($requiredEnvironment as $key) {
    if (empty($_ENV[$key])) {
        fwrite(STDERR, "Configura la variable {$key} en backend/.env.\n");
        exit(1);
    }
}

if (str_contains($_ENV['AUTH_SALT'], 'replace-this') || strlen($_ENV['AUTH_SALT']) < 32) {
    fwrite(STDERR, "Configura AUTH_SALT como un secreto aleatorio de al menos 32 caracteres.\n");
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $statements = array_filter(array_map(
        'trim',
        explode(';', (string) file_get_contents($migrationPath))
    ));
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $pdo->beginTransaction();

    $roles = [
        'administrador' => 'Administrador',
        'control-escolar' => 'Control Escolar',
        'finanzas' => 'Finanzas',
        'docente' => 'Docente',
        'alumno' => 'Alumno',
    ];
    $permissions = [
        'admin-access' => ['Acceso administrativo total', 'Acceso completo de administración del sistema.'],
        'admin.dashboard.view' => ['Consultar el panel administrativo', 'Acceder al panel administrativo.'],
        'users.manage' => ['Gestionar usuarios', 'Crear, editar, suspender y consultar usuarios.'],
        'roles.manage' => ['Gestionar roles y permisos', 'Administrar roles y permisos del sistema.'],
        'control-escolar.view' => ['Consultar Control Escolar', 'Consultar información del módulo escolar.'],
        'students.manage' => ['Gestionar alumnos', 'Registrar y actualizar expedientes escolares.'],
        'academic.assign' => ['Gestionar asignaciones académicas', 'Asignar materias y grupos.'],
        'records.review' => ['Revisar expedientes', 'Revisar documentos y expedientes escolares.'],
        'finance.view' => ['Consultar Finanzas', 'Consultar información financiera y estados de cuenta.'],
        'finance.payments.register' => ['Registrar pagos', 'Registrar pagos y referencias bancarias.'],
        'finance.payments.edit' => ['Editar pagos', 'Corregir información de pagos registrados.'],
        'finance.payments.void' => ['Anular pagos', 'Anular pagos y recibos con registro de auditoría.'],
        'finance.reports.export' => ['Exportar reportes financieros', 'Exportar reportes financieros autorizados.'],
        'teacher.attendance.manage' => ['Registrar asistencia', 'Registrar asistencia de grupos asignados.'],
        'teacher.grades.manage' => ['Gestionar calificaciones', 'Capturar y consultar calificaciones autorizadas.'],
        'student.portal.view' => ['Consultar portal de alumno', 'Consultar información académica propia.'],
    ];
    $rolePermissions = [
        'administrador' => ['admin-access', 'admin.dashboard.view'],
        'control-escolar' => ['control-escolar.view', 'students.manage', 'academic.assign', 'records.review'],
        'finanzas' => ['finance.view', 'finance.payments.register', 'finance.payments.edit', 'finance.payments.void', 'finance.reports.export'],
        'docente' => ['teacher.attendance.manage', 'teacher.grades.manage'],
        'alumno' => ['student.portal.view'],
    ];

    $insertRole = $pdo->prepare(
        'INSERT INTO bee_roles (nombre, slug) VALUES (:nombre, :slug)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)'
    );
    foreach ($roles as $slug => $name) {
        $insertRole->execute(['nombre' => $name, 'slug' => $slug]);
    }

    $insertPermission = $pdo->prepare(
        'INSERT INTO bee_permisos (nombre, slug, descripcion) VALUES (:nombre, :slug, :descripcion)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion)'
    );
    foreach ($permissions as $slug => [$name, $description]) {
        $insertPermission->execute([
            'nombre' => $name,
            'slug' => $slug,
            'descripcion' => $description,
        ]);
    }

    $findRole = $pdo->prepare('SELECT id FROM bee_roles WHERE slug = :slug');
    $findPermission = $pdo->prepare('SELECT id FROM bee_permisos WHERE slug = :slug');
    $assignPermission = $pdo->prepare(
        'INSERT IGNORE INTO bee_roles_permisos (id_role, id_permiso) VALUES (:role_id, :permission_id)'
    );
    foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
        $findRole->execute(['slug' => $roleSlug]);
        $roleId = $findRole->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException("No se pudo crear el rol {$roleSlug}.");
        }

        foreach ($permissionSlugs as $permissionSlug) {
            $findPermission->execute(['slug' => $permissionSlug]);
            $permissionId = $findPermission->fetchColumn();
            if ($permissionId === false) {
                throw new RuntimeException("No se pudo crear el permiso {$permissionSlug}.");
            }
            $assignPermission->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    $pdo->commit();
    printf(
        "Esquema RBAC listo en %s. Roles: %d. Permisos: %d.\n",
        $_ENV['DB_NAME'],
        count($roles),
        count($permissions)
    );

    if (in_array('--schema-only', $argv, true)) {
        echo "No se creó ninguna cuenta de usuario.\n";
        exit(0);
    }

    if (!function_exists('posix_isatty') || !posix_isatty(STDIN)) {
        throw new RuntimeException('La creación del administrador requiere una terminal interactiva. Usa --schema-only para preparar solo las tablas.');
    }

    $username = prompt('Usuario administrador: ');
    if (!preg_match('/\A[a-zA-Z0-9._-]{3,64}\z/', $username)) {
        throw new RuntimeException('El usuario debe tener entre 3 y 64 caracteres alfanuméricos, puntos, guiones o guiones bajos.');
    }

    $email = prompt('Correo del administrador: ');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo no tiene un formato válido.');
    }

    $fullName = prompt('Nombre completo: ');
    if ($fullName === '' || mb_strlen($fullName) > 160) {
        throw new RuntimeException('El nombre completo es obligatorio y debe tener como máximo 160 caracteres.');
    }

    $password = promptHidden('Contraseña (mínimo 12 caracteres): ');
    if (strlen($password) < 12) {
        throw new RuntimeException('La contraseña debe tener al menos 12 caracteres.');
    }
    $passwordConfirmation = promptHidden('Repite la contraseña: ');
    if (!hash_equals($password, $passwordConfirmation)) {
        throw new RuntimeException('Las contraseñas no coinciden.');
    }

    $pdo->beginTransaction();
    $findExistingUser = $pdo->prepare('SELECT id FROM bee_users WHERE username = :username OR email = :email LIMIT 1');
    $findExistingUser->execute(['username' => $username, 'email' => $email]);
    if ($findExistingUser->fetchColumn() !== false) {
        throw new RuntimeException('Ya existe una cuenta con ese usuario o correo.');
    }

    $insertUser = $pdo->prepare(
        'INSERT INTO bee_users (username, password, email, nombre_completo)
         VALUES (:username, :password, :email, :nombre_completo)'
    );
    $insertUser->execute([
        'username' => $username,
        'password' => password_hash($password . $_ENV['AUTH_SALT'], PASSWORD_BCRYPT),
        'email' => $email,
        'nombre_completo' => $fullName,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $assignAdminRole = $pdo->prepare(
        'INSERT INTO ceptri_user_roles (user_id, role_id)
         SELECT :user_id, id FROM bee_roles WHERE slug = :slug'
    );
    $assignAdminRole->execute(['user_id' => $userId, 'slug' => 'administrador']);
    if ($assignAdminRole->rowCount() !== 1) {
        throw new RuntimeException('No se pudo asignar el rol administrador.');
    }

    $pdo->commit();
    echo "Cuenta administradora creada correctamente. La contraseña no se guardó en archivos.\n";
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error al preparar RBAC: {$exception->getMessage()}\n");
    exit(1);
}

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}

function promptHidden(string $label): string
{
    if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
        exec('stty -echo', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('No se pudo ocultar la contraseña en la terminal.');
        }
    }

    fwrite(STDOUT, $label);
    try {
        $value = trim((string) fgets(STDIN));
    } finally {
        if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
            exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }
    }

    return $value;
}
