<?php

class recordsController extends Controller implements ControllerInterface
{
    private const MAX_UPLOAD_BYTES = 5242880;

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $user = CeptriAccess::requirePermission('records.review');
        $pdo = Db::connect(true);
        $search = trim((string) ($_GET['q'] ?? ''));
        $parameters = [];
        $where = '';
        if ($search !== '') {
            $where = 'WHERE s.matricula LIKE :matricula OR s.nombre_completo LIKE :nombre';
            $parameters = ['matricula' => '%' . $search . '%', 'nombre' => '%' . $search . '%'];
        }

        $studentsQuery = $pdo->prepare(
            "SELECT s.id, s.matricula, s.nombre_completo, s.carrera,
                    COUNT(DISTINCT CASE WHEN latest.status = 'aprobado' THEN latest.requirement_id END) AS approved_count,
                    COUNT(DISTINCT CASE WHEN latest.status = 'en_revision' THEN latest.requirement_id END) AS review_count,
                    COUNT(DISTINCT CASE WHEN latest.status = 'rechazado' THEN latest.requirement_id END) AS rejected_count,
                    COUNT(DISTINCT latest.requirement_id) AS uploaded_count
             FROM ceptri_students s
             LEFT JOIN (
                 SELECT d.*
                 FROM ceptri_student_documents d
                 INNER JOIN (
                     SELECT student_id, requirement_id, MAX(id) AS latest_id
                     FROM ceptri_student_documents
                     GROUP BY student_id, requirement_id
                 ) current_doc ON current_doc.latest_id = d.id
             ) latest ON latest.student_id = s.id
             {$where}
             GROUP BY s.id, s.matricula, s.nombre_completo, s.carrera
             ORDER BY s.nombre_completo"
        );
        $studentsQuery->execute($parameters);
        $students = $studentsQuery->fetchAll(PDO::FETCH_ASSOC);
        $selectedId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
        if (!$selectedId && $students) {
            $selectedId = (int) $students[0]['id'];
        }

        $selectedStudent = null;
        $documents = [];
        $requirements = [];
        if ($selectedId) {
            $studentQuery = $pdo->prepare(
                'SELECT id, matricula, nombre_completo, carrera
                 FROM ceptri_students
                 WHERE id = :id'
            );
            $studentQuery->execute(['id' => $selectedId]);
            $selectedStudent = $studentQuery->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedStudent) {
                $requirements = $pdo->query(
                    'SELECT id, name FROM ceptri_document_requirements ORDER BY sort_order'
                )->fetchAll(PDO::FETCH_ASSOC);
                $documentsQuery = $pdo->prepare(
                    'SELECT d.id, d.requirement_id, d.file_key, d.original_name, d.mime_type,
                            d.file_size, d.status, d.observation, d.created_at, d.reviewed_at,
                            r.name AS requirement_name, uploader.nombre_completo AS uploaded_by_name,
                            reviewer.nombre_completo AS reviewed_by_name
                     FROM ceptri_student_documents d
                     INNER JOIN (
                         SELECT requirement_id, MAX(id) AS latest_id
                         FROM ceptri_student_documents
                         WHERE student_id = :detail_student_id
                         GROUP BY requirement_id
                     ) latest ON latest.latest_id = d.id
                     INNER JOIN ceptri_document_requirements r ON r.id = d.requirement_id
                     LEFT JOIN bee_users uploader ON uploader.id = d.uploaded_by
                     LEFT JOIN bee_users reviewer ON reviewer.id = d.reviewed_by
                     WHERE d.student_id = :student_id
                     ORDER BY r.sort_order'
                );
                $documentsQuery->execute([
                    'student_id' => $selectedId,
                    'detail_student_id' => $selectedId,
                ]);
                $documents = $documentsQuery->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $documentByRequirement = [];
        foreach ($documents as $document) {
            $documentByRequirement[$document['requirement_id']] = $document;
        }
        foreach ($requirements as &$requirement) {
            $requirement['document'] = $documentByRequirement[$requirement['id']] ?? null;
        }
        unset($requirement);

        $approvedCount = 0;
        foreach ($requirements as $requirement) {
            if (($requirement['document']['status'] ?? '') === 'aprobado') {
                $approvedCount++;
            }
        }

        $this->setTitle('Revisión de expedientes · CEPTRI');
        $this->setView('index');
        $this->setData([
            'user' => $user,
            'moduleAccess' => CeptriAccess::moduleAccess($user),
            'csrfToken' => CSRF_TOKEN,
            'students' => $students,
            'selectedStudent' => $selectedStudent,
            'requirements' => $requirements,
            'approvedCount' => $approvedCount,
            'requirementCount' => count($requirements),
            'maxUploadBytes' => $this->effectiveUploadLimit(),
            'search' => $search,
            'flash' => $this->consumeFlash(),
        ]);
        $this->render();
    }

