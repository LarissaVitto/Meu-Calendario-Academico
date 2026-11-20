<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');
requireCsrf();
$body = readJsonBody();
$email = strtolower(trim(is_string($body['email'] ?? null) ? $body['email'] : ''));
$senha = is_string($body['senha'] ?? null) ? $body['senha'] : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)
    || !preg_match('/^[^\s@]+@(alunos\.)?utfpr\.edu\.br$/iD', $email)
    || strlen($email) > 150
    || strlen($senha) === 0
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
    $conta = findAccountByEmail($pdo, $email);
    // Hash bcrypt de referência para não encurtar muito o tempo de uma conta inexistente.
    $hash = $conta['senha'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $correta = password_verify($senha, $hash);
    if (!$correta || !$conta || !$conta['nome'] ||
        ($conta['papel'] === 'ALUNO' && $conta['id_estudante'] === null) ||
        ($conta['papel'] === 'PROFESSOR' && $conta['id_professor'] === null)) {
        $_SESSION['login_failures'][] = $agora;
        jsonResponse(401, ['ok' => false, 'mensagem' => 'E-mail ou senha incorretos.']);
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE conta SET senha = :senha WHERE id_conta = :id');
        $rehash->execute(['senha' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $conta['id_conta']]);
    }
    session_regenerate_id(true);  // Protege contra fixação de sessão.
    $_SESSION['conta_id'] = (int) $conta['id_conta'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_failures']);
    jsonResponse(200, [
        'ok' => true,
        'usuario' => publicAccount($conta),
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (Throwable $e) {
    serviceError($e);
}
