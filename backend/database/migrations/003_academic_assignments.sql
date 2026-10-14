CREATE TABLE IF NOT EXISTS ceptri_academic_groups (
    id INT NOT NULL AUTO_INCREMENT,
    career VARCHAR(200) NOT NULL,
    period_key CHAR(6) NOT NULL,
    group_name VARCHAR(80) NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_academic_group (career, period_key, group_name),
    KEY ix_ceptri_academic_groups_period (period_key),
    CONSTRAINT fk_ceptri_academic_groups_created_by
        FOREIGN KEY (created_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_academic_subjects (
    id INT NOT NULL AUTO_INCREMENT,
    career VARCHAR(200) NOT NULL,
    subject_name VARCHAR(160) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_academic_subject (career, subject_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_academic_group_subjects (
    id INT NOT NULL AUTO_INCREMENT,
    group_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_group_subject (group_id, subject_id),
    CONSTRAINT fk_ceptri_group_subject_group
        FOREIGN KEY (group_id) REFERENCES ceptri_academic_groups (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_group_subject_subject
        FOREIGN KEY (subject_id) REFERENCES ceptri_academic_subjects (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_academic_teacher_assignments (
    group_subject_id INT NOT NULL,
    teacher_user_id INT NOT NULL,
    assigned_by INT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_subject_id),
    KEY ix_ceptri_teacher_assignments_teacher (teacher_user_id),
    CONSTRAINT fk_ceptri_teacher_assignment_group_subject
        FOREIGN KEY (group_subject_id) REFERENCES ceptri_academic_group_subjects (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_teacher_assignment_teacher
        FOREIGN KEY (teacher_user_id) REFERENCES bee_users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ceptri_teacher_assignment_assigned_by
        FOREIGN KEY (assigned_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_academic_student_subjects (
    id INT NOT NULL AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    group_subject_id INT NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_student_group_subject (enrollment_id, group_subject_id),
    KEY ix_ceptri_academic_student_subjects_group_subject (group_subject_id),
    CONSTRAINT fk_ceptri_academic_student_subject_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES ceptri_student_enrollments (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_academic_student_subject_group_subject
        FOREIGN KEY (group_subject_id) REFERENCES ceptri_academic_group_subjects (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_academic_student_subject_created_by
        FOREIGN KEY (created_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ceptri_academic_subjects (career, subject_name) VALUES
    ('Especialidad en Responsabilidades Administrativas (ERA)', 'Responsabilidades Administrativas'),
    ('Especialidad en Responsabilidades Administrativas (ERA)', 'Procedimiento de Responsabilidad Administrativa'),
    ('Especialidad en Responsabilidades Administrativas (ERA)', 'Ética Pública'),
    ('Especialidad en Responsabilidades Administrativas (ERA)', 'Control Gubernamental'),
    ('Especialidad en Justicia Administrativa (EJA)', 'Justicia Administrativa'),
    ('Especialidad en Justicia Administrativa (EJA)', 'Procedimiento Contencioso Administrativo'),
    ('Especialidad en Justicia Administrativa (EJA)', 'Derecho Procesal Administrativo'),
    ('Especialidad en Justicia Administrativa (EJA)', 'Medios de Impugnación'),
    ('Especialidad en Derecho Administrativo (EDA)', 'Derecho Administrativo'),
    ('Especialidad en Derecho Administrativo (EDA)', 'Derecho Constitucional'),
    ('Especialidad en Derecho Administrativo (EDA)', 'Argumentación Jurídica'),
    ('Especialidad en Derecho Administrativo (EDA)', 'Teoría del Estado')
ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name);
