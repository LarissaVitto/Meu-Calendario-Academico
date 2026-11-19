<?php
declare(strict_types=1);

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        jsonResponse(419, ['ok' => false, 'mensagem' => 'Sessão de segurança expirada. Atualize a página.']);
    }
}

// Nome, e-mail e papel são consultados no banco, não no localStorage.
function findAccountByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT c.id_conta, c.email, c.senha, c.papel,
               COALESCE(e.nome, p.nome) AS nome,
               e.id_estudante, p.id_professor
        FROM conta c
        LEFT JOIN estudante e ON e.id_conta = c.id_conta
        LEFT JOIN professor p ON p.id_conta = c.id_conta
        WHERE LOWER(c.email) = LOWER(:email)
        LIMIT 1
    SQL);
    $stmt->execute(['email' => $email]);
    $conta = $stmt->fetch();
    return $conta === false ? null : $conta;
}

function findAccountById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT c.id_conta, c.email, c.papel,
               COALESCE(e.nome, p.nome) AS nome,
               e.id_estudante, p.id_professor
        FROM conta c
        LEFT JOIN estudante e ON e.id_conta = c.id_conta
        LEFT JOIN professor p ON p.id_conta = c.id_conta
        WHERE c.id_conta = :id AND
              ((c.papel = 'ALUNO' AND e.id_estudante IS NOT NULL) OR
               (c.papel = 'PROFESSOR' AND p.id_professor IS NOT NULL))
        LIMIT 1
    SQL);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $conta = $stmt->fetch();
    return $conta === false ? null : $conta;
}

function publicAccount(array $conta): array
{
    return [
        'id' => (int) $conta['id_conta'],
        'nome' => $conta['nome'],
        'email' => $conta['email'],
        'papel' => $conta['papel'],
        'id_estudante' => $conta['id_estudante'] !== null ? (int) $conta['id_estudante'] : null,
        'id_professor' => $conta['id_professor'] !== null ? (int) $conta['id_professor'] : null,
    ];
}

function authenticatedAccount(): array
{
    $id = $_SESSION['conta_id'] ?? null;
    if (!is_int($id) || $id <= 0) {
        jsonResponse(401, ['ok' => false, 'mensagem' => 'Você precisa entrar na sua conta.']);
    }
    try {
        $conta = findAccountById(db(), $id);
    } catch (Throwable $e) {
        serviceError($e);
    }
    if ($conta === null) {
        unset($_SESSION['conta_id']);
        jsonResponse(401, ['ok' => false, 'mensagem' => 'Sessão inválida. Faça login novamente.']);
    }
    return publicAccount($conta);
}

function requireRole(string $papel): array
{
    $conta = authenticatedAccount();
    if ($conta['papel'] !== $papel) {
        jsonResponse(403, ['ok' => false, 'mensagem' => 'Você não tem permissão para esta ação.']);
    }
    return $conta;
}
