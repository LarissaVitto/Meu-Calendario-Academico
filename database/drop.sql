-- Remove TODAS as tabelas do projeto (inclusive as do modelo antigo, com professor/conta).
-- Usado apenas por: php scripts/instalar.php --recriar
DROP TABLE IF EXISTS notificacao, atividade_concluida, historico_alteracao, sincronizacao,
    atividade, matricula, disciplina, estudante,
    lembrete, professor_disciplina, professor, conta CASCADE;
