<?php
// abas da pasta jogadores, usadas no procurar.php e no convites.php
// usa o $abaAtual e o $avisosConvites que vem do header.php
?>
<nav class="abas">
    <a href="<?= BASE_URL ?>/projeto/jogadores/procurar.php" class="<?= $abaAtual === 'procurar' ? 'ativo' : '' ?>">Procurar</a>
    <a href="<?= BASE_URL ?>/projeto/jogadores/convites.php" class="<?= $abaAtual === 'convites' ? 'ativo' : '' ?>">
        Convites
        <?php if ($avisosConvites > 0): ?>
            <span class="contador"><?= $avisosConvites ?></span>
        <?php endif; ?>
    </a>
</nav>
