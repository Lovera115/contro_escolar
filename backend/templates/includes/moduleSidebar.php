<?php
$activeModule = $d->controller === 'admin'
    ? match ($d->method) {
        'users' => 'users',
        'roles' => 'roles',
        default => 'admin',
    }
    : $d->controller;
$homePath = $d->moduleAccess->admin
    ? 'admin'
    : ($d->moduleAccess->academic
        ? 'academic'
        : ($d->moduleAccess->students
            ? 'students'
            : ($d->moduleAccess->records
                ? 'records'
                : 'finance')));
?>
<aside class="app-sidebar" aria-label="Navegación principal">
    <a class="sidebar-brand" href="<?= htmlspecialchars(URL . $homePath, ENT_QUOTES, 'UTF-8') ?>">
        <span class="sidebar-brand-mark" aria-hidden="true">C</span>
        <span><strong>CEPTRI</strong><small>Control escolar</small></span>
    </a>
    <div class="sidebar-user">
        <span class="sidebar-user-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($d->user->nombre_completo, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
        <span><strong><?= htmlspecialchars($d->user->nombre_completo, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($d->user->role_slug === 'control-escolar' ? 'Control Escolar' : ucfirst(str_replace('-', ' ', $d->user->role_slug)), ENT_QUOTES, 'UTF-8') ?></small></span>
    </div>
    <nav class="sidebar-nav">
        <span class="sidebar-section-label">MÓDULOS</span>
        <?php if ($d->moduleAccess->admin): ?>
            <a class="sidebar-link <?= $activeModule === 'admin' ? 'sidebar-link-active' : '' ?>" href="<?= htmlspecialchars(URL . 'admin', ENT_QUOTES, 'UTF-8') ?>" <?= $activeModule === 'admin' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon" aria-hidden="true">⌂</span><span>Resumen</span>
            </a>
        <?php endif; ?>
        <?php if ($d->moduleAccess->students): ?>
            <a class="sidebar-link <?= $activeModule === 'students' ? 'sidebar-link-active' : '' ?>" href="<?= htmlspecialchars(URL . 'students', ENT_QUOTES, 'UTF-8') ?>" <?= $activeModule === 'students' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon" aria-hidden="true">01</span><span>Padrón de alumnos</span>
            </a>
        <?php endif; ?>
        <?php if ($d->moduleAccess->academic): ?>
            <a class="sidebar-link <?= $activeModule === 'academic' ? 'sidebar-link-active' : '' ?>" href="<?= htmlspecialchars(URL . 'academic', ENT_QUOTES, 'UTF-8') ?>" <?= $activeModule === 'academic' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon" aria-hidden="true">02</span><span>Grupos y asignaciones</span>
            </a>
        <?php endif; ?>
        <?php if ($d->moduleAccess->records): ?>
            <a class="sidebar-link <?= $activeModule === 'records' ? 'sidebar-link-active' : '' ?>" href="<?= htmlspecialchars(URL . 'records', ENT_QUOTES, 'UTF-8') ?>" <?= $activeModule === 'records' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon" aria-hidden="true">03</span><span>Revisión de expedientes</span>
            </a>
        <?php endif; ?>
        <?php if ($d->moduleAccess->finance): ?>
            <a class="sidebar-link <?= $activeModule === 'finance' ? 'sidebar-link-active' : '' ?>" href="<?= htmlspecialchars(URL . 'finance', ENT_QUOTES, 'UTF-8') ?>" <?= $activeModule === 'finance' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon" aria-hidden="true">04</span><span>Finanzas</span>
            </a>
        <?php endif; ?>
        <?php if ($d->moduleAccess->users): ?>
            <span class="sidebar-section-label sidebar-admin-label">ADMINISTRACIÓN</span>
            <a class="sidebar-link <?= $activeModule === 'users' ? 'sidebar-link-active' : '' ?>" href="<?= htmlspecialchars(URL . 'admin/users', ENT_QUOTES, 'UTF-8') ?>" <?= $activeModule === 'users' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon" aria-hidden="true">05</span><span>Usuarios y cuentas</span>
            </a>
        <?php endif; ?>
        <?php if ($d->moduleAccess->roles): ?>
            <a class="sidebar-link <?= $activeModule === 'roles' ? 'sidebar-link-active' : '' ?>" href="<?= htmlspecialchars(URL . 'admin/roles', ENT_QUOTES, 'UTF-8') ?>" <?= $activeModule === 'roles' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link-icon" aria-hidden="true">06</span><span>Roles y permisos</span>
            </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <span>CEPTRI · Bee Framework</span>
    </div>
</aside>
