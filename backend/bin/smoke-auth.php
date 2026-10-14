<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
if (!is_file($backendRoot . '/.env')) {
    fwrite(STDERR, "No existe backend/.env.\n");
    exit(1);
}

putenv('APP_ENV=development');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8081';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS'] = 'off';
require_once $backendRoot . '/app/config/bee_config.php';

if (($_ENV['APP_ENV'] ?? '') !== 'development' || $_ENV['DB_NAME'] !== 'ceptri_dev') {
    fwrite(STDERR, "La prueba de autenticación solo se permite en APP_ENV=development y DB_NAME=ceptri_dev.\n");
    exit(1);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "La extensión curl de PHP es necesaria para la prueba HTTP.\n");
    exit(1);
}

$dsn = sprintf(
    '%s:host=%s;dbname=%s;charset=%s',
    $_ENV['DB_ENGINE'],
    $_ENV['DB_HOST'],
    $_ENV['DB_NAME'],
    $_ENV['DB_CHARSET']
);
$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$username = 'auth-smoke-' . bin2hex(random_bytes(6));
$email = $username . '@example.invalid';
$password = 'Smoke-' . bin2hex(random_bytes(16));
$userId = null;
$managedUserId = null;
$studentId = null;
$controlUserId = null;
$roleSlug = 'smoke-' . bin2hex(random_bytes(6));
$roleId = null;
$managedPassword = 'Managed-' . bin2hex(random_bytes(12));
$controlUsername = 'control-smoke-' . bin2hex(random_bytes(6));
$controlPassword = 'Control-' . bin2hex(random_bytes(12));
$studentRegistration = 'SMK-' . strtoupper(bin2hex(random_bytes(4)));
$curl = curl_init();
$controlCurl = null;

if ($curl === false) {
    throw new RuntimeException('No se pudo iniciar curl para la prueba HTTP.');
}

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEFILE => '',
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'CEPTRI local auth smoke test',
]);

function requestPage(CurlHandle $curl, string $url, ?array $form = null): array
{
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, $form !== null);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $form === null ? null : http_build_query($form));
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException('Fallo de prueba HTTP: ' . curl_error($curl));
    }

    return [
        'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'url' => (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
        'body' => $body,
    ];
}

function csrfFromPage(string $html): string
{
    if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('La vista no contiene el token CSRF esperado.');
    }

    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

