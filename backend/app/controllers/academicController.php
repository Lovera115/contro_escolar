<?php

class academicController extends Controller implements ControllerInterface
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
        $user = CeptriAccess::requirePermission('academic.assign');
        $pdo = Db::connect(true);
        $period = (string) ($_GET['period'] ?? $this->currentPeriod());
        if (!$this->validPeriod($period)) {
            $period = $this->currentPeriod();
        }

        $groupQuery = $pdo->prepare(
            'SELECT g.id, g.career, g.period_key, g.group_name,
                    COUNT(DISTINCT gs.id) AS subject_count,
                    COUNT(DISTINCT ass.enrollment_id) AS student_count,
                    COUNT(DISTINCT ta.teacher_user_id) AS teacher_count
             FROM ceptri_academic_groups g
             LEFT JOIN ceptri_academic_group_subjects gs ON gs.group_id = g.id
             LEFT JOIN ceptri_academic_student_subjects ass ON ass.group_subject_id = gs.id
             LEFT JOIN ceptri_academic_teacher_assignments ta ON ta.group_subject_id = gs.id
             WHERE g.period_key = :period
             GROUP BY g.id, g.career, g.period_key, g.group_name
             ORDER BY g.career, g.group_name'
        );
        $groupQuery->execute(['period' => $period]);
        $groups = $groupQuery->fetchAll(PDO::FETCH_ASSOC);

        $selectedGroupId = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
        $selectedGroup = null;
        $groupSubjects = [];
        $groupStudents = [];
        $eligibleStudents = [];
        if ($selectedGroupId) {
            $selectedQuery = $pdo->prepare(
                'SELECT id, career, period_key, group_name
                 FROM ceptri_academic_groups
                 WHERE id = :id
                 LIMIT 1'
            );
            $selectedQuery->execute(['id' => $selectedGroupId]);
            $selectedGroup = $selectedQuery->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedGroup) {
                $selectedGroup['period_label'] = $this->periodLabel($selectedGroup['period_key']);
                $subjectQuery = $pdo->prepare(
                    'SELECT gs.id AS group_subject_id, s.subject_name, u.nombre_completo AS teacher_name,
                            u.id AS teacher_user_id
                     FROM ceptri_academic_group_subjects gs
                     INNER JOIN ceptri_academic_subjects s ON s.id = gs.subject_id
                     LEFT JOIN ceptri_academic_teacher_assignments ta ON ta.group_subject_id = gs.id
                     LEFT JOIN bee_users u ON u.id = ta.teacher_user_id
                     WHERE gs.group_id = :group_id
                     ORDER BY s.subject_name'
                );
                $subjectQuery->execute(['group_id' => $selectedGroupId]);
                $groupSubjects = $subjectQuery->fetchAll(PDO::FETCH_ASSOC);

                $studentQuery = $pdo->prepare(
                    'SELECT DISTINCT e.id AS enrollment_id, s.id AS student_id, s.matricula, s.nombre_completo,
                            GROUP_CONCAT(DISTINCT subj.subject_name ORDER BY subj.subject_name SEPARATOR ", ") AS subjects
                     FROM ceptri_academic_student_subjects ass
                     INNER JOIN ceptri_student_enrollments e ON e.id = ass.enrollment_id
                     INNER JOIN ceptri_students s ON s.id = e.student_id
                     INNER JOIN ceptri_academic_group_subjects gs ON gs.id = ass.group_subject_id
                     INNER JOIN ceptri_academic_subjects subj ON subj.id = gs.subject_id
                     WHERE gs.group_id = :group_id
                     GROUP BY e.id, s.id, s.matricula, s.nombre_completo
                     ORDER BY s.nombre_completo'
                );
                $studentQuery->execute(['group_id' => $selectedGroupId]);
                $groupStudents = $studentQuery->fetchAll(PDO::FETCH_ASSOC);
                $studentAssignmentsQuery = $pdo->prepare(
                    'SELECT ass.id, e.id AS enrollment_id, s.id AS student_id, subj.subject_name
                     FROM ceptri_academic_student_subjects ass
                     INNER JOIN ceptri_student_enrollments e ON e.id = ass.enrollment_id
                     INNER JOIN ceptri_students s ON s.id = e.student_id
                     INNER JOIN ceptri_academic_group_subjects gs ON gs.id = ass.group_subject_id
                     INNER JOIN ceptri_academic_subjects subj ON subj.id = gs.subject_id
                     WHERE gs.group_id = :group_id
                     ORDER BY s.nombre_completo, subj.subject_name'
                );
                $studentAssignmentsQuery->execute(['group_id' => $selectedGroupId]);
                $assignmentsByEnrollment = [];
                foreach ($studentAssignmentsQuery->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
                    $assignmentsByEnrollment[$assignment['enrollment_id']][] = $assignment;
                }
                foreach ($groupStudents as &$student) {
                    $student['assignments'] = $assignmentsByEnrollment[$student['enrollment_id']] ?? [];
                }
                unset($student);

                $eligibleQuery = $pdo->prepare(
                    "SELECT e.id AS enrollment_id, s.id AS student_id, s.matricula, s.nombre_completo
                     FROM ceptri_student_enrollments e
                     INNER JOIN ceptri_students s ON s.id = e.student_id
                     WHERE e.period_key = :period AND e.estado = 'inscrito'
                         AND s.estado = 'activo' AND s.carrera = :career
                     ORDER BY s.nombre_completo"
                );
                $eligibleQuery->execute([
                    'period' => $selectedGroup['period_key'],
                    'career' => $selectedGroup['career'],
                ]);
                $eligibleStudents = $eligibleQuery->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $allPeriods = $this->periodOptions();
        $careers = self::CAREERS;
        $subjects = $pdo->query(
            'SELECT id, career, subject_name FROM ceptri_academic_subjects ORDER BY career, subject_name'
        )->fetchAll(PDO::FETCH_ASSOC);
        $teachers = $pdo->query(
            "SELECT u.id, u.nombre_completo, u.username
             FROM bee_users u
             INNER JOIN ceptri_user_roles ur ON ur.user_id = u.id
             INNER JOIN bee_roles r ON r.id = ur.role_id
             WHERE r.slug = 'docente' AND u.activo = 1
             ORDER BY u.nombre_completo"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->setTitle('Grupos y asignación académica · CEPTRI');
        $this->setView('index');
        $this->setData([
            'user' => $user,
            'moduleAccess' => CeptriAccess::moduleAccess($user),
            'csrfToken' => CSRF_TOKEN,
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'periodOptions' => $allPeriods,
            'careers' => $careers,
            'subjects' => $subjects,
            'teachers' => $teachers,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'groupSubjects' => $groupSubjects,
            'groupStudents' => $groupStudents,
            'eligibleStudents' => $eligibleStudents,
            'flash' => $this->consumeFlash(),
        ]);
        $this->render();
    }

    public function save_group()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('academic.assign');
        $career = trim((string) ($_POST['career'] ?? ''));
        $period = trim((string) ($_POST['period_key'] ?? ''));
        $groupName = trim((string) ($_POST['group_name'] ?? ''));
        $subjectIds = $_POST['subject_ids'] ?? [];
        if (!in_array($career, self::CAREERS, true)) {
            $this->fail('Selecciona una carrera válida.');
        }
        if (!$this->validPeriod($period)) {
            $this->fail('Selecciona un cuatrimestre válido.');
        }
        if ($groupName === '' || mb_strlen($groupName) > 80) {
            $this->fail('El nombre del grupo es obligatorio y debe tener máximo 80 caracteres.');
        }
        if (!is_array($subjectIds) || !$subjectIds) {
            $this->fail('Selecciona al menos una materia para el grupo.');
        }

        $pdo = Db::connect(true);
        $subjectIds = array_values(array_unique(array_filter(
            $subjectIds,
            static fn($id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false
        )));
        if (!$subjectIds) {
            $this->fail('La lista de materias recibida no es válida.');
        }

        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
            $subjectQuery = $pdo->prepare(
                "SELECT id FROM ceptri_academic_subjects
                 WHERE career = ? AND id IN ({$placeholders})"
            );
            $subjectQuery->execute(array_merge([$career], array_map('intval', $subjectIds)));
            $validSubjectIds = array_map('intval', $subjectQuery->fetchAll(PDO::FETCH_COLUMN));
            if (count($validSubjectIds) !== count($subjectIds)) {
                throw new DomainException('Una o más materias no pertenecen a la carrera seleccionada.');
            }

            $insertGroup = $pdo->prepare(
                'INSERT INTO ceptri_academic_groups (career, period_key, group_name, created_by)
                 VALUES (:career, :period, :group_name, :created_by)'
            );
            $insertGroup->execute([
                'career' => $career,
                'period' => $period,
                'group_name' => $groupName,
                'created_by' => (int) $user['id'],
            ]);
            $groupId = (int) $pdo->lastInsertId();
            $insertSubject = $pdo->prepare(
                'INSERT INTO ceptri_academic_group_subjects (group_id, subject_id)
                 VALUES (:group_id, :subject_id)'
            );
            foreach ($validSubjectIds as $subjectId) {
                $insertSubject->execute(['group_id' => $groupId, 'subject_id' => $subjectId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage());
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $this->fail('Ya existe un grupo con ese nombre para la carrera y cuatrimestre.');
            }
            throw $exception;
        }

        $this->setFlash('success', 'El grupo se creó con sus materias.');
        Redirect::to('academic?period=' . rawurlencode($period) . '&group_id=' . $groupId);
    }

    public function assign_teacher()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('academic.assign');
        $groupId = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $groupSubjectId = filter_input(INPUT_POST, 'group_subject_id', FILTER_VALIDATE_INT);
        $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
        if (!$groupId || !$groupSubjectId || !$teacherId) {
            $this->fail('Selecciona un grupo, materia y docente válidos.');
        }

        $pdo = Db::connect(true);
        $eligible = $pdo->prepare(
            "SELECT 1
             FROM ceptri_academic_group_subjects gs
             INNER JOIN ceptri_academic_groups g ON g.id = gs.group_id
             INNER JOIN bee_users u ON u.id = :teacher_id AND u.activo = 1
             INNER JOIN ceptri_user_roles ur ON ur.user_id = u.id
             INNER JOIN bee_roles r ON r.id = ur.role_id AND r.slug = 'docente'
             WHERE gs.id = :group_subject_id AND gs.group_id = :group_id"
        );
        $eligible->execute([
            'teacher_id' => $teacherId,
            'group_subject_id' => $groupSubjectId,
            'group_id' => $groupId,
        ]);
        if ($eligible->fetchColumn() === false) {
            $this->fail('La materia no pertenece al grupo o la cuenta seleccionada no es un docente activo.', $groupId);
        }

        $assign = $pdo->prepare(
            'INSERT INTO ceptri_academic_teacher_assignments
                (group_subject_id, teacher_user_id, assigned_by)
             VALUES (:group_subject_id, :teacher_id, :assigned_by)
             ON DUPLICATE KEY UPDATE teacher_user_id = VALUES(teacher_user_id), assigned_by = VALUES(assigned_by),
                 assigned_at = CURRENT_TIMESTAMP'
        );
        $assign->execute([
            'group_subject_id' => $groupSubjectId,
            'teacher_id' => $teacherId,
            'assigned_by' => (int) $user['id'],
        ]);

        $this->setFlash('success', 'El docente quedó asignado a la materia.');
        Redirect::to('academic?period=' . rawurlencode((string) ($_POST['period_key'] ?? $this->currentPeriod())) . '&group_id=' . $groupId);
    }

    public function enroll_students()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('academic.assign');
        $groupId = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $enrollmentId = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
        $groupSubjectIds = $_POST['group_subject_ids'] ?? [];
        if (!$groupId || !$enrollmentId || !is_array($groupSubjectIds) || !$groupSubjectIds) {
            $this->fail('Selecciona un alumno inscrito al cuatrimestre y al menos una materia.', $groupId ?: null);
        }
        $groupSubjectIds = array_values(array_unique(array_filter(
            $groupSubjectIds,
            static fn($id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false
        )));
        if (!$groupSubjectIds) {
            $this->fail('La lista de materias seleccionadas no es válida.', $groupId);
        }

        $pdo = Db::connect(true);
        try {
            $pdo->beginTransaction();
            $groupQuery = $pdo->prepare(
                'SELECT career, period_key FROM ceptri_academic_groups WHERE id = :id FOR UPDATE'
            );
            $groupQuery->execute(['id' => $groupId]);
            $group = $groupQuery->fetch(PDO::FETCH_ASSOC);
            if (!$group) {
                throw new DomainException('No se encontró el grupo seleccionado.');
            }
            $studentQuery = $pdo->prepare(
                "SELECT e.id, e.student_id
                 FROM ceptri_student_enrollments e
                 INNER JOIN ceptri_students s ON s.id = e.student_id
                 WHERE e.id = :enrollment_id AND e.period_key = :period
                     AND e.estado = 'inscrito' AND s.estado = 'activo' AND s.carrera = :career
                 FOR UPDATE"
            );
            $studentQuery->execute([
                'enrollment_id' => $enrollmentId,
                'period' => $group['period_key'],
                'career' => $group['career'],
            ]);
            if (!$studentQuery->fetch(PDO::FETCH_ASSOC)) {
                throw new DomainException('El alumno no está activo e inscrito en la carrera y cuatrimestre del grupo.');
            }
            $placeholders = implode(',', array_fill(0, count($groupSubjectIds), '?'));
            $subjectsQuery = $pdo->prepare(
                "SELECT id FROM ceptri_academic_group_subjects
                 WHERE group_id = ? AND id IN ({$placeholders})"
            );
            $subjectsQuery->execute(array_merge([$groupId], array_map('intval', $groupSubjectIds)));
            $validIds = array_map('intval', $subjectsQuery->fetchAll(PDO::FETCH_COLUMN));
            if (count($validIds) !== count($groupSubjectIds)) {
                throw new DomainException('Una o más materias no pertenecen al grupo seleccionado.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO ceptri_academic_student_subjects (enrollment_id, group_subject_id, created_by)
                 VALUES (:enrollment_id, :group_subject_id, :created_by)'
            );
            foreach ($validIds as $groupSubjectId) {
                $insert->execute([
                    'enrollment_id' => $enrollmentId,
                    'group_subject_id' => $groupSubjectId,
                    'created_by' => (int) $user['id'],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                $this->fail($exception->getMessage(), $groupId);
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                $this->fail('El alumno ya tiene una o más de esas materias en el grupo.', $groupId);
            }
            throw $exception;
        }

        $this->setFlash('success', count($validIds) . ' materia(s) inscrita(s) para el alumno.');
        Redirect::to('academic?period=' . rawurlencode((string) ($_POST['period_key'] ?? $this->currentPeriod())) . '&group_id=' . $groupId);
    }

    public function remove_teacher()
    {
        $this->removeAssignment('teacher');
    }

    public function remove_student_subject()
    {
        $this->removeAssignment('student');
    }

    public function report()
    {
        CeptriAccess::requirePermission('academic.assign');
        $groupId = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
        if (!$groupId) {
            http_response_code(400);
            exit('Selecciona un grupo válido.');
        }
        $pdo = Db::connect(true);
        $groupQuery = $pdo->prepare(
            'SELECT id, career, period_key, group_name FROM ceptri_academic_groups WHERE id = :id'
        );
        $groupQuery->execute(['id' => $groupId]);
        $group = $groupQuery->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            http_response_code(404);
            exit('No se encontró el grupo solicitado.');
        }
        $subjectsQuery = $pdo->prepare(
            'SELECT s.subject_name, u.nombre_completo AS teacher_name
             FROM ceptri_academic_group_subjects gs
             INNER JOIN ceptri_academic_subjects s ON s.id = gs.subject_id
             LEFT JOIN ceptri_academic_teacher_assignments ta ON ta.group_subject_id = gs.id
             LEFT JOIN bee_users u ON u.id = ta.teacher_user_id
             WHERE gs.group_id = :group_id
             ORDER BY s.subject_name'
        );
        $subjectsQuery->execute(['group_id' => $groupId]);
        $subjects = $subjectsQuery->fetchAll(PDO::FETCH_ASSOC);
        $studentsQuery = $pdo->prepare(
            'SELECT DISTINCT s.matricula, s.nombre_completo
             FROM ceptri_academic_student_subjects ass
             INNER JOIN ceptri_student_enrollments e ON e.id = ass.enrollment_id
             INNER JOIN ceptri_students s ON s.id = e.student_id
             INNER JOIN ceptri_academic_group_subjects gs ON gs.id = ass.group_subject_id
             WHERE gs.group_id = :group_id
             ORDER BY s.nombre_completo'
        );
        $studentsQuery->execute(['group_id' => $groupId]);
        $students = $studentsQuery->fetchAll(PDO::FETCH_ASSOC);

        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException('Dompdf no está disponible; ejecuta composer install en backend/app.');
        }
        $html = $this->reportHtml($group, $subjects, $students);
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="grupo-' . (int) $groupId . '.pdf"');
        echo $pdf->output();
    }

    private function removeAssignment(string $type): void
    {
        $this->requirePostAndCsrf();
        CeptriAccess::requirePermission('academic.assign');
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $groupId = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        if (!$id || !$groupId || !in_array($type, ['teacher', 'student'], true)) {
            $this->fail('La asignación seleccionada no es válida.');
        }
        $pdo = Db::connect(true);
        if ($type === 'teacher') {
            $statement = $pdo->prepare(
                'DELETE FROM ceptri_academic_teacher_assignments
                 WHERE group_subject_id = :assignment_id AND group_subject_id IN
                     (SELECT id FROM ceptri_academic_group_subjects WHERE group_id = :group_id)'
            );
        } else {
            $statement = $pdo->prepare(
                'DELETE ass FROM ceptri_academic_student_subjects ass
                 INNER JOIN ceptri_academic_group_subjects gs ON gs.id = ass.group_subject_id
                 WHERE ass.id = :assignment_id AND gs.group_id = :group_id'
            );
        }
        $statement->execute(['assignment_id' => $id, 'group_id' => $groupId]);
        if ($statement->rowCount() < 1) {
            $this->fail('No se encontró la asignación que intentas eliminar.', $groupId);
        }

        $this->setFlash('success', 'La asignación académica se eliminó.');
        Redirect::to('academic?group_id=' . $groupId);
    }

    private function reportHtml(array $group, array $subjects, array $students): string
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $subjectRows = '';
        foreach ($subjects as $subject) {
            $subjectRows .= '<tr><td>' . $escape($subject['subject_name']) . '</td><td>' .
                $escape($subject['teacher_name'] ?? 'Sin docente asignado') . '</td></tr>';
        }
        $studentRows = '';
        foreach ($students as $index => $student) {
            $studentRows .= '<tr><td>' . ($index + 1) . '</td><td>' . $escape($student['matricula']) .
                '</td><td>' . $escape($student['nombre_completo']) . '</td><td>Inscrito</td></tr>';
        }
        return '<!doctype html><html lang="es"><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;color:#0f172a;font-size:10px}
            h1{color:#1e3a8a} h2{margin-top:24px;color:#1e3a8a;font-size:14px}
            table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #cbd5e1;text-align:left}
            th{background:#eff6ff} .meta{line-height:1.7}
            </style><body><h1>CEPTRI · Reporte de grupo</h1><div class="meta"><strong>Grupo:</strong> ' .
            $escape($group['group_name']) . '<br><strong>Carrera:</strong> ' . $escape($group['career']) .
            '<br><strong>Cuatrimestre:</strong> ' . $escape($this->periodLabel($group['period_key'])) .
            '</div><h2>Materias y docentes</h2><table><thead><tr><th>Materia</th><th>Docente</th></tr></thead><tbody>' .
            ($subjectRows ?: '<tr><td colspan="2">Sin materias</td></tr>') .
            '</tbody></table><h2>Alumnos inscritos (' . count($students) . ')</h2><table><thead><tr>' .
            '<th>#</th><th>Matrícula</th><th>Nombre</th><th>Estado</th></tr></thead><tbody>' .
            ($studentRows ?: '<tr><td colspan="4">Sin alumnos inscritos</td></tr>') .
            '</tbody></table></body></html>';
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
        return sprintf('%04d-%d', (int) date('Y'), $month <= 4 ? 1 : ($month <= 8 ? 2 : 3));
    }

    private function periodLabel(string $period): string
    {
        [$year, $number] = array_pad(explode('-', $period, 2), 2, '');
        $months = ['1' => 'Enero-Abril', '2' => 'Mayo-Agosto', '3' => 'Septiembre-Diciembre'];
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
        $_SESSION['academic_flash'] = ['type' => $type, 'message' => $message];
    }

    private function consumeFlash(): array
    {
        $flash = $_SESSION['academic_flash'] ?? [];
        unset($_SESSION['academic_flash']);
        return is_array($flash) ? $flash : [];
    }

    private function fail(string $message, ?int $groupId = null): void
    {
        $this->setFlash('error', $message);
        Redirect::to($groupId ? 'academic?group_id=' . $groupId : 'academic');
    }
}
