<?php
declare(strict_types=1);

// Teste sem banco de dados: valida o leitor do planejamento do Moodle.
// Uso: php tests/testar_planejamentos.php
if (PHP_SAPI !== 'cli') {
    exit('Use apenas a linha de comando.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$falhas = 0;
$verificar = static function (bool $ok, string $descricao) use (&$falhas): void {
    echo ($ok ? 'OK    ' : 'ERRO  ') . $descricao . "\n";
    if (!$ok) {
        $falhas++;
    }
};

// --- datas ---
$d = parseDataPlanejamento('23/11/2026 23:59');
$verificar($d !== null && $d->format('Y-m-d H:i') === '2026-11-23 23:59', 'data dd/mm/aaaa hh:mm');
$d = parseDataPlanejamento('2026-12-01 19:00');
$verificar($d !== null && $d->format('d/m/Y H:i') === '01/12/2026 19:00', 'data aaaa-mm-dd hh:mm');
$d = parseDataPlanejamento('18/12/2026');
$verificar($d !== null && $d->format('H:i') === '23:59', 'só a data assume 23:59');
$verificar(parseDataPlanejamento('31/02/2026 10:00') === null, 'rejeita data inexistente (31/02)');
$verificar(parseDataPlanejamento('amanhã') === null, 'rejeita texto que não é data');
$verificar(parseDataPlanejamento('') === null, 'rejeita data vazia');

// --- arquivos reais do projeto ---
$pastas = [
    PROJECT_ROOT . '/moodle_simulado',
    PROJECT_ROOT . '/exemplos/planejamento-inicial',
    PROJECT_ROOT . '/exemplos/planejamento-atualizado',
];
foreach ($pastas as $pasta) {
    $arquivos = glob($pasta . '/*.csv') ?: [];
    $verificar(count($arquivos) > 0, basename($pasta) . ': possui arquivos .csv');
    foreach ($arquivos as $arquivo) {
        try {
            $plano = lerPlanejamentoCsv($arquivo);
            $verificar(
                count($plano['erros']) === 0 && count($plano['itens']) > 0,
                basename($pasta) . '/' . basename($arquivo) . ': ' . count($plano['itens']) . ' atividade(s), ' . count($plano['erros']) . ' erro(s)'
            );
        } catch (Throwable $e) {
            $verificar(false, basename($arquivo) . ': ' . $e->getMessage());
        }
    }
}

// --- linhas inválidas são ignoradas e relatadas ---
$tmp = tempnam(sys_get_temp_dir(), 'mca');
file_put_contents($tmp, "codigo_atv;titulo;tipo;data_hora;descricao\nX-01;Boa;Prova;01/12/2026 19:00;ok\nX-02;;Prova;01/12/2026 19:00;sem título\nX-03;Data ruim;Prova;32/13/2026;x\nX-01;Repetida;Prova;02/12/2026 19:00;x\n");
$plano = lerPlanejamentoCsv($tmp);
$verificar(count($plano['itens']) === 1 && count($plano['erros']) === 3, 'linhas inválidas/repetidas são ignoradas e relatadas');
file_put_contents($tmp, "nome;idade\nA;1\n");
try {
    lerPlanejamentoCsv($tmp);
    $verificar(false, 'cabeçalho inválido deve gerar erro');
} catch (PlanejamentoException) {
    $verificar(true, 'cabeçalho inválido gera erro (dados anteriores preservados)');
}
unlink($tmp);

echo $falhas === 0 ? "\nTudo certo.\n" : "\n{$falhas} verificação(ões) falharam.\n";
exit($falhas === 0 ? 0 : 1);
