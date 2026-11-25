<?php
// links Anterior e Seguinte sem perder a pesquisa do url
// precisa do $pagina e do $totalPaginas
?>
<?php if ($totalPaginas > 1): ?>
    <nav class="paginacao">
        <?php if ($pagina > 1): ?>
            <a href="<?= htmlspecialchars(urlPagina($pagina - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <span class="pagina-atual"><?= $pagina ?> de <?= $totalPaginas ?></span>
        <?php if ($pagina < $totalPaginas): ?>
            <a href="<?= htmlspecialchars(urlPagina($pagina + 1)) ?>">Seguinte</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
