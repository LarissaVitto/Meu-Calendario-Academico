<?php
declare(strict_types=1);

// Pasta raiz do projeto. Os arquivos de app/ ficam fora de public/ e não podem ser baixados pelo navegador.
define('PROJECT_ROOT', dirname(__DIR__));

/** Lê uma configuração do ambiente (ou do .env). */
function env(string $nome, ?string $padrao = null): ?string
{
    $valor = getenv($nome);
    return $valor === false ? $padrao : $valor;
}

/** Leitor simples de .env (NOME=valor, uma por linha, # para comentários). */
function carregarEnv(string $arquivo): void
{
    if (!is_file($arquivo)) {
        return;
    }
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
    if ($linhas === false) {
        throw new RuntimeException('Não foi possível ler o arquivo .env.');
    }
    foreach ($linhas as $i => $linha) {
        if ($i === 0 && str_starts_with($linha, "\xEF\xBB\xBF")) {
            $linha = substr($linha, 3); // BOM (Bloco de Notas do Windows)
        }
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
            continue;
        }
        [$nome, $valor] = explode('=', $linha, 2);
        $nome = trim($nome);
        $valor = trim($valor);
        $tamanho = strlen($valor);
        if ($tamanho >= 2 && (
            ($valor[0] === '"' && $valor[$tamanho - 1] === '"') ||
            ($valor[0] === "'" && $valor[$tamanho - 1] === "'")
        )) {
            $valor = substr($valor, 1, -1);
        }
        if ($nome !== '' && getenv($nome) === false) {
            putenv($nome . '=' . $valor);
        }
    }
}

carregarEnv(PROJECT_ROOT . '/.env');

// Horário local da UTFPR; o PostgreSQL guarda os instantes em TIMESTAMPTZ.
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/moodle.php';
require_once __DIR__ . '/avisos.php';
require_once __DIR__ . '/sync.php';
require_once __DIR__ . '/pdf.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('MCA_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Scripts de linha de comando (scripts/*.php) não usam sessão.
if (PHP_SAPI !== 'cli') {
    startSecureSession();
}
