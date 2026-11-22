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

const COLUNAS_ESTUDANTE = 'id_estudante, nome, email, curso, lembretes_ativos, antecedencia_dias, avisos_alteracao';

/** Busca o estudante pelo e-mail (inclui o hash da senha, para o login). */
function findStudentByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT ' . COLUNAS_ESTUDANTE . ', senha FROM estudante WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute(['email' => $email]);
    $estudante = $stmt->fetch();
    return $estudante === false ? null : $estudante;
}

function findStudentById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT ' . COLUNAS_ESTUDANTE . ' FROM estudante WHERE id_estudante = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $estudante = $stmt->fetch();
    return $estudante === false ? null : $estudante;
}

/** Dados do estudante que podem ser enviados ao navegador (nunca inclui a senha). */
function publicStudent(array $e): array
{
    return [
        'id' => (int) $e['id_estudante'],
        'nome' => $e['nome'],
        'email' => $e['email'],
        'curso' => $e['curso'],
        'preferencias' => [
            'lembretes_ativos' => pgBool($e['lembretes_ativos']),
            'antecedencia_dias' => (int) $e['antecedencia_dias'],
            'avisos_alteracao' => pgBool($e['avisos_alteracao']),
        ],
    ];
}

/** Exige sessão válida; devolve o estudante autenticado. */
function authenticatedStudent(): array
{
    $id = $_SESSION['estudante_id'] ?? null;
    if (!is_int($id) || $id <= 0) {
        jsonResponse(401, ['ok' => false, 'mensagem' => 'Você precisa entrar na sua conta.']);
    }
    try {
        $estudante = findStudentById(db(), $id);
    } catch (Throwable $e) {
        serviceError($e);
    }
    if ($estudante === null) {
        unset($_SESSION['estudante_id']);
        jsonResponse(401, ['ok' => false, 'mensagem' => 'Sessão inválida. Faça login novamente.']);
    }
    return publicStudent($estudante);
}
