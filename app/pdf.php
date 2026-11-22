<?php
declare(strict_types=1);

/**
 * Importação de atividades a partir de PDF.
 *
 * O PDF é lido no navegador (public/js/pdf-importacao.js); o servidor recebe só a
 * lista de atividades escolhidas na prévia, valida tudo de novo e grava pelo mesmo
 * caminho da sincronização (histórico, avisos, sem duplicar).
 *
 * Regras:
 *  - atividades importadas têm origem 'PDF' e código 'PDF-xxxxxxxx' (estável pelo título);
 *  - importar o mesmo PDF de novo não duplica nem avisa;
 *  - a importação nunca remove atividades, e a sincronização com o Moodle nunca
 *    remove o que veio de PDF;
 *  - só estudantes matriculados na disciplina podem importar para ela.
 */

const PDF_MAX_ITENS = 300;

/** Garante a coluna atividade.origem em bancos criados por versões anteriores. */
function garantirColunaOrigem(PDO $pdo): void
{
    static $verificado = false;
    if ($verificado) {
        return;
    }
    $existe = $pdo->query(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = current_schema() AND table_name = 'atividade' AND column_name = 'origem'"
    )->fetchColumn();
    if (!$existe) {
        $pdo->exec("ALTER TABLE atividade ADD COLUMN origem VARCHAR(10) NOT NULL DEFAULT 'MOODLE'");
    }
    $verificado = true;
}

function textoSeguro(mixed $valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

/** Valida as atividades vindas do navegador. Itens inválidos vão para 'erros' e são ignorados. */
function validarItensPdf(array $brutos): array
{
    $itens = [];
    $erros = [];
    $codigosVistos = [];

    foreach (array_values($brutos) as $indice => $bruto) {
        $n = $indice + 1;
        if (!is_array($bruto)) {
            $erros[] = 'Item ' . $n . ': formato inválido.';
            continue;
        }
        $codigo = textoSeguro($bruto['codigo_atv'] ?? null);
        $titulo = textoSeguro($bruto['titulo'] ?? null);
        $tipo = textoSeguro($bruto['tipo'] ?? null);
        $descricao = textoSeguro($bruto['descricao'] ?? null);
        $data = parseDataPlanejamento(textoSeguro($bruto['data_hora'] ?? null));

        $problema = null;
        if (preg_match('/^PDF-[A-Za-z0-9._-]{1,46}$/', $codigo) !== 1) {
            $problema = 'código inválido';
        } elseif (isset($codigosVistos[$codigo])) {
            $problema = 'código repetido';
        } elseif ($titulo === '' || mb_strlen($titulo) > 150) {
            $problema = 'título vazio ou maior que 150 caracteres';
        } elseif (mb_strlen($tipo) > 40) {
            $problema = 'tipo maior que 40 caracteres';
        } elseif (mb_strlen($descricao) > 10000) {
            $problema = 'descrição muito longa';
        } elseif ($data === null) {
            $problema = 'data inválida';
        }
        if ($problema !== null) {
            $erros[] = 'Item ' . $n . ($titulo !== '' ? ' (' . mb_substr($titulo, 0, 40) . ')' : '') . ': ' . $problema . '.';
            continue;
        }

        $codigosVistos[$codigo] = true;
        $itens[] = [
            'codigo_atv' => $codigo,
            'titulo' => $titulo,
            'tipo' => $tipo === '' ? 'Outro' : mb_convert_case(mb_strtolower($tipo), MB_CASE_TITLE, 'UTF-8'),
            'data' => $data,
            'descricao' => $descricao,
        ];
    }
    return ['itens' => $itens, 'erros' => $erros];
}

/**
 * Importa as atividades de um PDF para uma disciplina.
 * Lança PlanejamentoException (mensagem segura) quando não há o que importar.
 */
function importarAtividadesPdf(PDO $pdo, int $idDisciplina, array $itensBrutos): array
{
    $trava = $pdo->query('SELECT pg_try_advisory_lock(' . SYNC_LOCK_ID . ')')->fetchColumn();
    if (!pgBool($trava)) {
        throw new PlanejamentoException('Há uma sincronização em andamento. Tente novamente em instantes.');
    }
    try {
        garantirColunaOrigem($pdo);

        $stmt = $pdo->prepare('SELECT codigo_disc, nome FROM disciplina WHERE id_disciplina = :id');
        $stmt->execute(['id' => $idDisciplina]);
        $disciplina = $stmt->fetch();
        if ($disciplina === false) {
            throw new PlanejamentoException('Disciplina não encontrada.');
        }

        $validado = validarItensPdf($itensBrutos);
        if (count($validado['itens']) === 0) {
            throw new PlanejamentoException(
                'Nenhuma atividade válida para importar.'
                . ($validado['erros'] ? ' ' . implode(' ', array_slice($validado['erros'], 0, 3)) : '')
            );
        }

        $execucao = bin2hex(random_bytes(8));
        $r = aplicarPlanejamento(
            $pdo,
            (string) $disciplina['codigo_disc'],
            ['meta' => [], 'itens' => $validado['itens'], 'erros' => $validado['erros']],
            'PDF'
        );
        $detalhes = sprintf(
            'PDF: %d nova(s), %d alterada(s), %d sem mudança.',
            $r['novas'], $r['alteradas'], $r['iguais']
        );
        if ($r['avisos']) {
            $detalhes .= ' Ignorado: ' . implode(' ', array_slice($r['avisos'], 0, 5));
        }
        registrarSincronizacao(
            $pdo, $execucao, $r['id_disciplina'],
            count($r['avisos']) > 0 ? 'PARCIAL' : 'SUCESSO', $r, $detalhes, 'pdf'
        );

        return [
            'disciplina' => $disciplina['nome'],
            'novas' => $r['novas'],
            'alteradas' => $r['alteradas'],
            'iguais' => $r['iguais'],
            'ignoradas' => $r['avisos'],
        ];
    } finally {
        $pdo->query('SELECT pg_advisory_unlock(' . SYNC_LOCK_ID . ')')->fetchColumn();
    }
}
