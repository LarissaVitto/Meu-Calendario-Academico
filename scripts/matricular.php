<?php
declare(strict_types=1);

// Uso: php scripts/matricular.php EMAIL CODIGO [CODIGO ...]
// Exemplo: php scripts/matricular.php aluno.demo@alunos.utfpr.edu.br ARSW PWB1
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use apenas a linha de comando.');
}
if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/matricular.php EMAIL CODIGO [CODIGO ...]\n");
    exit(2);
}
require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $aluno = findStudentByEmail($pdo, strtolower(trim($argv[1])));
    if ($aluno === null) {
        fwrite(STDERR, "Estudante não encontrado: {$argv[1]}\n");
        exit(1);
    }
    $semestre = (int) date('n') >= 7 ? 2 : 1;
    $ano = (int) date('Y');
    $busca = $pdo->prepare('SELECT id_disciplina FROM disciplina WHERE UPPER(codigo_disc) = UPPER(:codigo)');
    $ins = $pdo->prepare(
        'INSERT INTO matricula (id_estudante, id_disciplina, semestre, ano)
         VALUES (:aluno, :disciplina, :semestre, :ano)
         ON CONFLICT (id_estudante, id_disciplina, ano, semestre) DO NOTHING'
    );
    $erros = 0;
    foreach (array_slice($argv, 2) as $codigo) {
        $busca->execute(['codigo' => $codigo]);
        $idDisciplina = $busca->fetchColumn();
        if ($idDisciplina === false) {
            fwrite(STDERR, "Disciplina não encontrada: {$codigo} (ela é criada pela sincronização do planejamento)\n");
            $erros++;
            continue;
        }
        $ins->execute(['aluno' => $aluno['id_estudante'], 'disciplina' => $idDisciplina, 'semestre' => $semestre, 'ano' => $ano]);
        echo "Matriculado em {$codigo}.\n";
    }
    exit($erros > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}
