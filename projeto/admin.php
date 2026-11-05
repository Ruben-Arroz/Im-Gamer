<?php
require_once __DIR__ . '/includes/bootstrap.php';

Admin::verificar();

$erro = null;
$numeros = Admin::resumo();
$plataformas = Admin::contasPorPlataforma();
$ultimos = Admin::ultimosRegistos(5);

$tituloPagina = 'Administração';
require_once __DIR__ . '/includes/header.php';
?>

<section class="admin">
    <?php require __DIR__ . '/includes/abas_admin.php'; ?>

    <div class="numeros">
        <div class="numero">
            <strong><?= $numeros['utilizadores'] ?></strong>
            <span>utilizadores</span>
        </div>
        <div class="numero">
            <strong><?= $numeros['contas'] ?></strong>
            <span>contas ligadas</span>
        </div>
        <div class="numero">
            <strong><?= $numeros['jogos'] ?></strong>
            <span>jogos guardados</span>
        </div>
        <div class="numero">
            <strong><?= $numeros['anuncios'] ?></strong>
            <span>anúncios de LFG no ar</span>
        </div>
        <div class="numero">
            <strong><?= $numeros['avaliacoes'] ?></strong>
            <span>avaliações</span>
        </div>
    </div>

    <h2>Contas por plataforma</h2>
    <?php if ($plataformas): ?>
        <ul class="lista-admin">
            <?php foreach ($plataformas as $plataforma): ?>
                <li>
                    <div class="admin-dados">
                        <strong><?= ContasLigadas::SERVICOS[$plataforma['servico']] ?? htmlspecialchars($plataforma['servico']) ?></strong>
                    </div>
                    <span class="texto-dim"><?= $plataforma['total'] ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="texto-dim">Ainda ninguém ligou uma conta.</p>
    <?php endif; ?>

    <h2>Últimos registos</h2>
    <ul class="lista-admin">
        <?php foreach ($ultimos as $utilizador): ?>
            <li>
                <div class="admin-dados">
                    <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>"><?= htmlspecialchars($utilizador['nome_utilizador']) ?></a>
                    <span class="texto-dim"><?= htmlspecialchars($utilizador['email']) ?></span>
                </div>
                <span class="texto-dim"><?= (new DateTime($utilizador['data_criacao']))->format('d/m/Y') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/admin_utilizadores.php">Ver todos os utilizadores</a></p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
