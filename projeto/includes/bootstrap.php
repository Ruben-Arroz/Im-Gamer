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

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Utilizador.php';
require_once __DIR__ . '/../classes/ContasLigadas.php';
