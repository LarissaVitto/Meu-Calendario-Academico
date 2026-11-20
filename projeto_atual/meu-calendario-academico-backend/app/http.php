<?php
declare(strict_types=1);

function jsonResponse(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Cookie');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        jsonResponse(405, ['ok' => false, 'mensagem' => 'Método HTTP não permitido.']);
    }
}

function readJsonBody(): array
{
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        jsonResponse(415, ['ok' => false, 'mensagem' => 'Envie JSON com Content-Type: application/json.']);
    }
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 8192) {
        jsonResponse(413, ['ok' => false, 'mensagem' => 'Corpo da requisição muito grande.']);
    }
    $body = file_get_contents('php://input', false, null, 0, 8193);
    if ($body === false || strlen($body) > 8192) {
        jsonResponse(413, ['ok' => false, 'mensagem' => 'Corpo da requisição muito grande.']);
    }
    try {
        $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        jsonResponse(400, ['ok' => false, 'mensagem' => 'JSON inválido.']);
    }
    if (!is_array($data) || array_is_list($data)) {
        jsonResponse(400, ['ok' => false, 'mensagem' => 'O corpo deve ser um objeto JSON.']);
    }
    return $data;
}

function serviceError(Throwable $error): never
{
    error_log('[MCA] ' . get_class($error) . ': ' . $error->getMessage());
    jsonResponse(503, [
        'ok' => false,
        'mensagem' => 'Serviço indisponível. Confira a conexão com o PostgreSQL e as migrações.',
    ]);
}
