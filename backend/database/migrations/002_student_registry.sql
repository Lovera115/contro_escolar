CREATE TABLE IF NOT EXISTS ceptri_students (
    id INT NOT NULL AUTO_INCREMENT,
    matricula VARCHAR(32) NOT NULL,
    nombre_completo VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    telefono VARCHAR(30) NULL,
    carrera VARCHAR(200) NOT NULL,
    estado ENUM('activo', 'baja', 'egresado') NOT NULL DEFAULT 'activo',
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_students_matricula (matricula),
    KEY ix_ceptri_students_nombre (nombre_completo),
    KEY ix_ceptri_students_estado (estado),
    CONSTRAINT fk_ceptri_students_created_by
        FOREIGN KEY (created_by) REFERENCES bee_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_ceptri_students_updated_by
        FOREIGN KEY (updated_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_student_enrollments (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    period_key CHAR(6) NOT NULL,
    estado ENUM('inscrito', 'baja', 'concluido') NOT NULL DEFAULT 'inscrito',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_student_period (student_id, period_key),
    KEY ix_ceptri_enrollments_period (period_key),
    CONSTRAINT fk_ceptri_enrollments_student
        FOREIGN KEY (student_id) REFERENCES ceptri_students (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_enrollments_created_by
        FOREIGN KEY (created_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
