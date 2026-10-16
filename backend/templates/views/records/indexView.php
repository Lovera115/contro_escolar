<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Revisión de expedientes · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(CSS . 'admin.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="app-layout">
<?php require INCLUDES . 'moduleSidebar.php'; ?>
<main class="admin-shell">
    <header class="admin-header">
        <div>
            <span class="eyebrow">CONTROL ESCOLAR</span>
            <h1>Revisión de expedientes</h1>
            <p>Consulta, carga y valida los documentos de cada alumno.</p>
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

    <div class="records-layout">
        <section class="panel records-student-panel">
            <div class="panel-heading">
                <div><span class="eyebrow">PADRÓN</span><h2>Alumnos</h2></div>
                <span class="count-badge"><?= count($d->students) ?> alumnos</span>
            </div>
            <form class="records-search" method="get" action="<?= htmlspecialchars(URL . 'records', ENT_QUOTES, 'UTF-8') ?>">
                <label>Buscar alumno o matrícula
                    <input type="search" name="q" value="<?= htmlspecialchars($d->search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o matrícula">
                </label>
                <button class="button button-secondary" type="submit">Buscar</button>
            </form>
            <div class="records-student-list">
                <?php foreach ($d->students as $student): ?>
                    <?php $complete = (int) $student->approved_count === (int) $d->requirementCount && (int) $d->requirementCount > 0; ?>
                    <a class="records-student-card <?= $d->selectedStudent && (int) $d->selectedStudent->id === (int) $student->id ? 'records-student-card-active' : '' ?>" href="<?= htmlspecialchars(URL . 'records?student_id=' . (int) $student->id . ($d->search !== '' ? '&q=' . rawurlencode($d->search) : ''), ENT_QUOTES, 'UTF-8') ?>">
                        <strong><?= htmlspecialchars($student->nombre_completo, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($student->matricula, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($student->carrera, ENT_QUOTES, 'UTF-8') ?></span>
                        <small><?= (int) $student->approved_count ?>/<?= (int) $d->requirementCount ?> aprobados
                            <?php if ((int) $student->rejected_count > 0): ?> · <?= (int) $student->rejected_count ?> rechazados<?php endif; ?>
                            <?php if ((int) $student->review_count > 0): ?> · <?= (int) $student->review_count ?> por revisar<?php endif; ?>
                            <?= $complete ? ' · Expediente validado' : '' ?>
                        </small>
                    </a>
                <?php endforeach; ?>
                <?php if (count($d->students) === 0): ?>
                    <p class="empty-state">Aún no hay alumnos en el padrón.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="records-main">
            <?php if ($d->selectedStudent): ?>
                <section class="panel">
                    <div class="panel-heading records-heading">
                        <div>
                            <span class="eyebrow">EXPEDIENTE DIGITAL</span>
                            <h2><?= htmlspecialchars($d->selectedStudent->nombre_completo, ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="muted"><?= htmlspecialchars($d->selectedStudent->matricula, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($d->selectedStudent->carrera, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="count-badge"><?= (int) $d->approvedCount ?>/<?= (int) $d->requirementCount ?> validados</span>
                    </div>

                    <form class="records-upload-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars(URL . 'records/upload', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="student_id" value="<?= (int) $d->selectedStudent->id ?>">
                        <label>Tipo de documento
                            <select name="requirement_id" required>
                                <option value="">Selecciona requisito...</option>
                                <?php foreach ($d->requirements as $requirement): ?>
                                    <option value="<?= htmlspecialchars($requirement->id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($requirement->name, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Archivo · PDF, JPG o PNG · Máximo <?= htmlspecialchars(number_format((int) $d->maxUploadBytes / 1048576, 1), ENT_QUOTES, 'UTF-8') ?> MB
                            <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                        </label>
                        <button class="button button-primary" type="submit">Cargar para revisión</button>
                    </form>
                    <p class="muted records-private-note">Los archivos se guardan en almacenamiento privado y solo usuarios autorizados pueden consultarlos.</p>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div><span class="eyebrow">REQUISITOS</span><h2>Documentos del alumno</h2></div>
                    </div>
                    <div class="records-document-list">
                    <?php foreach ($d->requirements as $requirement): ?>
                        <?php $document = $requirement->document; ?>
                        <?php
                            $status = $document
                                ? match ($document->status) {
                                    'aprobado' => ['Aprobado', 'status-active'],
                                    'rechazado' => ['Rechazado', 'status-inactive'],
                                    default => ['En revisión', 'status-review'],
                                }
                                : ['Sin archivo', 'status-missing'];
                        ?>
                        <article class="records-document-card">
                            <div class="records-document-info">
                                <span class="records-document-icon" aria-hidden="true">PDF</span>
                                <div>
                                    <h3><?= htmlspecialchars($requirement->name, ENT_QUOTES, 'UTF-8') ?></h3>
                                    <?php if ($document): ?>
                                        <p><a href="<?= htmlspecialchars(URL . 'records/file?id=' . (int) $document->id, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($document->original_name, ENT_QUOTES, 'UTF-8') ?></a>
                                            · <?= number_format((int) $document->file_size / 1024, 0) ?> KB
                                            · <?= htmlspecialchars($document->created_at, ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="records-audit-line">Cargó: <?= htmlspecialchars($document->uploaded_by_name ?? 'Cuenta desactivada o eliminada', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($document->reviewed_by_name): ?> · Revisó: <?= htmlspecialchars($document->reviewed_by_name, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        </p>
                                        <?php if ($document->observation): ?>
                                            <p class="records-observation"><strong>Observación:</strong> <?= htmlspecialchars($document->observation, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p>El requisito aún no tiene un archivo cargado.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="status-badge <?= $status[1] ?>"><?= $status[0] ?></span>
                            <?php if ($document): ?>
                                <div class="records-document-actions">
                                    <form method="post" action="<?= htmlspecialchars(URL . 'records/decide', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="student_id" value="<?= (int) $d->selectedStudent->id ?>">
                                        <input type="hidden" name="document_id" value="<?= (int) $document->id ?>">
                                        <input type="hidden" name="decision" value="aprobado">
                                        <button class="button button-small button-primary" type="submit">Aprobar</button>
                                    </form>
                                    <form class="records-reject-form" method="post" action="<?= htmlspecialchars(URL . 'records/decide', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="student_id" value="<?= (int) $d->selectedStudent->id ?>">
                                        <input type="hidden" name="document_id" value="<?= (int) $document->id ?>">
                                        <input type="hidden" name="decision" value="rechazado">
                                        <input name="observation" maxlength="500" required placeholder="Motivo del rechazo">
                                        <button class="button button-small button-secondary" type="submit">Rechazar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    </div>
                </section>
            <?php else: ?>
                <section class="panel records-empty-panel">
                    <h2>Selecciona un alumno</h2>
                    <p class="muted">Elige un expediente de la lista para revisar sus requisitos o carga el primer documento desde el padrón cuando haya alumnos registrados.</p>
                </section>
            <?php endif; ?>
        </section>
    </div>
</main>
</div>
</body>
</html>
