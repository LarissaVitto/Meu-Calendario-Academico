<?php
declare(strict_types=1);

/**
 * Fonte de dados: a "tabela de planejamento" do Moodle.
 *
 * O professor edita o planejamento no Moodle. Aqui o sistema lê uma exportação
 * dessa tabela: um arquivo CSV por disciplina, na pasta MOODLE_PASTA (.env).
 * O nome do arquivo é o código da disciplina (ex.: ARSW.csv).
 *
 * Formato (separador ";", UTF-8, uma atividade por linha):
 *   #nome=Arquitetura de Software          <- opcional
 *   #professor=Profa. Helena Carvalho      <- opcional
 *   codigo_atv;titulo;tipo;data_hora;descricao
 *   ARSW-01;Entrega 2;Entrega;23/11/2026 23:59;Texto de orientação
 *
 * data_hora aceita "dd/mm/aaaa hh:mm", "aaaa-mm-dd hh:mm" (ou só a data = 23:59),
 * sempre no horário de Brasília. codigo_atv identifica a linha de forma estável.
 *
 * Para integrar com o Moodle real (Web Services, exportação iCal etc.) basta
 * trocar listarPlanejamentosMoodle()/lerPlanejamentoCsv() por uma função que
 * devolva a mesma estrutura: ['meta' => [...], 'itens' => [...], 'erros' => [...]].
 */

/** Erro com mensagem segura para mostrar ao usuário. */
class PlanejamentoException extends RuntimeException
{
}

function listarPlanejamentosMoodle(): array
{
    $pasta = env('MOODLE_PASTA', 'moodle_simulado') ?? 'moodle_simulado';
    $absoluto = preg_match('#^(/|[A-Za-z]:[\\\\/])#', $pasta) === 1;
    $caminho = $absoluto ? $pasta : PROJECT_ROOT . '/' . $pasta;
    if (!is_dir($caminho)) {
        throw new PlanejamentoException('Pasta do planejamento do Moodle não encontrada: ' . $pasta);
    }
    $arquivos = glob(rtrim($caminho, '/\\') . '/*.csv');
    if ($arquivos === false) {
        $arquivos = [];
    }
    sort($arquivos);
    return $arquivos;
}

function descricaoFonteMoodle(): string
{
    return 'Arquivos CSV em ' . (env('MOODLE_PASTA', 'moodle_simulado') ?? 'moodle_simulado');
}

/** Interpreta a data do planejamento (horário de Brasília). Devolve null se inválida. */
function parseDataPlanejamento(string $texto): ?DateTimeImmutable
{
    $texto = trim($texto);
    $fuso = new DateTimeZone('America/Sao_Paulo');
    $comHora = ['d/m/Y H:i', 'd/m/Y H:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'];
    foreach ($comHora as $formato) {
        $d = DateTimeImmutable::createFromFormat('!' . $formato, $texto, $fuso);
        $erros = DateTimeImmutable::getLastErrors();
        $limpo = $erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0);
        if ($d !== false && $limpo) {
            return $d;
        }
    }
    foreach (['d/m/Y', 'Y-m-d'] as $formato) {
        $d = DateTimeImmutable::createFromFormat('!' . $formato, $texto, $fuso);
        $erros = DateTimeImmutable::getLastErrors();
        $limpo = $erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0);
        if ($d !== false && $limpo) {
            return $d->setTime(23, 59);
        }
    }
    return null;
}

/**
 * Lê e valida um arquivo de planejamento.
 * Linhas inválidas não derrubam a leitura: vão para 'erros' e são ignoradas.
 * Falhas que impedem a leitura (arquivo ilegível, cabeçalho ausente) lançam exceção.
 */
function lerPlanejamentoCsv(string $arquivo): array
{
    $texto = @file_get_contents($arquivo);
    if ($texto === false) {
        throw new PlanejamentoException('Não foi possível ler o arquivo ' . basename($arquivo) . '.');
    }
    if (str_starts_with($texto, "\xEF\xBB\xBF")) {
        $texto = substr($texto, 3); // BOM
    }
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1'); // exportações do Excel/Windows
    }

    $meta = [];
    $itens = [];
    $erros = [];
    $colunas = null;
    $separador = ';';
    $codigosVistos = [];

    foreach (preg_split('/\r\n|\n|\r/', $texto) as $indice => $linha) {
        $numero = $indice + 1;
        $linha = trim($linha);
        if ($linha === '') {
            continue;
        }
        if ($linha[0] === '#') {
            if (preg_match('/^#\s*(nome|professor)\s*[=:]\s*(.+)$/iu', $linha, $m) === 1) {
                $meta[mb_strtolower($m[1])] = trim($m[2]);
            }
            continue;
        }

        if ($colunas === null) {
            // Primeira linha útil: cabeçalho.
            $separador = substr_count($linha, ';') >= substr_count($linha, ',') ? ';' : ',';
            $cabecalho = array_map(
                static fn ($c) => mb_strtolower(trim((string) $c)),
                str_getcsv($linha, $separador, '"', '')
            );
            foreach (['codigo_atv', 'titulo', 'data_hora'] as $obrigatoria) {
                if (!in_array($obrigatoria, $cabecalho, true)) {
                    throw new PlanejamentoException(
                        'Cabeçalho inválido em ' . basename($arquivo) . ': esperado codigo_atv;titulo;tipo;data_hora;descricao.'
                    );
                }
            }
            $colunas = array_flip($cabecalho);
            continue;
        }

        $campos = str_getcsv($linha, $separador, '"', '');
        $pegar = static function (string $nome) use ($colunas, $campos): string {
            return isset($colunas[$nome]) ? trim((string) ($campos[$colunas[$nome]] ?? '')) : '';
        };

        $codigo = $pegar('codigo_atv');
        $titulo = $pegar('titulo');
        $tipo = $pegar('tipo');
        $descricao = $pegar('descricao');
        $data = parseDataPlanejamento($pegar('data_hora'));

        $problema = null;
        if (preg_match('/^[A-Za-z0-9._-]{1,50}$/', $codigo) !== 1) {
            $problema = 'codigo_atv inválido';
        } elseif (isset($codigosVistos[$codigo])) {
            $problema = 'codigo_atv repetido (' . $codigo . ')';
        } elseif ($titulo === '' || mb_strlen($titulo) > 150) {
            $problema = 'título vazio ou maior que 150 caracteres';
        } elseif (mb_strlen($tipo) > 40) {
            $problema = 'tipo maior que 40 caracteres';
        } elseif (mb_strlen($descricao) > 10000) {
            $problema = 'descrição muito longa';
        } elseif ($data === null) {
            $problema = 'data_hora inválida';
        }
        if ($problema !== null) {
            $erros[] = basename($arquivo) . ' linha ' . $numero . ': ' . $problema . '.';
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

    if ($colunas === null) {
        throw new PlanejamentoException('O arquivo ' . basename($arquivo) . ' está vazio.');
    }
    return ['meta' => $meta, 'itens' => $itens, 'erros' => $erros];
}
