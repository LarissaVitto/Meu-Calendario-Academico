<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethods(['GET']);
$u = authenticatedStudent();
try {
    $s = db()->prepare(<<<'SQL'
        SELECT DISTINCT d.id_disciplina, d.nome, d.codigo_disc, d.professor
        FROM disciplina d
        JOIN matricula m ON m.id_disciplina = d.id_disciplina
        WHERE m.id_estudante = :aluno
        ORDER BY d.nome
    SQL);
    $s->execute(['aluno' => $u['id']]);
    jsonResponse(200, ['ok' => true, 'disciplinas' => $s->fetchAll()]);
} catch (Throwable $e) {
    serviceError($e);
}
