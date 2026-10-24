<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    $hostname = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);

    if (in_array($hostname, ['localhost', '127.0.0.1'], true)) {
        define('BASE_URL', '');
    } else {
        define('BASE_URL', '/tesp-ds-g17');
    }
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
