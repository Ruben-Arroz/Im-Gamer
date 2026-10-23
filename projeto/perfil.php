<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

// com ?u=nome mostra o perfil de outra pessoa, sem nada mostra o meu
if (isset($_GET['u'])) {
    $utilizador = Utilizador::buscarPorNome($_GET['u']);
} else {
    $utilizador = Utilizador::buscarPorId($_SESSION['utilizador_id']);
}

if ($utilizador) {
    $proprio = (int) $utilizador['id'] === (int) $_SESSION['utilizador_id'];
    $contas = ContasLigadas::listar($utilizador['id']);
}

$tituloPagina = 'Perfil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="perfil">
    <?php if (!$utilizador): ?>
        <h1>Perfil</h1>
        <p>Este utilizador não existe.</p>
    <?php else: ?>
        <div class="perfil-topo">
            <?php if ($utilizador['foto_perfil']): ?>
                <img class="perfil-foto" src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($utilizador['foto_perfil']) ?>" alt="Foto de perfil">
            <?php else: ?>
                <div class="perfil-foto perfil-sem-foto">sem foto</div>
            <?php endif; ?>

            <div>
                <h1><?= htmlspecialchars($utilizador['nome_utilizador']) ?></h1>
                <?php if ($proprio): ?>
                    <p class="perfil-email"><?= htmlspecialchars($utilizador['email']) ?></p>
                <?php endif; ?>
                <p class="perfil-data">Membro desde <?= (new DateTime($utilizador['data_criacao']))->format('d/m/Y') ?></p>
            </div>
        </div>

        <p class="perfil-bio">
            <?= $utilizador['bio'] ? nl2br(htmlspecialchars($utilizador['bio'])) : 'Ainda sem biografia.' ?>
        </p>

        <?php if ($proprio): ?>
            <nav class="perfil-links">
                <a href="<?= BASE_URL ?>/projeto/editar_perfil.php">Editar perfil</a>
                <a href="<?= BASE_URL ?>/projeto/contas.php">Contas ligadas</a>
                <a href="<?= BASE_URL ?>/projeto/definicoes.php">Definições</a>
            </nav>
        <?php endif; ?>

        <h2>Contas ligadas</h2>

        <?php if (!$contas): ?>
            <p class="texto-dim">Nenhuma conta ligada.</p>
        <?php else: ?>
            <ul class="lista-contas">
                <?php foreach ($contas as $conta): ?>
                    <li>
                        <span class="conta-servico"><?= ContasLigadas::SERVICOS[$conta['servico']] ?></span>
                        <span><?= htmlspecialchars($conta['nome_publico'] ?? $conta['identificador']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
