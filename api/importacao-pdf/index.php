<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
// Recebe as atividades escolhidas na prévia do PDF (o PDF em si é lido no navegador).
requireMethods(['POST']);
$u = authenticatedStudent();
requireCsrf();
$body = readJsonBody(262144);

$idDisciplina = filter_var($body['id_disciplina'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$itens = $body['itens'] ?? null;
if (!$idDisciplina || !is_array($itens) || !array_is_list($itens) || count($itens) === 0) {
    jsonResponse(422, ['ok' => false, 'mensagem' => 'Informe a disciplina e ao menos uma atividade.']);
}
if (count($itens) > PDF_MAX_ITENS) {
    jsonResponse(422, ['ok' => false, 'mensagem' => 'Muitas atividades de uma vez (máximo ' . PDF_MAX_ITENS . ').']);
}

try {
    $pdo = db();
    $chk = $pdo->prepare('SELECT 1 FROM matricula WHERE id_disciplina = :disciplina AND id_estudante = :aluno');
    $chk->execute(['disciplina' => $idDisciplina, 'aluno' => $u['id']]);
    if (!$chk->fetchColumn()) {
        jsonResponse(403, ['ok' => false, 'mensagem' => 'Você só pode importar atividades das disciplinas em que está matriculado.']);
    }
    $resumo = importarAtividadesPdf($pdo, (int) $idDisciplina, $itens);
    jsonResponse(200, ['ok' => true, 'resumo' => $resumo]);
} catch (PlanejamentoException $e) {
    jsonResponse(422, ['ok' => false, 'mensagem' => $e->getMessage()]);
} catch (Throwable $e) {
    serviceError($e);
}
