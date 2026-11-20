<?php
declare(strict_types=1);

function avisarMatriculados(
    PDO $pdo,
    int $idAtividade,
    int $idDisciplina,
    string $tipo,
    string $mensagem,
    string $chave
): void {
    $sql = <<<'SQL'
        INSERT INTO notificacao
            (id_estudante, id_atividade, mensagem, tipo, chave_evento)
        SELECT DISTINCT
            m.id_estudante,
            CAST(:atividade AS INTEGER),
            :mensagem,
            :tipo,
            :chave
        FROM matricula m
        WHERE m.id_disciplina = :disciplina
        ON CONFLICT (id_estudante, chave_evento)
        WHERE chave_evento IS NOT NULL
        DO NOTHING
    SQL;

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'atividade' => $idAtividade,
        'mensagem' => $mensagem,
        'tipo' => $tipo,
        'chave' => $chave,
        'disciplina' => $idDisciplina
    ]);
}
