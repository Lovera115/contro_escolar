CREATE TABLE IF NOT EXISTS bee_users (
    id INT NOT NULL AUTO_INCREMENT,
    auth_token VARCHAR(255) NULL,
    username VARCHAR(64) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(254) NOT NULL,
    nombre_completo VARCHAR(160) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bee_users_username (username),
    UNIQUE KEY uq_bee_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bee_roles (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bee_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bee_permisos (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL DEFAULT '',
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bee_permisos_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bee_roles_permisos (
    id INT NOT NULL AUTO_INCREMENT,
    id_role INT NOT NULL,
    id_permiso INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bee_role_permission (id_role, id_permiso),
    CONSTRAINT fk_bee_role_permission_role
        FOREIGN KEY (id_role) REFERENCES bee_roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_bee_role_permission_permission
        FOREIGN KEY (id_permiso) REFERENCES bee_permisos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id),
    KEY ix_ceptri_user_roles_role (role_id),
    CONSTRAINT fk_ceptri_user_role_user
        FOREIGN KEY (user_id) REFERENCES bee_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_user_role_role
        FOREIGN KEY (role_id) REFERENCES bee_roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceptri_user_permissions (
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    allowed TINYINT(1) NOT NULL,
    PRIMARY KEY (user_id, permission_id),
    CONSTRAINT fk_ceptri_user_permission_user
        FOREIGN KEY (user_id) REFERENCES bee_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ceptri_user_permission_permission
        FOREIGN KEY (permission_id) REFERENCES bee_permisos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
