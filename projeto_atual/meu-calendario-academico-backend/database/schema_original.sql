-- ============================================================================
-- PROJETO: Meu Calendário Acadêmico (UTFPR - Campus Dois Vizinhos)
-- ARQUIVO: schema.sql (DDL - Estrutura do Banco de Dados)
-- SGBD: PostgreSQL
-- ============================================================================

-- Limpeza segura para recriação do banco em ambiente de desenvolvimento/testes
DROP TABLE IF EXISTS notificacao CASCADE;
DROP TABLE IF EXISTS lembrete CASCADE;
DROP TABLE IF EXISTS historico_alteracao CASCADE;
DROP TABLE IF EXISTS sincronizacao CASCADE;
DROP TABLE IF EXISTS atividade CASCADE;
DROP TABLE IF EXISTS matricula CASCADE;
DROP TABLE IF EXISTS disciplina CASCADE;
DROP TABLE IF EXISTS estudante CASCADE;

-- ============================================================================
-- 1. TABELA ESTUDANTE
-- Armazena os dados básicos e credenciais dos estudantes da UTFPR (RNF01, RNF02)
-- ============================================================================
CREATE TABLE estudante (
    id_estudante INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL, -- Armazenar sempre o hash (ex: password_hash no PHP)
    criado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- 2. TABELA DISCIPLINA
-- Armazena as disciplinas cadastradas ou importadas dos planejamentos
-- ============================================================================
CREATE TABLE disciplina (
    id_disciplina INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    codigo_disc VARCHAR(30) NOT NULL UNIQUE
);

-- ============================================================================
-- 3. TABELA MATRICULA
-- Relacionamento N:N entre Estudante e Disciplina por período letivo
-- ============================================================================
CREATE TABLE matricula (
    id_matricula INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_estudante INTEGER NOT NULL,
    id_disciplina INTEGER NOT NULL,
    semestre SMALLINT NOT NULL CHECK (semestre IN (1, 2)),
    ano SMALLINT NOT NULL CHECK (ano >= 2020 AND ano <= 2100),

    CONSTRAINT fk_matricula_estudante
        FOREIGN KEY (id_estudante) REFERENCES estudante(id_estudante)
        ON DELETE CASCADE,
    CONSTRAINT fk_matricula_disciplina
        FOREIGN KEY (id_disciplina) REFERENCES disciplina(id_disciplina)
        ON DELETE CASCADE,
    -- Impede matrícula duplicada do mesmo aluno na mesma disciplina no mesmo período
    CONSTRAINT uq_matricula_periodo
        UNIQUE (id_estudante, id_disciplina, ano, semestre)
);

-- ============================================================================
-- 4. TABELA ATIVIDADE
-- Armazena provas, trabalhos, entregas e demais compromissos das disciplinas
-- ============================================================================
CREATE TABLE atividade (
    id_atividade INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_disciplina INTEGER NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT,
    tipo VARCHAR(40) NOT NULL, -- Ex: 'Prova', 'Trabalho', 'Entrega', 'Seminário'
    data_hora TIMESTAMPTZ NOT NULL,
    codigo_atv VARCHAR(50) NOT NULL,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_atividade_disciplina
        FOREIGN KEY (id_disciplina) REFERENCES disciplina(id_disciplina)
        ON DELETE CASCADE,
    -- Evita duplicação de atividades durante a sincronização (RNF05, RNF11, HU9)
    CONSTRAINT uq_atividade_disciplina_codigo
        UNIQUE (id_disciplina, codigo_atv)
);

-- ============================================================================
-- 5. TABELA SINCRONIZACAO
-- Registra logs de execução das importações/sincronizações por disciplina (HU9, HU10)
-- Nota: PK ajustada para id_sincronizacao para evitar conflito semântico com atividade
-- ============================================================================
CREATE TABLE sincronizacao (
    id_sincronizacao INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_disciplina INTEGER NOT NULL,
    data_execucao TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resultado VARCHAR(30) NOT NULL CHECK (resultado IN ('SUCESSO', 'FALHA', 'PARCIAL')),
    detalhes TEXT,

    CONSTRAINT fk_sincronizacao_disciplina
        FOREIGN KEY (id_disciplina) REFERENCES disciplina(id_disciplina)
        ON DELETE CASCADE
);

-- ============================================================================
-- 6. TABELA HISTORICO_ALTERACAO
-- Auditoria de mudanças de datas ou informações nas atividades (HU10)
-- ============================================================================
CREATE TABLE historico_alteracao (
    id_historico INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_atividade INTEGER NOT NULL,
    descricao_alteracao TEXT NOT NULL,
    data_alteracao TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_historico_atividade
        FOREIGN KEY (id_atividade) REFERENCES atividade(id_atividade)
        ON DELETE CASCADE
);

-- ============================================================================
-- 7. TABELA LEMBRETE
-- Configurações de alertas programados pelos estudantes para atividades (HU11)
-- ============================================================================
CREATE TABLE lembrete (
    id_lembrete INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_estudante INTEGER NOT NULL,
    id_atividade INTEGER NOT NULL,
    data_lembrete TIMESTAMPTZ NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT fk_lembrete_estudante
        FOREIGN KEY (id_estudante) REFERENCES estudante(id_estudante)
        ON DELETE CASCADE,
    CONSTRAINT fk_lembrete_atividade
        FOREIGN KEY (id_atividade) REFERENCES atividade(id_atividade)
        ON DELETE CASCADE
);

-- ============================================================================
-- 8. TABELA NOTIFICACAO
-- Avisos enviados aos estudantes sobre prazos próximos e alterações (HU11, HU12)
-- ============================================================================
CREATE TABLE notificacao (
    id_notificacao INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_estudante INTEGER NOT NULL,
    id_atividade INTEGER NOT NULL,
    mensagem TEXT NOT NULL,
    tipo VARCHAR(40) NOT NULL, -- Ex: 'LEMBRETE_PRAZO', 'ALTERACAO_DATA', 'NOVA_ATIVIDADE'
    data_envio TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visualizacao BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT fk_notificacao_estudante
        FOREIGN KEY (id_estudante) REFERENCES estudante(id_estudante)
        ON DELETE CASCADE,
    CONSTRAINT fk_notificacao_atividade
        FOREIGN KEY (id_atividade) REFERENCES atividade(id_atividade)
        ON DELETE CASCADE
);

-- ============================================================================
-- ÍNDICES DE DESEMPENHO (RNF03 - Consultas do calendário < 2 segundos)
-- ============================================================================
CREATE INDEX idx_matricula_estudante ON matricula(id_estudante);
CREATE INDEX idx_matricula_disciplina ON matricula(id_disciplina);
CREATE INDEX idx_atividade_disciplina ON atividade(id_disciplina);
CREATE INDEX idx_atividade_data_hora ON atividade(data_hora);
CREATE INDEX idx_notificacao_estudante_lida ON notificacao(id_estudante, visualizacao);
CREATE INDEX idx_lembrete_ativo_data ON lembrete(ativo, data_lembrete);
CREATE INDEX idx_historico_atividade ON historico_alteracao(id_atividade);
CREATE INDEX idx_sincronizacao_disciplina ON sincronizacao(id_disciplina);
