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
$financeUserId = null;
$teacherUserId = null;
$documentTempPath = null;
$documentFileKeys = [];
$academicGroupId = null;
$roleSlug = 'smoke-' . bin2hex(random_bytes(6));
$roleId = null;
$managedPassword = 'Managed-' . bin2hex(random_bytes(12));
$controlUsername = 'control-smoke-' . bin2hex(random_bytes(6));
$controlPassword = 'Control-' . bin2hex(random_bytes(12));
$financeUsername = 'finance-smoke-' . bin2hex(random_bytes(6));
$financePassword = 'Finance-' . bin2hex(random_bytes(12));
$teacherUsername = 'teacher-smoke-' . bin2hex(random_bytes(6));
$teacherPassword = 'Teacher-' . bin2hex(random_bytes(12));
$studentRegistration = 'SMK-' . strtoupper(bin2hex(random_bytes(4)));
$curl = curl_init();
$controlCurl = null;
$financeCurl = null;

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
    $multipart = $form !== null && ($form['document_file'] ?? null) instanceof CURLFile;
    curl_setopt(
        $curl,
        CURLOPT_POSTFIELDS,
        $form === null ? null : ($multipart ? $form : http_build_query($form))
    );
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException('Fallo de prueba HTTP: ' . curl_error($curl));
    }

    return [
        'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'url' => (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
        'content_type' => (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE),
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
    $teacherRoleId = $pdo->query("SELECT id FROM bee_roles WHERE slug = 'docente'")->fetchColumn();
    if ($teacherRoleId === false) {
        throw new RuntimeException('No existe el rol Docente para probar las asignaciones.');
    }
    $insertTeacher = $pdo->prepare(
        'INSERT INTO bee_users (username, password, email, nombre_completo)
         VALUES (:username, :password, :email, :name)'
    );
    $insertTeacher->execute([
        'username' => $teacherUsername,
        'password' => password_hash($teacherPassword . $_ENV['AUTH_SALT'], PASSWORD_BCRYPT),
        'email' => $teacherUsername . '@example.invalid',
        'name' => 'Docente temporal de prueba',
    ]);
    $teacherUserId = (int) $pdo->lastInsertId();
    $assignTeacherRole = $pdo->prepare(
        'INSERT INTO ceptri_user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assignTeacherRole->execute(['user_id' => $teacherUserId, 'role_id' => (int) $teacherRoleId]);

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
    if (!str_contains($usersPage['body'], 'Padrón de alumnos') ||
        !str_contains($usersPage['body'], 'Grupos y asignaciones') ||
        !str_contains($usersPage['body'], 'aria-current="page"') ||
        !str_contains($usersPage['body'], 'Roles y permisos')) {
        throw new RuntimeException('El menú lateral de administración no muestra los módulos autorizados.');
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
    $findFinancialAccount = $pdo->prepare(
        'SELECT a.id, a.amount_due, a.status
         FROM ceptri_financial_accounts a
         INNER JOIN ceptri_student_enrollments e ON e.id = a.enrollment_id
         WHERE e.student_id = :student_id AND e.period_key = :period'
    );
    $findFinancialAccount->execute(['student_id' => $studentId, 'period' => '2026-1']);
    $financialAccount = $findFinancialAccount->fetch(PDO::FETCH_ASSOC);
    if (!$financialAccount || $financialAccount['status'] !== 'adeudo' ||
        (float) $financialAccount['amount_due'] !== 0.0) {
        throw new RuntimeException('La inscripción inicial no generó una cuenta con estado Adeudo.');
    }
    $accountId = (int) $financialAccount['id'];
    $financePage = requestPage($curl, $baseUrl . '/finance?account_id=' . $accountId);
    if ($financePage['status'] !== 200 ||
        !str_contains($financePage['body'], 'Estados de cuenta y pagos') ||
        !str_contains($financePage['body'], 'Adeudo')) {
        throw new RuntimeException('La pantalla de Finanzas no mostró la cuenta recién creada con adeudo.');
    }
    $saveFinancialAccount = requestPage($curl, $baseUrl . '/finance/save-account', [
        'csrf' => csrfFromPage($financePage['body']),
        'account_id' => (string) $accountId,
        'amount_due' => '1000.00',
        'status' => 'adeudo',
        'status_note' => '',
    ]);
    $checkFinancialAccount = $pdo->prepare(
        'SELECT amount_due, status FROM ceptri_financial_accounts WHERE id = :id'
    );
    $checkFinancialAccount->execute(['id' => $accountId]);
    $accountAfterEdit = $checkFinancialAccount->fetch(PDO::FETCH_ASSOC);
    if ($saveFinancialAccount['status'] !== 200 || !$accountAfterEdit ||
        (float) $accountAfterEdit['amount_due'] !== 1000.0 || $accountAfterEdit['status'] !== 'adeudo') {
        throw new RuntimeException(sprintf(
            'No se pudieron editar los cargos individuales del estado de cuenta (HTTP %d; cuota %s; estado %s; respuesta %s).',
            $saveFinancialAccount['status'],
            (string) ($accountAfterEdit['amount_due'] ?? 'sin cuenta'),
            (string) ($accountAfterEdit['status'] ?? 'sin cuenta'),
            trim(preg_replace('/\s+/', ' ', strip_tags(substr($saveFinancialAccount['body'], -400))))
        ));
    }

    $paymentFolio = 'SMK-' . strtoupper(bin2hex(random_bytes(5)));
    $financePage = requestPage($curl, $baseUrl . '/finance?account_id=' . $accountId);
    $registerPayment = requestPage($curl, $baseUrl . '/finance/register-payment', [
        'csrf' => csrfFromPage($financePage['body']),
        'account_id' => (string) $accountId,
        'folio' => $paymentFolio,
        'concept' => 'Pago parcial de prueba',
        'amount' => '250.00',
        'paid_on' => date('Y-m-d'),
        'payment_method' => 'transferencia',
        'payment_reference' => 'SMOKE-REF-1',
        'notes' => 'Prueba HTTP temporal',
    ]);
    $findFinancialPayment = $pdo->prepare(
        'SELECT id, amount, status FROM ceptri_financial_payments WHERE folio = :folio'
    );
    $findFinancialPayment->execute(['folio' => $paymentFolio]);
    $financialPayment = $findFinancialPayment->fetch(PDO::FETCH_ASSOC);
    if ($registerPayment['status'] !== 200 || !$financialPayment ||
        (float) $financialPayment['amount'] !== 250.0 || $financialPayment['status'] !== 'registrado') {
        throw new RuntimeException('No se pudo registrar un pago con folio y referencia.');
    }
    $paymentId = (int) $financialPayment['id'];

    $editPaymentPage = requestPage(
        $curl,
        $baseUrl . '/finance?account_id=' . $accountId . '&edit_payment=' . $paymentId
    );
    if ($editPaymentPage['status'] !== 200 ||
        !str_contains($editPaymentPage['body'], 'Corregir pago') ||
        !str_contains($editPaymentPage['body'], 'Pago parcial de prueba')) {
        throw new RuntimeException('No se pudo abrir el formulario de corrección del pago.');
    }
    $editFinancialPayment = requestPage($curl, $baseUrl . '/finance/edit-payment', [
        'csrf' => csrfFromPage($editPaymentPage['body']),
        'account_id' => (string) $accountId,
        'payment_id' => (string) $paymentId,
        'folio' => $paymentFolio,
        'concept' => 'Pago corregido de prueba',
        'amount' => '200.00',
        'paid_on' => date('Y-m-d'),
        'payment_method' => 'transferencia',
        'payment_reference' => 'SMOKE-REF-2',
        'notes' => 'Referencia corregida',
    ]);
    $findFinancialPayment->execute(['folio' => $paymentFolio]);
    $financialPayment = $findFinancialPayment->fetch(PDO::FETCH_ASSOC);
    if ($editFinancialPayment['status'] !== 200 || !$financialPayment ||
        (float) $financialPayment['amount'] !== 200.0) {
        throw new RuntimeException('No se pudo corregir un pago vigente.');
    }

    $historyPdf = requestPage($curl, $baseUrl . '/finance/history-pdf?student_id=' . $studentId);
    if ($historyPdf['status'] !== 200 || !str_starts_with($historyPdf['content_type'], 'application/pdf') ||
        !str_starts_with($historyPdf['body'], '%PDF-')) {
        throw new RuntimeException('No se pudo descargar el historial de pagos en PDF.');
    }

    $financePage = requestPage($curl, $baseUrl . '/finance?account_id=' . $accountId);
    $voidPayment = requestPage($curl, $baseUrl . '/finance/void-payment', [
        'csrf' => csrfFromPage($financePage['body']),
        'account_id' => (string) $accountId,
        'payment_id' => (string) $paymentId,
        'void_reason' => 'Anulación de prueba HTTP.',
    ]);
    $checkPaymentVoid = $pdo->prepare(
        'SELECT status, void_reason FROM ceptri_financial_payments WHERE id = :id'
    );
    $checkPaymentVoid->execute(['id' => $paymentId]);
    $voidedPayment = $checkPaymentVoid->fetch(PDO::FETCH_ASSOC);
    if ($voidPayment['status'] !== 200 || !$voidedPayment ||
        $voidedPayment['status'] !== 'anulado' || $voidedPayment['void_reason'] !== 'Anulación de prueba HTTP.') {
        throw new RuntimeException('No se pudo anular el pago preservando el motivo.');
    }

    $financePage = requestPage($curl, $baseUrl . '/finance?account_id=' . $accountId);
    $settlementFolio = 'SMK-' . strtoupper(bin2hex(random_bytes(5)));
    $settleAccount = requestPage($curl, $baseUrl . '/finance/register-payment', [
        'csrf' => csrfFromPage($financePage['body']),
        'account_id' => (string) $accountId,
        'folio' => $settlementFolio,
        'concept' => 'Liquidación de prueba',
        'amount' => '1000.00',
        'paid_on' => date('Y-m-d'),
        'payment_method' => 'efectivo',
        'payment_reference' => 'SMOKE-REF-3',
        'notes' => '',
    ]);
    $checkFinancialAccount->execute(['id' => $accountId]);
    $settledAccount = $checkFinancialAccount->fetch(PDO::FETCH_ASSOC);
    if ($settleAccount['status'] !== 200 || !$settledAccount ||
        $settledAccount['status'] !== 'al_corriente') {
        throw new RuntimeException('Al liquidar el saldo, la cuenta no quedó al corriente.');
    }
    $financePage = requestPage($curl, $baseUrl . '/finance?account_id=' . $accountId);
    $setAccountAgreement = requestPage($curl, $baseUrl . '/finance/save-account', [
        'csrf' => csrfFromPage($financePage['body']),
        'account_id' => (string) $accountId,
        'amount_due' => '1000.00',
        'status' => 'convenio',
        'status_note' => 'Convenio de prueba',
    ]);
    $checkFinancialAccount->execute(['id' => $accountId]);
    $agreementAccount = $checkFinancialAccount->fetch(PDO::FETCH_ASSOC);
    if ($setAccountAgreement['status'] !== 200 || !$agreementAccount ||
        $agreementAccount['status'] !== 'convenio') {
        throw new RuntimeException('No se pudo editar el estado de cuenta a convenio.');
    }
    $countFinanceEvents = $pdo->prepare(
        'SELECT COUNT(*) FROM ceptri_financial_events WHERE account_id = :account_id'
    );
    $countFinanceEvents->execute(['account_id' => $accountId]);
    if ((int) $countFinanceEvents->fetchColumn() < 5) {
        throw new RuntimeException('No quedaron auditados los cambios y movimientos financieros de la prueba.');
    }

    $recordsPage = requestPage($curl, $baseUrl . '/records?student_id=' . $studentId);
    if ($recordsPage['status'] !== 200 || !str_contains($recordsPage['body'], 'Revisión de expedientes')) {
        throw new RuntimeException('La pantalla de revisión de expedientes no respondió correctamente.');
    }
    $documentTempPath = tempnam(sys_get_temp_dir(), 'ceptri-document-smoke-');
    if ($documentTempPath === false ||
        file_put_contents($documentTempPath, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n") === false) {
        throw new RuntimeException('No se pudo crear el PDF temporal de prueba.');
    }
    $uploadDocument = requestPage($curl, $baseUrl . '/records/upload', [
        'csrf' => csrfFromPage($recordsPage['body']),
        'student_id' => (string) $studentId,
        'requirement_id' => 'acta',
        'document_file' => new CURLFile($documentTempPath, 'application/pdf', 'acta-smoke.pdf'),
    ]);
    if ($uploadDocument['status'] !== 200 || !str_contains($uploadDocument['body'], 'quedó en revisión')) {
        throw new RuntimeException('No se pudo cargar un documento para revisión.');
    }
    $findDocument = $pdo->prepare(
        'SELECT id, file_key, status FROM ceptri_student_documents
         WHERE student_id = :student_id AND requirement_id = :requirement_id ORDER BY id DESC LIMIT 1'
    );
    $findDocument->execute(['student_id' => $studentId, 'requirement_id' => 'acta']);
    $document = $findDocument->fetch(PDO::FETCH_ASSOC);
    if (!$document || $document['status'] !== 'en_revision') {
        throw new RuntimeException('El documento no quedó persistido con estado En revisión.');
    }
    $documentFileKeys[] = $document['file_key'];
    $viewDocument = requestPage($curl, $baseUrl . '/records/file?id=' . (int) $document['id']);
    if ($viewDocument['status'] !== 200 ||
        !str_contains($viewDocument['content_type'], 'application/pdf') ||
        !str_starts_with($viewDocument['body'], '%PDF-1.4')) {
        throw new RuntimeException('El archivo privado no se pudo consultar mediante la ruta autorizada.');
    }

    $recordsPage = requestPage($curl, $baseUrl . '/records?student_id=' . $studentId);
    $approveDocument = requestPage($curl, $baseUrl . '/records/decide', [
        'csrf' => csrfFromPage($recordsPage['body']),
        'student_id' => (string) $studentId,
        'document_id' => (string) $document['id'],
        'decision' => 'aprobado',
        'observation' => '',
    ]);
    $checkDocumentStatus = $pdo->prepare(
        'SELECT status FROM ceptri_student_documents WHERE id = :id'
    );
    $checkDocumentStatus->execute(['id' => $document['id']]);
    if ($approveDocument['status'] !== 200 || $checkDocumentStatus->fetchColumn() !== 'aprobado') {
        throw new RuntimeException('No se pudo aprobar el documento ni guardar la decisión.');
    }

    $recordsPage = requestPage($curl, $baseUrl . '/records?student_id=' . $studentId);
    $uploadDocument = requestPage($curl, $baseUrl . '/records/upload', [
        'csrf' => csrfFromPage($recordsPage['body']),
        'student_id' => (string) $studentId,
        'requirement_id' => 'acta',
        'document_file' => new CURLFile($documentTempPath, 'application/pdf', 'acta-smoke.pdf'),
    ]);
    $findDocument->execute(['student_id' => $studentId, 'requirement_id' => 'acta']);
    $replacementDocument = $findDocument->fetch(PDO::FETCH_ASSOC);
    if ($uploadDocument['status'] !== 200 || !$replacementDocument ||
        $replacementDocument['status'] !== 'en_revision' || $replacementDocument['id'] === $document['id']) {
        throw new RuntimeException('No se pudo guardar una nueva versión del documento.');
    }
    $documentFileKeys[] = $replacementDocument['file_key'];
    $recordsPage = requestPage($curl, $baseUrl . '/records?student_id=' . $studentId);
    $rejectDocument = requestPage($curl, $baseUrl . '/records/decide', [
        'csrf' => csrfFromPage($recordsPage['body']),
        'student_id' => (string) $studentId,
        'document_id' => (string) $replacementDocument['id'],
        'decision' => 'rechazado',
        'observation' => 'El documento de prueba requiere corrección.',
    ]);
    $checkDocumentStatus->execute(['id' => $replacementDocument['id']]);
    if ($rejectDocument['status'] !== 200 || $checkDocumentStatus->fetchColumn() !== 'rechazado') {
        throw new RuntimeException('No se pudo rechazar el documento ni guardar el motivo.');
    }

    $academicPage = requestPage($curl, $baseUrl . '/academic?period=2026-1');
    if ($academicPage['status'] !== 200 ||
        !str_contains($academicPage['body'], 'Grupos y asignación académica')) {
        throw new RuntimeException('La vista de grupos y asignación académica no respondió correctamente.');
    }
    $subjectQuery = $pdo->prepare(
        'SELECT id FROM ceptri_academic_subjects
         WHERE career = :career AND subject_name = :subject
         LIMIT 1'
    );
    $subjectQuery->execute([
        'career' => 'Especialidad en Derecho Administrativo (EDA)',
        'subject' => 'Derecho Administrativo',
    ]);
    $academicSubjectId = (int) $subjectQuery->fetchColumn();
    if ($academicSubjectId < 1) {
        throw new RuntimeException('La migración académica no instaló el catálogo de materias esperado.');
    }
    $createGroup = requestPage($curl, $baseUrl . '/academic/save-group', [
        'csrf' => csrfFromPage($academicPage['body']),
        'career' => 'Especialidad en Derecho Administrativo (EDA)',
        'period_key' => '2026-1',
        'group_name' => 'Grupo temporal',
        'subject_ids' => [(string) $academicSubjectId],
    ]);
    if ($createGroup['status'] !== 200 || !str_contains($createGroup['body'], 'Grupo temporal')) {
        throw new RuntimeException('No se pudo crear un grupo académico con su materia.');
    }
    $findAcademicGroup = $pdo->prepare(
        'SELECT id FROM ceptri_academic_groups
         WHERE career = :career AND period_key = :period AND group_name = :group_name'
    );
    $findAcademicGroup->execute([
        'career' => 'Especialidad en Derecho Administrativo (EDA)',
        'period' => '2026-1',
        'group_name' => 'Grupo temporal',
    ]);
    $academicGroupId = (int) $findAcademicGroup->fetchColumn();
    if ($academicGroupId < 1) {
        throw new RuntimeException('El grupo creado no quedó persistido en MariaDB.');
    }
    $academicPage = requestPage($curl, $baseUrl . '/academic?period=2026-1');
    $duplicateGroup = requestPage($curl, $baseUrl . '/academic/save-group', [
        'csrf' => csrfFromPage($academicPage['body']),
        'career' => 'Especialidad en Derecho Administrativo (EDA)',
        'period_key' => '2026-1',
        'group_name' => 'Grupo temporal',
        'subject_ids' => [(string) $academicSubjectId],
    ]);
    if ($duplicateGroup['status'] !== 200 ||
        !str_contains($duplicateGroup['body'], 'Ya existe un grupo con ese nombre')) {
        throw new RuntimeException('Se permitió duplicar un grupo para la misma carrera y cuatrimestre.');
    }
    $findGroupSubject = $pdo->prepare(
        'SELECT id FROM ceptri_academic_group_subjects WHERE group_id = :group_id AND subject_id = :subject_id'
    );
    $findGroupSubject->execute(['group_id' => $academicGroupId, 'subject_id' => $academicSubjectId]);
    $groupSubjectId = (int) $findGroupSubject->fetchColumn();
    if ($groupSubjectId < 1) {
        throw new RuntimeException('La materia no quedó relacionada al grupo creado.');
    }

    $groupPage = requestPage($curl, $baseUrl . '/academic?period=2026-1&group_id=' . $academicGroupId);
    $assignTeacherResponse = requestPage($curl, $baseUrl . '/academic/assign-teacher', [
        'csrf' => csrfFromPage($groupPage['body']),
        'group_id' => (string) $academicGroupId,
        'group_subject_id' => (string) $groupSubjectId,
        'teacher_id' => (string) $teacherUserId,
        'period_key' => '2026-1',
    ]);
    $checkTeacher = $pdo->prepare(
        'SELECT teacher_user_id FROM ceptri_academic_teacher_assignments WHERE group_subject_id = :id'
    );
    $checkTeacher->execute(['id' => $groupSubjectId]);
    if ($assignTeacherResponse['status'] !== 200 || (int) $checkTeacher->fetchColumn() !== $teacherUserId ||
        !str_contains($assignTeacherResponse['body'], 'Docente temporal de prueba')) {
        throw new RuntimeException('No se pudo asignar el docente activo a la materia del grupo.');
    }

    $findAcademicEnrollment = $pdo->prepare(
        'SELECT id FROM ceptri_student_enrollments WHERE student_id = :student_id AND period_key = :period'
    );
    $findAcademicEnrollment->execute(['student_id' => $studentId, 'period' => '2026-1']);
    $academicEnrollmentId = (int) $findAcademicEnrollment->fetchColumn();
    $groupPage = requestPage($curl, $baseUrl . '/academic?period=2026-1&group_id=' . $academicGroupId);
    $enrollSubject = requestPage($curl, $baseUrl . '/academic/enroll-students', [
        'csrf' => csrfFromPage($groupPage['body']),
        'group_id' => (string) $academicGroupId,
        'enrollment_id' => (string) $academicEnrollmentId,
        'period_key' => '2026-1',
        'group_subject_ids' => [(string) $groupSubjectId],
    ]);
    $checkStudentSubject = $pdo->prepare(
        'SELECT id FROM ceptri_academic_student_subjects
         WHERE enrollment_id = :enrollment_id AND group_subject_id = :group_subject_id'
    );
    $checkStudentSubject->execute([
        'enrollment_id' => $academicEnrollmentId,
        'group_subject_id' => $groupSubjectId,
    ]);
    $studentSubjectId = (int) $checkStudentSubject->fetchColumn();
    if ($enrollSubject['status'] !== 200 || $studentSubjectId < 1 ||
        !str_contains($enrollSubject['body'], $studentRegistration)) {
        throw new RuntimeException(sprintf(
            'No se pudo inscribir el alumno elegible a la materia (HTTP %d, registro %d): %s',
            $enrollSubject['status'],
            $studentSubjectId,
            trim(preg_replace('/\s+/', ' ', strip_tags(substr($enrollSubject['body'], -500))))
        ));
    }

    $groupPage = requestPage($curl, $baseUrl . '/academic?period=2026-1&group_id=' . $academicGroupId);
    $duplicateSubject = requestPage($curl, $baseUrl . '/academic/enroll-students', [
        'csrf' => csrfFromPage($groupPage['body']),
        'group_id' => (string) $academicGroupId,
        'enrollment_id' => (string) $academicEnrollmentId,
        'period_key' => '2026-1',
        'group_subject_ids' => [(string) $groupSubjectId],
    ]);
    if ($duplicateSubject['status'] !== 200 ||
        !str_contains($duplicateSubject['body'], 'ya tiene una o más de esas materias')) {
        throw new RuntimeException('Se permitió duplicar la inscripción de materia del alumno.');
    }

    $report = requestPage($curl, $baseUrl . '/academic/report?group_id=' . $academicGroupId);
    if ($report['status'] !== 200 || !str_starts_with($report['content_type'], 'application/pdf') ||
        !str_starts_with($report['body'], '%PDF-')) {
        throw new RuntimeException('El reporte PDF del grupo no se generó correctamente.');
    }

    $groupPage = requestPage($curl, $baseUrl . '/academic?period=2026-1&group_id=' . $academicGroupId);
    $removeStudentSubject = requestPage($curl, $baseUrl . '/academic/remove-student-subject', [
        'csrf' => csrfFromPage($groupPage['body']),
        'id' => (string) $studentSubjectId,
        'group_id' => (string) $academicGroupId,
    ]);
    $checkRemovedSubject = $pdo->prepare(
        'SELECT COUNT(*) FROM ceptri_academic_student_subjects WHERE id = :id'
    );
    $checkRemovedSubject->execute(['id' => $studentSubjectId]);
    if ($removeStudentSubject['status'] !== 200 || (int) $checkRemovedSubject->fetchColumn() !== 0) {
        throw new RuntimeException('No se pudo retirar una inscripción académica.');
    }
    $groupPage = requestPage($curl, $baseUrl . '/academic?period=2026-1&group_id=' . $academicGroupId);
    $removeTeacher = requestPage($curl, $baseUrl . '/academic/remove-teacher', [
        'csrf' => csrfFromPage($groupPage['body']),
        'id' => (string) $groupSubjectId,
        'group_id' => (string) $academicGroupId,
    ]);
    $checkRemovedTeacher = $pdo->prepare(
        'SELECT COUNT(*) FROM ceptri_academic_teacher_assignments WHERE group_subject_id = :id'
    );
    $checkRemovedTeacher->execute(['id' => $groupSubjectId]);
    if ($removeTeacher['status'] !== 200 || (int) $checkRemovedTeacher->fetchColumn() !== 0) {
        throw new RuntimeException('No se pudo retirar la asignación docente.');
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
    $findFinancialAccount->execute(['student_id' => $studentId, 'period' => '2027-2']);
    $newPeriodAccount = $findFinancialAccount->fetch(PDO::FETCH_ASSOC);
    if (!$newPeriodAccount || $newPeriodAccount['status'] !== 'adeudo') {
        throw new RuntimeException('La reinscripción no generó una cuenta de adeudo para el nuevo cuatrimestre.');
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
    $academicAccessDenied = requestPage($curl, $baseUrl . '/academic');
    if ($academicAccessDenied['status'] !== 403) {
        throw new RuntimeException('Una cuenta sin academic.assign pudo consultar grupos y asignaciones.');
    }
    $financeAccessDenied = requestPage($curl, $baseUrl . '/finance');
    if ($financeAccessDenied['status'] !== 403) {
        throw new RuntimeException('Una cuenta sin finance.view pudo consultar cuentas financieras.');
    }
    $recordsAccessDenied = requestPage($curl, $baseUrl . '/records');
    if ($recordsAccessDenied['status'] !== 403) {
        throw new RuntimeException('Una cuenta sin records.review pudo consultar expedientes escolares.');
    }
    $documentFileAccessDenied = requestPage(
        $curl,
        $baseUrl . '/records/file?id=' . (int) $document['id']
    );
    if ($documentFileAccessDenied['status'] !== 403) {
        throw new RuntimeException('Una cuenta sin records.review pudo consultar un archivo documental privado.');
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
        !str_ends_with($controlLogin['url'], '/academic') ||
        !str_contains($controlLogin['body'], 'Grupos y asignación académica') ||
        !str_contains($controlLogin['body'], 'Revisión de expedientes') ||
        !str_contains($controlLogin['body'], 'Padrón de alumnos') ||
        !str_contains($controlLogin['body'], 'Grupos y asignaciones') ||
        str_contains($controlLogin['body'], 'Usuarios y cuentas')) {
        throw new RuntimeException(sprintf(
            'El rol Control Escolar no recibió su menú autorizado (HTTP %d, ruta %s).',
            $controlLogin['status'],
            (string) parse_url($controlLogin['url'], PHP_URL_PATH)
        ));
    }
    $controlFinanceAccess = requestPage($controlCurl, $baseUrl . '/finance');
    if (str_contains($controlLogin['body'], '>Finanzas</span>') ||
        $controlFinanceAccess['status'] !== 403) {
        throw new RuntimeException('El rol Control Escolar recibió acceso no autorizado a Finanzas.');
    }
    $controlHome = requestPage($controlCurl, $baseUrl . '/auth');
    if ($controlHome['status'] !== 200 || !str_ends_with($controlHome['url'], '/academic')) {
        throw new RuntimeException('Una sesión de Control Escolar no regresó a grupos y asignaciones al abrir /auth.');
    }

    $financeRoleId = $pdo->query("SELECT id FROM bee_roles WHERE slug = 'finanzas'")->fetchColumn();
    if ($financeRoleId === false) {
        throw new RuntimeException('No existe el rol Finanzas para comprobar el acceso al módulo.');
    }
    $insertFinanceUser = $pdo->prepare(
        'INSERT INTO bee_users (username, password, email, nombre_completo)
         VALUES (:username, :password, :email, :name)'
    );
    $insertFinanceUser->execute([
        'username' => $financeUsername,
        'password' => password_hash($financePassword . $_ENV['AUTH_SALT'], PASSWORD_BCRYPT),
        'email' => $financeUsername . '@example.invalid',
        'name' => 'Cuenta temporal de Finanzas',
    ]);
    $financeUserId = (int) $pdo->lastInsertId();
    $assignFinanceRole = $pdo->prepare(
        'INSERT INTO ceptri_user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assignFinanceRole->execute(['user_id' => $financeUserId, 'role_id' => (int) $financeRoleId]);

    $financeCurl = curl_init();
    if ($financeCurl === false) {
        throw new RuntimeException('No se pudo iniciar la sesión HTTP de prueba para Finanzas.');
    }
    curl_setopt_array($financeCurl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'CEPTRI local finance smoke test',
    ]);
    $financeLoginPage = requestPage($financeCurl, $baseUrl . '/auth');
    $financeLogin = requestPage($financeCurl, $baseUrl . '/auth/login', [
        'csrf' => csrfFromPage($financeLoginPage['body']),
        'identifier' => $financeUsername,
        'password' => $financePassword,
    ]);
    if ($financeLogin['status'] !== 200 ||
        !str_ends_with($financeLogin['url'], '/finance') ||
        !str_contains($financeLogin['body'], 'Estados de cuenta y pagos') ||
        !str_contains($financeLogin['body'], '>Finanzas</span>')) {
        throw new RuntimeException(sprintf(
            'El rol Finanzas no recibió su espacio autorizado (HTTP %d, ruta %s).',
            $financeLogin['status'],
            (string) parse_url($financeLogin['url'], PHP_URL_PATH)
        ));
    }
    $financeStudentHistory = requestPage($financeCurl, $baseUrl . '/finance/history-pdf?student_id=' . $studentId);
    if ($financeStudentHistory['status'] !== 200 ||
        !str_starts_with($financeStudentHistory['content_type'], 'application/pdf') ||
        !str_starts_with($financeStudentHistory['body'], '%PDF-')) {
        throw new RuntimeException('El rol Finanzas no pudo exportar el historial de pagos permitido.');
    }
    if (requestPage($financeCurl, $baseUrl . '/students')['status'] !== 403 ||
        requestPage($financeCurl, $baseUrl . '/academic')['status'] !== 403 ||
        requestPage($financeCurl, $baseUrl . '/records')['status'] !== 403) {
        throw new RuntimeException('El rol Finanzas recibió acceso a un módulo fuera de sus permisos.');
    }

    echo "Pruebas HTTP OK: administración RBAC, padrón, expedientes, Finanzas cuatrimestrales, pagos auditados, PDF, grupos, docentes, materias, CSRF y permisos.\n";
} finally {
    if ($studentId !== null && $documentFileKeys) {
        $storageDirectory = dirname(__DIR__) . '/private/student-documents';
        foreach (array_unique($documentFileKeys) as $fileKey) {
            if (preg_match('/\A[a-f0-9]{64}\.(pdf|jpg|jpeg|png)\z/', $fileKey) &&
                is_file($storageDirectory . DIRECTORY_SEPARATOR . $fileKey) &&
                !unlink($storageDirectory . DIRECTORY_SEPARATOR . $fileKey)) {
                error_log('No se pudo limpiar el archivo de prueba ' . $fileKey);
            }
        }
    }
    if ($academicGroupId !== null) {
        $deleteGroup = $pdo->prepare('DELETE FROM ceptri_academic_groups WHERE id = :group_id');
        $deleteGroup->execute(['group_id' => $academicGroupId]);
    }
    if ($controlUserId !== null) {
        $deleteControlUser = $pdo->prepare('DELETE FROM bee_users WHERE id = :user_id');
        $deleteControlUser->execute(['user_id' => $controlUserId]);
    }
    if ($financeUserId !== null) {
        $deleteFinanceUser = $pdo->prepare('DELETE FROM bee_users WHERE id = :user_id');
        $deleteFinanceUser->execute(['user_id' => $financeUserId]);
    }
    if ($studentId !== null) {
        $deleteStudent = $pdo->prepare('DELETE FROM ceptri_students WHERE id = :student_id');
        $deleteStudent->execute(['student_id' => $studentId]);
    }
    if (is_string($documentTempPath) && is_file($documentTempPath) && !unlink($documentTempPath)) {
        error_log('No se pudo eliminar el PDF temporal de prueba.');
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
    if ($teacherUserId !== null) {
        $deleteTeacher = $pdo->prepare('DELETE FROM bee_users WHERE id = :user_id');
        $deleteTeacher->execute(['user_id' => $teacherUserId]);
    }
    if ($controlCurl instanceof CurlHandle) {
        curl_close($controlCurl);
    }
    if ($financeCurl instanceof CurlHandle) {
        curl_close($financeCurl);
    }
    curl_close($curl);
}
