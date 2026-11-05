<?php
require_once __DIR__ . '/includes/bootstrap.php';

Admin::verificar();

$erro = null;
$texto = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } else {
        $erro = Admin::apagarAnuncio((int) ($_POST['id'] ?? 0));
    }

    if (!$erro) {
        $_SESSION['aviso_admin'] = 'Anúncio apagado. O dono e quem estava na equipa foram avisados.';
        header('Location: ' . BASE_URL . '/projeto/admin_lfg.php?' . http_build_query($_GET));
        exit;
    }
}

$resultado = Admin::anuncios($texto, $pagina);
$totalPaginas = max(1, (int) ceil($resultado['total'] / Admin::POR_PAGINA));

$tituloPagina = 'Administração';
require_once __DIR__ . '/includes/header.php';
?>

<section class="admin">
    <?php require __DIR__ . '/includes/abas_admin.php'; ?>

    <form method="get" class="filtros-lfg">
        <div class="pesquisa-lfg">
            <input type="search" name="q" value="<?= htmlspecialchars($texto) ?>" placeholder="Jogo ou utilizador" aria-label="Procurar anúncio">
            <button type="submit">Procurar</button>
        </div>
        <?php if ($texto !== ''): ?>
            <a class="link-discreto" href="<?= BASE_URL ?>/projeto/admin_lfg.php">Limpar</a>
        <?php endif; ?>
    </form>

    <p class="texto-dim lfg-total"><?= $resultado['total'] == 1 ? '1 anúncio' : $resultado['total'] . ' anúncios' ?>, também os fechados e os que já saíram do ar</p>

    <ul class="lista-admin">
        <?php foreach ($resultado['linhas'] as $anuncio): ?>
            <li class="<?= Lfg::expirado($anuncio) ? 'cartao-passado' : '' ?>">
                <div class="admin-dados">
                    <span>
                        <a href="<?= BASE_URL ?>/projeto/lfg_ver.php?id=<?= $anuncio['id'] ?>"><?= htmlspecialchars($anuncio['jogo']) ?></a>
                        · de <?= htmlspecialchars($anuncio['nome_utilizador']) ?>
                    </span>
                    <span class="texto-dim">
                        <?= Lfg::estado($anuncio) ?> · no ar até <?= formatarDia($anuncio['data_fim']) ?>
                        · criado a <?= (new DateTime($anuncio['data_criacao']))->format('d/m/Y') ?>
                    </span>
                    <p class="admin-texto"><?= htmlspecialchars($anuncio['descricao']) ?></p>
                </div>

                <form method="post" onsubmit="return confirm('Apagar este anúncio? O dono e quem estava na equipa recebem um aviso.');">
                    <input type="hidden" name="token" value="<?= Auth::token() ?>">
                    <input type="hidden" name="id" value="<?= $anuncio['id'] ?>">
                    <button type="submit">Apagar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$resultado['linhas']): ?>
        <p class="texto-dim">Nenhum anúncio encontrado.</p>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/paginacao.php'; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
