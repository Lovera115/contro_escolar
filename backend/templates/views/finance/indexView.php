<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Finanzas · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(CSS . 'admin.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="app-layout">
<?php require INCLUDES . 'moduleSidebar.php'; ?>
<main class="admin-shell">
    <header class="admin-header">
        <div>
            <span class="eyebrow">FINANZAS</span>
            <h1>Estados de cuenta y pagos</h1>
            <p>Consulta adeudos por alumno y cuatrimestre; registra y audita los pagos.</p>
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
            <div><span class="eyebrow">CUATRIMESTRES INSCRITOS</span><h2>Cuentas de alumnos</h2></div>
            <span class="count-badge"><?= count($d->accounts) ?> cuentas</span>
        </div>
        <form class="finance-filter" method="get" action="<?= htmlspecialchars(URL . 'finance', ENT_QUOTES, 'UTF-8') ?>">
            <label>Buscar alumno, matrícula o folio
                <input type="search" name="q" value="<?= htmlspecialchars($d->search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre, matrícula o folio">
            </label>
            <label>Cuatrimestre
                <select name="period">
                    <option value="todos" <?= $d->period === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($d->periodOptions as $period): ?>
                        <option value="<?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?>" <?= $d->period === $period ? 'selected' : '' ?>>
                            <?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button-secondary" type="submit">Buscar</button>
            <a class="button button-secondary" href="<?= htmlspecialchars(URL . 'finance', ENT_QUOTES, 'UTF-8') ?>">Limpiar</a>
        </form>
        <div class="table-scroll">
            <table class="admin-table">
                <thead><tr><th>Alumno / matrícula</th><th>Carrera</th><th>Cuatrimestre</th><th>Estado</th><th>Saldo pendiente</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($d->accounts as $account): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($account->nombre_completo, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($account->matricula, ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= htmlspecialchars($account->carrera, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($account->period_key, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status-badge <?= $account->status === 'al_corriente' ? 'status-active' : ($account->status === 'baja' ? 'status-inactive' : 'status-review') ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $account->status)), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><strong>$<?= number_format((float) $account->balance, 2) ?></strong><small>Cuota: $<?= number_format((float) $account->amount_due, 2) ?> · Pagado: $<?= number_format((float) $account->total_paid, 2) ?></small></td>
                        <td><a class="button button-small button-secondary" href="<?= htmlspecialchars(URL . 'finance?account_id=' . (int) $account->id, ENT_QUOTES, 'UTF-8') ?>">Estado de cuenta</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($d->accounts) === 0): ?>
                    <tr><td colspan="6" class="empty-state">No hay cuentas que coincidan. Las cuentas se crean con cada inscripción cuatrimestral.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($d->selectedAccount): ?>
        <section class="panel">
            <div class="panel-heading finance-detail-heading">
                <div>
                    <span class="eyebrow">CUENTA DEL CUATRIMESTRE <?= htmlspecialchars($d->selectedAccount->period_key, ENT_QUOTES, 'UTF-8') ?></span>
                    <h2><?= htmlspecialchars($d->selectedAccount->nombre_completo, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="muted"><?= htmlspecialchars($d->selectedAccount->matricula, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($d->selectedAccount->carrera, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php if ($d->canExport): ?>
                    <a class="button button-secondary" href="<?= htmlspecialchars(URL . 'finance/history-pdf?student_id=' . (int) $d->selectedAccount->student_id, ENT_QUOTES, 'UTF-8') ?>">Descargar historial PDF</a>
                <?php endif; ?>
            </div>

            <div class="finance-summary-grid">
                <article><span>Cargos configurados</span><strong>$<?= number_format((float) $d->selectedAccount->amount_due, 2) ?></strong></article>
                <article><span>Pagos vigentes</span><strong>$<?= number_format((float) $d->selectedAccount->total_paid, 2) ?></strong></article>
                <article><span>Saldo pendiente</span><strong>$<?= number_format((float) $d->selectedAccount->balance, 2) ?></strong></article>
                <article><span>Estado</span><strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $d->selectedAccount->status)), ENT_QUOTES, 'UTF-8') ?></strong></article>
            </div>

            <?php if ($d->canEditPayments): ?>
                <form class="finance-account-form" method="post" action="<?= htmlspecialchars(URL . 'finance/save-account', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="account_id" value="<?= (int) $d->selectedAccount->id ?>">
                    <label>Cargos del cuatrimestre (MXN)
                        <input name="amount_due" type="number" min="0" max="9999999999.99" step="0.01" required value="<?= htmlspecialchars((string) $d->selectedAccount->amount_due, ENT_QUOTES, 'UTF-8') ?>">
                        <small>Configura aquí el importe individual del alumno para este periodo.</small>
                    </label>
                    <label>Estado de cuenta
                        <select name="status" required>
                            <?php foreach (['adeudo' => 'Adeudo', 'al_corriente' => 'Al corriente', 'pendiente' => 'Pendiente', 'convenio' => 'Convenio / prórroga', 'baja' => 'Baja financiera'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $d->selectedAccount->status === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Nota / motivo del estado
                        <input name="status_note" maxlength="500" value="<?= htmlspecialchars($d->selectedAccount->status_note ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Obligatoria si se marca convenio">
                    </label>
                    <button class="button button-primary" type="submit">Actualizar estado de cuenta</button>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($d->canRegisterPayments || $d->selectedPayment): ?>
            <section class="panel">
                <div class="panel-heading">
                    <div><span class="eyebrow">REGISTRO</span><h2><?= $d->selectedPayment ? 'Corregir pago' : 'Registrar pago' ?></h2></div>
                </div>
                <?php if ($d->selectedPayment): ?>
                    <?php $paymentAction = 'finance/edit-payment'; ?>
                <?php else: ?>
                    <?php $paymentAction = 'finance/register-payment'; ?>
                <?php endif; ?>
                <form class="finance-payment-form" method="post" action="<?= htmlspecialchars(URL . $paymentAction, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="account_id" value="<?= (int) $d->selectedAccount->id ?>">
                    <?php if ($d->selectedPayment): ?>
                        <input type="hidden" name="payment_id" value="<?= (int) $d->selectedPayment->id ?>">
                    <?php endif; ?>
                    <label>Folio de pago
                        <input name="folio" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._/-]{2,63}" required value="<?= htmlspecialchars($d->selectedPayment->folio ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. FAC-2026-01452">
                    </label>
                    <label>Concepto
                        <input name="concept" maxlength="120" required value="<?= htmlspecialchars($d->selectedPayment->concept ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Inscripción, mensualidad...">
                    </label>
                    <label>Importe (MXN)
                        <input name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" required value="<?= htmlspecialchars($d->selectedPayment->amount ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>Fecha de pago
                        <input name="paid_on" type="date" required value="<?= htmlspecialchars($d->selectedPayment->paid_on ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>Método
                        <select name="payment_method" required>
                            <?php foreach (['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'deposito' => 'Depósito', 'otro' => 'Otro'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($d->selectedPayment->payment_method ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Número de movimiento / referencia
                        <input name="payment_reference" maxlength="120" required value="<?= htmlspecialchars($d->selectedPayment->payment_reference ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Referencia bancaria o recibo">
                    </label>
                    <label class="finance-notes-field">Notas
                        <input name="notes" maxlength="500" value="<?= htmlspecialchars($d->selectedPayment->notes ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Opcional">
                    </label>
                    <div class="finance-form-actions">
                        <?php if ($d->selectedPayment): ?>
                            <a class="button button-secondary" href="<?= htmlspecialchars(URL . 'finance?account_id=' . (int) $d->selectedAccount->id, ENT_QUOTES, 'UTF-8') ?>">Cancelar edición</a>
                        <?php endif; ?>
                        <button class="button button-primary" type="submit"><?= $d->selectedPayment ? 'Guardar corrección' : 'Registrar pago' ?></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-heading">
                <div><span class="eyebrow">AUDITORÍA</span><h2>Movimientos de <?= htmlspecialchars($d->selectedAccount->period_key, ENT_QUOTES, 'UTF-8') ?></h2></div>
            </div>
            <div class="table-scroll">
                <table class="admin-table finance-payments-table">
                    <thead><tr><th>Fecha / folio</th><th>Concepto</th><th>Referencia</th><th>Método</th><th>Importe</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($d->payments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars($payment->paid_on, ENT_QUOTES, 'UTF-8') ?><small><?= htmlspecialchars($payment->folio, ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars($payment->concept, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($payment->payment_reference, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucfirst($payment->payment_method), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>$<?= number_format((float) $payment->amount, 2) ?></td>
                            <td><span class="status-badge <?= $payment->status === 'registrado' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars(ucfirst($payment->status), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($payment->void_reason): ?><small>Motivo: <?= htmlspecialchars($payment->void_reason, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                            </td>
                            <td class="finance-payment-actions">
                                <?php if ($payment->status === 'registrado' && $d->canEditPayments): ?>
                                    <a class="button button-small button-secondary" href="<?= htmlspecialchars(URL . 'finance?account_id=' . (int) $d->selectedAccount->id . '&edit_payment=' . (int) $payment->id, ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                                <?php endif; ?>
                                <?php if ($payment->status === 'registrado' && $d->canVoidPayments): ?>
                                    <form method="post" action="<?= htmlspecialchars(URL . 'finance/void-payment', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="account_id" value="<?= (int) $d->selectedAccount->id ?>">
                                        <input type="hidden" name="payment_id" value="<?= (int) $payment->id ?>">
                                        <input name="void_reason" maxlength="500" required placeholder="Motivo para anular">
                                        <button class="button button-small button-secondary" type="submit">Anular</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($d->payments) === 0): ?>
                        <tr><td colspan="7" class="empty-state">Aún no hay pagos registrados para este cuatrimestre.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted finance-audit-note">Las correcciones y anulaciones se conservan en la auditoría; no se borran pagos del historial.</p>
        </section>
    <?php else: ?>
        <section class="panel finance-empty">
            <h2>Selecciona una cuenta</h2>
            <p class="muted">Las cuentas financieras se generan automáticamente cuando se registra una inscripción a un cuatrimestre.</p>
        </section>
    <?php endif; ?>
</main>
</div>
</body>
</html>
