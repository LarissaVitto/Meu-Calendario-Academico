<?php
declare(strict_types=1);

// Executar: php scripts/criar_contas_teste.php
// Cria credenciais aleatórias para 1 aluno e 1 professor em desenvolvimento.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use apenas a linha de comando.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

function criarContaDemo(PDO $pdo, string $nome, string $email, string $papel): void
{
    $pdo->beginTransaction();
    try {
        $existe = $pdo->prepare('SELECT id_conta FROM conta WHERE LOWER(email) = LOWER(:email)');
        $existe->execute(['email' => $email]);
        if ($existe->fetch()) {
            $pdo->rollBack();
            echo "[IGNORADO] {$email} já existe; nenhuma senha foi alterada.\n";
            return;
        }

        $senha = bin2hex(random_bytes(12)); // 24 caracteres aleatórios; exibir apenas na execução.
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO conta (email, senha, papel) VALUES (:email, :senha, :papel) RETURNING id_conta');
        $stmt->execute(['email' => $email, 'senha' => $hash, 'papel' => $papel]);
        $idConta = (int) $stmt->fetchColumn();

        if ($papel === 'ALUNO') {
            // Campos legados em estudante mantidos para não quebrar o schema original.
            $stmt = $pdo->prepare('INSERT INTO estudante (nome, email, senha, id_conta) VALUES (:nome, :email, :senha, :id)');
            $stmt->execute(['nome' => $nome, 'email' => $email, 'senha' => $hash, 'id' => $idConta]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO professor (id_conta, nome) VALUES (:conta, :nome) RETURNING id_professor');
            $stmt->execute(['conta' => $idConta, 'nome' => $nome]);
            $idProfessor = (int) $stmt->fetchColumn();
            // Na ausência de disciplinas, o vínculo poderá ser definido posteriormente.
            $idDisciplina = $pdo->query('SELECT id_disciplina FROM disciplina ORDER BY id_disciplina LIMIT 1')->fetchColumn();
            if ($idDisciplina !== false) {
                $rel = $pdo->prepare('INSERT INTO professor_disciplina (id_professor, id_disciplina) VALUES (:professor, :disciplina) ON CONFLICT DO NOTHING');
                $rel->execute(['professor' => $idProfessor, 'disciplina' => $idDisciplina]);
            }
        }
        $pdo->commit();
        echo "[CRIADO] {$papel}\n  E-mail: {$email}\n  Senha temporária: {$senha}\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

try {
    $pdo = db();
    criarContaDemo($pdo, 'Aluno Demonstração', 'aluno.demo@alunos.utfpr.edu.br', 'ALUNO');
    criarContaDemo($pdo, 'Professor Demonstração', 'professor.demo@utfpr.edu.br', 'PROFESSOR');
    echo "\nGuarde as senhas exibidas. O script não as mostrará novamente.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao criar contas de teste: {$e->getMessage()}\n");
    exit(1);
}
