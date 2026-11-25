<?php
require_once __DIR__ . '/inicio.php';

// cada página define o seu título, a página inicial não define e fica só "I'm Gamer"
if (!isset($tituloPagina)) {
    $tituloPagina = "I'm Gamer";
}

// convites por responder e avisos por ler, é o número ao lado de Jogadores
$avisosConvites = 0;
if (Auth::autenticado()) {
    $avisosConvites = Convites::pendentes($_SESSION['utilizador_id']) + Notificacoes::porLer($_SESSION['utilizador_id']);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tituloPagina) ?></title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/projeto/assets/img/logos/versao3/logo_branco_fundo_preto.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/common/style.css?v=2">
<link rel="stylesheet" href="<?= BASE_URL ?>/projeto/assets/css/site.css?v=39">
</head>
<body>

<header class="site-header">
    <a href="<?= BASE_URL ?>/projeto/index.php" class="logo">I'm Gamer</a>
    <nav>
        <a href="<?= BASE_URL ?>/projeto/jogos/catalogo.php">Catálogo</a>
        <a href="<?= BASE_URL ?>/projeto/jogos/lancamentos.php">Lançamentos</a>
        <a href="<?= BASE_URL ?>/projeto/lfg/anuncios.php">LFG</a>
        <a href="<?= BASE_URL ?>/projeto/jogadores/procurar.php">
            Jogadores
            <?php if ($avisosConvites > 0): ?>
                <span class="contador" title="Convites e avisos por ver"><?= $avisosConvites ?></span>
            <?php endif; ?>
        </a>
        <?php if (Auth::admin()): ?>
            <a href="<?= BASE_URL ?>/projeto/admin/resumo.php">Admin</a>
        <?php endif; ?>
        <?php if (Auth::autenticado()): ?>
            <a href="<?= BASE_URL ?>/projeto/perfil/perfil.php">Perfil</a>
            <a href="<?= BASE_URL ?>/projeto/conta/logout.php">Sair</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/projeto/conta/login.php">Entrar</a>
            <a href="<?= BASE_URL ?>/projeto/conta/registo.php">Registar</a>
        <?php endif; ?>
    </nav>
</header>

<main>