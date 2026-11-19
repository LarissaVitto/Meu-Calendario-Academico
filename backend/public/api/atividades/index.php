<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
$u = authenticatedAccount();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    jsonResponse(405, ['ok' => false, 'mensagem' => 'Método não permitido.']);
}
if ($method !== 'GET') {
    requireRole('PROFESSOR');
    requireCsrf();
}
function authorizedDiscipline(PDO $pdo, int $disciplina, int $professor): bool {
    $s = $pdo->prepare('SELECT 1 FROM professor_disciplina WHERE id_disciplina = :d AND id_professor = :p');
    $s->execute(['d' => $disciplina, 'p' => $professor]);
    return (bool) $s->fetchColumn();
}
function avisarMatriculados(PDO $pdo, int $idAtividade, int $idDisciplina, string $tipo, string $mensagem, string $chave): void {
    $sql = <<<'SQL'
        INSERT INTO notificacao (id_estudante, id_atividade, mensagem, tipo, chave_evento)
        SELECT DISTINCT m.id_estudante, CAST(:atividade AS INTEGER), :mensagem, :tipo, :chave
        FROM matricula m WHERE m.id_disciplina = :disciplina
        ON CONFLICT (id_estudante, chave_evento) WHERE chave_evento IS NOT NULL DO NOTHING
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['atividade' => $idAtividade, 'mensagem' => $mensagem,
                    'tipo' => $tipo, 'chave' => $chave, 'disciplina' => $idDisciplina]);
}
function parseActivity(array $body): array {
    $disc = filter_var($body['id_disciplina'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $titulo = trim(is_string($body['titulo'] ?? null) ? $body['titulo'] : '');
    $tipo = trim(is_string($body['tipo'] ?? null) ? $body['tipo'] : '');
    $descricao = trim(is_string($body['descricao'] ?? null) ? $body['descricao'] : '');
    $data = $body['data_hora'] ?? null;
    $status = $body['status'] ?? 'RASCUNHO';
    if (!$disc || $titulo === '' || mb_strlen($titulo) > 150 || $tipo === '' || mb_strlen($tipo) > 40 || mb_strlen($descricao) > 10000 || !is_string($data) || !in_array($status, ['RASCUNHO','PUBLICADO'], true)) {
        jsonResponse(422, ['ok' => false, 'mensagem' => 'Dados inválidos. Informe disciplina, título, tipo, data e status.']);
    }
    try { $dt = new DateTimeImmutable($data); } catch (Throwable) { jsonResponse(422, ['ok' => false, 'mensagem' => 'Data inválida.']); }
    if (!preg_match('/(?:Z|[+-]\d\d:\d\d)$/', $data)) jsonResponse(422, ['ok' => false, 'mensagem' => 'Informe data ISO 8601 com fuso horário.']);
    return ['id_disciplina' => (int) $disc, 'titulo' => $titulo, 'tipo' => $tipo, 'descricao' => $descricao, 'data_hora' => $dt->format(DateTimeInterface::ATOM), 'status' => $status];
}
try {
    $pdo = db();
    if ($method === 'GET') {
        if ($u['papel'] === 'PROFESSOR') {
            $sql = 'SELECT a.*, d.nome AS disciplina FROM atividade a JOIN disciplina d ON d.id_disciplina = a.id_disciplina JOIN professor_disciplina pd ON pd.id_disciplina = d.id_disciplina WHERE pd.id_professor = :id ORDER BY a.data_hora';
            $id = $u['id_professor'];
        } else {
            $sql = "SELECT DISTINCT a.*, d.nome AS disciplina FROM atividade a JOIN disciplina d ON d.id_disciplina = a.id_disciplina JOIN matricula m ON m.id_disciplina = d.id_disciplina WHERE m.id_estudante = :id AND a.status = 'PUBLICADO' ORDER BY a.data_hora";
            $id = $u['id_estudante'];
        }
        $s = $pdo->prepare($sql); $s->execute(['id' => $id]);
        jsonResponse(200, ['ok' => true, 'atividades' => $s->fetchAll()]);
    }
    $body = readJsonBody();
    if ($method === 'POST') {
        $a = parseActivity($body);
        if (!authorizedDiscipline($pdo, $a['id_disciplina'], $u['id_professor'])) jsonResponse(403, ['ok' => false, 'mensagem' => 'Disciplina não autorizada.']);
        $a['codigo_atv'] = 'MANUAL-' . bin2hex(random_bytes(12));
        $s = $pdo->prepare('INSERT INTO atividade (id_disciplina, titulo, descricao, tipo, data_hora, status, codigo_atv) VALUES (:id_disciplina, :titulo, :descricao, :tipo, :data_hora, :status, :codigo_atv) RETURNING id_atividade');
        $pdo->beginTransaction();
        $s->execute($a);
        $novoId = (int) $s->fetchColumn();
        if ($a['status'] === 'PUBLICADO') {
            avisarMatriculados($pdo, $novoId, $a['id_disciplina'], 'NOVA_ATIVIDADE',
                'Nova atividade publicada: ' . $a['titulo'], 'nova-' . $novoId);
        }
        $pdo->commit();
        jsonResponse(201, ['ok' => true, 'id_atividade' => $novoId]);
    }
    $id = filter_var($body['id_atividade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) jsonResponse(422, ['ok' => false, 'mensagem' => 'ID de atividade inválido.']);
    $s = $pdo->prepare('SELECT a.* FROM atividade a JOIN professor_disciplina pd ON pd.id_disciplina = a.id_disciplina WHERE a.id_atividade = :id AND pd.id_professor = :p');
    $s->execute(['id' => $id, 'p' => $u['id_professor']]);
    $original = $s->fetch();
    if ($original === false) jsonResponse(404, ['ok' => false, 'mensagem' => 'Atividade não encontrada ou não autorizada.']);
    if ($method === 'DELETE') {
        $s = $pdo->prepare('DELETE FROM atividade WHERE id_atividade = :id'); $s->execute(['id' => $id]);
        jsonResponse(200, ['ok' => true]);
    }
    $a = parseActivity($body);
    if (!authorizedDiscipline($pdo, $a['id_disciplina'], $u['id_professor'])) jsonResponse(403, ['ok' => false, 'mensagem' => 'Disciplina não autorizada.']);
    $a['id_atividade'] = $id;
    $pdo->beginTransaction();
    $s = $pdo->prepare('UPDATE atividade SET id_disciplina = :id_disciplina, titulo = :titulo, descricao = :descricao, tipo = :tipo, data_hora = :data_hora, status = :status, atualizado_em = CURRENT_TIMESTAMP WHERE id_atividade = :id_atividade');
    $s->execute($a);
    
    $fuso = new DateTimeZone('America/Sao_Paulo');

    $mudancas = [];

    foreach (['titulo', 'descricao', 'tipo', 'data_hora', 'status', 'id_disciplina'] as $campo) {
        $antes = (string) $original[$campo];
        $depois = (string) $a[$campo];

        if ($campo === 'data_hora') {
            $antes = (new DateTimeImmutable($antes))
                ->setTimezone($fuso)
                ->format('d/m/Y H:i');

            $depois = (new DateTimeImmutable($depois))
                ->setTimezone($fuso)
                ->format('d/m/Y H:i');
        }

        if ($antes !== $depois) {
            $mudancas[] = "$campo: $antes → $depois";
        }
    }

    if ($mudancas) {
        $hist = $pdo->prepare('INSERT INTO historico_alteracao (id_atividade, descricao_alteracao) VALUES (:id, :descricao)');
        $hist->execute(['id' => $id, 'descricao' => implode('; ', $mudancas)]);
        if ($a['status'] === 'PUBLICADO') {
            $tipoAviso = $original['status'] !== 'PUBLICADO' ? 'NOVA_ATIVIDADE' : 'ALTERACAO_ATIVIDADE';
            $msg = $tipoAviso === 'NOVA_ATIVIDADE' ? 'Atividade publicada: ' : 'Atividade atualizada: ';
            avisarMatriculados($pdo, $id, $a['id_disciplina'], $tipoAviso, $msg . $a['titulo'],
                'alteracao-' . $id . '-' . bin2hex(random_bytes(8)));
        }
    }
    $pdo->commit();
    jsonResponse(200, ['ok' => true]);
} catch (Throwable $e) { serviceError($e); }
