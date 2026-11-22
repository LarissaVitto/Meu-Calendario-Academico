<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
// As atividades vêm do planejamento do Moodle (sincronização): aqui o estudante
// só consulta e marca o que já concluiu. Não há criação/edição/exclusão.
$metodo = requireMethods(['GET', 'PATCH']);
$u = authenticatedStudent();
try {
    $pdo = db();

    if ($metodo === 'GET') {
        $s = $pdo->prepare(<<<'SQL'
            SELECT a.id_atividade, a.id_disciplina, a.codigo_atv, a.titulo, a.descricao, a.tipo,
                   a.data_hora, a.data_anterior, a.alterada_em, c.concluida_em
            FROM atividade a
            LEFT JOIN atividade_concluida c
                   ON c.id_atividade = a.id_atividade AND c.id_estudante = :aluno1
            WHERE a.removida_em IS NULL
              AND EXISTS (SELECT 1 FROM matricula m
                          WHERE m.id_disciplina = a.id_disciplina AND m.id_estudante = :aluno2)
            ORDER BY a.data_hora, a.id_atividade
        SQL);
        $s->execute(['aluno1' => $u['id'], 'aluno2' => $u['id']]);
        $lista = [];
        foreach ($s->fetchAll() as $a) {
            $lista[] = [
                'id_atividade' => (int) $a['id_atividade'],
                'id_disciplina' => (int) $a['id_disciplina'],
                'codigo_atv' => $a['codigo_atv'],
                'titulo' => $a['titulo'],
                'descricao' => $a['descricao'],
                'tipo' => $a['tipo'],
                'data_hora' => isoOuNull($a['data_hora']),
                'data_anterior' => isoOuNull($a['data_anterior']),
                'alterada_em' => isoOuNull($a['alterada_em']),
                'concluida' => $a['concluida_em'] !== null,
                'concluida_em' => isoOuNull($a['concluida_em']),
            ];
        }
        jsonResponse(200, ['ok' => true, 'atividades' => $lista]);
    }

    // PATCH: marcar / desmarcar como concluída
    requireCsrf();
    $body = readJsonBody();
    $id = filter_var($body['id_atividade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id || !is_bool($body['concluida'] ?? null)) {
        jsonResponse(422, ['ok' => false, 'mensagem' => 'Informe id_atividade e concluida (true/false).']);
    }
    $chk = $pdo->prepare(<<<'SQL'
        SELECT 1 FROM atividade a
        WHERE a.id_atividade = :id AND a.removida_em IS NULL
          AND EXISTS (SELECT 1 FROM matricula m
                      WHERE m.id_disciplina = a.id_disciplina AND m.id_estudante = :aluno)
    SQL);
    $chk->execute(['id' => $id, 'aluno' => $u['id']]);
    if (!$chk->fetchColumn()) {
        jsonResponse(404, ['ok' => false, 'mensagem' => 'Atividade não encontrada.']);
    }
    if ($body['concluida'] === true) {
        $pdo->prepare('INSERT INTO atividade_concluida (id_estudante, id_atividade) VALUES (:aluno, :id)
                       ON CONFLICT (id_estudante, id_atividade) DO NOTHING')
            ->execute(['aluno' => $u['id'], 'id' => $id]);
    } else {
        $pdo->prepare('DELETE FROM atividade_concluida WHERE id_estudante = :aluno AND id_atividade = :id')
            ->execute(['aluno' => $u['id'], 'id' => $id]);
    }
    $q = $pdo->prepare('SELECT concluida_em FROM atividade_concluida WHERE id_estudante = :aluno AND id_atividade = :id');
    $q->execute(['aluno' => $u['id'], 'id' => $id]);
    $em = $q->fetchColumn();
    jsonResponse(200, [
        'ok' => true,
        'concluida' => $em !== false,
        'concluida_em' => $em === false ? null : isoOuNull((string) $em),
    ]);
} catch (Throwable $e) {
    serviceError($e);
}
