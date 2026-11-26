BEGIN;
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

-- Migrate existing control_diario rows if table exists and reporte_diario empty
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='control_diario') THEN
    INSERT INTO reporte_diario (fecha, usuario_id, dependencia, orientaciones, asistencia_contribuyentes, sucesiones_ingresadas, solvencias_sucesiones_entregadas, multas_notificadas, observaciones, created_at, updated_at)
    SELECT c.fecha, c.usuario_id, COALESCE(u.department,'Plaza Venezuela'),
           c.requerimientos_atendidos, 0, c.expedientes_ingresados, c.solvencias_emitidas, c.multas_registradas,
           CONCAT('[Migrado desde control_diario] ', COALESCE(c.observaciones,'')),
           c.created_at, c.updated_at
    FROM control_diario c LEFT JOIN users u ON u.id=c.usuario_id
    ON CONFLICT (fecha, dependencia) DO NOTHING;
    ALTER TABLE control_diario RENAME TO control_diario_legacy;
  END IF;
END $$;
COMMIT;
