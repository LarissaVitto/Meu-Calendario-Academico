<?php
declare(strict_types=1);

// Executar: php scripts/vincular_professor.php professor.demo@utfpr.edu.br ARSW
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use apenas a linha de comando.');
}
if ($argc !== 3) {
    fwrite(STDERR, "Uso: php scripts/vincular_professor.php EMAIL_PROFESSOR CODIGO_DISCIPLINA\n");
    exit(2);
}
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    $pdo = db();
    $stmt = $pdo->prepare(<<<'SQL'
        INSERT INTO professor_disciplina (id_professor, id_disciplina)
        SELECT p.id_professor, d.id_disciplina
        FROM professor p
        JOIN conta c ON c.id_conta = p.id_conta AND c.papel = 'PROFESSOR'
        CROSS JOIN disciplina d
        WHERE LOWER(c.email) = LOWER(:email) AND UPPER(d.codigo_disc) = UPPER(:codigo)
        ON CONFLICT DO NOTHING
        RETURNING id_professor
    SQL);
    $stmt->execute(['email' => $argv[1], 'codigo' => $argv[2]]);
    if ($stmt->fetchColumn() === false) {
        echo "Nenhum vínculo criado. Verifique professor e disciplina; ou o vínculo já existe.\n";
        exit(1);
    }
    echo "Professor vinculado à disciplina com sucesso.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}
