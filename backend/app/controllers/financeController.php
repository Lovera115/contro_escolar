<?php

class financeController extends Controller implements ControllerInterface
{
    private const STATUSES = ['adeudo', 'al_corriente', 'pendiente', 'convenio', 'baja'];
    private const METHODS = ['efectivo', 'transferencia', 'tarjeta', 'deposito', 'otro'];

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $user = CeptriAccess::requirePermission('finance.view');
        $pdo = Db::connect(true);
        $search = trim((string) ($_GET['q'] ?? ''));
        $period = trim((string) ($_GET['period'] ?? 'todos'));
        if ($period !== 'todos' && !$this->validPeriod($period)) {
            $period = 'todos';
        }

        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(s.matricula LIKE :matricula OR s.nombre_completo LIKE :nombre OR EXISTS (
                SELECT 1 FROM ceptri_financial_payments fp
                WHERE fp.account_id = a.id AND fp.folio LIKE :folio
            ))';
            $parameters['matricula'] = '%' . $search . '%';
            $parameters['nombre'] = '%' . $search . '%';
            $parameters['folio'] = '%' . $search . '%';
        }
        if ($period !== 'todos') {
            $conditions[] = 'e.period_key = :period_key';
            $parameters['period_key'] = $period;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $accountsQuery = $pdo->prepare(
            "SELECT a.id, e.id AS enrollment_id, e.period_key, s.id AS student_id,
                    s.matricula, s.nombre_completo, s.carrera, a.amount_due, a.status,
                    a.status_note, COALESCE(pay.total_paid, 0) AS total_paid,
                    GREATEST(a.amount_due - COALESCE(pay.total_paid, 0), 0) AS balance
             FROM ceptri_financial_accounts a
             INNER JOIN ceptri_student_enrollments e ON e.id = a.enrollment_id
             INNER JOIN ceptri_students s ON s.id = e.student_id
             LEFT JOIN (
                 SELECT account_id, SUM(amount) AS total_paid
                 FROM ceptri_financial_payments
                 WHERE status = 'registrado'
                 GROUP BY account_id
             ) pay ON pay.account_id = a.id
             {$where}
             ORDER BY e.period_key DESC, s.nombre_completo"
        );
        $accountsQuery->execute($parameters);
        $accounts = $accountsQuery->fetchAll(PDO::FETCH_ASSOC);
        $selectedId = filter_input(INPUT_GET, 'account_id', FILTER_VALIDATE_INT);
        if (!$selectedId && $accounts) {
            $selectedId = (int) $accounts[0]['id'];
        }

        $selectedAccount = null;
        $payments = [];
        $selectedPayment = null;
        if ($selectedId) {
            $selectedQuery = $pdo->prepare(
                "SELECT a.id, a.enrollment_id, a.amount_due, a.status, a.status_note,
                        e.period_key, s.id AS student_id, s.matricula, s.nombre_completo,
                        s.carrera, COALESCE(pay.total_paid, 0) AS total_paid,
                        GREATEST(a.amount_due - COALESCE(pay.total_paid, 0), 0) AS balance
                 FROM ceptri_financial_accounts a
                 INNER JOIN ceptri_student_enrollments e ON e.id = a.enrollment_id
                 INNER JOIN ceptri_students s ON s.id = e.student_id
                 LEFT JOIN (
                     SELECT account_id, SUM(amount) AS total_paid
                     FROM ceptri_financial_payments
                     WHERE status = 'registrado'
                     GROUP BY account_id
                 ) pay ON pay.account_id = a.id
                 WHERE a.id = :id"
            );
            $selectedQuery->execute(['id' => $selectedId]);
            $selectedAccount = $selectedQuery->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedAccount) {
                $paymentsQuery = $pdo->prepare(
                    'SELECT p.id, p.folio, p.concept, p.amount, p.paid_on, p.payment_method,
                            p.payment_reference, p.notes, p.status, p.void_reason,
                            p.created_at, creator.nombre_completo AS created_by_name,
                            editor.nombre_completo AS updated_by_name
                     FROM ceptri_financial_payments p
                     LEFT JOIN bee_users creator ON creator.id = p.created_by
                     LEFT JOIN bee_users editor ON editor.id = p.updated_by
                     WHERE p.account_id = :account_id
                     ORDER BY p.paid_on DESC, p.id DESC'
                );
                $paymentsQuery->execute(['account_id' => $selectedId]);
                $payments = $paymentsQuery->fetchAll(PDO::FETCH_ASSOC);

                $editPaymentId = filter_input(INPUT_GET, 'edit_payment', FILTER_VALIDATE_INT);
                if ($editPaymentId) {
                    $editQuery = $pdo->prepare(
                        "SELECT id, folio, concept, amount, paid_on, payment_method,
                                payment_reference, notes
                         FROM ceptri_financial_payments
                         WHERE id = :id AND account_id = :account_id AND status = 'registrado'"
                    );
                    $editQuery->execute(['id' => $editPaymentId, 'account_id' => $selectedId]);
                    $selectedPayment = $editQuery->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        }

        $periodOptions = $pdo->query(
            'SELECT DISTINCT period_key FROM ceptri_student_enrollments ORDER BY period_key DESC'
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->setTitle('Finanzas · CEPTRI');
        $this->setView('index');
        $this->setData([
            'user' => $user,
            'moduleAccess' => CeptriAccess::moduleAccess($user),
            'csrfToken' => CSRF_TOKEN,
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'payments' => $payments,
            'selectedPayment' => $selectedPayment,
            'periodOptions' => $periodOptions,
            'period' => $period,
            'search' => $search,
            'canRegisterPayments' => CeptriAccess::can($user, 'finance.payments.register'),
            'canEditPayments' => CeptriAccess::can($user, 'finance.payments.edit'),
            'canVoidPayments' => CeptriAccess::can($user, 'finance.payments.void'),
            'canExport' => CeptriAccess::can($user, 'finance.reports.export'),
            'flash' => $this->consumeFlash(),
        ]);
        $this->render();
    }

    public function save_account()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('finance.payments.edit');
        $accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
        $amountDue = $this->moneyToCents((string) ($_POST['amount_due'] ?? ''));
        $status = (string) ($_POST['status'] ?? '');
        $statusNote = trim((string) ($_POST['status_note'] ?? ''));
        if (!$accountId || $amountDue === null || !in_array($status, self::STATUSES, true) ||
            mb_strlen($statusNote) > 500 ||
            ($status === 'convenio' && $statusNote === '')) {
            $this->fail('Revisa el saldo, el estado y la nota del estado de cuenta.', $accountId ?: null);
        }

        $pdo = Db::connect(true);
        $pdo->beginTransaction();
        try {
            $account = $this->lockAccount($pdo, $accountId);
            if (!$account) {
                throw new DomainException('No se encontró la cuenta del cuatrimestre seleccionado.');
            }
            if ($amountDue < $this->totalPaidCents($pdo, $accountId)) {
                throw new DomainException('Los cargos no pueden quedar por debajo de los pagos vigentes ya registrados.');
            }
            $oldData = [
                'amount_due' => $account['amount_due'],
                'status' => $account['status'],
                'status_note' => $account['status_note'],
            ];
            $update = $pdo->prepare(
                'UPDATE ceptri_financial_accounts
                 SET amount_due = :amount_due, status = :status, status_note = :status_note, updated_by = :updated_by
                 WHERE id = :id'
            );
            $update->execute([
                'amount_due' => $this->centsToMoney($amountDue),
                'status' => $status,
                'status_note' => $statusNote !== '' ? $statusNote : null,
                'updated_by' => (int) $user['id'],
                'id' => $accountId,
            ]);
            $this->recordEvent($pdo, $accountId, null, 'account_updated', $oldData, [
                'amount_due' => $this->centsToMoney($amountDue),
                'status' => $status,
                'status_note' => $statusNote !== '' ? $statusNote : null,
            ], (int) $user['id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $accountId);
            }
            throw $exception;
        }

        $this->setFlash('success', 'El estado de cuenta del cuatrimestre se actualizó.');
        Redirect::to('finance?account_id=' . $accountId);
    }

    public function register_payment()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('finance.payments.register');
        $accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
        $data = $this->paymentDataFromPost();
        if (!$accountId || !$this->validPaymentData($data)) {
            $this->fail('Completa correctamente folio, concepto, importe, fecha, método y referencia.', $accountId ?: null);
        }

        $pdo = Db::connect(true);
        $pdo->beginTransaction();
        try {
            $account = $this->lockAccount($pdo, $accountId);
            if (!$account) {
                throw new DomainException('No se encontró la cuenta del cuatrimestre seleccionado.');
            }
            $this->assertPaymentFitsBalance($pdo, $accountId, (int) $data['amount_cents'], null, $account['amount_due']);
            $insert = $pdo->prepare(
                "INSERT INTO ceptri_financial_payments
                    (account_id, folio, concept, amount, paid_on, payment_method, payment_reference, notes, created_by, updated_by)
                 VALUES
                    (:account_id, :folio, :concept, :amount, :paid_on, :payment_method, :payment_reference, :notes, :created_by, :updated_by)"
            );
            $insert->execute($this->paymentParameters($data) + [
                'account_id' => $accountId,
                'created_by' => (int) $user['id'],
                'updated_by' => (int) $user['id'],
            ]);
            $paymentId = (int) $pdo->lastInsertId();
            $this->recordEvent($pdo, $accountId, $paymentId, 'payment_registered', null, $this->eventPaymentData($data), (int) $user['id']);
            $this->syncAccountStatus($pdo, $accountId, (string) $account['status'], (int) $user['id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $accountId);
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $this->fail('Ese folio de pago ya está registrado. Verifica el folio antes de continuar.', $accountId);
            }
            throw $exception;
        }

        $this->setFlash('success', 'El pago quedó registrado con su folio y referencia.');
        Redirect::to('finance?account_id=' . $accountId);
    }

    public function edit_payment()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('finance.payments.edit');
        $accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
        $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
        $data = $this->paymentDataFromPost();
        if (!$accountId || !$paymentId || !$this->validPaymentData($data)) {
            $this->fail('Revisa los datos del pago que intentas corregir.', $accountId ?: null);
        }

        $pdo = Db::connect(true);
        $pdo->beginTransaction();
        try {
            $account = $this->lockAccount($pdo, $accountId);
            $lockPayment = $pdo->prepare(
                "SELECT id, folio, concept, amount, paid_on, payment_method, payment_reference, notes
                 FROM ceptri_financial_payments
                 WHERE id = :id AND account_id = :account_id AND status = 'registrado'
                 FOR UPDATE"
            );
            $lockPayment->execute(['id' => $paymentId, 'account_id' => $accountId]);
            $oldPayment = $lockPayment->fetch(PDO::FETCH_ASSOC);
            if (!$account || !$oldPayment) {
                throw new DomainException('No se encontró un pago vigente de la cuenta seleccionada.');
            }
            $this->assertPaymentFitsBalance(
                $pdo,
                $accountId,
                (int) $data['amount_cents'],
                $paymentId,
                $account['amount_due']
            );
            $update = $pdo->prepare(
                'UPDATE ceptri_financial_payments
                 SET folio = :folio, concept = :concept, amount = :amount, paid_on = :paid_on,
                     payment_method = :payment_method, payment_reference = :payment_reference,
                     notes = :notes, updated_by = :updated_by
                 WHERE id = :id AND account_id = :account_id'
            );
            $update->execute($this->paymentParameters($data) + [
                'updated_by' => (int) $user['id'],
                'id' => $paymentId,
                'account_id' => $accountId,
            ]);
            $this->recordEvent(
                $pdo,
                $accountId,
                $paymentId,
                'payment_edited',
                $oldPayment,
                $this->eventPaymentData($data),
                (int) $user['id']
            );
            $this->syncAccountStatus($pdo, $accountId, (string) $account['status'], (int) $user['id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $accountId);
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $this->fail('Ese folio de pago ya está asignado a otro registro.', $accountId);
            }
            throw $exception;
        }

        $this->setFlash('success', 'La corrección del pago quedó guardada en la auditoría.');
        Redirect::to('finance?account_id=' . $accountId);
    }

    public function void_payment()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('finance.payments.void');
        $accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
        $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
        $reason = trim((string) ($_POST['void_reason'] ?? ''));
        if (!$accountId || !$paymentId || $reason === '' || mb_strlen($reason) > 500) {
            $this->fail('Escribe un motivo de anulación de hasta 500 caracteres.', $accountId ?: null);
        }

        $pdo = Db::connect(true);
        $pdo->beginTransaction();
        try {
            $account = $this->lockAccount($pdo, $accountId);
            $lockPayment = $pdo->prepare(
                "SELECT id, folio, concept, amount, paid_on, payment_method, payment_reference, notes
                 FROM ceptri_financial_payments
                 WHERE id = :id AND account_id = :account_id AND status = 'registrado'
                 FOR UPDATE"
            );
            $lockPayment->execute(['id' => $paymentId, 'account_id' => $accountId]);
            $oldPayment = $lockPayment->fetch(PDO::FETCH_ASSOC);
            if (!$account || !$oldPayment) {
                throw new DomainException('El pago ya fue anulado o no pertenece a la cuenta seleccionada.');
            }
            $void = $pdo->prepare(
                "UPDATE ceptri_financial_payments
                 SET status = 'anulado', void_reason = :reason, voided_by = :voided_by,
                     voided_at = CURRENT_TIMESTAMP, updated_by = :updated_by
                 WHERE id = :id AND account_id = :account_id"
            );
            $void->execute([
                'reason' => $reason,
                'voided_by' => (int) $user['id'],
                'updated_by' => (int) $user['id'],
                'id' => $paymentId,
                'account_id' => $accountId,
            ]);
            $this->recordEvent(
                $pdo,
                $accountId,
                $paymentId,
                'payment_voided',
                $oldPayment,
                ['void_reason' => $reason, 'status' => 'anulado'],
                (int) $user['id']
            );
            $this->syncAccountStatus($pdo, $accountId, (string) $account['status'], (int) $user['id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $accountId);
            }
            throw $exception;
        }

        $this->setFlash('success', 'El pago se anuló sin eliminar el registro y quedó auditado.');
        Redirect::to('finance?account_id=' . $accountId);
    }

    public function history_pdf()
    {
        $user = CeptriAccess::requirePermission('finance.reports.export');
        $studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
        if (!$studentId) {
            http_response_code(400);
            exit('Selecciona un alumno válido para descargar su historial.');
        }

        $pdo = Db::connect(true);
        $studentQuery = $pdo->prepare(
            'SELECT id, matricula, nombre_completo, carrera FROM ceptri_students WHERE id = :id'
        );
        $studentQuery->execute(['id' => $studentId]);
        $student = $studentQuery->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            http_response_code(404);
            exit('No se encontró el alumno solicitado.');
        }
        $historyQuery = $pdo->prepare(
            "SELECT e.period_key, a.amount_due, a.status AS account_status,
                    p.folio, p.concept, p.amount, p.paid_on, p.payment_method,
                    p.payment_reference, p.status AS payment_status, p.void_reason
             FROM ceptri_student_enrollments e
             LEFT JOIN ceptri_financial_accounts a ON a.enrollment_id = e.id
             LEFT JOIN ceptri_financial_payments p ON p.account_id = a.id
             WHERE e.student_id = :student_id
             ORDER BY e.period_key DESC, p.paid_on DESC, p.id DESC"
        );
        $historyQuery->execute(['student_id' => $studentId]);
        $history = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
        $totalPaidQuery = $pdo->prepare(
            "SELECT COALESCE(SUM(p.amount), 0)
             FROM ceptri_financial_payments p
             INNER JOIN ceptri_financial_accounts a ON a.id = p.account_id
             INNER JOIN ceptri_student_enrollments e ON e.id = a.enrollment_id
             WHERE e.student_id = :student_id AND p.status = 'registrado'"
        );
        $totalPaidQuery->execute(['student_id' => $studentId]);
        $totalPaid = (float) $totalPaidQuery->fetchColumn();

        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException('Dompdf no está disponible; ejecuta composer install en backend/app.');
        }
        $html = $this->historyHtml($student, $history, $totalPaid);
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();
        header('Content-Type: application/pdf');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="historial-pagos-' . (int) $studentId . '.pdf"');
        echo $pdf->output();
    }

    private function paymentDataFromPost(): array
    {
        $amountText = trim((string) ($_POST['amount'] ?? ''));
        $amountCents = $this->moneyToCents($amountText);
        return [
            'folio' => mb_strtoupper(trim((string) ($_POST['folio'] ?? ''))),
            'concept' => trim((string) ($_POST['concept'] ?? '')),
            'amount_cents' => $amountCents,
            'paid_on' => trim((string) ($_POST['paid_on'] ?? '')),
            'payment_method' => (string) ($_POST['payment_method'] ?? ''),
            'payment_reference' => trim((string) ($_POST['payment_reference'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];
    }

    private function validPaymentData(array $data): bool
    {
        return preg_match('/\A[A-Z0-9][A-Z0-9._\/-]{2,63}\z/', $data['folio']) === 1 &&
            $data['concept'] !== '' && mb_strlen($data['concept']) <= 120 &&
            is_int($data['amount_cents']) && $data['amount_cents'] > 0 &&
            $this->validDate($data['paid_on']) &&
            in_array($data['payment_method'], self::METHODS, true) &&
            $data['payment_reference'] !== '' && mb_strlen($data['payment_reference']) <= 120 &&
            mb_strlen($data['notes']) <= 500;
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        return $parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
            $parsed->format('Y-m-d') === $date;
    }

    private function validPeriod(string $period): bool
    {
        return preg_match('/\A20[0-9]{2}-[1-3]\z/', $period) === 1;
    }

    private function moneyToCents(string $amount): ?int
    {
        if (preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $amount) !== 1) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function paymentParameters(array $data): array
    {
        return [
            'folio' => $data['folio'],
            'concept' => $data['concept'],
            'amount' => $this->centsToMoney((int) $data['amount_cents']),
            'paid_on' => $data['paid_on'],
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'],
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
        ];
    }

    private function eventPaymentData(array $data): array
    {
        $data['amount'] = $this->centsToMoney((int) $data['amount_cents']);
        unset($data['amount_cents']);
        return $data;
    }

    private function lockAccount(PDO $pdo, int $accountId): array|false
    {
        $query = $pdo->prepare(
            'SELECT id, amount_due, status FROM ceptri_financial_accounts WHERE id = :id FOR UPDATE'
        );
        $query->execute(['id' => $accountId]);
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    private function assertPaymentFitsBalance(
        PDO $pdo,
        int $accountId,
        int $newAmountCents,
        ?int $excludedPaymentId,
        string $amountDue
    ): void {
        $paidCents = $this->totalPaidCents($pdo, $accountId, $excludedPaymentId);
        $dueCents = $this->moneyToCents($amountDue) ?? 0;
        if ($newAmountCents > $dueCents - $paidCents) {
            throw new DomainException('El importe del pago supera el saldo pendiente del cuatrimestre.');
        }
    }

    private function totalPaidCents(PDO $pdo, int $accountId, ?int $excludedPaymentId = null): int
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM ceptri_financial_payments
                WHERE account_id = :account_id AND status = 'registrado'";
        $parameters = ['account_id' => $accountId];
        if ($excludedPaymentId !== null) {
            $sql .= ' AND id <> :excluded_payment_id';
            $parameters['excluded_payment_id'] = $excludedPaymentId;
        }
        $paidQuery = $pdo->prepare($sql);
        $paidQuery->execute($parameters);
        return $this->moneyToCents((string) $paidQuery->fetchColumn()) ?? 0;
    }

    private function syncAccountStatus(PDO $pdo, int $accountId, string $currentStatus, int $userId): void
    {
        $balance = $pdo->prepare(
            "SELECT a.amount_due - COALESCE(SUM(CASE WHEN p.status = 'registrado' THEN p.amount ELSE 0 END), 0)
             FROM ceptri_financial_accounts a
             LEFT JOIN ceptri_financial_payments p ON p.account_id = a.id
             WHERE a.id = :id
             GROUP BY a.id, a.amount_due"
        );
        $balance->execute(['id' => $accountId]);
        $remaining = $this->moneyToCents((string) $balance->fetchColumn());
        if ($remaining === null) {
            throw new RuntimeException('No se pudo calcular el saldo actualizado de la cuenta.');
        }
        $nextStatus = $currentStatus;
        if ($remaining <= 0) {
            $nextStatus = 'al_corriente';
        } elseif ($currentStatus === 'al_corriente') {
            $nextStatus = 'adeudo';
        }
        if ($nextStatus !== $currentStatus) {
            $update = $pdo->prepare(
                'UPDATE ceptri_financial_accounts SET status = :status, updated_by = :updated_by WHERE id = :id'
            );
            $update->execute(['status' => $nextStatus, 'updated_by' => $userId, 'id' => $accountId]);
        }
    }

    private function recordEvent(
        PDO $pdo,
        int $accountId,
        ?int $paymentId,
        string $eventType,
        ?array $oldData,
        ?array $newData,
        int $changedBy
    ): void {
        $event = $pdo->prepare(
            'INSERT INTO ceptri_financial_events
                (account_id, payment_id, event_type, old_data, new_data, changed_by)
             VALUES
                (:account_id, :payment_id, :event_type, :old_data, :new_data, :changed_by)'
        );
        $event->execute([
            'account_id' => $accountId,
            'payment_id' => $paymentId,
            'event_type' => $eventType,
            'old_data' => $oldData === null ? null : json_encode($oldData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'new_data' => $newData === null ? null : json_encode($newData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'changed_by' => $changedBy,
        ]);
    }

    private function historyHtml(array $student, array $history, float $totalPaid): string
    {
        $rows = '';
        foreach ($history as $entry) {
            $rows .= '<tr><td>' . $this->escape((string) $entry['period_key']) . '</td><td>' .
                $this->escape((string) ($entry['folio'] ?? '—')) . '</td><td>' .
                $this->escape((string) ($entry['concept'] ?? '—')) . '</td><td>' .
                $this->escape((string) ($entry['paid_on'] ?? '—')) . '</td><td>' .
                $this->escape((string) ($entry['payment_reference'] ?? '—')) . '</td><td>' .
                ($entry['amount'] === null ? '—' : '$' . number_format((float) $entry['amount'], 2)) . '</td><td>' .
                $this->escape((string) ($entry['payment_status'] === 'anulado' ? 'Anulado' : ($entry['payment_method'] ?? 'Sin pagos'))) .
                '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7">Sin inscripciones financieras.</td></tr>';
        }

        return '<!doctype html><html lang="es"><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:10px}
            h1{color:#1e3a8a;font-size:20px;margin-bottom:4px}
            p{margin:3px 0;color:#475569}
            .summary{margin:16px 0;padding:10px;background:#eff6ff;border:1px solid #bfdbfe}
            table{width:100%;border-collapse:collapse;margin-top:14px}
            th,td{border-bottom:1px solid #cbd5e1;padding:7px 5px;text-align:left}
            th{background:#f1f5f9;color:#334155}
            </style><body><h1>Historial de pagos</h1><p>CEPTRI · Estado financiero por cuatrimestre</p>' .
            '<p><strong>Alumno:</strong> ' . $this->escape((string) $student['nombre_completo']) .
            ' · <strong>Matrícula:</strong> ' . $this->escape((string) $student['matricula']) . '</p>' .
            '<p><strong>Carrera:</strong> ' . $this->escape((string) $student['carrera']) . '</p>' .
            '<div class="summary"><strong>Total de pagos vigentes registrados:</strong> $' .
            number_format($totalPaid, 2) . ' · <strong>Fecha de emisión:</strong> ' .
            date('Y-m-d H:i') . '</div><table><thead><tr><th>Cuatrimestre</th><th>Folio</th><th>Concepto</th>' .
            '<th>Fecha</th><th>Referencia</th><th>Importe</th><th>Estado / método</th></tr></thead><tbody>' .
            $rows . '</tbody></table></body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function requirePostAndCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido.');
        }
        $csrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
        if (!Csrf::validate($csrf, true)) {
            http_response_code(400);
            exit('El token de seguridad no es válido o expiró. Recarga el formulario e inténtalo de nuevo.');
        }
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['finance_flash'] = ['type' => $type, 'message' => $message];
    }

    private function consumeFlash(): array
    {
        $flash = $_SESSION['finance_flash'] ?? [];
        unset($_SESSION['finance_flash']);
        return is_array($flash) ? $flash : [];
    }

    private function fail(string $message, ?int $accountId = null): void
    {
        $this->setFlash('error', $message);
        Redirect::to($accountId ? 'finance?account_id=' . $accountId : 'finance');
    }
}
