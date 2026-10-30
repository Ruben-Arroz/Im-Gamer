<?php
// separadores do LFG, usados no lfg.php e no convites.php
// $abaAtual diz qual está ativo e $avisosLfg (do header.php) é o número de convites e avisos por ver
?>
<nav class="abas">
    <?php foreach (Lfg::ABAS as $chave => $nome): ?>
        <a href="<?= BASE_URL ?>/projeto/lfg.php?aba=<?= $chave ?>" class="<?= $abaAtual === $chave ? 'ativo' : '' ?>"><?= $nome ?></a>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/projeto/convites.php" class="<?= $abaAtual === 'convites' ? 'ativo' : '' ?>">
        Convites
        <?php if ($avisosLfg > 0): ?>
            <span class="contador"><?= $avisosLfg ?></span>
        <?php endif; ?>
    </a>
</nav>
