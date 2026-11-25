<?php
// abas do lfg/anuncios.php, os convites agora estão na pasta jogadores
// usa o $abaAtual
?>
<nav class="abas">
    <?php foreach (Lfg::ABAS as $chave => $nome): ?>
        <a href="<?= BASE_URL ?>/projeto/lfg/anuncios.php?aba=<?= $chave ?>" class="<?= $abaAtual === $chave ? 'ativo' : '' ?>"><?= $nome ?></a>
    <?php endforeach; ?>
</nav>
