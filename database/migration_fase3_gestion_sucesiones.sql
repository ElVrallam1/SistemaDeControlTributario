BEGIN;
CREATE TABLE IF NOT EXISTS gestion_sucesiones (
  id BIGSERIAL PRIMARY KEY,
  gerencia_regional VARCHAR(120) NOT NULL DEFAULT 'CENTRO AUXILIAR',
  numero_expediente VARCHAR(80) NOT NULL,
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
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (numero_expediente)
);
CREATE INDEX IF NOT EXISTS idx_gestion_sucesiones_fecha ON gestion_sucesiones(fecha_ingreso_coordinacion DESC);
CREATE INDEX IF NOT EXISTS idx_gestion_sucesiones_estatus ON gestion_sucesiones(estatus);
CREATE INDEX IF NOT EXISTS idx_gestion_sucesiones_gerencia ON gestion_sucesiones(gerencia_regional);
COMMIT;
