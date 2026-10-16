<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Administración · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(CSS . 'admin.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="app-layout">
        <?php require INCLUDES . 'moduleSidebar.php'; ?>
        <main class="admin-shell">
        <header class="admin-header">
            <div>
                <span class="eyebrow">SISTEMA DE CONTROL ESCOLAR</span>
                <h1>Administración CEPTRI</h1>
                <p>Sesión activa: <?= htmlspecialchars($d->user->nombre_completo, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <form method="post" action="<?= htmlspecialchars(URL . 'auth/logout', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button class="button button-secondary" type="submit">Cerrar sesión</button>
            </form>
        </header>

        <section class="metric-grid" aria-label="Resumen administrativo">
            <article class="metric-card"><span>Cuentas registradas</span><strong><?= (int) $d->summary->total_users ?></strong></article>
            <article class="metric-card"><span>Cuentas activas</span><strong><?= (int) $d->summary->active_users ?></strong></article>
            <article class="metric-card"><span>Roles disponibles</span><strong><?= (int) $d->summary->total_roles ?></strong></article>
            <article class="metric-card"><span>Permisos configurados</span><strong><?= (int) $d->summary->total_permissions ?></strong></article>
        </section>

        <nav class="admin-nav" aria-label="Administración">
            <a class="nav-card" href="<?= htmlspecialchars(URL . 'admin/users', ENT_QUOTES, 'UTF-8') ?>">
                <span class="nav-icon" aria-hidden="true">01</span>
                <span><strong>Usuarios y cuentas</strong><small>Crear, editar, activar o asignar roles.</small></span>
                <span aria-hidden="true">→</span>
            </a>
            <a class="nav-card" href="<?= htmlspecialchars(URL . 'admin/roles', ENT_QUOTES, 'UTF-8') ?>">
                <span class="nav-icon" aria-hidden="true">02</span>
                <span><strong>Roles y permisos</strong><small>Configurar accesos por módulo.</small></span>
                <span aria-hidden="true">→</span>
            </a>
            <?php if ($d->canManageStudents): ?>
                <a class="nav-card" href="<?= htmlspecialchars(URL . 'students', ENT_QUOTES, 'UTF-8') ?>">
                    <span class="nav-icon" aria-hidden="true">03</span>
                    <span><strong>Padrón e inscripción</strong><small>Expedientes e inscripciones por cuatrimestre.</small></span>
                    <span aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
            <?php if ($d->canManageAcademic): ?>
                <a class="nav-card" href="<?= htmlspecialchars(URL . 'academic', ENT_QUOTES, 'UTF-8') ?>">
                    <span class="nav-icon" aria-hidden="true">04</span>
                    <span><strong>Grupos y asignación académica</strong><small>Organizar materias, docentes e inscripciones.</small></span>
                    <span aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
            <?php if ($d->canReviewRecords): ?>
                <a class="nav-card" href="<?= htmlspecialchars(URL . 'records', ENT_QUOTES, 'UTF-8') ?>">
                    <span class="nav-icon" aria-hidden="true">05</span>
                    <span><strong>Revisión de expedientes</strong><small>Validar documentos de inscripción.</small></span>
                    <span aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
            <?php if ($d->canViewFinance): ?>
                <a class="nav-card" href="<?= htmlspecialchars(URL . 'finance', ENT_QUOTES, 'UTF-8') ?>">
                    <span class="nav-icon" aria-hidden="true">06</span>
                    <span><strong>Finanzas</strong><small>Estados de cuenta y pagos por cuatrimestre.</small></span>
                    <span aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
        </nav>

        <section class="notice-card">
            <h2>Migración gradual</h2>
            <p>La administración, el RBAC, el padrón y las asignaciones académicas ya se guardan en MariaDB desde el servidor. Las pantallas originales permanecen disponibles mientras se migran las demás funciones.</p>
        </section>
        </main>
    </div>
</body>
</html>
