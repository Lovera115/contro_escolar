<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Grupos académicos · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(CSS . 'admin.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="app-layout">
<?php require INCLUDES . 'moduleSidebar.php'; ?>
<main class="admin-shell">
    <header class="admin-header">
        <div>
            <?php if ($d->moduleAccess->students): ?>
                <a class="back-link" href="<?= htmlspecialchars(URL . 'students', ENT_QUOTES, 'UTF-8') ?>">← Padrón e inscripción</a>
            <?php endif; ?>
            <span class="eyebrow">CONTROL ESCOLAR</span>
            <h1>Grupos y asignación académica</h1>
            <p>Organiza grupos, docentes y materias por cuatrimestre.</p>
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
            <div><span class="eyebrow">CUATRIMESTRE</span><h2>Grupos existentes</h2></div>
            <form class="period-filter" method="get" action="<?= htmlspecialchars(URL . 'academic', ENT_QUOTES, 'UTF-8') ?>">
                <label>Consultar periodo
                    <select name="period" onchange="this.form.submit()">
                        <?php foreach ($d->periodOptions as $period): ?>
                            <option value="<?= htmlspecialchars($period->key, ENT_QUOTES, 'UTF-8') ?>" <?= $period->key === $d->period ? 'selected' : '' ?>><?= htmlspecialchars($period->label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </div>
        <?php if (count($d->groups) > 0): ?>
            <div class="academic-group-cards">
                <?php foreach ($d->groups as $group): ?>
                    <a class="academic-group-card <?= $d->selectedGroup && (int) $d->selectedGroup->id === (int) $group->id ? 'academic-group-card-selected' : '' ?>" href="<?= htmlspecialchars(URL . 'academic?period=' . rawurlencode($group->period_key) . '&group_id=' . (int) $group->id, ENT_QUOTES, 'UTF-8') ?>">
                        <strong><?= htmlspecialchars($group->group_name, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($group->career, ENT_QUOTES, 'UTF-8') ?></span>
                        <small><?= (int) $group->subject_count ?> materias · <?= (int) $group->student_count ?> inscripciones</small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">No hay grupos registrados para <?= htmlspecialchars($d->periodLabel, ENT_QUOTES, 'UTF-8') ?>. Puedes crear uno desde el formulario.</p>
        <?php endif; ?>
    </section>

    <div class="academic-admin-grid">
        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">CATÁLOGO</span><h2>Crear grupo</h2></div>
            </div>
            <form class="stack-form" method="post" action="<?= htmlspecialchars(URL . 'academic/save-group', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label>Carrera
                    <select name="career" id="academic-career" required>
                        <option value="">Selecciona carrera...</option>
                        <?php foreach ($d->careers as $career): ?>
                            <option value="<?= htmlspecialchars($career, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($career, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Cuatrimestre
                    <select name="period_key" required>
                        <?php foreach ($d->periodOptions as $period): ?>
                            <option value="<?= htmlspecialchars($period->key, ENT_QUOTES, 'UTF-8') ?>" <?= $period->key === $d->period ? 'selected' : '' ?>><?= htmlspecialchars($period->label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Nombre del grupo
                    <input name="group_name" maxlength="80" required placeholder="Ej. 1º A">
                </label>
                <fieldset class="academic-subject-fieldset">
                    <legend>Materias impartidas</legend>
                    <div id="academic-subject-picker" class="academic-subject-picker">
                        <span class="muted">Selecciona una carrera para consultar sus materias.</span>
                    </div>
                </fieldset>
                <button class="button button-primary" type="submit">Crear grupo y materias</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">DOCENTES</span><h2>Asignaciones registradas</h2></div>
            </div>
            <?php if (count($d->groups) === 0): ?>
                <p class="empty-state">Registra un grupo para asignar docentes.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="admin-table">
                        <thead><tr><th>Grupo</th><th>Carrera</th><th>Materias</th><th>Docentes asignados</th></tr></thead>
                        <tbody>
                        <?php foreach ($d->groups as $group): ?>
                            <tr>
                                <td><a href="<?= htmlspecialchars(URL . 'academic?period=' . rawurlencode($group->period_key) . '&group_id=' . (int) $group->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($group->group_name, ENT_QUOTES, 'UTF-8') ?></a></td>
                                <td><?= htmlspecialchars($group->career, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) $group->subject_count ?></td>
                                <td><?= (int) $group->teacher_count ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($d->selectedGroup): ?>
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <span class="eyebrow">DETALLE DEL GRUPO</span>
                    <h2><?= htmlspecialchars($d->selectedGroup->group_name, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($d->selectedGroup->period_label, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="muted"><?= htmlspecialchars($d->selectedGroup->career, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <a class="button button-secondary" href="<?= htmlspecialchars(URL . 'academic/report?group_id=' . (int) $d->selectedGroup->id, ENT_QUOTES, 'UTF-8') ?>">Descargar reporte PDF</a>
            </div>

            <h3>Materias y docentes</h3>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Materia</th><th>Docente asignado</th><th>Asignar o cambiar docente</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($d->groupSubjects as $subject): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($subject->subject_name, ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($subject->teacher_name ?? 'Sin docente asignado', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if (count($d->teachers) > 0): ?>
                                    <form class="academic-inline-action" method="post" action="<?= htmlspecialchars(URL . 'academic/assign-teacher', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="group_id" value="<?= (int) $d->selectedGroup->id ?>">
                                        <input type="hidden" name="group_subject_id" value="<?= (int) $subject->group_subject_id ?>">
                                        <input type="hidden" name="period_key" value="<?= htmlspecialchars($d->selectedGroup->period_key, ENT_QUOTES, 'UTF-8') ?>">
                                        <select name="teacher_id" required aria-label="Docente para <?= htmlspecialchars($subject->subject_name, ENT_QUOTES, 'UTF-8') ?>">
                                            <option value="">Seleccionar docente...</option>
                                            <?php foreach ($d->teachers as $teacher): ?>
                                                <option value="<?= (int) $teacher->id ?>" <?= (int) ($subject->teacher_user_id ?? 0) === (int) $teacher->id ? 'selected' : '' ?>><?= htmlspecialchars($teacher->nombre_completo, ENT_QUOTES, 'UTF-8') ?> (@<?= htmlspecialchars($teacher->username, ENT_QUOTES, 'UTF-8') ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="button button-small button-secondary" type="submit">Guardar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">No hay cuentas activas con rol Docente.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($subject->teacher_user_id): ?>
                                    <form method="post" action="<?= htmlspecialchars(URL . 'academic/remove-teacher', ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('¿Quitar la asignación docente de esta materia?')">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= (int) $subject->group_subject_id ?>">
                                        <input type="hidden" name="group_id" value="<?= (int) $d->selectedGroup->id ?>">
                                        <button class="button button-small button-secondary" type="submit">Quitar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($d->groupSubjects) === 0): ?>
                        <tr><td colspan="4" class="empty-state">El grupo no tiene materias configuradas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="academic-admin-grid academic-roster-grid">
                <section>
                    <h3>Inscribir alumno a materias</h3>
                    <?php if (count($d->eligibleStudents) > 0 && count($d->groupSubjects) > 0): ?>
                        <form class="stack-form academic-enroll-form" method="post" action="<?= htmlspecialchars(URL . 'academic/enroll-students', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="group_id" value="<?= (int) $d->selectedGroup->id ?>">
                            <input type="hidden" name="period_key" value="<?= htmlspecialchars($d->selectedGroup->period_key, ENT_QUOTES, 'UTF-8') ?>">
                            <label>Alumno inscrito en el cuatrimestre
                                <select name="enrollment_id" required>
                                    <option value="">Selecciona alumno...</option>
                                    <?php foreach ($d->eligibleStudents as $student): ?>
                                        <option value="<?= (int) $student->enrollment_id ?>"><?= htmlspecialchars($student->matricula . ' · ' . $student->nombre_completo, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <fieldset class="academic-subject-fieldset">
                                <legend>Selecciona una o varias materias</legend>
                                <?php foreach ($d->groupSubjects as $subject): ?>
                                    <label class="academic-subject-option">
                                        <input type="checkbox" name="group_subject_ids[]" value="<?= (int) $subject->group_subject_id ?>">
                                        <span><?= htmlspecialchars($subject->subject_name, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <button class="button button-primary" type="submit">Registrar materias</button>
                        </form>
                    <?php else: ?>
                        <p class="empty-state"><?= count($d->groupSubjects) === 0 ? 'Agrega materias al crear un grupo.' : 'No hay alumnos activos inscritos a esta carrera y cuatrimestre.' ?></p>
                    <?php endif; ?>
                </section>

                <section>
                    <h3>Alumnos inscritos <span class="count-badge"><?= count($d->groupStudents) ?></span></h3>
                    <div class="table-scroll">
                        <table class="admin-table">
                            <thead><tr><th>Matrícula</th><th>Nombre</th><th>Materias</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($d->groupStudents as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student->matricula, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($student->nombre_completo, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($student->subjects ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php foreach ($student->assignments as $assignment): ?>
                                            <form class="academic-remove-form" method="post" action="<?= htmlspecialchars(URL . 'academic/remove-student-subject', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id" value="<?= (int) $assignment->id ?>">
                                                <input type="hidden" name="group_id" value="<?= (int) $d->selectedGroup->id ?>">
                                                <button class="button button-small button-secondary" type="submit" aria-label="Quitar <?= htmlspecialchars($assignment->subject_name, ENT_QUOTES, 'UTF-8') ?>">Quitar <?= htmlspecialchars($assignment->subject_name, ENT_QUOTES, 'UTF-8') ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($d->groupStudents) === 0): ?>
                                <tr><td colspan="4" class="empty-state">Aún no hay alumnos inscritos en materias.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </section>
    <?php endif; ?>
</main>
</div>

<script>
const academicSubjects = <?= json_encode($d->subjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const careerSelect = document.getElementById('academic-career');
const subjectPicker = document.getElementById('academic-subject-picker');
careerSelect.addEventListener('change', () => {
    const subjects = academicSubjects.filter(subject => subject.career === careerSelect.value);
    subjectPicker.innerHTML = subjects.length
        ? subjects.map(subject => `<label class="academic-subject-option"><input type="checkbox" name="subject_ids[]" value="${Number(subject.id)}"><span>${escapeSubject(subject.subject_name)}</span></label>`).join('')
        : '<span class="muted">No hay materias disponibles para esta carrera.</span>';
});
function escapeSubject(value) {
    const element = document.createElement('span');
    element.textContent = value;
    return element.innerHTML;
}
</script>
</body>
</html>
