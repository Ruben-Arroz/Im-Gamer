<?php
require_once __DIR__ . '/../config/database.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '/tesp-ds-g17');
}

$tituloBase = "I'm Gamer";
$tituloPagina = isset($tituloPagina) ? "{$tituloPagina} | {$tituloBase}" : $tituloBase;
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tituloPagina) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/projeto/logos/versao3/logo_branco_fundo_preto.png">
<link rel="stylesheet" href="<?= BASE_URL ?>/common/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/projeto/assets/css/site.css">
</head>
<body>

<header class="site-header">
    <a href="<?= BASE_URL ?>/projeto/index.php" class="logo">I'm Gamer</a>
    <nav>
        <a href="<?= BASE_URL ?>/projeto/index.php">Novidades</a>
        <a href="<?= BASE_URL ?>/projeto/lfg.php">LFG</a>
    </nav>
</header>

<main>