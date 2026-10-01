<?php
require_once __DIR__ . '/../config/database.php';

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
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/common/style.css">
<link rel="stylesheet" href="/projeto/assets/css/site.css">
</head>
<body>

<header class="site-header">
    <a href="/projeto/index.php" class="logo">I'm Gamer</a>
    <nav>
        <a href="/projeto/index.php">Novidades</a>
        <a href="/projeto/lfg.php">LFG</a>
    </nav>
</header>

<main>