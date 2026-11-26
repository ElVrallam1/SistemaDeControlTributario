-- SENIAT Control - Esquema PostgreSQL
-- Ejecutar: psql -U postgres -d seniat_control -f database/schema.sql

BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Roles
CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255)
);

INSERT INTO roles (name, description) VALUES
    ('admin', 'Administrador del sistema'),
    ('supervisor', 'Supervisor regional'),
    ('operador', 'Operador de atención y carga')
ON CONFLICT (name) DO NOTHING;

-- Usuarios
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    department VARCHAR(120) NOT NULL DEFAULT 'No asignado',
    password_hash TEXT NOT NULL,
    role_id INTEGER NOT NULL REFERENCES roles(id),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    temp_password_expires_at TIMESTAMPTZ NULL,
    last_login_at TIMESTAMPTZ NULL,
    last_login_ip VARCHAR(45) NULL,
    failed_login_count INTEGER NOT NULL DEFAULT 0,
    locked_until TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- Usuario administrador inicial (cambiar contraseña al primer inicio)
INSERT INTO users (full_name, username, email, department, password_hash, role_id, must_change_password)
SELECT
    'Administrador SENIAT',
    'admin',
    'admin@seniat-control.local',
    'Despacho de la Gerencia',
    crypt('Admin123*', gen_salt('bf', 12)),
    r.id,
    TRUE
FROM roles r
WHERE r.name = 'admin'
ON CONFLICT (username) DO NOTHING;

