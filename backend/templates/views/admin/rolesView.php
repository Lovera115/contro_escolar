<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Roles y permisos · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(CSS . 'admin.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="app-layout">
<?php require INCLUDES . 'moduleSidebar.php'; ?>
<main class="admin-shell">
    <header class="admin-header">
        <div>
            <a class="back-link" href="<?= htmlspecialchars(URL . 'admin', ENT_QUOTES, 'UTF-8') ?>">← Administración</a>
            <span class="eyebrow">CONTROL DE ACCESO</span>
            <h1>Roles y permisos</h1>
            <p>La autorización se valida en el backend antes de cada operación.</p>
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

    <section class="panel">
        <div class="panel-heading">
            <div><span class="eyebrow">ROLES</span><h2>Crear un rol adicional</h2></div>
        </div>
        <form class="inline-form" method="post" action="<?= htmlspecialchars(URL . 'admin/save-role', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>Nombre del rol<input name="nombre" maxlength="100" required placeholder="Ej. Coordinación"></label>
            <label>Identificador<input name="slug" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" required placeholder="Ej. coordinacion"></label>
            <button class="button button-primary" type="submit">Crear rol</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div><span class="eyebrow">MATRIZ RBAC</span><h2>Acceso por módulo y operación</h2></div>
            <span class="count-badge"><?= count($d->permissions) ?> permisos</span>
        </div>
        <p class="muted">El administrador tiene acceso global mediante <code>admin-access</code>; ese permiso está protegido y no se puede quitar desde la matriz. Las capacidades quedan limitadas a lo que realmente ejecuta cada módulo del backend.</p>
        <?php foreach ($d->roles as $role): ?>
            <form id="role-form-<?= htmlspecialchars($role->slug, ENT_QUOTES, 'UTF-8') ?>" method="post" action="<?= htmlspecialchars(URL . 'admin/save-role-permissions', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="role_slug" value="<?= htmlspecialchars($role->slug, ENT_QUOTES, 'UTF-8') ?>">
            </form>
        <?php endforeach; ?>
        <div class="table-scroll">
            <table class="admin-table permission-matrix">
                <thead>
                    <tr>
                        <th>Permiso / operación</th>
                        <?php foreach ($d->roles as $role): ?>
                            <th>
                                <strong><?= htmlspecialchars($role->nombre, ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= (int) $role->assigned_users ?> cuentas</small>
                                <?php if ($role->slug === 'administrador'): ?>
                                    <span class="matrix-locked">Acceso global Bee</span>
                                <?php else: ?>
                                    <button class="button button-small button-secondary" type="submit" form="role-form-<?= htmlspecialchars($role->slug, ENT_QUOTES, 'UTF-8') ?>">Guardar columna</button>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d->permissions as $permission): ?>
                    <tr>
                        <th scope="row">
                            <strong><?= htmlspecialchars($permission->nombre, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($permission->descripcion, ENT_QUOTES, 'UTF-8') ?></small>
                            <code><?= htmlspecialchars($permission->slug, ENT_QUOTES, 'UTF-8') ?></code>
                        </th>
                        <?php foreach ($d->roles as $role): ?>
                            <?php
                                $isAdmin = $role->slug === 'administrador';
                                $granted = $isAdmin || isset($d->rolePermissions->{$role->slug}->{$permission->slug});
                            ?>
                            <td class="permission-cell">
                                <?php if ($isAdmin): ?>
                                    <span class="permission-global" aria-label="Concedido por administrador global">✓</span>
                                <?php else: ?>
                                    <label class="permission-check" aria-label="<?= htmlspecialchars($permission->nombre . ' para ' . $role->nombre, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="checkbox" name="permissions[]" value="<?= (int) $permission->id ?>" form="role-form-<?= htmlspecialchars($role->slug, ENT_QUOTES, 'UTF-8') ?>" <?= $granted ? 'checked' : '' ?>>
                                    </label>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</body>
</html>
