<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '5432');
    $name = env('DB_NAME', 'meu_calendario_academico');
    $user = env('DB_USER', 'postgres');
    $password = env('DB_PASSWORD');
    if ($password === null) {
        throw new RuntimeException('DB_PASSWORD não foi configurado (veja o arquivo .env).');
    }
    // PDO com comandos preparados: entradas do usuário nunca são concatenadas no SQL.
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);
    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET client_encoding TO 'UTF8'");
    $connection->exec("SET TIME ZONE 'America/Sao_Paulo'");
    return $connection;
}

/** Converte o valor booleano devolvido pelo PostgreSQL/PDO para bool do PHP. */
function pgBool(mixed $valor): bool
{
    return $valor === true || $valor === 't' || $valor === 'true' || $valor === 1 || $valor === '1';
}

/** Converte um timestamp do PostgreSQL em ISO 8601 com fuso (ou null). */
function isoOuNull(?string $valor): ?string
{
    if ($valor === null || $valor === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($valor))->format(DATE_ATOM);
    } catch (Throwable) {
        return null;
    }
}
