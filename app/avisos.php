<?php
declare(strict_types=1);

/**
 * Notificações (HU11, HU12).
 * Cada notificação tem uma chave_evento única por estudante, então o mesmo
 * evento nunca é registrado (nem exibido) duas vezes.
 */

/** Avisa todos os estudantes matriculados na disciplina. */
function notificarMatriculados(PDO $pdo, int $idAtividade, int $idDisciplina, string $tipo, string $mensagem, string $chave): void
{
    $stmt = $pdo->prepare(<<<'SQL'
        INSERT INTO notificacao (id_estudante, id_atividade, mensagem, tipo, chave_evento)
        SELECT m.id_estudante,
               CAST(:atividade AS INTEGER),
               CAST(:mensagem AS TEXT),
               CAST(:tipo AS VARCHAR(24)),
               CAST(:chave AS VARCHAR(120))
        FROM matricula m
        WHERE m.id_disciplina = CAST(:disciplina AS INTEGER)
        ON CONFLICT (id_estudante, chave_evento) DO NOTHING
    SQL);
    $stmt->execute([
        'atividade' => $idAtividade,
        'mensagem' => $mensagem,
        'tipo' => $tipo,
        'chave' => $chave,
        'disciplina' => $idDisciplina,
    ]);
}

/**
 * Gera os lembretes de prazo do estudante: atividades futuras, dentro da
 * antecedência escolhida, ainda não concluídas. Roda quando o estudante
 * consulta os avisos (não há serviço em segundo plano).
 */
function gerarLembretes(PDO $pdo, int $idEstudante, int $dias): void
{
    $stmt = $pdo->prepare(<<<'SQL'
        INSERT INTO notificacao (id_estudante, id_atividade, mensagem, tipo, chave_evento)
        SELECT CAST(:aluno1 AS INTEGER),
               a.id_atividade,
               'Prazo próximo: ' || a.titulo || ' (' || d.nome || ')',
               'LEMBRETE_PRAZO',
               'prazo-' || a.id_atividade || '-' || to_char(a.data_hora AT TIME ZONE 'America/Sao_Paulo', 'YYYYMMDDHH24MI')
        FROM atividade a
        JOIN disciplina d ON d.id_disciplina = a.id_disciplina
        WHERE a.removida_em IS NULL
          AND a.data_hora >= CURRENT_TIMESTAMP
          AND a.data_hora <= CURRENT_TIMESTAMP + (CAST(:dias AS INTEGER) * INTERVAL '1 day')
          AND EXISTS (SELECT 1 FROM matricula m
                      WHERE m.id_disciplina = a.id_disciplina AND m.id_estudante = CAST(:aluno2 AS INTEGER))
          AND NOT EXISTS (SELECT 1 FROM atividade_concluida c
                          WHERE c.id_atividade = a.id_atividade AND c.id_estudante = CAST(:aluno3 AS INTEGER))
        ON CONFLICT (id_estudante, chave_evento) DO NOTHING
    SQL);
    $stmt->execute([
        'aluno1' => $idEstudante,
        'aluno2' => $idEstudante,
        'aluno3' => $idEstudante,
        'dias' => $dias,
    ]);
}
