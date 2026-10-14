<?php

final class CeptriAccess
{
    public static function currentUser(): ?array
    {
        if (!Auth::validate()) {
            return null;
        }

        $sessionUserId = $_SESSION['user_session']['id'] ?? null;
        if (!is_numeric($sessionUserId)) {
            return null;
        }

        $statement = Db::connect(true)->prepare(
            'SELECT u.id, u.username, u.email, u.nombre_completo, u.activo, r.slug AS role_slug
             FROM bee_users u
             INNER JOIN ceptri_user_roles ur ON ur.user_id = u.id
             INNER JOIN bee_roles r ON r.id = ur.role_id
             WHERE u.id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => (int) $sessionUserId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int) $user['activo'] !== 1) {
            Auth::logout();
            return null;
        }

        $override = Db::connect(true)->prepare(
            'SELECT up.allowed
             FROM ceptri_user_permissions up
             INNER JOIN bee_permisos p ON p.id = up.permission_id
             WHERE up.user_id = :user_id AND p.slug = :permission
             LIMIT 1'
        );
        $override->execute([
            'user_id' => (int) $user['id'],
            'permission' => 'admin-access',
        ]);
        $adminOverride = $override->fetchColumn();
        if ($adminOverride !== false && (int) $adminOverride !== 1) {
            Auth::logout();
            return null;
        }

        return $user;
    }

    public static function can(array $user, string $permission): bool
    {
        $statement = Db::connect(true)->prepare(
            'SELECT allowed
             FROM ceptri_user_permissions up
             INNER JOIN bee_permisos p ON p.id = up.permission_id
             WHERE up.user_id = :user_id AND p.slug = :permission
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => (int) $user['id'],
            'permission' => $permission,
        ]);
        $override = $statement->fetchColumn();
        if ($override !== false) {
            return (int) $override === 1;
        }

        return (new BeeRoleManager($user['role_slug']))->can($permission);
    }

    public static function requirePermission(string $permission): array
    {
        $user = self::currentUser();
        if ($user === null) {
            Redirect::to('auth');
        }

        if (!self::can($user, $permission)) {
            http_response_code(403);
            View::render('forbidden', [
                'title' => 'Acceso denegado',
                'user' => $user,
                'permission' => $permission,
            ]);
            exit;
        }

        return $user;
    }
}
