<?php
// abas do lfg.php, os convites agora estão no jogadores.php
// usa o $abaAtual
?>
<nav class="abas">
    <?php foreach (Lfg::ABAS as $chave => $nome): ?>
        <a href="<?= BASE_URL ?>/projeto/lfg.php?aba=<?= $chave ?>" class="<?= $abaAtual === $chave ? 'ativo' : '' ?>"><?= $nome ?></a>
    <?php endforeach; ?>
</nav>
