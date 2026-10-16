<?php

class authController extends Controller implements ControllerInterface
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $user = CeptriAccess::currentUser();
        if ($user !== null) {
            $this->redirectToWorkspace($user);
        }

        $this->setTitle('Iniciar sesión · CEPTRI');
        $this->setView('index');
        $this->setData([
            'csrfToken' => CSRF_TOKEN,
            'error' => $_SESSION['auth_error'] ?? '',
        ]);
        unset($_SESSION['auth_error']);
        $this->render();
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido.';
            return;
        }

        $csrfToken = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
        if (!Csrf::validate($csrfToken, true)) {
            unset($_SESSION['csrf_token']);
            $_SESSION['auth_error'] = 'La sesión del formulario expiró. Intenta de nuevo.';
            Redirect::to('auth');
        }

        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($identifier === '' || $password === '') {
            $_SESSION['auth_error'] = 'Escribe tu usuario o correo y tu contraseña.';
            Redirect::to('auth');
        }

        $statement = Db::connect(true)->prepare(
            'SELECT u.id, u.username, u.password, u.email, u.nombre_completo, u.activo, r.slug AS role_slug
             FROM bee_users u
             INNER JOIN ceptri_user_roles ur ON ur.user_id = u.id
             INNER JOIN bee_roles r ON r.id = ur.role_id
             WHERE (u.username = :username OR u.email = :email)
             LIMIT 1'
        );
        $statement->execute([
            'username' => $identifier,
            'email' => $identifier,
        ]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int) $user['activo'] !== 1 ||
            !password_verify($password . AUTH_SALT, $user['password'])) {
            $_SESSION['auth_error'] = 'No se pudo iniciar sesión con esos datos.';
            Redirect::to('auth');
        }

        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            $rehash = Db::connect(true)->prepare('UPDATE bee_users SET password = :password WHERE id = :user_id');
            $rehash->execute([
                'password' => password_hash($password . AUTH_SALT, PASSWORD_BCRYPT),
                'user_id' => (int) $user['id'],
            ]);
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('No se pudo renovar el identificador de sesión.');
        }

        unset($user['password'], $user['activo']);
        unset($_SESSION['csrf_token']);
        Auth::login((int) $user['id'], $user);
        $this->redirectToWorkspace($user);
    }

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido.';
            return;
        }

        $csrfToken = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
        if (!Csrf::validate($csrfToken, true)) {
            http_response_code(400);
            echo 'El token de seguridad no es válido o expiró.';
            return;
        }

        $cookieParameters = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookieParameters['path'],
                'domain' => $cookieParameters['domain'],
                'secure' => $cookieParameters['secure'],
                'httponly' => $cookieParameters['httponly'],
                'samesite' => $cookieParameters['samesite'] ?? 'Lax',
            ]);
        }

        Auth::logout();
        Redirect::to('auth');
    }

    private function redirectToWorkspace(array $user): void
    {
        if (CeptriAccess::can($user, 'admin-access')) {
            Redirect::to('admin');
        }
        if (CeptriAccess::can($user, 'academic.assign')) {
            Redirect::to('academic');
        }
        if (CeptriAccess::can($user, 'records.review')) {
            Redirect::to('records');
        }
        if (CeptriAccess::can($user, 'finance.view')) {
            Redirect::to('finance');
        }
        if (CeptriAccess::can($user, 'students.manage')) {
            Redirect::to('students');
        }
        Redirect::to('admin');
    }
}
