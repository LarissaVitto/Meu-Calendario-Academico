-- ============================================================================
-- PROJETO: Meu Calendário Acadêmico (UTFPR - Campus Dois Vizinhos)
-- ARQUIVO: seed.sql (Dados Iniciais para Desenvolvimento e Testes - DEV/QA)
-- SGBD: PostgreSQL
-- ============================================================================

BEGIN;

-- 1. ESTUDANTES
-- Nota: Os hashes abaixo são exemplos simulados de bcrypt ($2y$10$...)
INSERT INTO estudante (nome, email, senha) VALUES
('Vitor Alexandre Spanholi Pinto', 'vitor.pinto@alunos.utfpr.edu.br', '$2y$10$ExemploHashSenhaSegura1234567890abcdefghijklmno'),
('Gabriel de Lima Pereira', 'gabriel.pereira@alunos.utfpr.edu.br', '$2y$10$ExemploHashSenhaSegura0987654321zyxwvutsrqponml'),
('Larissa Regina Costa Vitto', 'larissa.vitto@alunos.utfpr.edu.br', '$2y$10$ExemploHashSenhaSegura1122334455aabbccddeeffggh');

-- 2. DISCIPLINAS
INSERT INTO disciplina (nome, codigo_disc) VALUES
('Arquitetura de Software', 'ES64A'),
('Banco de Dados 2', 'ES64B'),
('Engenharia de Requisitos', 'ES63C'),
('Redes de Computadores', 'ES64D');

-- 3. MATRÍCULAS (Semestre 2 de 2026)
INSERT INTO matricula (id_estudante, id_disciplina, semestre, ano) VALUES
(1, 1, 2, 2026), -- Vitor em Arquitetura de Software
(1, 2, 2, 2026), -- Vitor em Banco de Dados 2
(1, 3, 2, 2026), -- Vitor em Engenharia de Requisitos
(2, 1, 2, 2026), -- Gabriel em Arquitetura de Software
(2, 4, 2, 2026), -- Gabriel em Redes de Computadores
(3, 1, 2, 2026), -- Larissa em Arquitetura de Software
(3, 2, 2, 2026); -- Larissa em Banco de Dados 2

-- 4. ATIVIDADES ACADÊMICAS
INSERT INTO atividade (id_disciplina, titulo, descricao, tipo, data_hora, codigo_atv) VALUES
(1, 'Entrega 1 - Visão Geral e Trello', 'Documento inicial com descrição do problema, demanda e organização da equipe.', 'Entrega', '2026-10-15 23:59:00-03', 'ES64A-ENT01'),
(1, 'Entrega 2 - Decisões Arquiteturais e DER', 'Modelagem C4, UML, DER e justificativa da arquitetura monolítica em camadas.', 'Entrega', '2026-11-20 23:59:00-03', 'ES64A-ENT02'),
(1, 'Prova 1 - Estilos Arquiteturais', 'Avaliação escrita sobre Client-Server, Camadas, MVC e Atributos de Qualidade.', 'Prova', '2026-11-27 19:30:00-03', 'ES64A-PRV01'),
(2, 'Trabalho Prático - Normalização e Índices', 'Implementação de esquema físico e análise de planos de execução no PostgreSQL.', 'Trabalho', '2026-11-24 23:59:00-03', 'ES64B-TRB01'),
(2, 'Prova 1 - Controle de Concorrência e Transações', 'Avaliação sobre propriedades ACID, bloqueios e níveis de isolamento.', 'Prova', '2026-12-02 21:10:00-03', 'ES64B-PRV01'),
(3, 'Seminário - Elicitação e Histórias de Usuário', 'Apresentação em grupo sobre épicos, critérios de aceitação e RNFs.', 'Seminário', '2026-11-18 19:30:00-03', 'ES63C-SEM01'),
(4, 'Laboratório - Configuração de Roteamento', 'Prática guiada de sub-redes e protocolos de roteamento dinâmico.', 'Trabalho', '2026-11-25 22:00:00-03', 'ES64D-LAB01');

-- 5. SINCRONIZAÇÕES
INSERT INTO sincronizacao (id_disciplina, data_execucao, resultado, detalhes) VALUES
(1, '2026-11-10 08:00:00-03', 'SUCESSO', 'Importação concluída via arquivo .ics institucional. 3 atividades processadas.'),
(2, '2026-11-12 14:30:00-03', 'SUCESSO', 'Sincronização via CSV do plano de ensino. 2 atividades atualizadas.'),
(4, '2026-11-15 09:15:00-03', 'FALHA', 'Timeout ao consultar o portal institucional da UTFPR. Dados anteriores preservados.');

-- 6. HISTÓRICO DE ALTERAÇÕES
INSERT INTO historico_alteracao (id_atividade, descricao_alteracao, data_alteracao) VALUES
(2, 'Data de entrega alterada de 2026-11-16 23:59 para 2026-11-20 23:59 pelo professor.', '2026-11-11 10:20:00-03'),
(3, 'Sala da avaliação atualizada na descrição: Bloco C - Sala 104.', '2026-11-14 16:05:00-03');

-- 7. LEMBRETES
INSERT INTO lembrete (id_estudante, id_atividade, data_lembrete, ativo) VALUES
(1, 2, '2026-11-19 18:00:00-03', TRUE),
(1, 3, '2026-11-26 09:00:00-03', TRUE),
(2, 2, '2026-11-19 20:00:00-03', TRUE),
(3, 4, '2026-11-23 14:00:00-03', FALSE);

-- 8. NOTIFICAÇÕES
INSERT INTO notificacao (id_estudante, id_atividade, mensagem, tipo, data_envio, visualizacao) VALUES
(1, 2, 'A entrega "Entrega 2 - Decisões Arquiteturais e DER" teve sua data alterada para 20/11/2026 às 23:59.', 'ALTERACAO_DATA', '2026-11-11 10:21:00-03', TRUE),
(2, 2, 'A entrega "Entrega 2 - Decisões Arquiteturais e DER" teve sua data alterada para 20/11/2026 às 23:59.', 'ALTERACAO_DATA', '2026-11-11 10:21:00-03', FALSE),
(3, 2, 'A entrega "Entrega 2 - Decisões Arquiteturais e DER" teve sua data alterada para 20/11/2026 às 23:59.', 'ALTERACAO_DATA', '2026-11-11 10:21:00-03', FALSE),
(1, 6, 'Lembrete: O seminário "Seminário - Elicitação e Histórias de Usuário" acontece amanhã às 19:30.', 'LEMBRETE_PRAZO', '2026-11-17 08:00:00-03', FALSE);

COMMIT;
