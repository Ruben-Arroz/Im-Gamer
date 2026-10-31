<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

const POR_PAGINA = 24;

$utilizadorId = (int) $_SESSION['utilizador_id'];
$plataformas = Jogo::plataformas();

// filtros do url, validados contra as listas
$abaAtual = isset(Lfg::ABAS[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'proximos';
$plataformaId = (int) ($_GET['plataforma'] ?? 0);

$filtros = [
    'jogo' => mb_substr(trim($_GET['jogo'] ?? ''), 0, 100),
    'plataforma' => in_array($plataformaId, array_column($plataformas, 'id')) ? $plataformaId : 0,
    'quando' => isset(Lfg::QUANDO[$_GET['quando'] ?? '']) ? $_GET['quando'] : '',
    'ordem' => isset(Lfg::ORDENS[$_GET['ordem'] ?? '']) ? $_GET['ordem'] : 'data',
    'vagas' => isset($_GET['vagas']),
];
$comFiltros = $filtros['jogo'] !== '' || $filtros['plataforma'] || $filtros['quando'] !== '' || $filtros['vagas'];
$sugestoes = Lfg::jogosComAnuncios();

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$resultado = Lfg::pesquisar($abaAtual, $filtros, $utilizadorId, $pagina, POR_PAGINA);
$totalPaginas = max(1, (int) ceil($resultado['total'] / POR_PAGINA));

// url com outra página, sem perder os filtros
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
        <h1>LFG</h1>
        <a class="botao-principal" href="<?= BASE_URL ?>/projeto/lfg_criar.php">Criar anúncio</a>
    </div>

    <?php require __DIR__ . '/includes/abas_lfg.php'; ?>

    <form method="get" class="filtros-lfg">
        <input type="hidden" name="aba" value="<?= $abaAtual ?>">

        <div class="pesquisa-lfg">
            <input type="search" name="jogo" list="jogos-lfg" value="<?= htmlspecialchars($filtros['jogo']) ?>" placeholder="Procurar jogo" aria-label="Procurar jogo">
            <button type="submit">Procurar</button>
        </div>
        <datalist id="jogos-lfg">
            <?php foreach ($sugestoes as $nomeJogo): ?>
                <option value="<?= htmlspecialchars($nomeJogo) ?>">
            <?php endforeach; ?>
        </datalist>

        <select name="plataforma" aria-label="Plataforma" onchange="this.form.submit()">
            <option value="0">Todas as plataformas</option>
            <?php foreach ($plataformas as $plataforma): ?>
                <option value="<?= $plataforma['id'] ?>" <?= $filtros['plataforma'] === (int) $plataforma['id'] ? 'selected' : '' ?>><?= htmlspecialchars($plataforma['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="quando" aria-label="Quando" onchange="this.form.submit()">
            <option value="">Qualquer dia</option>
            <?php foreach (Lfg::QUANDO as $chave => $nome): ?>
                <option value="<?= $chave ?>" <?= $filtros['quando'] === $chave ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($abaAtual === 'proximos'): ?>
            <select name="ordem" aria-label="Ordenar por" onchange="this.form.submit()">
                <?php foreach (Lfg::ORDENS as $chave => $ordem): ?>
                    <option value="<?= $chave ?>" <?= $filtros['ordem'] === $chave ? 'selected' : '' ?>><?= $ordem[0] ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <label class="opcao"><input type="checkbox" name="vagas" onchange="this.form.submit()" <?= $filtros['vagas'] ? 'checked' : '' ?>> Só com vagas</label>

        <?php if ($comFiltros): ?>
            <a class="link-discreto" href="<?= BASE_URL ?>/projeto/lfg.php?aba=<?= $abaAtual ?>">Limpar filtros</a>
        <?php endif; ?>
    </form>

    <?php if ($resultado['total'] > 0): ?>
        <p class="texto-dim lfg-total"><?= $resultado['total'] == 1 ? '1 anúncio' : $resultado['total'] . ' anúncios' ?></p>
    <?php endif; ?>

    <?php if (!$resultado['anuncios']): ?>
        <p class="texto-dim">
            <?php if ($comFiltros): ?>
                Nenhum anúncio com estes filtros.
            <?php elseif ($abaAtual === 'meus'): ?>
                Ainda não criaste nem entraste em nenhum anúncio.
            <?php else: ?>
                Não há anúncios para já. <a href="<?= BASE_URL ?>/projeto/lfg_criar.php">Cria o primeiro</a>.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="grelha-jogos">
        <?php foreach ($resultado['anuncios'] as $anuncio): ?>
            <?php $estado = Lfg::estado($anuncio); ?>
            <a class="cartao-jogo <?= $estado === 'Terminado' ? 'cartao-passado' : '' ?>" href="<?= BASE_URL ?>/projeto/lfg_ver.php?id=<?= $anuncio['id'] ?>">
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
                <p class="cartao-precos"><strong><?= $estado ?></strong></p>
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
