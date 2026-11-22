<?php
declare(strict_types=1);

// Uso: php scripts/criar_aluno.php "Nome Completo" email@alunos.utfpr.edu.br [senha]
// Sem senha, uma senha aleatória é gerada e exibida uma única vez.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use apenas a linha de comando.');
}
if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/criar_aluno.php \"Nome Completo\" email@alunos.utfpr.edu.br [senha]\n");
    exit(2);
}
require dirname(__DIR__) . '/app/bootstrap.php';

$nome = trim($argv[1]);
$email = strtolower(trim($argv[2]));
$senha = $argv[3] ?? bin2hex(random_bytes(6));

if (mb_strlen($nome) < 2 || preg_match('/^[^\s@]+@(alunos\.)?utfpr\.edu\.br$/iD', $email) !== 1) {
    fwrite(STDERR, "Informe um nome e um e-mail institucional (@utfpr.edu.br ou @alunos.utfpr.edu.br).\n");
    exit(2);
}
try {
    $pdo = db();
    if (findStudentByEmail($pdo, $email) !== null) {
        fwrite(STDERR, "Já existe um estudante com este e-mail.\n");
        exit(1);
    }
    $stmt = $pdo->prepare('INSERT INTO estudante (nome, email, senha) VALUES (:nome, :email, :senha)');
    $stmt->execute(['nome' => $nome, 'email' => $email, 'senha' => password_hash($senha, PASSWORD_DEFAULT)]);
    echo "Estudante criado.\n  E-mail: {$email}\n  Senha:  {$senha}\n";
    echo "Use scripts/matricular.php para matriculá-lo nas disciplinas.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}
