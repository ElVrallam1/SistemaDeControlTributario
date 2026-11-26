BEGIN;
CREATE TABLE IF NOT EXISTS inventario_sucesiones (
  id BIGSERIAL PRIMARY KEY,
  numero_expediente VARCHAR(80) NOT NULL,
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
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (numero_expediente)
);
CREATE INDEX IF NOT EXISTS idx_inv_sucesiones_fecha ON inventario_sucesiones(fecha_solicitud DESC);
CREATE INDEX IF NOT EXISTS idx_inv_sucesiones_estatus ON inventario_sucesiones(estatus);
CREATE INDEX IF NOT EXISTS idx_inv_sucesiones_tipo ON inventario_sucesiones(tipo_caso);
COMMIT;