try {
    $adminRoleId = $pdo->query("SELECT id FROM bee_roles WHERE slug = 'administrador'")->fetchColumn();
    if ($adminRoleId === false) {
        throw new RuntimeException('No existe el rol administrador.');
    }

    $insertUser = $pdo->prepare(
        'INSERT INTO bee_users (username, password, email, nombre_completo)
         VALUES (:username, :password, :email, :name)'
    );
    $insertUser->execute([
        'username' => $username,
        'password' => password_hash($password . $_ENV['AUTH_SALT'], PASSWORD_BCRYPT),
        'email' => $email,
        'name' => 'Cuenta temporal de prueba',
    ]);
    $userId = (int) $pdo->lastInsertId();

    $assignRole = $pdo->prepare(
        'INSERT INTO ceptri_user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assignRole->execute(['user_id' => $userId, 'role_id' => (int) $adminRoleId]);

    $baseUrl = 'http://127.0.0.1:8081';
    $loginPage = requestPage($curl, $baseUrl . '/auth');
    if ($loginPage['status'] !== 200) {
        throw new RuntimeException('La página de acceso no respondió HTTP 200.');
    }

    $login = requestPage($curl, $baseUrl . '/auth/login', [
        'csrf' => csrfFromPage($loginPage['body']),
        'identifier' => $username,
        'password' => $password,
    ]);
    if ($login['status'] !== 200 || !str_ends_with($login['url'], '/admin') ||
        !str_contains($login['body'], 'Administración CEPTRI')) {
        throw new RuntimeException(sprintf(
            'La cuenta temporal no pudo iniciar sesión o entrar al panel (HTTP %d, ruta %s).',
            $login['status'],
            (string) parse_url($login['url'], PHP_URL_PATH)
        ));
    }

    $adminCsrf = csrfFromPage($login['body']);
    $usersPage = requestPage($curl, $baseUrl . '/admin/users');
    $rolesPage = requestPage($curl, $baseUrl . '/admin/roles');
    if ($usersPage['status'] !== 200 || !str_contains($usersPage['body'], 'Gestión de usuarios')) {
        throw new RuntimeException('La pantalla de gestión de usuarios no respondió correctamente.');
    }
    if ($rolesPage['status'] !== 200 || !str_contains($rolesPage['body'], 'Roles y permisos')) {
        throw new RuntimeException('La matriz de roles y permisos no respondió correctamente.');
    }
    $csrfRejected = requestPage($curl, $baseUrl . '/admin/save-role', [
        'csrf' => 'invalid-token',
        'nombre' => 'Rol rechazado',
        'slug' => 'csrf-rejected-' . bin2hex(random_bytes(4)),
    ]);
    if ($csrfRejected['status'] !== 400) {
        throw new RuntimeException('La administración aceptó un formulario sin token CSRF válido.');
    }

    $createRole = requestPage($curl, $baseUrl . '/admin/save-role', [
        'csrf' => csrfFromPage($rolesPage['body']),
        'nombre' => 'Rol de prueba',
        'slug' => $roleSlug,
    ]);
    if ($createRole['status'] !== 200 || !str_contains($createRole['body'], $roleSlug)) {
        throw new RuntimeException('No se pudo crear un rol de prueba desde el panel.');
    }
    $findRole = $pdo->prepare('SELECT id FROM bee_roles WHERE slug = :slug');
    $findRole->execute(['slug' => $roleSlug]);
    $roleId = (int) $findRole->fetchColumn();
    if ($roleId < 1) {
        throw new RuntimeException('El rol creado no quedó persistido en la base de datos.');
    }

    $permissionId = $pdo->query("SELECT id FROM bee_permisos WHERE slug = 'finance.view'")->fetchColumn();
    if ($permissionId === false) {
        throw new RuntimeException('No existe el permiso finance.view para probar la matriz.');
    }
    $matrix = requestPage($curl, $baseUrl . '/admin/save-role-permissions', [
        'csrf' => csrfFromPage($createRole['body']),
        'role_slug' => $roleSlug,
        'permissions' => [(string) $permissionId],
    ]);
    if ($matrix['status'] !== 200) {
        throw new RuntimeException('No se pudo guardar la matriz del rol temporal.');
    }
    $checkRolePermission = $pdo->prepare(
        'SELECT 1 FROM bee_roles_permisos WHERE id_role = :role_id AND id_permiso = :permission_id'
    );
    $checkRolePermission->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    if ($checkRolePermission->fetchColumn() === false) {
        throw new RuntimeException('La matriz no persistió el permiso seleccionado.');
    }

    $usersPage = requestPage($curl, $baseUrl . '/admin/users');
    $managedUsername = 'managed-smoke-' . bin2hex(random_bytes(6));
    $manageUser = requestPage($curl, $baseUrl . '/admin/manage-users', [
        'csrf' => csrfFromPage($usersPage['body']),
        'nombre_completo' => 'Usuario temporal de administración',
        'username' => $managedUsername,
        'email' => $managedUsername . '@example.invalid',
        'role_slug' => $roleSlug,
        'password' => $managedPassword,
        'activo' => '1',
    ]);
    if ($manageUser['status'] !== 200 || !str_contains($manageUser['body'], $managedUsername)) {
        throw new RuntimeException('No se pudo crear un usuario desde la pantalla de administración.');
    }
    $findManagedUser = $pdo->prepare('SELECT id FROM bee_users WHERE username = :username');
    $findManagedUser->execute(['username' => $managedUsername]);
    $managedUserId = (int) $findManagedUser->fetchColumn();
    if ($managedUserId < 1) {
        throw new RuntimeException('El usuario creado no quedó persistido en la base de datos.');
    }

    $editUserPage = requestPage($curl, $baseUrl . '/admin/users?edit=' . $managedUserId);
    $saveOverride = requestPage($curl, $baseUrl . '/admin/save-user-overrides', [
        'csrf' => csrfFromPage($editUserPage['body']),
        'user_id' => (string) $managedUserId,
        'overrides' => ['finance.view' => 'deny'],
    ]);
    if ($saveOverride['status'] !== 200) {
        throw new RuntimeException('No se pudo guardar la excepción individual de permisos.');
    }
    $checkOverride = $pdo->prepare(
        'SELECT up.allowed
         FROM ceptri_user_permissions up
         INNER JOIN bee_permisos p ON p.id = up.permission_id
         WHERE up.user_id = :user_id AND p.slug = :permission'
    );
    $checkOverride->execute(['user_id' => $managedUserId, 'permission' => 'finance.view']);
    if ((int) $checkOverride->fetchColumn() !== 0) {
        throw new RuntimeException('La excepción individual no negó el permiso heredado del rol.');
    }

    $studentsPage = requestPage($curl, $baseUrl . '/students');
    if ($studentsPage['status'] !== 200 || !str_contains($studentsPage['body'], 'Padrón e inscripción de alumnos')) {
        throw new RuntimeException('El padrón de alumnos no respondió correctamente para Control Escolar.');
    }
    $createStudent = requestPage($curl, $baseUrl . '/students/save', [
        'csrf' => csrfFromPage($studentsPage['body']),
        'matricula' => $studentRegistration,
        'nombre_completo' => 'Alumno temporal de prueba',
        'email' => strtolower($studentRegistration) . '@example.invalid',
        'telefono' => '722 123 4567',
        'carrera' => 'Especialidad en Derecho Administrativo (EDA)',
        'estado' => 'activo',
        'period_key' => '2026-1',
    ]);
    if ($createStudent['status'] !== 200 || !str_contains($createStudent['body'], 'Alumno temporal de prueba')) {
        throw new RuntimeException('No se pudo registrar un alumno e inscribirlo a su primer cuatrimestre.');
    }
    $findStudent = $pdo->prepare('SELECT id FROM ceptri_students WHERE matricula = :matricula');
    $findStudent->execute(['matricula' => $studentRegistration]);
    $studentId = (int) $findStudent->fetchColumn();
    if ($studentId < 1) {
        throw new RuntimeException('El expediente creado no quedó persistido en la base de datos.');
    }
    $checkInitialEnrollment = $pdo->prepare(
        'SELECT COUNT(*) FROM ceptri_student_enrollments WHERE student_id = :student_id AND period_key = :period'
    );
    $checkInitialEnrollment->execute(['student_id' => $studentId, 'period' => '2026-1']);
    if ((int) $checkInitialEnrollment->fetchColumn() !== 1) {
        throw new RuntimeException('El registro del alumno no creó su inscripción inicial.');
    }

    $studentPage = requestPage($curl, $baseUrl . '/students?edit=' . $studentId);
    $editStudent = requestPage($curl, $baseUrl . '/students/save', [
        'csrf' => csrfFromPage($studentPage['body']),
        'student_id' => (string) $studentId,
        'matricula' => $studentRegistration,
        'nombre_completo' => 'Alumno temporal actualizado',
        'email' => strtolower($studentRegistration) . '@example.invalid',
        'telefono' => '722 123 4567',
        'carrera' => 'Especialidad en Derecho Administrativo (EDA)',
        'estado' => 'activo',
    ]);
    $checkStudentName = $pdo->prepare('SELECT nombre_completo FROM ceptri_students WHERE id = :id');
    $checkStudentName->execute(['id' => $studentId]);
    if ($editStudent['status'] !== 200 || $checkStudentName->fetchColumn() !== 'Alumno temporal actualizado') {
        throw new RuntimeException('No se pudo actualizar el expediente del alumno.');
    }

    $studentPage = requestPage($curl, $baseUrl . '/students?edit=' . $studentId);
    $addEnrollment = requestPage($curl, $baseUrl . '/students/enroll', [
        'csrf' => csrfFromPage($studentPage['body']),
        'student_id' => (string) $studentId,
        'period_key' => '2027-2',
    ]);
    if ($addEnrollment['status'] !== 200) {
        throw new RuntimeException('No se pudo agregar una inscripción de cuatrimestre posterior.');
    }
    $checkEnrollmentCount = $pdo->prepare(
        'SELECT COUNT(*) FROM ceptri_student_enrollments WHERE student_id = :student_id'
    );
    $checkEnrollmentCount->execute(['student_id' => $studentId]);
    if ((int) $checkEnrollmentCount->fetchColumn() !== 2) {
        throw new RuntimeException('La inscripción posterior no quedó registrada.');
    }

    $studentPage = requestPage($curl, $baseUrl . '/students?edit=' . $studentId);
    $duplicateEnrollment = requestPage($curl, $baseUrl . '/students/enroll', [
        'csrf' => csrfFromPage($studentPage['body']),
        'student_id' => (string) $studentId,
        'period_key' => '2027-2',
    ]);
    if ($duplicateEnrollment['status'] !== 200 ||
        !str_contains($duplicateEnrollment['body'], 'ya tiene una inscripción registrada')) {
        throw new RuntimeException('Se aceptó una inscripción duplicada para el mismo cuatrimestre.');
    }

    $self = $pdo->prepare(
        'SELECT username, email, nombre_completo FROM bee_users WHERE id = :user_id'
    );
    $self->execute(['user_id' => $userId]);
    $selfAccount = $self->fetch(PDO::FETCH_ASSOC);
    $usersPage = requestPage($curl, $baseUrl . '/admin/users');
    $selfUpdate = requestPage($curl, $baseUrl . '/admin/manage-users', [
        'csrf' => csrfFromPage($usersPage['body']),
        'user_id' => (string) $userId,
        'nombre_completo' => $selfAccount['nombre_completo'],
        'username' => $selfAccount['username'],
        'email' => $selfAccount['email'],
        'role_slug' => 'administrador',
    ]);
    $selfStatus = $pdo->prepare('SELECT activo FROM bee_users WHERE id = :user_id');
    $selfStatus->execute(['user_id' => $userId]);
    if ($selfUpdate['status'] !== 200 || (int) $selfStatus->fetchColumn() !== 1 ||
        !str_contains($selfUpdate['body'], 'No puedes desactivar tu cuenta')) {
        throw new RuntimeException('La administración permitió desactivar la cuenta administradora actual.');
    }

    $logout = requestPage($curl, $baseUrl . '/auth/logout', ['csrf' => $adminCsrf]);
    if ($logout['status'] !== 200 || !str_ends_with($logout['url'], '/auth') ||
        !str_contains($logout['body'], 'Iniciar sesión')) {
        throw new RuntimeException('El cierre de sesión no redirigió correctamente al acceso.');
    }

    $restrictedLogin = requestPage($curl, $baseUrl . '/auth/login', [
        'csrf' => csrfFromPage($logout['body']),
        'identifier' => $managedUsername,
        'password' => $managedPassword,
    ]);
    $studentAccessDenied = requestPage($curl, $baseUrl . '/students');
    if ($studentAccessDenied['status'] !== 403) {
        throw new RuntimeException(sprintf(
            'La prueba de autorización respondió HTTP %d en %s, no HTTP 403.',
            $studentAccessDenied['status'],
            (string) parse_url($studentAccessDenied['url'], PHP_URL_PATH)
        ));
    }

    $controlRoleId = $pdo->query("SELECT id FROM bee_roles WHERE slug = 'control-escolar'")->fetchColumn();
    if ($controlRoleId === false) {
        throw new RuntimeException('No existe el rol Control Escolar para comprobar su acceso al módulo.');
    }
    $insertControlUser = $pdo->prepare(
        'INSERT INTO bee_users (username, password, email, nombre_completo)
         VALUES (:username, :password, :email, :name)'
    );
    $insertControlUser->execute([
        'username' => $controlUsername,
        'password' => password_hash($controlPassword . $_ENV['AUTH_SALT'], PASSWORD_BCRYPT),
        'email' => $controlUsername . '@example.invalid',
        'name' => 'Cuenta temporal de Control Escolar',
    ]);
    $controlUserId = (int) $pdo->lastInsertId();
    $assignControlRole = $pdo->prepare(
        'INSERT INTO ceptri_user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assignControlRole->execute(['user_id' => $controlUserId, 'role_id' => (int) $controlRoleId]);

    $controlCurl = curl_init();
    if ($controlCurl === false) {
        throw new RuntimeException('No se pudo iniciar la sesión HTTP de prueba para Control Escolar.');
    }
    curl_setopt_array($controlCurl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'CEPTRI local control-school smoke test',
    ]);
    $controlLoginPage = requestPage($controlCurl, $baseUrl . '/auth');
    $controlLogin = requestPage($controlCurl, $baseUrl . '/auth/login', [
        'csrf' => csrfFromPage($controlLoginPage['body']),
        'identifier' => $controlUsername,
        'password' => $controlPassword,
    ]);
    if ($controlLogin['status'] !== 200 ||
        !str_ends_with($controlLogin['url'], '/students') ||
        !str_contains($controlLogin['body'], 'Padrón e inscripción de alumnos')) {
        throw new RuntimeException('El rol Control Escolar no fue redirigido a su módulo autorizado.');
    }
    $controlHome = requestPage($controlCurl, $baseUrl . '/auth');
    if ($controlHome['status'] !== 200 || !str_ends_with($controlHome['url'], '/students')) {
        throw new RuntimeException('Una sesión de Control Escolar no regresó a su módulo al abrir /auth.');
    }

    echo "Pruebas HTTP OK: administración RBAC, padrón, cuatrimestres, CSRF, autorización y acceso Control Escolar.\n";
} finally {
    if ($controlUserId !== null) {
        $deleteControlUser = $pdo->prepare('DELETE FROM bee_users WHERE id = :user_id');
        $deleteControlUser->execute(['user_id' => $controlUserId]);
    }
    if ($studentId !== null) {
        $deleteStudent = $pdo->prepare('DELETE FROM ceptri_students WHERE id = :student_id');
        $deleteStudent->execute(['student_id' => $studentId]);
    }
    if ($managedUserId !== null) {
        $deleteManagedUser = $pdo->prepare('DELETE FROM bee_users WHERE id = :user_id');
        $deleteManagedUser->execute(['user_id' => $managedUserId]);
    }
    if ($roleId !== null) {
        $deleteRole = $pdo->prepare('DELETE FROM bee_roles WHERE id = :role_id');
        $deleteRole->execute(['role_id' => $roleId]);
    }
    if ($userId !== null) {
        $deleteUser = $pdo->prepare('DELETE FROM bee_users WHERE id = :user_id');
        $deleteUser->execute(['user_id' => $userId]);
    }
    if ($controlCurl instanceof CurlHandle) {
        curl_close($controlCurl);
    }
    curl_close($curl);
}
