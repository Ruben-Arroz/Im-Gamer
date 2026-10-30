<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

const POR_PAGINA = 24;

$utilizadorId = (int) $_SESSION['utilizador_id'];
$plataformas = Jogo::plataformas();

// os filtros vêm do url e são todos validados (a aba e a plataforma só podem ser das listas)
$abaAtual = isset(Lfg::ABAS[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'proximos';
$plataformaId = (int) ($_GET['plataforma'] ?? 0);

$filtros = [
    'jogo' => mb_substr(trim($_GET['jogo'] ?? ''), 0, 100),
    'plataforma' => in_array($plataformaId, array_column($plataformas, 'id')) ? $plataformaId : 0,
    'vagas' => isset($_GET['vagas']),
];

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$resultado = Lfg::pesquisar($abaAtual, $filtros, $utilizadorId, $pagina, POR_PAGINA);
$totalPaginas = max(1, (int) ceil($resultado['total'] / POR_PAGINA));

// o mesmo url com outra página, para a paginação não perder os filtros
function urlPagina(int $numero): string
{
    $parametros = $_GET;
    $parametros['pagina'] = $numero;
    return '?' . http_build_query($parametros);
}

$tituloPagina = 'LFG';
require_once __DIR__ . '/includes/header.php';
?>

<section class="lfg pagina-larga">
    <div class="lfg-topo">
        <div>
            <h1>LFG</h1>
            <p class="texto-dim">Procura de equipa: publica quando queres jogar e encontra quem jogue contigo.</p>
        </div>
        <a class="botao-principal" href="<?= BASE_URL ?>/projeto/lfg_criar.php">Criar anúncio</a>
    </div>

    <?php require __DIR__ . '/includes/abas_lfg.php'; ?>

    <form method="get" class="filtros-lfg">
        <input type="hidden" name="aba" value="<?= $abaAtual ?>">
        <input type="text" name="jogo" value="<?= htmlspecialchars($filtros['jogo']) ?>" placeholder="Nome do jogo" aria-label="Nome do jogo">
        <select name="plataforma" aria-label="Plataforma">
            <option value="0">Todas as plataformas</option>
            <?php foreach ($plataformas as $plataforma): ?>
                <option value="<?= $plataforma['id'] ?>" <?= $filtros['plataforma'] === (int) $plataforma['id'] ? 'selected' : '' ?>><?= htmlspecialchars($plataforma['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="opcao"><input type="checkbox" name="vagas" <?= $filtros['vagas'] ? 'checked' : '' ?>> Só com vagas</label>
        <button type="submit">Filtrar</button>
    </form>

    <?php if (!$resultado['anuncios']): ?>
        <p class="texto-dim">
            <?php if ($abaAtual === 'meus'): ?>
                Ainda não criaste nem entraste em nenhum anúncio.
            <?php elseif ($abaAtual === 'terminados'): ?>
                Não há anúncios terminados.
            <?php else: ?>
                Não há anúncios para já. <a href="<?= BASE_URL ?>/projeto/lfg_criar.php">Cria o primeiro</a>.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="grelha-jogos">
        <?php foreach ($resultado['anuncios'] as $anuncio): ?>
            <a class="cartao-jogo" href="<?= BASE_URL ?>/projeto/lfg_ver.php?id=<?= $anuncio['id'] ?>">
                <?php if ($anuncio['imagem_capa']): ?>
                    <img src="<?= htmlspecialchars($anuncio['imagem_capa']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <p class="cartao-sem-imagem">sem imagem</p>
                <?php endif; ?>
                <p class="cartao-titulo"><?= htmlspecialchars($anuncio['jogo']) ?></p>
                <p class="cartao-loja"><?= formatarDataEvento($anuncio['data_hora_evento']) ?></p>
                <p class="cartao-loja">
                    <?= htmlspecialchars($anuncio['plataforma'] ?? 'Qualquer plataforma') ?>
                    · de <?= htmlspecialchars($anuncio['nome_utilizador']) ?>
                </p>
                <p class="cartao-precos"><strong><?= Lfg::estado($anuncio) ?></strong></p>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <nav class="paginacao">
            <?php if ($pagina > 1): ?>
                <a href="<?= htmlspecialchars(urlPagina($pagina - 1)) ?>">Anterior</a>
            <?php endif; ?>
            <span class="pagina-atual"><?= $pagina ?> de <?= $totalPaginas ?></span>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="<?= htmlspecialchars(urlPagina($pagina + 1)) ?>">Seguinte</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
