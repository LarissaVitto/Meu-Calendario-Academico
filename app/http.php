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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Aceita apenas os métodos HTTP informados; devolve o método usado. */
function requireMethods(array $permitidos): string
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($metodo, $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));
        jsonResponse(405, ['ok' => false, 'mensagem' => 'Método HTTP não permitido.']);
    }
    return $metodo;
}

function readJsonBody(int $maxBytes = 8192): array
{
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        jsonResponse(415, ['ok' => false, 'mensagem' => 'Envie JSON com Content-Type: application/json.']);
    }
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > $maxBytes) {
        jsonResponse(413, ['ok' => false, 'mensagem' => 'Corpo da requisição muito grande.']);
    }
    $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($body === false || strlen($body) > $maxBytes) {
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
        'mensagem' => 'Serviço indisponível. Confira a conexão com o PostgreSQL e se o banco foi instalado (php scripts/instalar.php).',
    ]);
}
