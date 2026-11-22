<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethods(['POST']);
requireCsrf();
$body = readJsonBody();
$email = strtolower(trim(is_string($body['email'] ?? null) ? $body['email'] : ''));
$senha = is_string($body['senha'] ?? null) ? $body['senha'] : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)
    || preg_match('/^[^\s@]+@(alunos\.)?utfpr\.edu\.br$/iD', $email) !== 1
    || strlen($email) > 150
    || $senha === ''
    || strlen($senha) > 4096) {
    jsonResponse(422, ['ok' => false, 'mensagem' => 'Informe e-mail institucional e senha válidos.']);
}

// Limite básico de tentativas por sessão; em produção complementar com IP/rede.
$agora = time();
$tentativas = $_SESSION['login_failures'] ?? [];
$tentativas = array_values(array_filter($tentativas, static fn ($t) => is_int($t) && $t > $agora - 900));
$_SESSION['login_failures'] = $tentativas;
if (count($tentativas) >= 5) {
    jsonResponse(429, ['ok' => false, 'mensagem' => 'Muitas tentativas. Tente novamente em 15 minutos.']);
}

try {
    $pdo = db();
    $estudante = findStudentByEmail($pdo, $email);
    // Hash de referência: mantém o tempo de resposta parecido quando o e-mail não existe.
    $hash = $estudante['senha'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $correta = password_verify($senha, $hash);
    if (!$correta || $estudante === null) {
        $_SESSION['login_failures'][] = $agora;
        jsonResponse(401, ['ok' => false, 'mensagem' => 'E-mail ou senha incorretos.']);
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE estudante SET senha = :senha WHERE id_estudante = :id');
        $rehash->execute(['senha' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $estudante['id_estudante']]);
    }
    session_regenerate_id(true);  // protege contra fixação de sessão
    $_SESSION['estudante_id'] = (int) $estudante['id_estudante'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_failures']);
    jsonResponse(200, [
        'ok' => true,
        'usuario' => publicStudent($estudante),
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (Throwable $e) {
    serviceError($e);
}
