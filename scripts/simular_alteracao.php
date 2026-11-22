<?php
declare(strict_types=1);

// Simula o professor editando o planejamento no Moodle (para demonstração/testes).
// Uso: php scripts/simular_alteracao.php atualizado   (copia exemplos/planejamento-atualizado/ para moodle_simulado/)
//      php scripts/simular_alteracao.php inicial      (volta ao planejamento inicial)
// Depois: clique em "Sincronizar agora" no sistema, ou rode php scripts/sincronizar.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use apenas a linha de comando.');
}
$versao = $argv[1] ?? '';
if (!in_array($versao, ['inicial', 'atualizado'], true)) {
    fwrite(STDERR, "Uso: php scripts/simular_alteracao.php [inicial|atualizado]\n");
    exit(2);
}
$raiz = dirname(__DIR__);
$origem = $raiz . '/exemplos/planejamento-' . $versao;
$destino = $raiz . '/moodle_simulado';
if (!is_dir($origem) || !is_dir($destino)) {
    fwrite(STDERR, "Pastas de exemplo não encontradas.\n");
    exit(1);
}
// Limpa os CSVs atuais para que arquivos que não existem na versão escolhida também saiam.
foreach (glob($destino . '/*.csv') ?: [] as $antigo) {
    unlink($antigo);
}
$copiados = 0;
foreach (glob($origem . '/*.csv') ?: [] as $arquivo) {
    if (!copy($arquivo, $destino . '/' . basename($arquivo))) {
        fwrite(STDERR, 'Falha ao copiar ' . basename($arquivo) . "\n");
        exit(1);
    }
    $copiados++;
}
echo "Planejamento '{$versao}' aplicado em moodle_simulado/ ({$copiados} arquivo(s)).\n";
echo "Agora sincronize: botão \"Sincronizar agora\" no sistema ou php scripts/sincronizar.php\n";
