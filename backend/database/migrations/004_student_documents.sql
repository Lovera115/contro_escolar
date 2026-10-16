CREATE TABLE IF NOT EXISTS ceptri_document_requirements (
    id VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_document_requirement_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_student_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    requirement_id VARCHAR(32) NOT NULL,
    file_key VARCHAR(69) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(32) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    status ENUM('en_revision', 'aprobado', 'rechazado') NOT NULL DEFAULT 'en_revision',
    observation VARCHAR(500) NULL,
    uploaded_by INT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_student_document_file_key (file_key),
    KEY ix_ceptri_student_documents_latest (student_id, requirement_id, id),
    KEY ix_ceptri_student_documents_status (status),
    CONSTRAINT fk_ceptri_student_documents_student
        FOREIGN KEY (student_id) REFERENCES ceptri_students (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_student_documents_requirement
        FOREIGN KEY (requirement_id) REFERENCES ceptri_document_requirements (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ceptri_student_documents_uploaded_by
        FOREIGN KEY (uploaded_by) REFERENCES bee_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_ceptri_student_documents_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ceptri_document_requirements (id, name, sort_order) VALUES
    ('acta', 'Acta de Nacimiento', 1),
    ('bachillerato', 'Certificado de Bachillerato', 2),
    ('curp', 'CURP', 3),
    ('domicilio', 'Comprobante de Domicilio', 4),
    ('identificacion', 'Identificación Oficial', 5)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);
