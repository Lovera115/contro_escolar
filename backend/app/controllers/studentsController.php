<?php

class studentsController extends Controller implements ControllerInterface
{
    private const CAREERS = [
        'Especialidad en Responsabilidades Administrativas (ERA)',
        'Especialidad en Justicia Administrativa (EJA)',
        'Especialidad en Derecho Administrativo (EDA)',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $user = CeptriAccess::requirePermission('students.manage');
        $pdo = Db::connect(true);
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'todos');
        if (!in_array($status, ['todos', 'activo', 'baja', 'egresado'], true)) {
            $status = 'todos';
        }

        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(s.matricula LIKE :matricula OR s.nombre_completo LIKE :nombre)';
            $parameters['matricula'] = '%' . $search . '%';
            $parameters['nombre'] = '%' . $search . '%';
        }
        if ($status !== 'todos') {
            $conditions[] = 's.estado = :estado';
            $parameters['estado'] = $status;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $list = $pdo->prepare(
            "SELECT s.id, s.matricula, s.nombre_completo, s.email, s.telefono, s.carrera, s.estado,
                    (SELECT e.period_key
                     FROM ceptri_student_enrollments e
                     WHERE e.student_id = s.id
                     ORDER BY e.period_key DESC
                     LIMIT 1) AS ultimo_cuatrimestre
             FROM ceptri_students s
             {$where}
             ORDER BY FIELD(s.estado, 'activo', 'baja', 'egresado'), s.nombre_completo"
        );
        $list->execute($parameters);
        $students = $list->fetchAll(PDO::FETCH_ASSOC);
        $selectedId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
        $selectedStudent = null;
        $enrollments = [];

        if ($selectedId) {
            $selectedQuery = $pdo->prepare(
                'SELECT id, matricula, nombre_completo, email, telefono, carrera, estado
                 FROM ceptri_students
                 WHERE id = :id
                 LIMIT 1'
            );
            $selectedQuery->execute(['id' => $selectedId]);
            $selectedStudent = $selectedQuery->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedStudent) {
                $historyQuery = $pdo->prepare(
                    'SELECT period_key, estado, created_at
                     FROM ceptri_student_enrollments
                     WHERE student_id = :student_id
                     ORDER BY period_key DESC'
                );
                $historyQuery->execute(['student_id' => $selectedId]);
                $enrollments = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
                foreach ($enrollments as &$enrollment) {
                    $enrollment['period_label'] = $this->periodLabel($enrollment['period_key']);
                }
                unset($enrollment);
            }
        }

        foreach ($students as &$student) {
            if ($student['ultimo_cuatrimestre'] !== null) {
                $student['ultimo_cuatrimestre_label'] = $this->periodLabel($student['ultimo_cuatrimestre']);
            }
        }
        unset($student);
        $periodOptions = $this->periodOptions();
        $enrolledPeriods = array_column($enrollments, 'period_key');
        $availablePeriods = array_values(array_filter(
            $periodOptions,
            static fn(array $period): bool => !in_array($period['key'], $enrolledPeriods, true)
        ));

        $this->setTitle('Padrón e inscripción de alumnos · CEPTRI');
        $this->setView('index');
        $this->setData([
            'user' => $user,
            'csrfToken' => CSRF_TOKEN,
            'students' => $students,
            'selectedStudent' => $selectedStudent,
            'enrollments' => $enrollments,
            'careers' => self::CAREERS,
            'periodOptions' => $periodOptions,
            'availablePeriods' => $availablePeriods,
            'defaultPeriod' => $this->currentPeriod(),
            'search' => $search,
            'statusFilter' => $status,
            'flash' => $this->consumeFlash(),
        ]);
        $this->render();
    }

    public function save()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('students.manage');
        $pdo = Db::connect(true);
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT) ?: null;
        $registration = strtoupper(trim((string) ($_POST['matricula'] ?? '')));
        $fullName = trim((string) ($_POST['nombre_completo'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['telefono'] ?? ''));
        $career = trim((string) ($_POST['carrera'] ?? ''));
        $studentStatus = (string) ($_POST['estado'] ?? 'activo');
        $initialPeriod = trim((string) ($_POST['period_key'] ?? ''));

        if (!preg_match('/\A[A-Z0-9][A-Z0-9-]{2,31}\z/', $registration)) {
            $this->fail('La matrícula debe tener entre 3 y 32 caracteres alfanuméricos o guiones.', $studentId);
        }
        if ($fullName === '' || mb_strlen($fullName) > 160) {
            $this->fail('El nombre completo es obligatorio y debe tener máximo 160 caracteres.', $studentId);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $this->fail('Captura un correo electrónico válido de máximo 254 caracteres.', $studentId);
        }
        if (mb_strlen($phone) > 30 || ($phone !== '' && !preg_match('/\A[0-9+(). -]+\z/', $phone))) {
            $this->fail('El teléfono solo puede contener números, espacios y los signos + ( ) . -.', $studentId);
        }
        if (!in_array($career, self::CAREERS, true)) {
            $this->fail('Selecciona una carrera válida.', $studentId);
        }
        if (!in_array($studentStatus, ['activo', 'baja', 'egresado'], true)) {
            $this->fail('Selecciona un estado académico válido.', $studentId);
        }
        if (!$studentId && !$this->validPeriod($initialPeriod)) {
            $this->fail('Selecciona un cuatrimestre válido para la inscripción inicial.');
        }

        try {
            $pdo->beginTransaction();
            if ($studentId) {
                $lock = $pdo->prepare('SELECT id FROM ceptri_students WHERE id = :id FOR UPDATE');
                $lock->execute(['id' => $studentId]);
                if ($lock->fetchColumn() === false) {
                    throw new DomainException('No se encontró el alumno que intentas editar.');
                }
                $update = $pdo->prepare(
                    'UPDATE ceptri_students
                     SET matricula = :matricula, nombre_completo = :nombre, email = :email,
                         telefono = :telefono, carrera = :carrera, estado = :estado, updated_by = :updated_by
                     WHERE id = :id'
                );
                $update->execute([
                    'matricula' => $registration,
                    'nombre' => $fullName,
                    'email' => $email,
                    'telefono' => $phone !== '' ? $phone : null,
                    'carrera' => $career,
                    'estado' => $studentStatus,
                    'updated_by' => (int) $user['id'],
                    'id' => $studentId,
                ]);
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO ceptri_students
                        (matricula, nombre_completo, email, telefono, carrera, estado, created_by, updated_by)
                     VALUES
                        (:matricula, :nombre, :email, :telefono, :carrera, :estado, :created_by, :updated_by)'
                );
                $insert->execute([
                    'matricula' => $registration,
                    'nombre' => $fullName,
                    'email' => $email,
                    'telefono' => $phone !== '' ? $phone : null,
                    'carrera' => $career,
                    'estado' => $studentStatus,
                    'created_by' => (int) $user['id'],
                    'updated_by' => (int) $user['id'],
                ]);
                $studentId = (int) $pdo->lastInsertId();
                $enroll = $pdo->prepare(
                    'INSERT INTO ceptri_student_enrollments (student_id, period_key, created_by)
                     VALUES (:student_id, :period_key, :created_by)'
                );
                $enroll->execute([
                    'student_id' => $studentId,
                    'period_key' => $initialPeriod,
                    'created_by' => (int) $user['id'],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $studentId);
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $this->fail('Ya existe un alumno con esa matrícula.', $studentId);
            }
            throw $exception;
        }

        $this->setFlash('success', $studentId && filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT)
            ? 'El expediente del alumno se actualizó correctamente.'
            : 'El alumno quedó registrado e inscrito al cuatrimestre seleccionado.');
        Redirect::to('students?edit=' . $studentId);
    }

    public function enroll()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('students.manage');
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $period = trim((string) ($_POST['period_key'] ?? ''));
        if (!$studentId || !$this->validPeriod($period)) {
            $this->fail('Selecciona un alumno y un cuatrimestre válidos.', $studentId ?: null);
        }

        $pdo = Db::connect(true);
        try {
            $pdo->beginTransaction();
            $studentQuery = $pdo->prepare('SELECT estado FROM ceptri_students WHERE id = :id FOR UPDATE');
            $studentQuery->execute(['id' => $studentId]);
            $studentStatus = $studentQuery->fetchColumn();
            if ($studentStatus === false) {
                throw new DomainException('No se encontró el alumno seleccionado.');
            }
            if ($studentStatus !== 'activo') {
                throw new DomainException('Solo se puede inscribir a un alumno con estado académico activo.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO ceptri_student_enrollments (student_id, period_key, created_by)
                 VALUES (:student_id, :period_key, :created_by)'
            );
            $insert->execute([
                'student_id' => $studentId,
                'period_key' => $period,
                'created_by' => (int) $user['id'],
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $studentId);
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $this->fail('El alumno ya tiene una inscripción registrada en ese cuatrimestre.', $studentId);
            }
            throw $exception;
        }

        $this->setFlash('success', 'La inscripción al cuatrimestre se registró correctamente.');
        Redirect::to('students?edit=' . $studentId);
    }

    private function periodOptions(): array
    {
        $options = [];
        for ($year = 2020; $year <= (int) date('Y') + 5; $year++) {
            foreach ([1 => 'Enero-Abril', 2 => 'Mayo-Agosto', 3 => 'Septiembre-Diciembre'] as $number => $months) {
                $key = sprintf('%04d-%d', $year, $number);
                $options[] = ['key' => $key, 'label' => $key . '-' . $months];
            }
        }
        return $options;
    }

    private function currentPeriod(): string
    {
        $month = (int) date('n');
        $number = $month <= 4 ? 1 : ($month <= 8 ? 2 : 3);
        return sprintf('%04d-%d', (int) date('Y'), $number);
    }

    private function periodLabel(string $period): string
    {
        [$year, $number] = array_pad(explode('-', $period, 2), 2, '');
        $months = [
            '1' => 'Enero-Abril',
            '2' => 'Mayo-Agosto',
            '3' => 'Septiembre-Diciembre',
        ];
        return isset($months[$number]) ? $year . '-' . $number . '-' . $months[$number] : $period;
    }

    private function validPeriod(string $period): bool
    {
        if (preg_match('/\A20[0-9]{2}-[1-3]\z/', $period) !== 1) {
            return false;
        }

        $year = (int) substr($period, 0, 4);
        return $year >= 2020 && $year <= (int) date('Y') + 5;
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
        $_SESSION['students_flash'] = ['type' => $type, 'message' => $message];
    }

    private function consumeFlash(): array
    {
        $flash = $_SESSION['students_flash'] ?? [];
        unset($_SESSION['students_flash']);
        return is_array($flash) ? $flash : [];
    }

    private function fail(string $message, ?int $studentId = null): void
    {
        $this->setFlash('error', $message);
        Redirect::to($studentId ? 'students?edit=' . $studentId : 'students');
    }
}
