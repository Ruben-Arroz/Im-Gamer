<?php
require_once __DIR__ . '/includes/bootstrap.php';

Admin::verificar();

$erro = null;
$texto = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$soComentario = isset($_GET['comentario']);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } else {
        $erro = Admin::apagarAvaliacao((int) ($_POST['id'] ?? 0));
    }

    if (!$erro) {
        $_SESSION['aviso_admin'] = 'Avaliação apagada. O autor foi avisado.';
        header('Location: ' . BASE_URL . '/projeto/admin_avaliacoes.php?' . http_build_query($_GET));
        exit;
    }
}

$resultado = Admin::avaliacoes($texto, $soComentario, $pagina);
$totalPaginas = max(1, (int) ceil($resultado['total'] / Admin::POR_PAGINA));

$tituloPagina = 'Administração';
require_once __DIR__ . '/includes/header.php';
?>

<section class="admin">
    <?php require __DIR__ . '/includes/abas_admin.php'; ?>

    <form method="get" class="filtros-lfg">
        <div class="pesquisa-lfg">
            <input type="search" name="q" value="<?= htmlspecialchars($texto) ?>" placeholder="Jogo, utilizador ou comentário" aria-label="Procurar avaliação">
            <button type="submit">Procurar</button>
        </div>
        <label class="opcao"><input type="checkbox" name="comentario" onchange="this.form.submit()" <?= $soComentario ? 'checked' : '' ?>> Só com comentário</label>
        <?php if ($texto !== '' || $soComentario): ?>
            <a class="link-discreto" href="<?= BASE_URL ?>/projeto/admin_avaliacoes.php">Limpar</a>
        <?php endif; ?>
    </form>

    <p class="texto-dim lfg-total"><?= $resultado['total'] == 1 ? '1 avaliação' : $resultado['total'] . ' avaliações' ?></p>

    <ul class="lista-admin">
        <?php foreach ($resultado['linhas'] as $avaliacao): ?>
            <li>
                <div class="admin-dados">
                    <span>
                        <a href="<?= BASE_URL ?>/projeto/jogo.php?id=<?= $avaliacao['jogo_id'] ?>"><?= htmlspecialchars($avaliacao['jogo']) ?></a>
                        · <?= $avaliacao['pontuacao'] ?> de 5
                    </span>
                    <span class="texto-dim">
                        de <?= htmlspecialchars($avaliacao['nome_utilizador']) ?>
                        · <?= (new DateTime($avaliacao['data_criacao']))->format('d/m/Y H:i') ?>
                    </span>
                    <?php if ($avaliacao['comentario']): ?>
                        <p class="admin-texto"><?= htmlspecialchars($avaliacao['comentario']) ?></p>
                    <?php endif; ?>
                </div>

                <form method="post" onsubmit="return confirm('Apagar esta avaliação? O autor recebe um aviso.');">
                    <input type="hidden" name="token" value="<?= Auth::token() ?>">
                    <input type="hidden" name="id" value="<?= $avaliacao['id'] ?>">
                    <button type="submit">Apagar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$resultado['linhas']): ?>
        <p class="texto-dim">Nenhuma avaliação encontrada.</p>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/paginacao.php'; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
