<?php
require_once __DIR__ . '/includes/bootstrap.php';

Admin::verificar();

$erro = null;
$texto = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'juntar') {
        $ficaId = (int) ($_POST['fica_id'] ?? 0);
        $erro = Admin::juntarJogos($id, $ficaId);
        $aviso = 'O jogo nº ' . $id . ' foi junto ao nº ' . $ficaId . '.';
    } elseif ($acao === 'apagar') {
        $erro = Admin::apagarJogo($id);
        $aviso = 'Jogo apagado.';
    } else {
        $erro = 'Ação desconhecida.';
    }

    if (!$erro) {
        $_SESSION['aviso_admin'] = $aviso;
        header('Location: ' . BASE_URL . '/projeto/admin_jogos.php?' . http_build_query($_GET));
        exit;
    }
}

$resultado = Admin::jogos($texto, $pagina);
$totalPaginas = max(1, (int) ceil($resultado['total'] / Admin::POR_PAGINA));

// de onde veio cada jogo (as colunas com o id de cada plataforma)
$origens = ['rawg_id' => 'RAWG', 'steam_app_id' => 'Steam', 'xbox_id' => 'Xbox', 'gog_id' => 'GOG', 'epic_id' => 'Epic'];

$tituloPagina = 'Administração';
require_once __DIR__ . '/includes/header.php';
?>

<section class="admin">
    <?php require __DIR__ . '/includes/abas_admin.php'; ?>

    <form method="get" class="filtros-lfg">
        <div class="pesquisa-lfg">
            <input type="search" name="q" value="<?= htmlspecialchars($texto) ?>" placeholder="Nome ou nº do jogo" aria-label="Procurar jogo">
            <button type="submit">Procurar</button>
        </div>
        <?php if ($texto !== ''): ?>
            <a class="link-discreto" href="<?= BASE_URL ?>/projeto/admin_jogos.php">Limpar</a>
        <?php endif; ?>
    </form>

    <p class="texto-dim lfg-total"><?= $resultado['total'] == 1 ? '1 jogo' : $resultado['total'] . ' jogos' ?>. Para um jogo repetido, junta-o ao outro: tudo o que estava no repetido passa para esse.</p>

    <ul class="lista-admin">
        <?php foreach ($resultado['linhas'] as $jogo): ?>
            <?php
            // o aviso do apagar diz o que vai junto com o jogo
            $vaiJunto = $jogo['anuncios'] . ' anúncios, ' . $jogo['desejos'] . ' desejos e ' . $jogo['avaliacoes'] . ' avaliações';
            ?>
            <li>
                <?php if ($jogo['imagem_capa']): ?>
                    <img class="capa-manual" src="<?= htmlspecialchars($jogo['imagem_capa']) ?>" alt="" loading="lazy">
                <?php endif; ?>

                <div class="admin-dados">
                    <span>
                        <a href="<?= BASE_URL ?>/projeto/jogo.php?id=<?= $jogo['id'] ?>"><?= htmlspecialchars($jogo['nome']) ?></a>
                        <span class="texto-dim">nº <?= $jogo['id'] ?><?= $jogo['data_lancamento'] ? ' · ' . substr($jogo['data_lancamento'], 0, 4) : '' ?></span>
                    </span>
                    <span class="texto-dim">
                        <?php foreach ($origens as $coluna => $nome): ?>
                            <?php if ($jogo[$coluna] !== null): ?>
                                <span class="etiqueta-chave"><?= $nome ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </span>
                    <span class="texto-dim">
                        <?= $jogo['bibliotecas'] ?> em bibliotecas · <?= $vaiJunto ?>
                    </span>
                </div>

                <div class="admin-acoes">
                    <form method="post" class="admin-juntar" onsubmit="return confirm('Juntar este jogo ao outro? Este deixa de existir.');">
                        <input type="hidden" name="token" value="<?= Auth::token() ?>">
                        <input type="hidden" name="acao" value="juntar">
                        <input type="hidden" name="id" value="<?= $jogo['id'] ?>">
                        <input type="number" name="fica_id" min="1" placeholder="nº" aria-label="Número do jogo que fica" required>
                        <button type="submit">Juntar</button>
                    </form>

                    <form method="post" onsubmit="return confirm('Apagar este jogo? Também vão <?= $vaiJunto ?>.');">
                        <input type="hidden" name="token" value="<?= Auth::token() ?>">
                        <input type="hidden" name="acao" value="apagar">
                        <input type="hidden" name="id" value="<?= $jogo['id'] ?>">
                        <button type="submit">Apagar</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$resultado['linhas']): ?>
        <p class="texto-dim">Nenhum jogo encontrado.</p>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/paginacao.php'; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
