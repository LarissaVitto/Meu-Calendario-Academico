<?php
declare(strict_types=1);

// Arquivo fora da pasta public/: não pode ser baixado pelo navegador.
const PROJECT_ROOT = __DIR__ . '/..';

// Usa configuração de ambiente, com .env local simples (sem bibliotecas externas).
$envFile = PROJECT_ROOT . '/.env';
if (is_file($envFile)) {
    $vars = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($vars === false) {
        throw new RuntimeException('Arquivo .env inválido.');
    }
    foreach ($vars as $name => $value) {
        if (getenv((string) $name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

// Horário local da UTFPR; a sessão e o PostgreSQL armazenam os timestamps de forma segura.
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/auth.php';

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

startSecureSession();
