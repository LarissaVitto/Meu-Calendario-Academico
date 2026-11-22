<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
$metodo = requireMethods(['GET', 'PATCH']);
$u = authenticatedStudent();
if ($metodo === 'GET') {
    jsonResponse(200, ['ok' => true, 'usuario' => $u]);
}

requireCsrf();
$body = readJsonBody();
$sets = [];
$params = [];

if (array_key_exists('nome', $body)) {
    $nome = is_string($body['nome']) ? trim($body['nome']) : '';
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
        jsonResponse(422, ['ok' => false, 'mensagem' => 'Informe um nome com 2 a 120 caracteres.']);
    }
    $sets[] = 'nome = :nome';
    $params['nome'] = $nome;
}
if (array_key_exists('lembretes_ativos', $body)) {
    if (!is_bool($body['lembretes_ativos'])) {
        jsonResponse(422, ['ok' => false, 'mensagem' => 'lembretes_ativos deve ser verdadeiro ou falso.']);
    }
    $sets[] = 'lembretes_ativos = CAST(:lembretes AS BOOLEAN)';
    $params['lembretes'] = $body['lembretes_ativos'] ? 'true' : 'false';
}
if (array_key_exists('avisos_alteracao', $body)) {
    if (!is_bool($body['avisos_alteracao'])) {
        jsonResponse(422, ['ok' => false, 'mensagem' => 'avisos_alteracao deve ser verdadeiro ou falso.']);
    }
    $sets[] = 'avisos_alteracao = CAST(:avisos AS BOOLEAN)';
    $params['avisos'] = $body['avisos_alteracao'] ? 'true' : 'false';
}
if (array_key_exists('antecedencia_dias', $body)) {
    $dias = filter_var($body['antecedencia_dias'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 14]]);
    if ($dias === false) {
        jsonResponse(422, ['ok' => false, 'mensagem' => 'A antecedência deve ser de 1 a 14 dias.']);
    }
    $sets[] = 'antecedencia_dias = :dias';
    $params['dias'] = $dias;
}
if (count($sets) === 0) {
    jsonResponse(422, ['ok' => false, 'mensagem' => 'Nenhum dado para atualizar.']);
}

try {
    $pdo = db();
    $params['id'] = $u['id'];
    $pdo->prepare('UPDATE estudante SET ' . implode(', ', $sets) . ' WHERE id_estudante = :id')->execute($params);
    $atualizado = findStudentById($pdo, $u['id']);
    jsonResponse(200, ['ok' => true, 'usuario' => publicStudent($atualizado ?? [])]);
} catch (Throwable $e) {
    serviceError($e);
}
