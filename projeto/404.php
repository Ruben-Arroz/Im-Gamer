<?php
require_once __DIR__ . '/includes/bootstrap.php';

// o IIS mostra esta página quando um endereço do projeto não existe (projeto/web.config)
http_response_code(404);

$tituloPagina = 'Página não encontrada';
require_once __DIR__ . '/includes/header.php';
?>

<section class="erro-404">
    <h1>404</h1>
    <p>Esta página não existe ou mudou de sítio.</p>
    <a class="botao-principal" href="<?= BASE_URL ?>/projeto/index.php">Voltar ao início</a>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
