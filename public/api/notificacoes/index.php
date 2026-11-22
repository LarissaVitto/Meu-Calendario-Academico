<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
$metodo = requireMethods(['GET', 'PATCH']);
$u = authenticatedStudent();
try {
    $pdo = db();
    $aluno = $u['id'];

    if ($metodo === 'GET') {
        // Lembretes são gerados ao consultar (não há serviço em segundo plano);
        // a chave única de cada evento impede repetição a cada atualização.
        if ($u['preferencias']['lembretes_ativos']) {
            gerarLembretes($pdo, $aluno, $u['preferencias']['antecedencia_dias']);
        }
        $s = $pdo->prepare(<<<'SQL'
            SELECT n.id_notificacao, n.id_atividade, n.mensagem, n.tipo, n.data_envio, n.visualizacao
            FROM notificacao n
            JOIN estudante e ON e.id_estudante = n.id_estudante
            LEFT JOIN atividade_concluida c
                   ON c.id_atividade = n.id_atividade AND c.id_estudante = n.id_estudante
            WHERE n.id_estudante = :aluno
              AND (n.tipo <> 'LEMBRETE_PRAZO' OR (e.lembretes_ativos AND c.id_estudante IS NULL))
              AND (n.tipo = 'LEMBRETE_PRAZO' OR e.avisos_alteracao)
            ORDER BY n.data_envio DESC, n.id_notificacao DESC
            LIMIT 100
        SQL);
        $s->execute(['aluno' => $aluno]);
        $lista = [];
        foreach ($s->fetchAll() as $n) {
            $lista[] = [
                'id_notificacao' => (int) $n['id_notificacao'],
                'id_atividade' => (int) $n['id_atividade'],
                'mensagem' => $n['mensagem'],
                'tipo' => $n['tipo'],
                'data_envio' => isoOuNull($n['data_envio']),
                'visualizacao' => pgBool($n['visualizacao']),
            ];
        }
        jsonResponse(200, ['ok' => true, 'notificacoes' => $lista]);
    }

    // PATCH: marcar como lida(s)
    requireCsrf();
    $body = readJsonBody();
    if (($body['todas'] ?? false) === true) {
        $s = $pdo->prepare('UPDATE notificacao SET visualizacao = TRUE WHERE id_estudante = :aluno');
        $s->execute(['aluno' => $aluno]);
    } else {
        $id = filter_var($body['id_notificacao'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            jsonResponse(422, ['ok' => false, 'mensagem' => 'ID de aviso inválido.']);
        }
        $s = $pdo->prepare('UPDATE notificacao SET visualizacao = TRUE WHERE id_estudante = :aluno AND id_notificacao = :id');
        $s->execute(['aluno' => $aluno, 'id' => $id]);
    }
    jsonResponse(200, ['ok' => true]);
} catch (Throwable $e) {
    serviceError($e);
}
