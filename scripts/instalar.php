<?php
declare(strict_types=1);

// Uso:  php scripts/instalar.php            (cria as tabelas e carrega os dados de exemplo)
//       php scripts/instalar.php --recriar  (APAGA tudo e recria do zero)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use apenas a linha de comando.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$recriar = in_array('--recriar', $argv, true);

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Não foi possível conectar ao PostgreSQL: {$e->getMessage()}\n");
    fwrite(STDERR, "Confira o arquivo .env e se o banco '" . (env('DB_NAME', 'meu_calendario_academico')) . "' foi criado.\n");
    exit(1);
}

try {
    $existe = $pdo->query("SELECT to_regclass('public.estudante')")->fetchColumn();
    if ($existe !== false && $existe !== null && !$recriar) {
        fwrite(STDERR, "O banco já possui tabelas do projeto. Para apagar tudo e recriar, use:\n  php scripts/instalar.php --recriar\n");
        exit(1);
    }

    if ($recriar) {
        echo "Removendo tabelas antigas...\n";
        $drop = file_get_contents(PROJECT_ROOT . '/database/drop.sql');
        if ($drop === false) {
            throw new RuntimeException('database/drop.sql não encontrado.');
        }
        $pdo->exec($drop);
    }

    echo "Criando tabelas...\n";
    $schema = file_get_contents(PROJECT_ROOT . '/database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('database/schema.sql não encontrado.');
    }
    $pdo->exec($schema);

    echo "Sincronizando com o planejamento do Moodle (" . descricaoFonteMoodle() . ")...\n";
    $resumo = sincronizarPlanejamentos($pdo, 'cli');
    foreach ($resumo['disciplinas'] ?? [] as $d) {
        echo "  [{$d['resultado']}] {$d['codigo']}: {$d['detalhes']}\n";
    }
    if (($resumo['resultado'] ?? 'FALHA') === 'FALHA') {
        fwrite(STDERR, "Atenção: a sincronização inicial falhou: " . ($resumo['mensagem'] ?? 'veja os detalhes acima') . "\n");
    }

    // Contas de demonstração (somente para uso local).
    $senhaDemo = env('DEMO_SENHA', 'demo1234') ?? 'demo1234';
    $hash = password_hash($senhaDemo, PASSWORD_DEFAULT);
    $mes = (int) date('n');
    $semestre = $mes >= 7 ? 2 : 1;
    $ano = (int) date('Y');

    $disciplinas = $pdo->query('SELECT id_disciplina, codigo_disc FROM disciplina ORDER BY codigo_disc')->fetchAll();
    $contas = [
        ['Estudante Exemplo', 'aluno.demo@alunos.utfpr.edu.br', null],
        ['Estudante Dois', 'aluno2.demo@alunos.utfpr.edu.br', ['AED2', 'ARSW', 'RDCP']],
    ];
    $insEstudante = $pdo->prepare('INSERT INTO estudante (nome, email, senha) VALUES (:nome, :email, :senha) RETURNING id_estudante');
    $insMatricula = $pdo->prepare(
        'INSERT INTO matricula (id_estudante, id_disciplina, semestre, ano)
         VALUES (:aluno, :disciplina, :semestre, :ano)
         ON CONFLICT (id_estudante, id_disciplina, ano, semestre) DO NOTHING'
    );
    foreach ($contas as [$nome, $email, $codigos]) {
        $insEstudante->execute(['nome' => $nome, 'email' => $email, 'senha' => $hash]);
        $idAluno = (int) $insEstudante->fetchColumn();
        foreach ($disciplinas as $d) {
            if ($codigos !== null && !in_array($d['codigo_disc'], $codigos, true)) {
                continue;
            }
            $insMatricula->execute([
                'aluno' => $idAluno, 'disciplina' => $d['id_disciplina'],
                'semestre' => $semestre, 'ano' => $ano,
            ]);
        }
    }

    echo "\nInstalação concluída.\n\n";
    echo "Contas de demonstração (apenas para uso local):\n";
    foreach ($contas as [$nome, $email, $codigos]) {
        echo "  {$email}  /  senha: {$senhaDemo}  (" . ($codigos === null ? 'todas as disciplinas' : implode(', ', $codigos)) . ")\n";
    }
    echo "\nPara iniciar o sistema:\n  php -S localhost:8000 -t public\nDepois abra http://localhost:8000\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro na instalação: {$e->getMessage()}\n");
    exit(1);
}
