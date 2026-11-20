<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/notificacoes.php';

$u = authenticatedAccount();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');

    jsonResponse(405, [
        'ok' => false,
        'mensagem' => 'Método não permitido.'
    ]);
}

if ($method !== 'GET') {
    requireRole('PROFESSOR');
    requireCsrf();
}

/**
 * Verifica se o professor está autorizado
 * a gerenciar a disciplina.
 */
function authorizedDiscipline(
    PDO $pdo,
    int $disciplina,
    int $professor
): bool {
    $s = $pdo->prepare(
        'SELECT 1
         FROM professor_disciplina
         WHERE id_disciplina = :d
           AND id_professor = :p'
    );

    $s->execute([
        'd' => $disciplina,
        'p' => $professor
    ]);

    return (bool) $s->fetchColumn();
}

/**
 * Valida os dados de uma atividade.
 */
function parseActivity(array $body): array {
    $disc = filter_var(
        $body['id_disciplina'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $titulo = trim(
        is_string($body['titulo'] ?? null)
            ? $body['titulo']
            : ''
    );

    $tipo = trim(
        is_string($body['tipo'] ?? null)
            ? $body['tipo']
            : ''
    );

    $descricao = trim(
        is_string($body['descricao'] ?? null)
            ? $body['descricao']
            : ''
    );

    $data = $body['data_hora'] ?? null;
    $status = $body['status'] ?? 'RASCUNHO';

    if (
        !$disc ||
        $titulo === '' ||
        mb_strlen($titulo) > 150 ||
        $tipo === '' ||
        mb_strlen($tipo) > 40 ||
        mb_strlen($descricao) > 10000 ||
        !is_string($data) ||
        !is_string($status) ||
        !in_array($status, ['RASCUNHO', 'PUBLICADO'], true)
    ) {
        jsonResponse(422, [
            'ok' => false,
            'mensagem' => 'Dados inválidos. Informe disciplina, título, tipo, data e status.'
        ]);
    }

    // Exige data ISO 8601 com fuso horário.
    if (!preg_match(
        '/(?:Z|[+-]\d{2}:\d{2})$/i',
        $data
    )) {
        jsonResponse(422, [
            'ok' => false,
            'mensagem' => 'Informe data ISO 8601 com fuso horário.'
        ]);
    }

    try {
        $dt = new DateTimeImmutable($data);
    } catch (Throwable) {
        jsonResponse(422, [
            'ok' => false,
            'mensagem' => 'Data inválida.'
        ]);
    }

    return [
        'id_disciplina' => (int) $disc,
        'titulo' => $titulo,
        'tipo' => $tipo,
        'descricao' => $descricao,
        'data_hora' => $dt->format(DateTimeInterface::ATOM),
        'status' => $status
    ];
}

try {
    $pdo = db();

    /**
     * GET - Listar atividades
     */
    if ($method === 'GET') {

        if ($u['papel'] === 'PROFESSOR') {

            $sql = "
                SELECT
                    a.*,
                    d.nome AS disciplina
                FROM atividade a
                INNER JOIN disciplina d
                    ON d.id_disciplina = a.id_disciplina
                INNER JOIN professor_disciplina pd
                    ON pd.id_disciplina = d.id_disciplina
                WHERE pd.id_professor = :id
                ORDER BY a.data_hora
            ";

            $id = $u['id_professor'];

        } else {

            $sql = "
                SELECT
                    a.*,
                    d.nome AS disciplina,
                    EXISTS (
                        SELECT 1
                        FROM atividade_conclusao ac
                        WHERE ac.id_atividade = a.id_atividade
                          AND ac.id_estudante = :id
                    ) AS concluida
                FROM atividade a
                INNER JOIN disciplina d
                    ON d.id_disciplina = a.id_disciplina
                WHERE a.status = 'PUBLICADO'
                  AND EXISTS (
                      SELECT 1
                      FROM matricula m
                      WHERE m.id_disciplina = a.id_disciplina
                        AND m.id_estudante = :id
                  )
                ORDER BY a.data_hora
            ";

            $id = $u['id_estudante'];
        }

        $s = $pdo->prepare($sql);
        $s->execute(['id' => $id]);

        $atividades = $s->fetchAll();

        // Normaliza booleanos retornados pelo PostgreSQL.
        if ($u['papel'] !== 'PROFESSOR') {
            foreach ($atividades as &$atividade) {
                $atividade['concluida'] = in_array(
                    $atividade['concluida'],
                    [true, 't', 'true', '1', 1],
                    true
                );
            }

            unset($atividade);
        }

        jsonResponse(200, [
            'ok' => true,
            'atividades' => $atividades
        ]);
    }

    $body = readJsonBody();

    /**
     * POST - Cadastrar atividade
     */
    if ($method === 'POST') {

        $a = parseActivity($body);

        if (!authorizedDiscipline(
            $pdo,
            $a['id_disciplina'],
            $u['id_professor']
        )) {
            jsonResponse(403, [
                'ok' => false,
                'mensagem' => 'Disciplina não autorizada.'
            ]);
        }

        $a['codigo_atv'] =
            'MANUAL-' . bin2hex(random_bytes(12));

        $sql = '
            INSERT INTO atividade (
                id_disciplina,
                titulo,
                descricao,
                tipo,
                data_hora,
                status,
                codigo_atv
            )
            VALUES (
                :id_disciplina,
                :titulo,
                :descricao,
                :tipo,
                :data_hora,
                :status,
                :codigo_atv
            )
            RETURNING id_atividade
        ';

        $pdo->beginTransaction();

        $s = $pdo->prepare($sql);
        $s->execute($a);

        $novoId = (int) $s->fetchColumn();

        if ($a['status'] === 'PUBLICADO') {
            avisarMatriculados(
                $pdo,
                $novoId,
                $a['id_disciplina'],
                'NOVA_ATIVIDADE',
                'Nova atividade publicada: ' . $a['titulo'],
                'nova-' . $novoId
            );
        }

        $pdo->commit();

        jsonResponse(201, [
            'ok' => true,
            'id_atividade' => $novoId
        ]);
    }

    /**
     * Identifica a atividade para PUT ou DELETE.
     */
    $id = filter_var(
        $body['id_atividade'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$id) {
        jsonResponse(422, [
            'ok' => false,
            'mensagem' => 'ID de atividade inválido.'
        ]);
    }

    $s = $pdo->prepare(
        'SELECT a.*
         FROM atividade a
         JOIN professor_disciplina pd
           ON pd.id_disciplina = a.id_disciplina
         WHERE a.id_atividade = :id
           AND pd.id_professor = :p'
    );

    $s->execute([
        'id' => $id,
        'p' => $u['id_professor']
    ]);

    $original = $s->fetch();

    if ($original === false) {
        jsonResponse(404, [
            'ok' => false,
            'mensagem' => 'Atividade não encontrada ou não autorizada.'
        ]);
    }

    /**
     * DELETE - Excluir atividade
     */
    if ($method === 'DELETE') {

        $s = $pdo->prepare(
            'DELETE FROM atividade
             WHERE id_atividade = :id'
        );

        $s->execute(['id' => $id]);

        jsonResponse(200, [
            'ok' => true
        ]);
    }

    /**
     * PUT - Atualizar atividade
     */
    $a = parseActivity($body);

    if (!authorizedDiscipline(
        $pdo,
        $a['id_disciplina'],
        $u['id_professor']
    )) {
        jsonResponse(403, [
            'ok' => false,
            'mensagem' => 'Disciplina não autorizada.'
        ]);
    }

    $a['id_atividade'] = $id;

    $pdo->beginTransaction();

    $sql = '
        UPDATE atividade
        SET
            id_disciplina = :id_disciplina,
            titulo = :titulo,
            descricao = :descricao,
            tipo = :tipo,
            data_hora = :data_hora,
            status = :status,
            atualizado_em = CURRENT_TIMESTAMP
        WHERE id_atividade = :id_atividade
    ';

    $s = $pdo->prepare($sql);
    $s->execute($a);

    /**
     * Compara os dados anteriores com os novos.
     */
    $fuso = new DateTimeZone('America/Sao_Paulo');
    $mudancas = [];

    foreach (
        [
            'titulo',
            'descricao',
            'tipo',
            'data_hora',
            'status',
            'id_disciplina'
        ] as $campo
    ) {
        $antes = (string) $original[$campo];
        $depois = (string) $a[$campo];

        if ($campo === 'data_hora') {

            $antesData = new DateTimeImmutable($antes);
            $depoisData = new DateTimeImmutable($depois);

            // Compara instantes, não representações de fuso.
            if (
                $antesData->getTimestamp() ===
                $depoisData->getTimestamp()
            ) {
                continue;
            }

            $antes = $antesData
                ->setTimezone($fuso)
                ->format('d/m/Y H:i:s');

            $depois = $depoisData
                ->setTimezone($fuso)
                ->format('d/m/Y H:i:s');
        }

        if ($antes !== $depois) {
            $mudancas[] =
                "$campo: $antes → $depois";
        }
    }

    /**
     * Registra alterações no histórico.
     */
    if ($mudancas) {

        $hist = $pdo->prepare(
            'INSERT INTO historico_alteracao (
                id_atividade,
                descricao_alteracao
            )
            VALUES (
                :id,
                :descricao
            )'
        );

        $hist->execute([
            'id' => $id,
            'descricao' => implode('; ', $mudancas)
        ]);

        /**
         * Notifica os alunos matriculados.
         */
        if ($a['status'] === 'PUBLICADO') {

            $tipoAviso =
                $original['status'] !== 'PUBLICADO'
                    ? 'NOVA_ATIVIDADE'
                    : 'ALTERACAO_ATIVIDADE';

            $msg =
                $tipoAviso === 'NOVA_ATIVIDADE'
                    ? 'Atividade publicada: '
                    : 'Atividade atualizada: ';

            avisarMatriculados(
                $pdo,
                $id,
                $a['id_disciplina'],
                $tipoAviso,
                $msg . $a['titulo'],
                'alteracao-' . $id . '-' .
                    bin2hex(random_bytes(8))
            );
        }
    }

    $pdo->commit();

    jsonResponse(200, [
        'ok' => true
    ]);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    serviceError($e);
}
