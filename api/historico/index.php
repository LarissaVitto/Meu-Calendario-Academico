<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethods(['GET']);
$u = authenticatedStudent();
$id = filter_var($_GET['id_atividade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    jsonResponse(422, ['ok' => false, 'mensagem' => 'ID de atividade inválido.']);
}
try {
    $pdo = db();
    $chk = $pdo->prepare(<<<'SQL'
        SELECT 1 FROM atividade a
        WHERE a.id_atividade = :id
          AND EXISTS (SELECT 1 FROM matricula m
                      WHERE m.id_disciplina = a.id_disciplina AND m.id_estudante = :aluno)
    SQL);
    $chk->execute(['id' => $id, 'aluno' => $u['id']]);
    if (!$chk->fetchColumn()) {
        jsonResponse(404, ['ok' => false, 'mensagem' => 'Atividade não encontrada.']);
    }
    $s = $pdo->prepare(
        'SELECT id_historico, tipo_alteracao, descricao_alteracao, data_alteracao
         FROM historico_alteracao WHERE id_atividade = :id
         ORDER BY data_alteracao DESC, id_historico DESC LIMIT 100'
    );
    $s->execute(['id' => $id]);
    $lista = [];
    foreach ($s->fetchAll() as $h) {
        $lista[] = [
            'id_historico' => (int) $h['id_historico'],
            'tipo_alteracao' => $h['tipo_alteracao'],
            'descricao_alteracao' => $h['descricao_alteracao'],
            'data_alteracao' => isoOuNull($h['data_alteracao']),
        ];
    }
    jsonResponse(200, ['ok' => true, 'historico' => $lista]);
} catch (Throwable $e) {
    serviceError($e);
}
