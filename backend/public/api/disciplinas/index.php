<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');
$u = authenticatedAccount();
try {
    $pdo = db();
    if ($u['papel'] === 'PROFESSOR') {
        $sql = 'SELECT d.id_disciplina, d.nome, d.codigo_disc FROM disciplina d JOIN professor_disciplina pd ON pd.id_disciplina = d.id_disciplina WHERE pd.id_professor = :id ORDER BY d.nome';
        $id = $u['id_professor'];
    } else {
        $sql = 'SELECT DISTINCT d.id_disciplina, d.nome, d.codigo_disc FROM disciplina d JOIN matricula m ON m.id_disciplina = d.id_disciplina WHERE m.id_estudante = :id ORDER BY d.nome';
        $id = $u['id_estudante'];
    }
    $s = $pdo->prepare($sql);
    $s->execute(['id' => $id]);
    jsonResponse(200, ['ok' => true, 'disciplinas' => $s->fetchAll()]);
} catch (Throwable $e) { serviceError($e); }
