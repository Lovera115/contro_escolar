<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Padrón de alumnos · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(CSS . 'admin.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="admin-shell">
    <header class="admin-header">
        <div>
            <a class="back-link" href="<?= htmlspecialchars(URL . 'admin', ENT_QUOTES, 'UTF-8') ?>">← Administración</a>
            <span class="eyebrow">CONTROL ESCOLAR</span>
            <h1>Padrón e inscripción de alumnos</h1>
            <p>Expedientes centrales e inscripciones organizadas por cuatrimestre.</p>
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

    <div class="student-registry-layout">
        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">EXPEDIENTES</span><h2>Alumnos registrados</h2></div>
                <span class="count-badge"><?= count($d->students) ?> alumnos</span>
            </div>
            <form class="student-filter-form" method="get" action="<?= htmlspecialchars(URL . 'students', ENT_QUOTES, 'UTF-8') ?>">
                <label>Buscar por matrícula o nombre
                    <input type="search" name="q" value="<?= htmlspecialchars($d->search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 2024-0312">
                </label>
                <label>Estado académico
                    <select name="status">
                        <option value="todos" <?= $d->statusFilter === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="activo" <?= $d->statusFilter === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="baja" <?= $d->statusFilter === 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="egresado" <?= $d->statusFilter === 'egresado' ? 'selected' : '' ?>>Egresado</option>
                    </select>
                </label>
                <button class="button button-secondary" type="submit">Filtrar</button>
                <a class="button button-secondary" href="<?= htmlspecialchars(URL . 'students', ENT_QUOTES, 'UTF-8') ?>">Limpiar</a>
            </form>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Matrícula / alumno</th><th>Carrera</th><th>Cuatrimestre</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($d->students as $student): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($student->matricula, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($student->nombre_completo, ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars($student->email, ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars($student->carrera, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($student->ultimo_cuatrimestre_label ?? 'Sin inscripción', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="status-badge <?= $student->estado === 'activo' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars(ucfirst($student->estado), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><a class="button button-small button-secondary" href="<?= htmlspecialchars(URL . 'students?edit=' . (int) $student->id, ENT_QUOTES, 'UTF-8') ?>">Expediente</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($d->students) === 0): ?>
                        <tr><td colspan="5" class="empty-state">No hay alumnos que coincidan con los filtros.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">EXPEDIENTE</span><h2><?= $d->selectedStudent ? 'Editar alumno' : 'Registrar alumno' ?></h2></div>
            </div>
            <form class="stack-form" method="post" action="<?= htmlspecialchars(URL . 'students/save', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($d->selectedStudent): ?>
                    <input type="hidden" name="student_id" value="<?= (int) $d->selectedStudent->id ?>">
                <?php endif; ?>
                <label>Matrícula
                    <input name="matricula" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9-]{2,31}" required placeholder="Ej. 2024-0312" value="<?= htmlspecialchars($d->selectedStudent->matricula ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Nombre completo
                    <input name="nombre_completo" maxlength="160" required value="<?= htmlspecialchars($d->selectedStudent->nombre_completo ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Correo electrónico
                    <input type="email" name="email" maxlength="254" required value="<?= htmlspecialchars($d->selectedStudent->email ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Teléfono (opcional)
                    <input type="tel" name="telefono" maxlength="30" value="<?= htmlspecialchars($d->selectedStudent->telefono ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Carrera
                    <select name="carrera" required>
                        <option value="">Selecciona una carrera...</option>
                        <?php foreach ($d->careers as $career): ?>
                            <option value="<?= htmlspecialchars($career, ENT_QUOTES, 'UTF-8') ?>" <?= ($d->selectedStudent->carrera ?? '') === $career ? 'selected' : '' ?>><?= htmlspecialchars($career, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Estado académico
                    <select name="estado" required>
                        <option value="activo" <?= ($d->selectedStudent->estado ?? 'activo') === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="baja" <?= ($d->selectedStudent->estado ?? '') === 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="egresado" <?= ($d->selectedStudent->estado ?? '') === 'egresado' ? 'selected' : '' ?>>Egresado</option>
                    </select>
                </label>
                <?php if (!$d->selectedStudent): ?>
                    <label>Cuatrimestre de inscripción
                        <select name="period_key" required>
                            <?php foreach ($d->periodOptions as $period): ?>
                                <option value="<?= htmlspecialchars($period->key, ENT_QUOTES, 'UTF-8') ?>" <?= $period->key === $d->defaultPeriod ? 'selected' : '' ?>><?= htmlspecialchars($period->label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Se crea el expediente y su primera inscripción en una sola operación.</small>
                    </label>
                <?php endif; ?>
                <button class="button button-primary" type="submit"><?= $d->selectedStudent ? 'Guardar cambios' : 'Registrar e inscribir' ?></button>
                <?php if ($d->selectedStudent): ?>
                    <a class="cancel-link" href="<?= htmlspecialchars(URL . 'students', ENT_QUOTES, 'UTF-8') ?>">Cancelar edición</a>
                <?php endif; ?>
            </form>
        </section>
    </div>

    <?php if ($d->selectedStudent): ?>
        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">TRAYECTORIA</span><h2>Inscripciones · <?= htmlspecialchars($d->selectedStudent->matricula, ENT_QUOTES, 'UTF-8') ?></h2></div>
                <span class="count-badge"><?= count($d->enrollments) ?> cuatrimestres</span>
            </div>
            <?php if ($d->selectedStudent->estado === 'activo' && count($d->availablePeriods) > 0): ?>
                <form class="inline-form student-enroll-form" method="post" action="<?= htmlspecialchars(URL . 'students/enroll', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="student_id" value="<?= (int) $d->selectedStudent->id ?>">
                    <label>Inscribir a otro cuatrimestre
                        <select name="period_key" required>
                            <?php foreach ($d->availablePeriods as $period): ?>
                                <option value="<?= htmlspecialchars($period->key, ENT_QUOTES, 'UTF-8') ?>" <?= $period->key === $d->defaultPeriod ? 'selected' : '' ?>><?= htmlspecialchars($period->label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit">Registrar inscripción</button>
                </form>
            <?php elseif ($d->selectedStudent->estado !== 'activo'): ?>
                <p class="muted">El alumno no está activo; no se pueden agregar nuevas inscripciones.</p>
            <?php endif; ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Cuatrimestre</th><th>Estado</th><th>Fecha de registro</th></tr></thead>
                    <tbody>
                    <?php foreach ($d->enrollments as $enrollment): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($enrollment->period_label, ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><span class="status-badge status-active"><?= htmlspecialchars(ucfirst($enrollment->estado), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($enrollment->created_at, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($d->enrollments) === 0): ?>
                        <tr><td colspan="3" class="empty-state">Aún no hay inscripciones registradas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
