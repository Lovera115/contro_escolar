<?php

class adminController extends Controller implements ControllerInterface
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $user = CeptriAccess::requirePermission('admin-access');
        $pdo = Db::connect(true);
        $summary = $pdo->query(
            'SELECT
                COUNT(*) AS total_users,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) AS active_users,
                (SELECT COUNT(*) FROM bee_roles) AS total_roles,
                (SELECT COUNT(*) FROM bee_permisos) AS total_permissions
             FROM bee_users'
        )->fetch(PDO::FETCH_ASSOC);

        $this->setTitle('Administración · CEPTRI');
        $this->setView('index');
        $this->setData([
            'user' => $user,
            'moduleAccess' => CeptriAccess::moduleAccess($user),
            'canManageStudents' => CeptriAccess::can($user, 'students.manage'),
            'canManageAcademic' => CeptriAccess::can($user, 'academic.assign'),
            'canReviewRecords' => CeptriAccess::can($user, 'records.review'),
            'canViewFinance' => CeptriAccess::can($user, 'finance.view'),
            'csrfToken' => CSRF_TOKEN,
            'summary' => $summary,
        ]);
        $this->render();
    }

    public function users()
    {
        $currentUser = CeptriAccess::requirePermission('admin-access');
        $pdo = Db::connect(true);
        $users = $pdo->query(
            'SELECT u.id, u.username, u.email, u.nombre_completo, u.activo, u.created_at,
                    r.slug AS role_slug, r.nombre AS role_name
             FROM bee_users u
             LEFT JOIN ceptri_user_roles ur ON ur.user_id = u.id
             LEFT JOIN bee_roles r ON r.id = ur.role_id
             ORDER BY u.nombre_completo, u.id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $roles = $pdo->query('SELECT id, nombre, slug FROM bee_roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $selectedId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
        $selectedUser = null;
        $overrides = [];

        if ($selectedId) {
            $statement = $pdo->prepare(
                'SELECT u.id, u.username, u.email, u.nombre_completo, u.activo,
                        r.slug AS role_slug, r.nombre AS role_name
                 FROM bee_users u
                 LEFT JOIN ceptri_user_roles ur ON ur.user_id = u.id
                 LEFT JOIN bee_roles r ON r.id = ur.role_id
                 WHERE u.id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $selectedId]);
            $selectedUser = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedUser) {
                $overrideStatement = $pdo->prepare(
                    'SELECT p.slug, up.allowed
                     FROM ceptri_user_permissions up
                     INNER JOIN bee_permisos p ON p.id = up.permission_id
                     WHERE up.user_id = :user_id'
                );
                $overrideStatement->execute(['user_id' => $selectedId]);
                foreach ($overrideStatement->fetchAll(PDO::FETCH_ASSOC) as $override) {
                    $overrides[$override['slug']] = (int) $override['allowed'] === 1 ? 'allow' : 'deny';
                }
            }
        }

        $permissions = $pdo->query(
            "SELECT id, nombre, slug, descripcion FROM bee_permisos
             WHERE slug <> 'admin-access'
             ORDER BY slug"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->setTitle('Gestión de usuarios · CEPTRI');
        $this->setView('users');
        $this->setData([
            'user' => $currentUser,
            'moduleAccess' => CeptriAccess::moduleAccess($currentUser),
            'csrfToken' => CSRF_TOKEN,
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'selectedUser' => $selectedUser,
            'overrides' => $overrides,
            'flash' => $this->consumeFlash(),
        ]);
        $this->render();
    }

    public function manage_users()
    {
        $this->requirePostAndCsrf();
        CeptriAccess::requirePermission('admin-access');

        $pdo = Db::connect(true);
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: null;
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['nombre_completo'] ?? ''));
        $roleSlug = trim((string) ($_POST['role_slug'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $active = isset($_POST['activo']) ? 1 : 0;

        if (!preg_match('/\A[a-zA-Z0-9._-]{3,64}\z/', $username)) {
            $this->fail('El usuario debe tener entre 3 y 64 caracteres alfanuméricos, puntos, guiones o guiones bajos.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $this->fail('Captura un correo válido de máximo 254 caracteres.');
        }
        if ($fullName === '' || mb_strlen($fullName) > 160) {
            $this->fail('El nombre completo es obligatorio y debe tener como máximo 160 caracteres.');
        }
        if (!$this->roleExists($pdo, $roleSlug)) {
            $this->fail('Selecciona un rol existente.');
        }
        if (!$userId && strlen($password) < 12) {
            $this->fail('La contraseña debe tener al menos 12 caracteres.');
        }
        if ($password !== '' && strlen($password) < 12) {
            $this->fail('La nueva contraseña debe tener al menos 12 caracteres.');
        }

        try {
            $pdo->beginTransaction();
            $existingUser = null;
            if ($userId) {
                $lockUser = $pdo->prepare(
                    'SELECT u.id, u.activo, r.slug AS role_slug
                     FROM bee_users u
                     LEFT JOIN ceptri_user_roles ur ON ur.user_id = u.id
                     LEFT JOIN bee_roles r ON r.id = ur.role_id
                     WHERE u.id = :id
                     FOR UPDATE'
                );
                $lockUser->execute(['id' => $userId]);
                $existingUser = $lockUser->fetch(PDO::FETCH_ASSOC);
                if (!$existingUser) {
                    throw new DomainException('No se encontró la cuenta que intentas editar.');
                }
            }

            $duplicateSql = 'SELECT id FROM bee_users WHERE (username = :username OR email = :email)';
            $duplicateParams = [
                'username' => $username,
                'email' => $email,
            ];
            if ($userId) {
                $duplicateSql .= ' AND id <> :exclude_id';
                $duplicateParams['exclude_id'] = $userId;
            }
            $duplicate = $pdo->prepare($duplicateSql . ' LIMIT 1');
            $duplicate->execute($duplicateParams);
            if ($duplicate->fetchColumn() !== false) {
                throw new DomainException('Ese usuario o correo ya está registrado.');
            }

            $currentId = (int) ($_SESSION['user_session']['id'] ?? 0);
            if ($userId === $currentId && ($active !== 1 || $roleSlug !== $existingUser['role_slug'])) {
                throw new DomainException('No puedes desactivar tu cuenta ni cambiar tu propio rol.');
            }

            if ($existingUser &&
                $existingUser['role_slug'] === 'administrador' &&
                (int) $existingUser['activo'] === 1 &&
                ($roleSlug !== 'administrador' || $active !== 1)) {
                $this->assertAnotherActiveAdminExists($pdo, $userId);
            }

            if ($userId) {
                $update = $pdo->prepare(
                    'UPDATE bee_users
                     SET username = :username, email = :email, nombre_completo = :nombre, activo = :activo
                     WHERE id = :id'
                );
                $update->execute([
                    'username' => $username,
                    'email' => $email,
                    'nombre' => $fullName,
                    'activo' => $active,
                    'id' => $userId,
                ]);
                if ($password !== '') {
                    $updatePassword = $pdo->prepare('UPDATE bee_users SET password = :password WHERE id = :id');
                    $updatePassword->execute([
                        'password' => password_hash($password . AUTH_SALT, PASSWORD_BCRYPT),
                        'id' => $userId,
                    ]);
                }
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO bee_users (username, password, email, nombre_completo, activo)
                     VALUES (:username, :password, :email, :nombre, :activo)'
                );
                $insert->execute([
                    'username' => $username,
                    'password' => password_hash($password . AUTH_SALT, PASSWORD_BCRYPT),
                    'email' => $email,
                    'nombre' => $fullName,
                    'activo' => $active,
                ]);
                $userId = (int) $pdo->lastInsertId();
            }

            $assignRole = $pdo->prepare(
                'INSERT INTO ceptri_user_roles (user_id, role_id)
                 SELECT :user_id, id FROM bee_roles WHERE slug = :slug
                 ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)'
            );
            $assignRole->execute(['user_id' => $userId, 'slug' => $roleSlug]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $userId);
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $this->fail('Ese usuario o correo ya está registrado.', $userId);
            }
            throw $exception;
        }

        $this->setFlash('success', $userId && filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT)
            ? 'La cuenta se actualizó correctamente.'
            : 'La cuenta se creó correctamente.');
        Redirect::to('admin/users');
    }

    public function roles()
    {
        $currentUser = CeptriAccess::requirePermission('admin-access');
        $pdo = Db::connect(true);
        $roles = $pdo->query(
            'SELECT r.id, r.nombre, r.slug, COUNT(DISTINCT ur.user_id) AS assigned_users
             FROM bee_roles r
             LEFT JOIN ceptri_user_roles ur ON ur.role_id = r.id
             GROUP BY r.id, r.nombre, r.slug
             ORDER BY r.id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $permissions = $pdo->query(
            "SELECT id, nombre, slug, descripcion FROM bee_permisos
             WHERE slug <> 'admin-access'
             ORDER BY slug"
        )->fetchAll(PDO::FETCH_ASSOC);

        $rolePermissionRows = $pdo->query(
            'SELECT r.slug AS role_slug, p.slug AS permission_slug
             FROM bee_roles_permisos rp
             INNER JOIN bee_roles r ON r.id = rp.id_role
             INNER JOIN bee_permisos p ON p.id = rp.id_permiso'
        )->fetchAll(PDO::FETCH_ASSOC);
        $rolePermissions = [];
        foreach ($rolePermissionRows as $row) {
            $rolePermissions[$row['role_slug']][$row['permission_slug']] = true;
        }

        $this->setTitle('Roles y permisos · CEPTRI');
        $this->setView('roles');
        $this->setData([
            'user' => $currentUser,
            'moduleAccess' => CeptriAccess::moduleAccess($currentUser),
            'csrfToken' => CSRF_TOKEN,
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePermissions' => $rolePermissions,
            'flash' => $this->consumeFlash(),
        ]);
        $this->render();
    }

    public function save_role()
    {
        $this->requirePostAndCsrf();
        CeptriAccess::requirePermission('admin-access');
        $name = trim((string) ($_POST['nombre'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $this->fail('El nombre del rol es obligatorio y debe tener máximo 100 caracteres.');
        }
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) || mb_strlen($slug) > 100) {
            $this->fail('El identificador del rol solo admite minúsculas, números y guiones.');
        }

        $pdo = Db::connect(true);
        try {
            $statement = $pdo->prepare('INSERT INTO bee_roles (nombre, slug) VALUES (:nombre, :slug)');
            $statement->execute(['nombre' => $name, 'slug' => $slug]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $this->fail('Ya existe un rol con ese identificador.');
            }
            throw $exception;
        }

        $this->setFlash('success', 'El rol se creó correctamente. Asigna sus permisos en la matriz.');
        Redirect::to('admin/roles');
    }

    public function save_role_permissions()
    {
        $this->requirePostAndCsrf();
        CeptriAccess::requirePermission('admin-access');
        $slug = trim((string) ($_POST['role_slug'] ?? ''));
        $requested = $_POST['permissions'] ?? [];
        if (!is_array($requested)) {
            $this->fail('La matriz de permisos recibida no es válida.');
        }

        $pdo = Db::connect(true);
        $roleQuery = $pdo->prepare('SELECT id, slug FROM bee_roles WHERE slug = :slug');
        $roleQuery->execute(['slug' => $slug]);
        $role = $roleQuery->fetch(PDO::FETCH_ASSOC);
        if (!$role) {
            $this->fail('No se encontró el rol seleccionado.');
        }
        if ($role['slug'] === 'administrador') {
            $this->setFlash('info', 'El rol Administrador conserva el permiso global de Bee; no se modifica desde la matriz.');
            Redirect::to('admin/roles');
        }

        $permissionQuery = $pdo->query("SELECT id, slug FROM bee_permisos WHERE slug <> 'admin-access'");
        $available = $permissionQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        $allowedIds = [];
        foreach ($requested as $permissionId) {
            if (!filter_var($permissionId, FILTER_VALIDATE_INT) || !array_key_exists((int) $permissionId, $available)) {
                $this->fail('La matriz contiene un permiso no válido.');
            }
            $allowedIds[] = (int) $permissionId;
        }

        try {
            $pdo->beginTransaction();
            $delete = $pdo->prepare('DELETE FROM bee_roles_permisos WHERE id_role = :role_id');
            $delete->execute(['role_id' => (int) $role['id']]);
            if ($allowedIds) {
                $insert = $pdo->prepare(
                    'INSERT INTO bee_roles_permisos (id_role, id_permiso) VALUES (:role_id, :permission_id)'
                );
                foreach (array_unique($allowedIds) as $permissionId) {
                    $insert->execute(['role_id' => (int) $role['id'], 'permission_id' => $permissionId]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $this->setFlash('success', 'Los permisos del rol se actualizaron correctamente.');
        Redirect::to('admin/roles');
    }

    public function save_user_overrides()
    {
        $this->requirePostAndCsrf();
        CeptriAccess::requirePermission('admin-access');
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $submitted = $_POST['overrides'] ?? [];
        if (!$userId || !is_array($submitted)) {
            $this->fail('Los permisos personalizados recibidos no son válidos.');
        }

        $pdo = Db::connect(true);
        $userQuery = $pdo->prepare('SELECT id FROM bee_users WHERE id = :id');
        $userQuery->execute(['id' => $userId]);
        if ($userQuery->fetchColumn() === false) {
            $this->fail('No se encontró el usuario seleccionado.');
        }

        $permissionQuery = $pdo->query("SELECT id, slug FROM bee_permisos WHERE slug <> 'admin-access'");
        $permissionIds = array_flip($permissionQuery->fetchAll(PDO::FETCH_KEY_PAIR));
        $currentUserId = (int) ($_SESSION['user_session']['id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $delete = $pdo->prepare(
                'DELETE up FROM ceptri_user_permissions up
                 INNER JOIN bee_permisos p ON p.id = up.permission_id
                 WHERE up.user_id = :user_id AND p.slug <> :admin_permission'
            );
            $delete->execute(['user_id' => $userId, 'admin_permission' => 'admin-access']);
            $upsert = $pdo->prepare(
                'INSERT INTO ceptri_user_permissions (user_id, permission_id, allowed)
                 VALUES (:user_id, :permission_id, :allowed)'
            );
            foreach ($submitted as $permissionSlug => $value) {
                if (!isset($permissionIds[$permissionSlug]) || !in_array($value, ['allow', 'deny'], true)) {
                    throw new DomainException('El formulario contiene una anulación de permiso no válida.');
                }
                if ($userId === $currentUserId && $permissionSlug === 'admin.dashboard.view' && $value === 'deny') {
                    throw new DomainException('No puedes quitarte a ti mismo el acceso a tu panel.');
                }
                $upsert->execute([
                    'user_id' => $userId,
                    'permission_id' => $permissionIds[$permissionSlug],
                    'allowed' => $value === 'allow' ? 1 : 0,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $userId);
            }
            throw $exception;
        }

        $this->setFlash('success', 'Los permisos personalizados se guardaron correctamente.');
        Redirect::to('admin/users?edit=' . $userId);
    }

    private function requirePostAndCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido.');
        }

        $csrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
        if (!Csrf::validate($csrf, true)) {
            http_response_code(400);
            exit('El token de seguridad no es válido o expiró. Recarga el formulario e inténtalo de nuevo.');
        }
    }

    private function roleExists(PDO $pdo, string $slug): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM bee_roles WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        return $statement->fetchColumn() !== false;
    }

    private function assertAnotherActiveAdminExists(PDO $pdo, int $excludedUserId): void
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM bee_users u
             INNER JOIN ceptri_user_roles ur ON ur.user_id = u.id
             INNER JOIN bee_roles r ON r.id = ur.role_id
             WHERE u.activo = 1 AND r.slug = 'administrador' AND u.id <> :user_id"
        );
        $statement->execute(['user_id' => $excludedUserId]);
        if ((int) $statement->fetchColumn() < 1) {
            throw new DomainException('No puedes quitar el último administrador activo del sistema.');
        }
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    }

    private function consumeFlash(): array
    {
        $flash = $_SESSION['admin_flash'] ?? [];
        unset($_SESSION['admin_flash']);
        return is_array($flash) ? $flash : [];
    }

    private function fail(string $message, ?int $userId = null): void
    {
        $this->setFlash('error', $message);
        Redirect::to($userId ? 'admin/users?edit=' . $userId : 'admin/users');
    }
}
