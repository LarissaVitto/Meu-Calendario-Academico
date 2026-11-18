-- Execute UMA vez no Query Tool do banco meu_calendario_academico.
-- Não apaga registros existentes.
ALTER TABLE atividade ADD COLUMN IF NOT EXISTS status VARCHAR(12) NOT NULL DEFAULT 'PUBLICADO';
ALTER TABLE atividade DROP CONSTRAINT IF EXISTS ck_atividade_status;
ALTER TABLE atividade ADD CONSTRAINT ck_atividade_status CHECK (status IN ('RASCUNHO', 'PUBLICADO'));
CREATE INDEX IF NOT EXISTS idx_atividade_status_data ON atividade(status, data_hora);
