-- Línea base 1.3. MySQL 8.4 / InnoDB. Inicialización solo en base nueva.
USE ins_tickets;
SET time_zone = '+00:00';

CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('usuario','agente','administrador') NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_usuarios_email (email),
    CONSTRAINT ck_usuarios_activo CHECK (activo IN (0,1)),
    CONSTRAINT ck_usuarios_nombre CHECK (CHAR_LENGTH(TRIM(nombre)) BETWEEN 2 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE categorias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_categorias_nombre (nombre),
    CONSTRAINT ck_categorias_activa CHECK (activa IN (0,1)),
    CONSTRAINT ck_categorias_nombre CHECK (CHAR_LENGTH(TRIM(nombre)) BETWEEN 2 AND 80)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    solicitante_id BIGINT UNSIGNED NOT NULL,
    agente_id BIGINT UNSIGNED NULL,
    categoria_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    prioridad ENUM('baja','media','alta') NOT NULL,
    estado ENUM('abierto','en_progreso','resuelto','cerrado') NOT NULL DEFAULT 'abierto',
    first_response_at DATETIME(6) NULL,
    resolved_at DATETIME(6) NULL,
    closed_at DATETIME(6) NULL,
    resolved_by BIGINT UNSIGNED NULL,
    closed_by BIGINT UNSIGNED NULL,
    resolution_summary TEXT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    submission_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_tickets_submission (submission_key),
    KEY ix_tickets_solicitante_fecha (solicitante_id,created_at),
    KEY ix_tickets_agente_estado (agente_id,estado),
    KEY ix_tickets_estado_fecha (estado,created_at),
    KEY ix_tickets_categoria (categoria_id),
    CONSTRAINT fk_ticket_solicitante FOREIGN KEY (solicitante_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_agente FOREIGN KEY (agente_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_resolutor FOREIGN KEY (resolved_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_cerrador FOREIGN KEY (closed_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT ck_ticket_titulo CHECK (CHAR_LENGTH(TRIM(titulo)) BETWEEN 5 AND 150),
    CONSTRAINT ck_ticket_descripcion CHECK (CHAR_LENGTH(TRIM(descripcion)) BETWEEN 10 AND 5000),
    CONSTRAINT ck_ticket_version CHECK (version >= 1),
    CONSTRAINT ck_ticket_no_autoasignado CHECK (agente_id IS NULL OR agente_id <> solicitante_id),
    CONSTRAINT ck_ticket_agente_atencion CHECK (estado = 'abierto' OR agente_id IS NOT NULL),
    CONSTRAINT ck_ticket_resolucion CHECK (
        (estado IN ('abierto','en_progreso') AND resolved_at IS NULL AND resolved_by IS NULL AND resolution_summary IS NULL)
        OR (estado IN ('resuelto','cerrado') AND resolved_at IS NOT NULL AND resolved_by IS NOT NULL
            AND resolved_by <> solicitante_id AND resolution_summary IS NOT NULL
            AND CHAR_LENGTH(TRIM(resolution_summary)) BETWEEN 10 AND 2000)
    ),
    CONSTRAINT ck_ticket_cierre CHECK (
        (estado <> 'cerrado' AND closed_at IS NULL AND closed_by IS NULL)
        OR (estado = 'cerrado' AND closed_at IS NOT NULL AND closed_by IS NOT NULL)
    ),
    CONSTRAINT ck_ticket_primera_fecha CHECK (first_response_at IS NULL OR first_response_at >= created_at),
    CONSTRAINT ck_ticket_resuelto_fecha CHECK (resolved_at IS NULL OR resolved_at >= created_at),
    CONSTRAINT ck_ticket_cerrado_fecha CHECK (closed_at IS NULL OR (resolved_at IS NOT NULL AND closed_at >= resolved_at))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE comentarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    autor_id BIGINT UNSIGNED NOT NULL,
    autor_rol VARCHAR(20) NOT NULL,
    contenido TEXT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY ix_comentarios_ticket_fecha (ticket_id,created_at,id),
    CONSTRAINT fk_comentario_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_comentario_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT ck_comentario_rol CHECK (autor_rol IN ('usuario','agente','administrador')),
    CONSTRAINT ck_comentario_contenido CHECK (CHAR_LENGTH(TRIM(contenido)) BETWEEN 1 AND 2000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE historial_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    anterior JSON NULL,
    nuevo JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY ix_historial_ticket_fecha (ticket_id,created_at,id),
    CONSTRAINT fk_historial_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_historial_actor FOREIGN KEY (actor_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT ck_historial_tipo CHECK (tipo IN ('creacion','edicion','asignacion','estado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE intentos_acceso (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    exitoso BOOLEAN NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY ix_intentos_clave_fecha (clave,created_at),
    CONSTRAINT ck_intento_exitoso CHECK (exitoso IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
