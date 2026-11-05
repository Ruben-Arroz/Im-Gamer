<?php
// título, separadores e avisos do painel, usados em todas as páginas admin_*.php
// precisa do $erro (ou null). o aviso de sucesso vem da sessão e só aparece uma vez
$paginaAtual = basename($_SERVER['SCRIPT_NAME']);
$avisoAdmin = $_SESSION['aviso_admin'] ?? null;
unset($_SESSION['aviso_admin']);
?>
<h1>Administração</h1>

<nav class="abas">
    <?php foreach (Admin::ABAS as $ficheiro => $nome): ?>
        <a href="<?= BASE_URL ?>/projeto/<?= $ficheiro ?>" class="<?= $paginaAtual === $ficheiro ? 'ativo' : '' ?>"><?= $nome ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($erro): ?>
    <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<?php if ($avisoAdmin): ?>
    <p class="aviso aviso-sucesso"><?= htmlspecialchars($avisoAdmin) ?></p>
<?php endif; ?>
