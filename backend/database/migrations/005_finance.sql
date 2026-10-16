CREATE TABLE IF NOT EXISTS ceptri_financial_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('adeudo', 'al_corriente', 'pendiente', 'convenio', 'baja') NOT NULL DEFAULT 'adeudo',
    status_note VARCHAR(500) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_financial_account_enrollment (enrollment_id),
    KEY ix_ceptri_financial_accounts_status (status),
    CONSTRAINT fk_ceptri_financial_accounts_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES ceptri_student_enrollments (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_financial_accounts_created_by
        FOREIGN KEY (created_by) REFERENCES bee_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_ceptri_financial_accounts_updated_by
        FOREIGN KEY (updated_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_financial_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    folio VARCHAR(64) NOT NULL,
    concept VARCHAR(120) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    paid_on DATE NOT NULL,
    payment_method ENUM('efectivo', 'transferencia', 'tarjeta', 'deposito', 'otro') NOT NULL,
    payment_reference VARCHAR(120) NOT NULL,
    notes VARCHAR(500) NULL,
    status ENUM('registrado', 'anulado') NOT NULL DEFAULT 'registrado',
    created_by INT NULL,
    updated_by INT NULL,
    voided_by INT NULL,
    void_reason VARCHAR(500) NULL,
    voided_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ceptri_financial_payment_folio (folio),
    KEY ix_ceptri_financial_payments_account (account_id, status, paid_on),
    CONSTRAINT fk_ceptri_financial_payments_account
        FOREIGN KEY (account_id) REFERENCES ceptri_financial_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_financial_payments_created_by
        FOREIGN KEY (created_by) REFERENCES bee_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_ceptri_financial_payments_updated_by
        FOREIGN KEY (updated_by) REFERENCES bee_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_ceptri_financial_payments_voided_by
        FOREIGN KEY (voided_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_financial_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    event_type ENUM('account_updated', 'payment_registered', 'payment_edited', 'payment_voided') NOT NULL,
    old_data TEXT NULL,
    new_data TEXT NULL,
    changed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_ceptri_financial_events_account (account_id, created_at),
    CONSTRAINT fk_ceptri_financial_events_account
        FOREIGN KEY (account_id) REFERENCES ceptri_financial_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_financial_events_payment
        FOREIGN KEY (payment_id) REFERENCES ceptri_financial_payments (id) ON DELETE SET NULL,
    CONSTRAINT fk_ceptri_financial_events_changed_by
        FOREIGN KEY (changed_by) REFERENCES bee_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ceptri_financial_accounts (enrollment_id)
SELECT id FROM ceptri_student_enrollments;
