<?php
declare(strict_types=1);

// Uso: php scripts/sincronizar.php
// Sincroniza com o planejamento do Moodle. Pode ser agendado (cron / Agendador de Tarefas).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use apenas a linha de comando.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $resumo = sincronizarPlanejamentos(db(), 'cli');
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}
if (!empty($resumo['ocupada'])) {
    fwrite(STDERR, $resumo['mensagem'] . "\n");
    exit(2);
}
echo "Fonte: " . descricaoFonteMoodle() . "\n";
foreach ($resumo['disciplinas'] ?? [] as $d) {
    echo "[{$d['resultado']}] {$d['codigo']}: {$d['detalhes']}\n";
}
if (isset($resumo['mensagem'])) {
    echo $resumo['mensagem'] . "\n";
}
echo sprintf(
    "Resultado: %s — %d nova(s), %d alterada(s), %d removida(s), %d sem mudança.\n",
    $resumo['resultado'] ?? 'FALHA', $resumo['novas'], $resumo['alteradas'], $resumo['removidas'], $resumo['iguais']
);
exit(($resumo['resultado'] ?? 'FALHA') === 'FALHA' ? 1 : 0);
