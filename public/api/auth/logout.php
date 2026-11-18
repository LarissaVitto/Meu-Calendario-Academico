<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');
requireCsrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
}
session_destroy();
jsonResponse(200, ['ok' => true, 'mensagem' => 'Sessão encerrada.']);