    public function upload()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('records.review');
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $requirementId = trim((string) ($_POST['requirement_id'] ?? ''));
        $upload = $_FILES['document_file'] ?? null;
        if (!$studentId || $requirementId === '' || !is_array($upload)) {
            $this->fail('Selecciona un alumno, requisito y archivo válidos.', $studentId ?: null);
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->fail($this->uploadErrorMessage((int) $upload['error']), $studentId);
        }
        if (!isset($upload['tmp_name'], $upload['name'], $upload['size']) ||
            !is_uploaded_file($upload['tmp_name'])) {
            $this->fail('El archivo recibido no es una carga válida.', $studentId);
        }
        $maxUploadBytes = $this->effectiveUploadLimit();
        if ((int) $upload['size'] < 1 || (int) $upload['size'] > $maxUploadBytes) {
            $this->fail(
                'El archivo debe medir entre 1 byte y ' . $this->formatBytes($maxUploadBytes) . '.',
                $studentId
            );
        }

        $extension = strtolower(pathinfo(str_replace('\\', '/', (string) $upload['name']), PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $mimeExtensions = [
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
        ];
        if (!is_string($mime) || !isset($mimeExtensions[$mime]) ||
            !in_array($extension, $mimeExtensions[$mime], true)) {
            $this->fail('Formato no válido. El archivo debe ser PDF, JPG o PNG y coincidir con su extensión.', $studentId);
        }

        $pdo = Db::connect(true);
        $studentQuery = $pdo->prepare('SELECT id FROM ceptri_students WHERE id = :id');
        $studentQuery->execute(['id' => $studentId]);
        if ($studentQuery->fetchColumn() === false) {
            $this->fail('No se encontró el alumno seleccionado.', $studentId);
        }
        $requirementQuery = $pdo->prepare('SELECT id FROM ceptri_document_requirements WHERE id = :id');
        $requirementQuery->execute(['id' => $requirementId]);
        if ($requirementQuery->fetchColumn() === false) {
            $this->fail('El requisito documental seleccionado no es válido.', $studentId);
        }

        $originalName = basename(str_replace('\\', '/', (string) $upload['name']));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName);
        if (!is_string($originalName) || $originalName === '') {
            $originalName = 'documento.' . $extension;
        }
        $originalName = mb_substr($originalName, 0, 255);

        $storageDirectory = $this->storageDirectory();
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
            throw new RuntimeException('No se pudo crear el almacenamiento privado de documentos.');
        }
        if (!chmod($storageDirectory, 0700)) {
            throw new RuntimeException('No se pudieron proteger los permisos del almacenamiento privado.');
        }
        $fileKey = bin2hex(random_bytes(32)) . '.' . $extension;
        $destination = $storageDirectory . DIRECTORY_SEPARATOR . $fileKey;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('No se pudo guardar el archivo cargado.');
        }
        if (!chmod($destination, 0600)) {
            if (!unlink($destination)) {
                error_log('No se pudo eliminar un archivo cuya protección de permisos falló: ' . $fileKey);
            }
            throw new RuntimeException('No se pudieron proteger los permisos del documento cargado.');
        }
        $fileSize = filesize($destination);
        if ($fileSize === false) {
            if (!unlink($destination)) {
                error_log('No se pudo eliminar un archivo cuyo tamaño no pudo leerse: ' . $fileKey);
            }
            throw new RuntimeException('No se pudo determinar el tamaño del archivo cargado.');
        }
        $hash = hash_file('sha256', $destination);
        if ($hash === false) {
            if (!unlink($destination)) {
                error_log('No se pudo eliminar un archivo cuya huella no pudo calcularse: ' . $fileKey);
            }
            throw new RuntimeException('No se pudo calcular la huella de integridad del archivo.');
        }

        try {
            $insert = $pdo->prepare(
                "INSERT INTO ceptri_student_documents
                    (student_id, requirement_id, file_key, original_name, mime_type, file_size, sha256, status, uploaded_by)
                 VALUES
                    (:student_id, :requirement_id, :file_key, :original_name, :mime_type, :file_size, :sha256, 'en_revision', :uploaded_by)"
            );
            $insert->execute([
                'student_id' => $studentId,
                'requirement_id' => $requirementId,
                'file_key' => $fileKey,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'file_size' => $fileSize,
                'sha256' => $hash,
                'uploaded_by' => (int) $user['id'],
            ]);
        } catch (Throwable $exception) {
            if (is_file($destination) && !unlink($destination)) {
                error_log('No se pudo eliminar un archivo huérfano de carga: ' . $fileKey);
            }
            throw $exception;
        }

        $this->setFlash('success', 'El documento se guardó de forma privada y quedó en revisión.');
        Redirect::to('records?student_id=' . $studentId);
    }

    public function decide()
    {
        $this->requirePostAndCsrf();
        $user = CeptriAccess::requirePermission('records.review');
        $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $documentId = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        $observation = trim((string) ($_POST['observation'] ?? ''));
        if (!$studentId || !$documentId || !in_array($decision, ['aprobado', 'rechazado'], true)) {
            $this->fail('La decisión de revisión recibida no es válida.', $studentId ?: null);
        }
        if (($decision === 'rechazado' && ($observation === '' || mb_strlen($observation) > 500)) ||
            ($decision === 'aprobado' && $observation !== '')) {
            $this->fail('Escribe un motivo de rechazo de hasta 500 caracteres, o deja vacío el motivo al aprobar.', $studentId);
        }

        $pdo = Db::connect(true);
        $update = $pdo->prepare(
            "UPDATE ceptri_student_documents
             SET status = :status, observation = :observation, reviewed_by = :reviewed_by,
                 reviewed_at = CURRENT_TIMESTAMP
             WHERE id = :id AND student_id = :student_id"
        );
        $update->execute([
            'status' => $decision,
            'observation' => $decision === 'rechazado' ? $observation : null,
            'reviewed_by' => (int) $user['id'],
            'id' => $documentId,
            'student_id' => $studentId,
        ]);
        if ($update->rowCount() < 1) {
            $exists = $pdo->prepare(
                'SELECT 1 FROM ceptri_student_documents WHERE id = :id AND student_id = :student_id'
            );
            $exists->execute(['id' => $documentId, 'student_id' => $studentId]);
            if ($exists->fetchColumn() === false) {
                $this->fail('No se encontró el documento del alumno seleccionado.', $studentId);
            }
        }

        $this->setFlash(
            'success',
            $decision === 'aprobado' ? 'El documento fue aprobado.' : 'El documento fue rechazado y se guardó el motivo.'
        );
        Redirect::to('records?student_id=' . $studentId);
    }

    public function file()
    {
        CeptriAccess::requirePermission('records.review');
        $documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$documentId) {
            http_response_code(400);
            exit('Selecciona un documento válido.');
        }
        $query = Db::connect(true)->prepare(
            'SELECT file_key, original_name, mime_type, file_size
             FROM ceptri_student_documents WHERE id = :id'
        );
        $query->execute(['id' => $documentId]);
        $document = $query->fetch(PDO::FETCH_ASSOC);
        if (!$document) {
            http_response_code(404);
            exit('No se encontró el documento solicitado.');
        }

        $fileKey = (string) $document['file_key'];
        if (!preg_match('/\A[a-f0-9]{64}\.(pdf|jpg|jpeg|png)\z/', $fileKey)) {
            throw new RuntimeException('La referencia del documento guardada no es válida.');
        }
        $path = $this->storageDirectory() . DIRECTORY_SEPARATOR . $fileKey;
        $realPath = realpath($path);
        $realDirectory = realpath($this->storageDirectory());
        if ($realPath === false || $realDirectory === false ||
            !str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
            http_response_code(404);
            exit('El archivo del documento no está disponible.');
        }

        header('Content-Type: ' . $document['mime_type']);
        header('Content-Length: ' . filesize($realPath));
        header('Content-Disposition: inline; filename="documento-' . $documentId . '.' . pathinfo($fileKey, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        readfile($realPath);
    }

    private function storageDirectory(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'student-documents';
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite de ' .
                $this->formatBytes($this->effectiveUploadLimit()) . ' configurado para el servidor.',
            UPLOAD_ERR_PARTIAL => 'El archivo se recibió parcialmente; vuelve a cargarlo.',
            UPLOAD_ERR_NO_FILE => 'Selecciona un archivo para cargar.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'El servidor no pudo recibir el archivo. Inténtalo nuevamente o contacta al administrador.',
            default => 'La carga del archivo falló con un error desconocido.',
        };
    }

    private function effectiveUploadLimit(): int
    {
        $limits = [self::MAX_UPLOAD_BYTES];
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $value = trim((string) ini_get($setting));
            if ($value === '' || $value === '0') {
                continue;
            }
            if (!preg_match('/\A(\d+)\s*([KMG]?)\z/i', $value, $matches)) {
                throw new RuntimeException('La configuración PHP ' . $setting . ' no tiene un formato válido.');
            }
            $multiplier = match (strtoupper($matches[2])) {
                'G' => 1024 ** 3,
                'M' => 1024 ** 2,
                'K' => 1024,
                default => 1,
            };
            $limit = (int) $matches[1] * $multiplier;
            if ($setting === 'post_max_size') {
                $limit = max(1, $limit - 65536);
            }
            $limits[] = $limit;
        }

        return min($limits);
    }

    private function formatBytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1) . ' MB';
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
        $_SESSION['records_flash'] = ['type' => $type, 'message' => $message];
    }

    private function consumeFlash(): array
    {
        $flash = $_SESSION['records_flash'] ?? [];
        unset($_SESSION['records_flash']);
        return is_array($flash) ? $flash : [];
    }

    private function fail(string $message, ?int $studentId = null): void
    {
        $this->setFlash('error', $message);
        Redirect::to($studentId ? 'records?student_id=' . $studentId : 'records');
    }
}
