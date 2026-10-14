<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Usuarios · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(CSS . 'admin.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="admin-shell">
    <header class="admin-header">
        <div>
            <a class="back-link" href="<?= htmlspecialchars(URL . 'admin', ENT_QUOTES, 'UTF-8') ?>">← Administración</a>
            <span class="eyebrow">ACCESO Y CUENTAS</span>
            <h1>Gestión de usuarios</h1>
            <p>Administra cuentas, rol principal y excepciones individuales de acceso.</p>
        </div>
        <form method="post" action="<?= htmlspecialchars(URL . 'auth/logout', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button class="button button-secondary" type="submit">Cerrar sesión</button>
        </form>
    </header>

    <?php if (!empty($d->flash->message)): ?>
        <div class="flash flash-<?= htmlspecialchars($d->flash->type ?? 'info', ENT_QUOTES, 'UTF-8') ?>" role="status">
            <?= htmlspecialchars($d->flash->message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="admin-layout">
        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">DIRECTORIO</span><h2>Cuentas del sistema</h2></div>
                <span class="count-badge"><?= count($d->users) ?> usuarios</span>
            </div>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($d->users as $account): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($account->nombre_completo, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($account->email, ENT_QUOTES, 'UTF-8') ?><br>@<?= htmlspecialchars($account->username, ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><span class="role-badge"><?= htmlspecialchars($account->role_name ?? 'Sin rol', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="status-badge <?= (int) $account->activo === 1 ? 'status-active' : 'status-inactive' ?>"><?= (int) $account->activo === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td><a class="button button-small button-secondary" href="<?= htmlspecialchars(URL . 'admin/users?edit=' . (int) $account->id, ENT_QUOTES, 'UTF-8') ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($d->users) === 0): ?>
                        <tr><td colspan="4" class="empty-state">Todavía no hay cuentas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">CUENTA</span><h2><?= $d->selectedUser ? 'Editar usuario' : 'Crear usuario' ?></h2></div>
            </div>
            <form class="stack-form" method="post" action="<?= htmlspecialchars(URL . 'admin/manage-users', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($d->selectedUser): ?><input type="hidden" name="user_id" value="<?= (int) $d->selectedUser->id ?>"><?php endif; ?>
                <label>Nombre completo<input name="nombre_completo" maxlength="160" required value="<?= htmlspecialchars($d->selectedUser->nombre_completo ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Usuario<input name="username" maxlength="64" pattern="[A-Za-z0-9._-]{3,64}" required value="<?= htmlspecialchars($d->selectedUser->username ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Correo electrónico<input type="email" name="email" maxlength="254" required value="<?= htmlspecialchars($d->selectedUser->email ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Rol principal
                    <select name="role_slug" required>
                        <?php foreach ($d->roles as $role): ?>
                            <option value="<?= htmlspecialchars($role->slug, ENT_QUOTES, 'UTF-8') ?>" <?= ($d->selectedUser->role_slug ?? '') === $role->slug ? 'selected' : '' ?>><?= htmlspecialchars($role->nombre, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= $d->selectedUser ? 'Nueva contraseña (opcional)' : 'Contraseña temporal' ?>
                    <input type="password" name="password" minlength="12" autocomplete="new-password" <?= $d->selectedUser ? '' : 'required' ?>>
                    <small><?= $d->selectedUser ? 'Déjala vacía para conservar la contraseña actual.' : 'Mínimo 12 caracteres.' ?></small>
                </label>
                <label class="check-row"><input type="checkbox" name="activo" value="1" <?= !$d->selectedUser || (int) $d->selectedUser->activo === 1 ? 'checked' : '' ?>> Cuenta activa</label>
                <button class="button button-primary" type="submit"><?= $d->selectedUser ? 'Guardar cambios' : 'Crear cuenta' ?></button>
                <?php if ($d->selectedUser): ?><a class="cancel-link" href="<?= htmlspecialchars(URL . 'admin/users', ENT_QUOTES, 'UTF-8') ?>">Cancelar edición</a><?php endif; ?>
            </form>
        </section>
    </div>

    <?php if ($d->selectedUser): ?>
        <section class="panel overrides-panel">
            <div class="panel-heading">
                <div><span class="eyebrow">EXCEPCIONES RBAC</span><h2>Permisos individuales · <?= htmlspecialchars($d->selectedUser->nombre_completo, ENT_QUOTES, 'UTF-8') ?></h2></div>
            </div>
            <p class="muted">“Heredar del rol” respeta la matriz global. Las excepciones se evalúan en el servidor; el permiso global de administración no se puede conceder desde esta pantalla.</p>
            <form method="post" action="<?= htmlspecialchars(URL . 'admin/save-user-overrides', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="user_id" value="<?= (int) $d->selectedUser->id ?>">
                <div class="table-scroll">
                    <table class="admin-table">
                        <thead><tr><th>Permiso</th><th>Identificador</th><th>Excepción para esta cuenta</th></tr></thead>
                        <tbody>
                        <?php foreach ($d->permissions as $permission): ?>
                            <?php $override = $d->overrides->{$permission->slug} ?? 'inherit'; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($permission->nombre, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($permission->descripcion, ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><code><?= htmlspecialchars($permission->slug, ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><select name="overrides[<?= htmlspecialchars($permission->slug, ENT_QUOTES, 'UTF-8') ?>]">
                                    <option value="inherit" <?= $override === 'inherit' ? 'selected' : '' ?>>Heredar del rol</option>
                                    <option value="allow" <?= $override === 'allow' ? 'selected' : '' ?>>Conceder</option>
                                    <option value="deny" <?= $override === 'deny' ? 'selected' : '' ?>>Denegar</option>
                                </select></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-actions"><button class="button button-primary" type="submit">Guardar excepciones</button></div>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