-- Intentos de login / rate limit
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGSERIAL PRIMARY KEY,
    identifier VARCHAR(150) NOT NULL, -- email, username o IP
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    was_successful BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_identifier_created ON login_attempts(identifier, created_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_created ON login_attempts(ip_address, created_at);

-- Solicitudes de recuperación
CREATE TABLE IF NOT EXISTS password_recovery_requests (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    temp_password_hash TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_recovery_user_expires ON password_recovery_requests(user_id, expires_at);

-- Auditoría / actividad sospechosa
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id VARCHAR(100),
    details JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    severity VARCHAR(20) NOT NULL DEFAULT 'info', -- info, warning, critical
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);

-- Expedientes
CREATE TABLE IF NOT EXISTS expedientes (
    id BIGSERIAL PRIMARY KEY,
    numero_expediente VARCHAR(80) UNIQUE NOT NULL,
    contribuyente VARCHAR(200) NOT NULL,
    rif VARCHAR(30),
    tipo_tramite VARCHAR(120) NOT NULL,
    division VARCHAR(120) NOT NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'pendiente', -- pendiente, en_proceso, completado, detenido, faltante
    prioridad VARCHAR(20) NOT NULL DEFAULT 'media', -- baja, media, alta
    descripcion TEXT,
    fecha_apertura DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_cierre DATE NULL,
    responsable_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_expedientes_estado ON expedientes(estado);
CREATE INDEX IF NOT EXISTS idx_expedientes_fecha ON expedientes(fecha_apertura);

-- Productividad de expedientes (registro por día/usuario)
CREATE TABLE IF NOT EXISTS productividad_expedientes (
    id BIGSERIAL PRIMARY KEY,
    expediente_id BIGINT NULL REFERENCES expedientes(id) ON DELETE SET NULL,
    usuario_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    fecha DATE NOT NULL DEFAULT CURRENT_DATE,
    expedientes_procesados INTEGER NOT NULL DEFAULT 0 CHECK (expedientes_procesados >= 0),
    expedientes_resueltos INTEGER NOT NULL DEFAULT 0 CHECK (expedientes_resueltos >= 0),
    expedientes_pendientes INTEGER NOT NULL DEFAULT 0 CHECK (expedientes_pendientes >= 0),
    observaciones TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_productividad_fecha ON productividad_expedientes(fecha);

-- Solvencias
CREATE TABLE IF NOT EXISTS solvencias (
    id BIGSERIAL PRIMARY KEY,
    numero_solvencia VARCHAR(80) UNIQUE NOT NULL,
    contribuyente VARCHAR(200) NOT NULL,
    rif VARCHAR(30),
    tipo VARCHAR(100) NOT NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'pendiente', -- pendiente, emitida, vencida, rechazada
    monto NUMERIC(14,2) NOT NULL DEFAULT 0,
    fecha_emision DATE NULL,
    fecha_vencimiento DATE NULL,
    responsable_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    observaciones TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_solvencias_estado ON solvencias(estado);

-- Multas
CREATE TABLE IF NOT EXISTS multas (
    id BIGSERIAL PRIMARY KEY,
    numero_multa VARCHAR(80) UNIQUE NOT NULL,
    contribuyente VARCHAR(200) NOT NULL,
    rif VARCHAR(30),
    motivo TEXT NOT NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'pendiente', -- pendiente, notificada, pagada, anulada, en_recurso
    monto NUMERIC(14,2) NOT NULL DEFAULT 0 CHECK (monto >= 0),
    fecha_registro DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_pago DATE NULL,
    responsable_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    observaciones TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_multas_estado ON multas(estado);

-- Requerimientos
CREATE TABLE IF NOT EXISTS requerimientos (
    id BIGSERIAL PRIMARY KEY,
    numero_requerimiento VARCHAR(80) UNIQUE NOT NULL,
    contribuyente VARCHAR(200) NOT NULL,
    rif VARCHAR(30),
    tipo VARCHAR(120) NOT NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'pendiente', -- pendiente, enviado, atendido, vencido, cancelado
    fecha_emision DATE NOT NULL DEFAULT CURRENT_DATE,
    fecha_limite DATE NULL,
    fecha_respuesta DATE NULL,
    responsable_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    descripcion TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_requerimientos_estado ON requerimientos(estado);

-- Reporte diario (reemplaza control_diario; formato real Plaza Venezuela)
CREATE TABLE IF NOT EXISTS reporte_diario (
    id BIGSERIAL PRIMARY KEY,
    fecha DATE NOT NULL,
    usuario_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    dependencia VARCHAR(120) NOT NULL DEFAULT 'Plaza Venezuela',
    vivienda_solicitudes_recibidas INT NOT NULL DEFAULT 0 CHECK (vivienda_solicitudes_recibidas>=0),
    vivienda_registros_entregados INT NOT NULL DEFAULT 0 CHECK (vivienda_registros_entregados>=0),
    orientaciones INT NOT NULL DEFAULT 0 CHECK (orientaciones>=0),
    copias_solicitadas INT NOT NULL DEFAULT 0 CHECK (copias_solicitadas>=0),
    copias_entregadas INT NOT NULL DEFAULT 0 CHECK (copias_entregadas>=0),
    rif_insc_pn INT NOT NULL DEFAULT 0 CHECK (rif_insc_pn>=0),
    rif_act_pn INT NOT NULL DEFAULT 0 CHECK (rif_act_pn>=0),
    rif_insc_pj INT NOT NULL DEFAULT 0 CHECK (rif_insc_pj>=0),
    rif_act_pj INT NOT NULL DEFAULT 0 CHECK (rif_act_pj>=0),
    rif_insc_suc INT NOT NULL DEFAULT 0 CHECK (rif_insc_suc>=0),
    rif_act_suc INT NOT NULL DEFAULT 0 CHECK (rif_act_suc>=0),
    rif_insc_emp INT NOT NULL DEFAULT 0 CHECK (rif_insc_emp>=0),
    rif_act_emp INT NOT NULL DEFAULT 0 CHECK (rif_act_emp>=0),
    rif_insc_gob INT NOT NULL DEFAULT 0 CHECK (rif_insc_gob>=0),
    rif_act_gob INT NOT NULL DEFAULT 0 CHECK (rif_act_gob>=0),
    rif_insc_cc INT NOT NULL DEFAULT 0 CHECK (rif_insc_cc>=0),
    rif_act_cc INT NOT NULL DEFAULT 0 CHECK (rif_act_cc>=0),
    rif_total_atendidos INT NOT NULL DEFAULT 0 CHECK (rif_total_atendidos>=0),
    asistencia_contribuyentes INT NOT NULL DEFAULT 0 CHECK (asistencia_contribuyentes>=0),
    sucesiones_atendidos INT NOT NULL DEFAULT 0 CHECK (sucesiones_atendidos>=0),
    sucesiones_ingresadas INT NOT NULL DEFAULT 0 CHECK (sucesiones_ingresadas>=0),
    solvencias_sucesiones_entregadas INT NOT NULL DEFAULT 0 CHECK (solvencias_sucesiones_entregadas>=0),
    multas_notificadas INT NOT NULL DEFAULT 0 CHECK (multas_notificadas>=0),
    papel_seguridad_anulado INT NOT NULL DEFAULT 0 CHECK (papel_seguridad_anulado>=0),
    observaciones TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (fecha, dependencia)
);

CREATE INDEX IF NOT EXISTS idx_reporte_diario_fecha ON reporte_diario(fecha DESC);

-- Inventario consolidado sucesiones / donaciones (Fase 2)
CREATE TABLE IF NOT EXISTS inventario_sucesiones (
    id BIGSERIAL PRIMARY KEY,
    numero_expediente VARCHAR(80) NOT NULL UNIQUE,
    razon_social VARCHAR(220) NOT NULL,
    rif VARCHAR(30),
    unidad_adscripcion VARCHAR(120) NOT NULL DEFAULT 'CENTRO AUXILIAR',
    tipo_caso VARCHAR(30) NOT NULL DEFAULT 'SUCESION',
    fecha_solicitud DATE NOT NULL,
    estatus VARCHAR(60) NOT NULL DEFAULT 'EN REVISION',
    observacion TEXT,
    fecha_acta_requerimiento DATE NULL,
    fecha_notificacion_acto DATE NULL,
    numero_planilla_liquidacion VARCHAR(80),
    importe NUMERIC(14,2) NOT NULL DEFAULT 0 CHECK (importe >= 0),
    usuario_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_inv_sucesiones_fecha ON inventario_sucesiones(fecha_solicitud DESC);
CREATE INDEX IF NOT EXISTS idx_inv_sucesiones_estatus ON inventario_sucesiones(estatus);
CREATE INDEX IF NOT EXISTS idx_inv_sucesiones_tipo ON inventario_sucesiones(tipo_caso);

-- Gestión sucesiones formato Queli (Fase 3)
CREATE TABLE IF NOT EXISTS gestion_sucesiones (
    id BIGSERIAL PRIMARY KEY,
    gerencia_regional VARCHAR(120) NOT NULL DEFAULT 'CENTRO AUXILIAR',
    numero_expediente VARCHAR(80) NOT NULL UNIQUE,
    fecha_ingreso_coordinacion DATE NULL,
    estatus VARCHAR(60) NOT NULL DEFAULT 'POR ASIGNAR',
    auto_reapertura VARCHAR(5) NOT NULL DEFAULT 'NO',
    fecha_consigno_recaudo DATE NULL,
    fecha_asignado_analista DATE NULL,
    fecha_acta_requerimiento DATE NULL,
    fecha_consigno_requerimiento DATE NULL,
    fecha_series_especiales DATE NULL,
    fecha_tramitaciones DATE NULL,
    fecha_consigno_pago DATE NULL,
    fecha_control_calidad DATE NULL,
    fecha_elaboracion_solvencia DATE NULL,
    fecha_entrega_solvencia DATE NULL,
    fecha_auto_cierre_expediente DATE NULL,
    fecha_enviado_archivo DATE NULL,
    observacion TEXT,
    usuario_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_gestion_sucesiones_fecha ON gestion_sucesiones(fecha_ingreso_coordinacion DESC);
CREATE INDEX IF NOT EXISTS idx_gestion_sucesiones_estatus ON gestion_sucesiones(estatus);
CREATE INDEX IF NOT EXISTS idx_gestion_sucesiones_gerencia ON gestion_sucesiones(gerencia_regional);

-- Atenciones al contribuyente (Fase 4)
CREATE TABLE IF NOT EXISTS atenciones_contribuyentes (
    id BIGSERIAL PRIMARY KEY,
    fecha DATE NOT NULL,
    area VARCHAR(40) NOT NULL,
    contribuyente VARCHAR(220) NOT NULL,
    cedula_rif VARCHAR(40),
    tipo_persona VARCHAR(20),
    tramite VARCHAR(160),
    orientado BOOLEAN NOT NULL DEFAULT FALSE,
    usuario_clave BOOLEAN NOT NULL DEFAULT FALSE,
    funcionario VARCHAR(150),
    n_declaracion VARCHAR(80),
    monto NUMERIC(14,2) NOT NULL DEFAULT 0 CHECK (monto >= 0),
    n_aprobacion VARCHAR(80),
    telefono VARCHAR(60),
    observaciones TEXT,
    usuario_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_atenciones_fecha ON atenciones_contribuyentes(fecha DESC);
CREATE INDEX IF NOT EXISTS idx_atenciones_area ON atenciones_contribuyentes(area);
CREATE INDEX IF NOT EXISTS idx_atenciones_funcionario ON atenciones_contribuyentes(funcionario);

-- Configuración de aplicación (Fase seguridad admin)
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMIT;
