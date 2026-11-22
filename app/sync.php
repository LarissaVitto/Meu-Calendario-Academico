<?php
declare(strict_types=1);

/**
 * Sincronização com o planejamento do Moodle (HU8, HU9, HU10, RNF05, RNF11).
 *
 * Para cada disciplina da fonte:
 *   - atividade nova          -> cadastrada (e os alunos são avisados);
 *   - atividade modificada    -> atualizada, com histórico e aviso (data anterior guardada);
 *   - atividade que sumiu     -> marcada como removida (não é apagada);
 *   - atividade igual         -> nada acontece (reimportar não duplica nem notifica).
 * Se a leitura falhar, os dados já gravados são preservados e a falha é registrada.
 */

const SYNC_LOCK_ID = 727401;

function sincronizarPlanejamentos(PDO $pdo, string $origem = 'manual'): array
{
    // Trava para duas sincronizações não rodarem ao mesmo tempo.
    $trava = $pdo->query('SELECT pg_try_advisory_lock(' . SYNC_LOCK_ID . ')')->fetchColumn();
    if (!pgBool($trava)) {
        return ['ok' => false, 'ocupada' => true, 'mensagem' => 'Já existe uma sincronização em andamento. Tente novamente em instantes.'];
    }
    try {
        return executarSincronizacao($pdo, $origem);
    } finally {
        $pdo->query('SELECT pg_advisory_unlock(' . SYNC_LOCK_ID . ')')->fetchColumn();
    }
}

function executarSincronizacao(PDO $pdo, string $origem): array
{
    garantirColunaOrigem($pdo);
    $execucao = bin2hex(random_bytes(8));
    $resumo = [
        'execucao' => $execucao,
        'data' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        'novas' => 0, 'alteradas' => 0, 'removidas' => 0, 'iguais' => 0,
        'disciplinas' => [],
    ];

    try {
        $arquivos = listarPlanejamentosMoodle();
        if (count($arquivos) === 0) {
            throw new PlanejamentoException('Nenhum arquivo de planejamento (.csv) encontrado na fonte do Moodle.');
        }
    } catch (PlanejamentoException $e) {
        registrarSincronizacao($pdo, $execucao, null, 'FALHA', [], $e->getMessage(), $origem);
        return $resumo + ['ok' => false, 'resultado' => 'FALHA', 'mensagem' => $e->getMessage()];
    }

    $resultados = [];
    foreach ($arquivos as $arquivo) {
        $codigo = strtoupper(pathinfo($arquivo, PATHINFO_FILENAME));
        try {
            $plano = lerPlanejamentoCsv($arquivo);
            $r = aplicarPlanejamento($pdo, $codigo, $plano);
            $resultado = count($r['avisos']) > 0 ? 'PARCIAL' : 'SUCESSO';
            $detalhes = sprintf(
                '%d nova(s), %d alterada(s), %d removida(s), %d sem mudança.',
                $r['novas'], $r['alteradas'], $r['removidas'], $r['iguais']
            );
            if ($r['avisos']) {
                $detalhes .= ' Atenção: ' . implode(' ', array_slice($r['avisos'], 0, 5));
            }
            registrarSincronizacao($pdo, $execucao, $r['id_disciplina'], $resultado, $r, $detalhes, $origem);
            foreach (['novas', 'alteradas', 'removidas', 'iguais'] as $campo) {
                $resumo[$campo] += $r[$campo];
            }
            $resumo['disciplinas'][] = ['codigo' => $codigo, 'resultado' => $resultado, 'detalhes' => $detalhes];
            $resultados[] = $resultado;
        } catch (Throwable $e) {
            // Falha numa disciplina não afeta as demais nem apaga dados já gravados.
            $mensagem = $e instanceof PlanejamentoException
                ? $e->getMessage()
                : 'Erro interno ao processar o planejamento; os dados anteriores foram preservados.';
            if (!($e instanceof PlanejamentoException)) {
                error_log('[MCA] sincronização ' . $codigo . ': ' . get_class($e) . ': ' . $e->getMessage());
            }
            registrarSincronizacao($pdo, $execucao, buscarIdDisciplina($pdo, $codigo), 'FALHA', [], $codigo . ': ' . $mensagem, $origem);
            $resumo['disciplinas'][] = ['codigo' => $codigo, 'resultado' => 'FALHA', 'detalhes' => $mensagem];
            $resultados[] = 'FALHA';
        }
    }

    $resumo['resultado'] = resultadoGeral($resultados);
    return $resumo + ['ok' => true];
}

