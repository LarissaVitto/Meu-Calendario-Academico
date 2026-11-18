<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');
$usuario = authenticatedAccount();
$id = filter_var($_GET['id_atividade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) jsonResponse(422, ['ok' => false, 'mensagem' => 'ID de atividade inválido.']);
try {
    $pdo = db();
    if ($usuario['papel'] === 'PROFESSOR') {
        $sql = 'SELECT 1 FROM atividade a JOIN professor_disciplina pd ON pd.id_disciplina = a.id_disciplina WHERE a.id_atividade = :id AND pd.id_professor = :usuario';
        $uid = $usuario['id_professor'];
    } else {
        $sql = "SELECT 1 FROM atividade a JOIN matricula m ON m.id_disciplina = a.id_disciplina WHERE a.id_atividade = :id AND a.status = 'PUBLICADO' AND m.id_estudante = :usuario";
        $uid = $usuario['id_estudante'];
    }
    $s = $pdo->prepare($sql); $s->execute(['id' => $id, 'usuario' => $uid]);
    if (!$s->fetchColumn()) jsonResponse(404, ['ok' => false, 'mensagem' => 'Atividade não encontrada.']);
    $s = $pdo->prepare('SELECT id_historico, descricao_alteracao, data_alteracao FROM historico_alteracao WHERE id_atividade = :id ORDER BY data_alteracao DESC, id_historico DESC LIMIT 100');
    $s->execute(['id' => $id]);
    jsonResponse(200, ['ok' => true, 'historico' => $s->fetchAll()]);
} catch (Throwable $e) { serviceError($e); }
