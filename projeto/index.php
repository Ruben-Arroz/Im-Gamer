<?php
$tituloPagina = 'Novidades';
require_once __DIR__ . '/includes/header.php';
?>

<section class="novidades">
    <h1>Novidades</h1>

    <h2>Jogos em promoção</h2>

    <div class="filtros">
        <button type="button" data-ordem="Reviews" class="ativo">Popularidade</button>
        <button type="button" data-ordem="Savings">Maior desconto</button>
        <button type="button" data-ordem="Price">Menor preço</button>
    </div>

    <p id="promocoes-aviso" class="texto-dim"></p>
    <div id="promocoes" class="grelha-jogos"></div>

    <div class="paginacao">
        <button type="button" id="pagina-anterior">Anterior</button>
        <button type="button" id="pagina-seguinte">Seguinte</button>
    </div>

    <p class="fonte">Promoções da <a href="https://www.cheapshark.com" target="_blank" rel="noopener">CheapShark</a>. Preços em dólares.</p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/novidades.js?v=1"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