/** SUCESSO se tudo deu certo, FALHA se tudo falhou, PARCIAL nos demais casos. */
function resultadoGeral(array $resultados): string
{
    if (count($resultados) === 0) {
        return 'FALHA';
    }
    if (count(array_filter($resultados, static fn ($r) => $r !== 'SUCESSO')) === 0) {
        return 'SUCESSO';
    }
    if (count(array_filter($resultados, static fn ($r) => $r !== 'FALHA')) === 0) {
        return 'FALHA';
    }
    return 'PARCIAL';
}

function buscarIdDisciplina(PDO $pdo, string $codigo): ?int
{
    $stmt = $pdo->prepare('SELECT id_disciplina FROM disciplina WHERE UPPER(codigo_disc) = UPPER(:codigo)');
    $stmt->execute(['codigo' => $codigo]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function registrarSincronizacao(PDO $pdo, string $execucao, ?int $idDisciplina, string $resultado, array $contagem, string $detalhes, string $origem): void
{
    $stmt = $pdo->prepare(<<<'SQL'
        INSERT INTO sincronizacao (execucao, id_disciplina, resultado, novas, alteradas, removidas, iguais, detalhes, origem)
        VALUES (:execucao, :disciplina, :resultado, :novas, :alteradas, :removidas, :iguais, :detalhes, :origem)
    SQL);
    $stmt->execute([
        'execucao' => $execucao,
        'disciplina' => $idDisciplina,
        'resultado' => $resultado,
        'novas' => $contagem['novas'] ?? 0,
        'alteradas' => $contagem['alteradas'] ?? 0,
        'removidas' => $contagem['removidas'] ?? 0,
        'iguais' => $contagem['iguais'] ?? 0,
        'detalhes' => $detalhes,
        'origem' => $origem,
    ]);
}

function formatarDataBr(DateTimeImmutable $data): string
{
    return $data->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i');
}

function registrarHistorico(PDO $pdo, int $idAtividade, string $tipo, string $descricao): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO historico_alteracao (id_atividade, tipo_alteracao, descricao_alteracao)
         VALUES (:atividade, :tipo, :descricao) RETURNING id_historico'
    );
    $stmt->execute(['atividade' => $idAtividade, 'tipo' => $tipo, 'descricao' => $descricao]);
    return (int) $stmt->fetchColumn();
}

/** Cria a disciplina se ainda não existir e mantém nome/professor atualizados. */
function obterOuCriarDisciplina(PDO $pdo, string $codigo, array $meta): array
{
    $stmt = $pdo->prepare('SELECT id_disciplina, nome, professor FROM disciplina WHERE UPPER(codigo_disc) = UPPER(:codigo)');
    $stmt->execute(['codigo' => $codigo]);
    $atual = $stmt->fetch();
    $nome = $meta['nome'] ?? null;
    $professor = $meta['professor'] ?? null;

    if ($atual === false) {
        $ins = $pdo->prepare('INSERT INTO disciplina (nome, codigo_disc, professor) VALUES (:nome, :codigo, :professor) RETURNING id_disciplina');
        $ins->execute(['nome' => $nome ?? $codigo, 'codigo' => $codigo, 'professor' => $professor]);
        return ['id' => (int) $ins->fetchColumn(), 'nome' => $nome ?? $codigo];
    }

    $novoNome = $nome ?? $atual['nome'];
    $novoProfessor = $professor ?? $atual['professor'];
    if ($novoNome !== $atual['nome'] || $novoProfessor !== $atual['professor']) {
        $upd = $pdo->prepare('UPDATE disciplina SET nome = :nome, professor = :professor WHERE id_disciplina = :id');
        $upd->execute(['nome' => $novoNome, 'professor' => $novoProfessor, 'id' => $atual['id_disciplina']]);
    }
    return ['id' => (int) $atual['id_disciplina'], 'nome' => $novoNome];
}

/**
 * Aplica o planejamento lido de uma disciplina, numa única transação.
 * Devolve contagens e avisos; lança exceção se nada puder ser aplicado.
 */
