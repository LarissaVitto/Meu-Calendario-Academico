-- ============================================================================
-- Meu Calendário Acadêmico (UTFPR - Campus Dois Vizinhos)
-- Esquema do banco (PostgreSQL 12+)
--
-- Modelo: o professor altera o planejamento no Moodle; o sistema sincroniza
-- essas alterações (tabela "atividade") e o estudante apenas consulta o
-- calendário e marca o que já fez (tabela "atividade_concluida").
-- Não existe publicação de atividades dentro do sistema.
--
-- Uso: php scripts/instalar.php   (cria tudo e carrega os dados de exemplo)
-- ============================================================================

-- 1. ESTUDANTE: credenciais, perfil e preferências de lembretes/avisos (RNF01, RNF02, HU1, HU2, HU11)
CREATE TABLE estudante (
    id_estudante      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome              VARCHAR(120) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    senha             VARCHAR(255) NOT NULL,                 -- somente hash (password_hash)
    curso             VARCHAR(80)  NOT NULL DEFAULT 'Engenharia de Software',
    lembretes_ativos  BOOLEAN      NOT NULL DEFAULT TRUE,
    antecedencia_dias SMALLINT     NOT NULL DEFAULT 3 CHECK (antecedencia_dias BETWEEN 1 AND 14),
    avisos_alteracao  BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em         TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX uq_estudante_email ON estudante (LOWER(email));

-- 2. DISCIPLINA: criada/atualizada pela sincronização com o planejamento do Moodle (HU3)
CREATE TABLE disciplina (
    id_disciplina INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome          VARCHAR(150) NOT NULL,
    codigo_disc   VARCHAR(30)  NOT NULL,
    professor     VARCHAR(120)                               -- apenas informativo (vem do Moodle)
);
CREATE UNIQUE INDEX uq_disciplina_codigo ON disciplina (UPPER(codigo_disc));

-- 3. MATRICULA: N:N entre estudante e disciplina por período letivo (HU3)
CREATE TABLE matricula (
    id_matricula  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_estudante  INTEGER  NOT NULL REFERENCES estudante(id_estudante)  ON DELETE CASCADE,
    id_disciplina INTEGER  NOT NULL REFERENCES disciplina(id_disciplina) ON DELETE CASCADE,
    semestre      SMALLINT NOT NULL CHECK (semestre IN (1, 2)),
    ano           SMALLINT NOT NULL CHECK (ano >= 2020 AND ano <= 2100),
    CONSTRAINT uq_matricula_periodo UNIQUE (id_estudante, id_disciplina, ano, semestre)
);

-- 4. ATIVIDADE: espelho do planejamento do Moodle (HU4, HU5, HU7, HU9)
--    codigo_atv é o identificador estável da linha no planejamento; é ele que
--    permite detectar alteração (em vez de criar duplicata) - RNF05, RNF11.
CREATE TABLE atividade (
    id_atividade  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_disciplina INTEGER      NOT NULL REFERENCES disciplina(id_disciplina) ON DELETE CASCADE,
    codigo_atv    VARCHAR(50)  NOT NULL,
    titulo        VARCHAR(150) NOT NULL,
    descricao     TEXT         NOT NULL DEFAULT '',
    tipo          VARCHAR(40)  NOT NULL,
    data_hora     TIMESTAMPTZ  NOT NULL,
    data_anterior TIMESTAMPTZ,                               -- data antes da última mudança de data
    alterada_em   TIMESTAMPTZ,                               -- última alteração detectada na sincronização
    removida_em   TIMESTAMPTZ,                               -- preenchido quando a linha some do planejamento
    origem        VARCHAR(10)  NOT NULL DEFAULT 'MOODLE',    -- 'MOODLE' (sincronização) ou 'PDF' (importada de PDF)
    criado_em     TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_atividade_disciplina_codigo UNIQUE (id_disciplina, codigo_atv)
);

-- 5. ATIVIDADE_CONCLUIDA: o que cada estudante marcou como feito
CREATE TABLE atividade_concluida (
    id_estudante INTEGER     NOT NULL REFERENCES estudante(id_estudante) ON DELETE CASCADE,
    id_atividade INTEGER     NOT NULL REFERENCES atividade(id_atividade) ON DELETE CASCADE,
    concluida_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_estudante, id_atividade)
);

-- 6. SINCRONIZACAO: registro de cada execução, por disciplina (HU9, HU10, RNF05)
--    execucao agrupa as linhas de uma mesma rodada; id_disciplina fica nulo em falha geral.
CREATE TABLE sincronizacao (
    id_sincronizacao INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    execucao         VARCHAR(32) NOT NULL,
    id_disciplina    INTEGER REFERENCES disciplina(id_disciplina) ON DELETE CASCADE,
    data_execucao    TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resultado        VARCHAR(10) NOT NULL CHECK (resultado IN ('SUCESSO', 'FALHA', 'PARCIAL')),
    novas            SMALLINT NOT NULL DEFAULT 0,
    alteradas        SMALLINT NOT NULL DEFAULT 0,
    removidas        SMALLINT NOT NULL DEFAULT 0,
    iguais           SMALLINT NOT NULL DEFAULT 0,
    detalhes         TEXT,
    origem           VARCHAR(12) NOT NULL DEFAULT 'manual'
);

-- 7. HISTORICO_ALTERACAO: auditoria das mudanças detectadas no planejamento (HU10)
CREATE TABLE historico_alteracao (
    id_historico        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_atividade        INTEGER     NOT NULL REFERENCES atividade(id_atividade) ON DELETE CASCADE,
    tipo_alteracao      VARCHAR(12) NOT NULL CHECK (tipo_alteracao IN ('CRIADA', 'DATA', 'INFO', 'REMOVIDA', 'REINSERIDA')),
    descricao_alteracao TEXT        NOT NULL,
    data_alteracao      TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 8. NOTIFICACAO: lembretes de prazo e avisos de alteração (HU11, HU12)
--    (id_estudante, chave_evento) é único: o mesmo evento nunca notifica duas vezes.
CREATE TABLE notificacao (
    id_notificacao INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_estudante   INTEGER      NOT NULL REFERENCES estudante(id_estudante) ON DELETE CASCADE,
    id_atividade   INTEGER      NOT NULL REFERENCES atividade(id_atividade) ON DELETE CASCADE,
    mensagem       TEXT         NOT NULL,
    tipo           VARCHAR(24)  NOT NULL CHECK (tipo IN ('LEMBRETE_PRAZO', 'ALTERACAO_DATA', 'ALTERACAO_ATIVIDADE', 'NOVA_ATIVIDADE', 'ATIVIDADE_REMOVIDA')),
    chave_evento   VARCHAR(120) NOT NULL,
    data_envio     TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visualizacao   BOOLEAN      NOT NULL DEFAULT FALSE,
    CONSTRAINT uq_notificacao_evento UNIQUE (id_estudante, chave_evento)
);

-- Índices de desempenho (RNF03)
CREATE INDEX idx_matricula_estudante   ON matricula(id_estudante);
CREATE INDEX idx_matricula_disciplina  ON matricula(id_disciplina);
CREATE INDEX idx_atividade_disciplina  ON atividade(id_disciplina);
CREATE INDEX idx_atividade_data_hora   ON atividade(data_hora);
CREATE INDEX idx_notificacao_estudante ON notificacao(id_estudante, visualizacao);
CREATE INDEX idx_historico_atividade   ON historico_alteracao(id_atividade);
CREATE INDEX idx_sincronizacao_data    ON sincronizacao(data_execucao);
