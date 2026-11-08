<?php

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

require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Api.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Utilizador.php';
require_once __DIR__ . '/../classes/ContasLigadas.php';
require_once __DIR__ . '/../classes/Biblioteca.php';
require_once __DIR__ . '/../classes/Jogo.php';
require_once __DIR__ . '/../classes/Catalogo.php';
require_once __DIR__ . '/../classes/Moeda.php';
require_once __DIR__ . '/../classes/Steam.php';
require_once __DIR__ . '/../classes/Xbox.php';
require_once __DIR__ . '/../classes/Gog.php';
require_once __DIR__ . '/../classes/Epic.php';
require_once __DIR__ . '/../classes/Playstation.php';
require_once __DIR__ . '/../classes/JogosManuais.php';
require_once __DIR__ . '/../classes/Estatisticas.php';
require_once __DIR__ . '/../classes/ListaDesejos.php';
require_once __DIR__ . '/../classes/Avaliacoes.php';
require_once __DIR__ . '/../classes/Notificacoes.php';
require_once __DIR__ . '/../classes/Convites.php';
require_once __DIR__ . '/../classes/Lfg.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/TokensApi.php';