function aplicarPlanejamento(PDO $pdo, string $codigoDisc, array $plano, string $origem = 'MOODLE'): array
{
    $itens = $plano['itens'];
    if (count($itens) === 0) {
        throw new PlanejamentoException('Planejamento sem nenhuma atividade válida; os dados anteriores foram preservados.');
    }

    $res = ['novas' => 0, 'alteradas' => 0, 'removidas' => 0, 'iguais' => 0, 'avisos' => $plano['erros'], 'id_disciplina' => 0];

    $pdo->beginTransaction();
    try {
        $disciplina = obterOuCriarDisciplina($pdo, $codigoDisc, $plano['meta']);
        $idDisc = $disciplina['id'];
        $nomeDisc = $disciplina['nome'];
        $res['id_disciplina'] = $idDisc;

        $stmt = $pdo->prepare(
            'SELECT id_atividade, codigo_atv, titulo, descricao, tipo, data_hora, removida_em, origem
             FROM atividade WHERE id_disciplina = :disciplina'
        );
        $stmt->execute(['disciplina' => $idDisc]);
        $existentes = [];
        foreach ($stmt->fetchAll() as $linha) {
            $existentes[$linha['codigo_atv']] = $linha;
        }
        // Na primeira carga da disciplina não há o que "avisar": tudo é novo.
        $primeiraCarga = count($existentes) === 0;

        $inserir = $pdo->prepare(
            'INSERT INTO atividade (id_disciplina, codigo_atv, titulo, descricao, tipo, data_hora, origem)
             VALUES (:disciplina, :codigo, :titulo, :descricao, :tipo, :data, :origem) RETURNING id_atividade'
        );

        $vistos = [];
        foreach ($itens as $item) {
            $codigo = $item['codigo_atv'];
            $vistos[$codigo] = true;
            $atual = $existentes[$codigo] ?? null;

            // ---- atividade nova ----
            if ($atual === null) {
                $inserir->execute([
                    'disciplina' => $idDisc,
                    'codigo' => $codigo,
                    'titulo' => $item['titulo'],
                    'descricao' => $item['descricao'],
                    'tipo' => $item['tipo'],
                    'data' => $item['data']->format(DATE_ATOM),
                    'origem' => $origem,
                ]);
                $idAtv = (int) $inserir->fetchColumn();
                if (!$primeiraCarga) {
                    registrarHistorico($pdo, $idAtv, 'CRIADA', 'Atividade incluída no planejamento para ' . formatarDataBr($item['data']) . '.');
                    notificarMatriculados(
                        $pdo, $idAtv, $idDisc, 'NOVA_ATIVIDADE',
                        'Nova atividade em ' . $nomeDisc . ': ' . $item['titulo'] . ' (' . formatarDataBr($item['data']) . ').',
                        'nova-' . $idAtv
                    );
                }
                $res['novas']++;
                continue;
            }

            // Importação de PDF nunca mexe em atividades que vieram do Moodle.
            if ($origem === 'PDF' && $atual['origem'] !== 'PDF') {
                $res['iguais']++;
                continue;
            }

            // ---- atividade já conhecida: o que mudou? ----
            $idAtv = (int) $atual['id_atividade'];
            $dataAntiga = new DateTimeImmutable($atual['data_hora']);
            $dataMudou = $dataAntiga->getTimestamp() !== $item['data']->getTimestamp();
            $mudancas = [];
            if ($dataMudou) {
                $mudancas[] = 'Data: ' . formatarDataBr($dataAntiga) . ' → ' . formatarDataBr($item['data']);
            }
            if ($atual['titulo'] !== $item['titulo']) {
                $mudancas[] = 'Título: "' . $atual['titulo'] . '" → "' . $item['titulo'] . '"';
            }
            if ($atual['tipo'] !== $item['tipo']) {
                $mudancas[] = 'Tipo: ' . $atual['tipo'] . ' → ' . $item['tipo'];
            }
            if (trim((string) $atual['descricao']) !== $item['descricao']) {
                $mudancas[] = 'Descrição atualizada';
            }
            $reinserida = $atual['removida_em'] !== null;

            if (count($mudancas) === 0 && !$reinserida) {
                $res['iguais']++;
                continue;
            }

            $sql = 'UPDATE atividade SET titulo = :titulo, descricao = :descricao, tipo = :tipo, data_hora = :data,
                    removida_em = NULL, origem = :origem, atualizado_em = CURRENT_TIMESTAMP';
            $params = [
                'origem' => $origem,
                'titulo' => $item['titulo'],
                'descricao' => $item['descricao'],
                'tipo' => $item['tipo'],
                'data' => $item['data']->format(DATE_ATOM),
                'id' => $idAtv,
            ];
            if ($dataMudou) {
                $sql .= ', data_anterior = :anterior';
                $params['anterior'] = $dataAntiga->format(DATE_ATOM);
            }
            if (count($mudancas) > 0) {
                $sql .= ', alterada_em = CURRENT_TIMESTAMP';
            }
            $sql .= ' WHERE id_atividade = :id';
            $pdo->prepare($sql)->execute($params);

            if ($reinserida) {
                array_unshift($mudancas, 'Atividade voltou ao planejamento');
            }
            $idHist = registrarHistorico($pdo, $idAtv, $dataMudou ? 'DATA' : ($reinserida ? 'REINSERIDA' : 'INFO'), implode('; ', $mudancas) . '.');

            if ($dataMudou) {
                $mensagem = 'A data de "' . $item['titulo'] . '" (' . $nomeDisc . ') mudou de ' . formatarDataBr($dataAntiga) . ' para ' . formatarDataBr($item['data']) . '.';
                $tipoAviso = 'ALTERACAO_DATA';
            } elseif ($reinserida) {
                $mensagem = '"' . $item['titulo'] . '" (' . $nomeDisc . ') voltou ao planejamento para ' . formatarDataBr($item['data']) . '.';
                $tipoAviso = 'NOVA_ATIVIDADE';
            } else {
                $mensagem = '"' . $item['titulo'] . '" (' . $nomeDisc . ') foi atualizada: ' . implode('; ', $mudancas) . '.';
                $tipoAviso = 'ALTERACAO_ATIVIDADE';
            }
            notificarMatriculados($pdo, $idAtv, $idDisc, $tipoAviso, $mensagem, 'hist-' . $idHist);
            $res['alteradas']++;
        }

        // ---- atividades que sumiram do planejamento ----
        // Só quando o arquivo foi lido sem nenhum erro: com linhas inválidas não dá
        // para ter certeza de que a atividade realmente foi retirada.
        // Importação de PDF é aditiva: nunca remove nada, e a sincronização com o
        // Moodle nunca remove o que foi importado de PDF.
        if ($origem === 'MOODLE' && count($plano['erros']) === 0) {
            $remover = $pdo->prepare('UPDATE atividade SET removida_em = CURRENT_TIMESTAMP, atualizado_em = CURRENT_TIMESTAMP WHERE id_atividade = :id');
            foreach ($existentes as $codigo => $atual) {
                if (isset($vistos[$codigo]) || $atual['removida_em'] !== null || $atual['origem'] !== 'MOODLE') {
                    continue;
                }
                $idAtv = (int) $atual['id_atividade'];
                $remover->execute(['id' => $idAtv]);
                $idHist = registrarHistorico($pdo, $idAtv, 'REMOVIDA', 'Atividade retirada do planejamento.');
                notificarMatriculados(
                    $pdo, $idAtv, $idDisc, 'ATIVIDADE_REMOVIDA',
                    '"' . $atual['titulo'] . '" (' . $nomeDisc . ') foi retirada do planejamento.',
                    'hist-' . $idHist
                );
                $res['removidas']++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $res;
}

/** Última execução + histórico recente, agrupado por rodada (para a tela de sincronização). */
function statusSincronizacao(PDO $pdo): array
{
    $linhas = $pdo->query(<<<'SQL'
        SELECT s.execucao, s.data_execucao, s.resultado, s.novas, s.alteradas, s.removidas, s.iguais,
               s.detalhes, s.origem, d.codigo_disc, d.nome AS disciplina
        FROM sincronizacao s
        LEFT JOIN disciplina d ON d.id_disciplina = s.id_disciplina
        ORDER BY s.data_execucao DESC, s.id_sincronizacao DESC
        LIMIT 150
    SQL)->fetchAll();

    $rodadas = [];
    foreach ($linhas as $l) {
        $chave = $l['execucao'];
        if (!isset($rodadas[$chave])) {
            $rodadas[$chave] = [
                'execucao' => $chave,
                'data' => isoOuNull($l['data_execucao']),
                'origem' => $l['origem'],
                'novas' => 0, 'alteradas' => 0, 'removidas' => 0, 'iguais' => 0,
                'resultados' => [],
                'itens' => [],
            ];
        }
        $rodadas[$chave]['novas'] += (int) $l['novas'];
        $rodadas[$chave]['alteradas'] += (int) $l['alteradas'];
        $rodadas[$chave]['removidas'] += (int) $l['removidas'];
        $rodadas[$chave]['iguais'] += (int) $l['iguais'];
        $rodadas[$chave]['resultados'][] = $l['resultado'];
        $rodadas[$chave]['itens'][] = [
            'codigo' => $l['codigo_disc'],
            'disciplina' => $l['disciplina'],
            'resultado' => $l['resultado'],
            'detalhes' => $l['detalhes'],
        ];
    }
    $lista = [];
    foreach (array_slice(array_values($rodadas), 0, 10) as $r) {
        $r['resultado'] = resultadoGeral($r['resultados']);
        unset($r['resultados']);
        $lista[] = $r;
    }

    $ultima = $lista[0] ?? null;
    $intervalo = max(1, (int) (env('SYNC_INTERVALO_MIN', '30') ?? '30'));
    $precisa = $ultima === null || $ultima['data'] === null
        || (time() - strtotime($ultima['data'])) > $intervalo * 60;

    return [
        'fonte' => descricaoFonteMoodle(),
        'intervalo_min' => $intervalo,
        'precisa_sincronizar' => $precisa,
        'ultima' => $ultima,
        'execucoes' => $lista,
    ];
}
