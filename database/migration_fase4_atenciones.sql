BEGIN;
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
COMMIT;
