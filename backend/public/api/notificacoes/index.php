<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
$usuario = requireRole('ALUNO');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'PATCH'], true)) {
    header('Allow: GET, PATCH');
    jsonResponse(405, ['ok' => false, 'mensagem' => 'Método não permitido.']);
}
try {
    $pdo = db();
    $aluno = $usuario['id_estudante'];
    if ($metodo === 'GET') {
        // Lembretes gerados ao consultar o sistema; não há serviço em segundo plano.
        // Chave única impede duplicações a cada atualização da página.
        $pdo->prepare(<<<'SQL'
            INSERT INTO notificacao (id_estudante, id_atividade, mensagem, tipo, chave_evento)
            SELECT DISTINCT :aluno, a.id_atividade,
                   'Prazo próximo: ' || a.titulo || ' (' || d.nome || ')',
                   'LEMBRETE_PRAZO',
                   'prazo-' || a.id_atividade || '-' || to_char(a.data_hora AT TIME ZONE 'America/Sao_Paulo', 'YYYYMMDDHH24MI')
            FROM atividade a
            JOIN disciplina d ON d.id_disciplina = a.id_disciplina
            JOIN matricula m ON m.id_disciplina = a.id_disciplina AND m.id_estudante = :aluno
            WHERE a.status = 'PUBLICADO'
              AND a.data_hora >= CURRENT_TIMESTAMP
              AND a.data_hora <= CURRENT_TIMESTAMP + INTERVAL '3 days'
            ON CONFLICT (id_estudante, chave_evento) WHERE chave_evento IS NOT NULL DO NOTHING
        SQL)->execute(['aluno' => $aluno]);
        $s = $pdo->prepare(<<<'SQL'
            SELECT n.id_notificacao, n.id_atividade, n.mensagem, n.tipo,
                   n.data_envio, n.visualizacao
            FROM notificacao n
            JOIN atividade a ON a.id_atividade = n.id_atividade
            JOIN matricula m ON m.id_disciplina = a.id_disciplina AND m.id_estudante = :aluno
            WHERE n.id_estudante = :aluno AND a.status = 'PUBLICADO'
            ORDER BY n.data_envio DESC, n.id_notificacao DESC LIMIT 100
        SQL);
        $s->execute(['aluno' => $aluno]);
        jsonResponse(200, ['ok' => true, 'notificacoes' => $s->fetchAll()]);
    }
    requireCsrf();
    $body = readJsonBody();
    if (($body['todas'] ?? false) === true) {
        $s = $pdo->prepare('UPDATE notificacao SET visualizacao = TRUE WHERE id_estudante = :aluno');
        $s->execute(['aluno' => $aluno]);
    } else {
        $id = filter_var($body['id_notificacao'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) jsonResponse(422, ['ok' => false, 'mensagem' => 'ID de aviso inválido.']);
        $s = $pdo->prepare('UPDATE notificacao SET visualizacao = TRUE WHERE id_estudante = :aluno AND id_notificacao = :id');
        $s->execute(['aluno' => $aluno, 'id' => $id]);
    }
    jsonResponse(200, ['ok' => true]);
} catch (Throwable $e) {
    serviceError($e);
}
