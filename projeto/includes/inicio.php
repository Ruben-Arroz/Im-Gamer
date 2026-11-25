<?php
// é chamado no início de todas as páginas, prepara a sessão, o BASE_URL e as funções

// as horas dos anúncios de LFG são as de Portugal, mesmo que o servidor esteja configurado com outra
date_default_timezone_set('Europe/Lisbon');

if (!defined('BASE_URL')) {
    $hostname = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);

    if (in_array($hostname, ['localhost', '127.0.0.1'], true)) {
        define('BASE_URL', '');
    } else {
        define('BASE_URL', '/tesp-ds-g17');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    // o servidor tem os sites dos grupos todos no mesmo endereço, por isso o cookie tem nome próprio
    // e só vai para a nossa pasta. o httponly não deixa o javascript lê-lo
    session_name('imgamer');
    session_set_cookie_params([
        'path' => BASE_URL . '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    // só aceita ids de sessão criados pelo próprio php
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// outros sites não podem abrir o I'm Gamer dentro de um iframe
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
}

// pasta das fotos de perfil, fica na raiz do site e não dentro do projeto
define('PASTA_UPLOADS', __DIR__ . '/../../uploads/');

// passwords da bd e chaves das apis, este ficheiro não vai para o git
require_once __DIR__ . '/../config/credentials.php';
require_once __DIR__ . '/funcoes.php';

// as classes só são carregadas quando uma página as usa pela primeira vez
// por exemplo quando uma página usa a classe Lfg o php vai buscar o classes/Lfg.php
function carregarClasse(string $classe): void
{
    $ficheiro = __DIR__ . '/../classes/' . $classe . '.php';
    if (is_file($ficheiro)) {
        require_once $ficheiro;
    }
}

spl_autoload_register('carregarClasse');
