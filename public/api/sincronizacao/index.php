<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
$metodo = requireMethods(['GET', 'POST']);
$u = authenticatedStudent();
try {
    $pdo = db();

    if ($metodo === 'GET') {
        jsonResponse(200, ['ok' => true] + statusSincronizacao($pdo));
    }

    // POST: sincroniza agora com o planejamento do Moodle.
    requireCsrf();
    $body = readJsonBody();
    $origem = ($body['origem'] ?? 'manual') === 'automatica' ? 'automatica' : 'manual';

    // Evita rajadas: se alguém acabou de sincronizar, reaproveita o resultado.
    $estado = statusSincronizacao($pdo);
    $ultima = $estado['ultima'];
    if ($ultima !== null && $ultima['data'] !== null && (time() - strtotime($ultima['data'])) < 10) {
        jsonResponse(200, ['ok' => true, 'ignorada' => true, 'sincronizacao' => null, 'status' => $estado]);
    }

    $resumo = sincronizarPlanejamentos($pdo, $origem);
    if (!empty($resumo['ocupada'])) {
        jsonResponse(409, ['ok' => false, 'mensagem' => $resumo['mensagem']]);
    }
    jsonResponse(200, [
        'ok' => true,
        'ignorada' => false,
        'sincronizacao' => $resumo,
        'status' => statusSincronizacao($pdo),
    ]);
} catch (Throwable $e) {
    serviceError($e);
}
