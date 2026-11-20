<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';

$usuario = authenticatedAccount();
requireRole('ALUNO');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($metodo, ['POST', 'DELETE'], true)) {
    header('Allow: POST, DELETE');
    jsonResponse(405, [
        'ok' => false,
        'mensagem' => 'Método não permitido.'
    ]);
}

requireCsrf();

try {
    $pdo = db();
    $dados = readJsonBody();

    $idAtividade = filter_var(
        $dados['id_atividade'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$idAtividade) {
        jsonResponse(422, [
            'ok' => false,
            'mensagem' => 'ID da atividade inválido.'
        ]);
    }

    $idEstudante = (int) $usuario['id_estudante'];

    // Confirma se a atividade pertence a uma disciplina
    // em que o estudante está matriculado e está publicada.
    $sql = "
        SELECT 1
        FROM atividade a
        INNER JOIN matricula m
            ON m.id_disciplina = a.id_disciplina
        WHERE a.id_atividade = :atividade
          AND m.id_estudante = :estudante
          AND a.status = 'PUBLICADO'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'atividade' => $idAtividade,
        'estudante' => $idEstudante
    ]);

    if (!$stmt->fetchColumn()) {
        jsonResponse(404, [
            'ok' => false,
            'mensagem' => 'Atividade não encontrada ou não autorizada.'
        ]);
    }

    if ($metodo === 'POST') {
        $sql = "
            INSERT INTO atividade_conclusao
                (id_estudante, id_atividade)
            VALUES
                (:estudante, :atividade)
            ON CONFLICT (id_estudante, id_atividade)
            DO NOTHING
        ";

        $mensagem = 'Atividade marcada como concluída.';
        $concluida = true;
    } else {
        $sql = "
            DELETE FROM atividade_conclusao
            WHERE id_estudante = :estudante
              AND id_atividade = :atividade
        ";

        $mensagem = 'Conclusão da atividade desfeita.';
        $concluida = false;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'estudante' => $idEstudante,
        'atividade' => $idAtividade
    ]);

    jsonResponse(200, [
        'ok' => true,
        'mensagem' => $mensagem,
        'id_atividade' => $idAtividade,
        'concluida' => $concluida
    ]);

} catch (Throwable $e) {
    serviceError($e);
}
