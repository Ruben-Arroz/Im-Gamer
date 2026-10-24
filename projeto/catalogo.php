<?php
require_once __DIR__ . '/includes/bootstrap.php';

// lojas ativas na cheapshark: id => [nome, se é a loja oficial da plataforma]
// as que não são oficiais vendem chaves para ativar noutra plataforma (steam, epic, ...)
const LOJAS = [
    1 => ['Steam', true],
    7 => ['GOG', true],
    13 => ['Ubisoft Store', true],
    25 => ['Epic Games Store', true],
    2 => ['GamersGate', false],
    3 => ['Green Man Gaming', false],
    11 => ['Humble Store', false],
    15 => ['Fanatical', false],
    21 => ['WinGameStore', false],
    23 => ['GameBillet', false],
    27 => ['Gamesplanet', false],
    28 => ['Gamesload', false],
    30 => ['IndieGala', false],
    35 => ['DreamGame', false],
];

const ORDENS = [
    'Deal Rating' => 'Melhores promoções',
    'Savings' => 'Maior desconto',
    'Price' => 'Menor preço',
    'Metacritic' => 'Nota Metacritic',
    'Reviews' => 'Avaliações da Steam',
    'Release' => 'Lançamento',
    'Recent' => 'Promoções mais recentes',
    'Title' => 'Nome',
];

// os preços da cheapshark vêm em dólares, aqui calcula-se quanto vale 1 dólar na moeda de quem está a ver
$moeda = $_SESSION['moeda'] ?? 'EUR';
$taxaDolar = Moeda::taxa('USD');
$taxaMoeda = Moeda::taxa($moeda);

if ($taxaDolar && $taxaMoeda) {
    $dolarParaMoeda = $taxaMoeda / $taxaDolar;
} else {
    // sem taxas na bd mostra em dólares
    $moeda = 'USD';
    $dolarParaMoeda = 1;
}

$lojasEscolhidas = array_map('intval', (array) ($_GET['loja'] ?? []));
$tipoLoja = $_GET['tipo'] ?? 'todas';
$ordem = isset(ORDENS[$_GET['ordem'] ?? '']) ? $_GET['ordem'] : 'Deal Rating';

$tituloPagina = 'Catálogo';
require_once __DIR__ . '/includes/header.php';
?>

<section class="catalogo pagina-larga" id="catalogo"
    data-moeda="<?= $moeda ?>"
    data-simbolo="<?= htmlspecialchars(Moeda::SIMBOLOS[$moeda]) ?>"
    data-taxa="<?= $dolarParaMoeda ?>"
    data-oficiais="<?= implode(',', array_keys(array_filter(LOJAS, fn($loja) => $loja[1]))) ?>"
    data-todas="<?= implode(',', array_keys(LOJAS)) ?>">

    <div class="biblioteca-topo">
        <div>
            <h1>Catálogo</h1>
            <p class="texto-dim" id="catalogo-resumo">Jogos em promoção nas lojas de PC</p>
        </div>
        <button type="button" id="botao-filtros" class="botao-filtros">Filtros</button>
    </div>

    <div class="biblioteca-corpo">
        <aside id="filtros" class="filtros-biblioteca">
            <form method="get" id="form-catalogo">
                <div class="filtro-grupo">
                    <label for="q" class="filtro-titulo">Pesquisar</label>
                    <input type="text" id="q" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Nome do jogo">
                </div>

                <div class="filtro-grupo">
                    <label for="ordem" class="filtro-titulo">Ordenar por</label>
                    <select id="ordem" name="ordem">
                        <?php foreach (ORDENS as $chave => $nome): ?>
                            <option value="<?= $chave ?>" <?= $ordem === $chave ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo">Tipo de loja</p>
                    <label class="opcao"><input type="radio" name="tipo" value="todas" <?= $tipoLoja === 'todas' ? 'checked' : '' ?>> Todas</label>
                    <label class="opcao"><input type="radio" name="tipo" value="oficiais" <?= $tipoLoja === 'oficiais' ? 'checked' : '' ?>> Só lojas oficiais</label>
                    <label class="opcao"><input type="radio" name="tipo" value="chaves" <?= $tipoLoja === 'chaves' ? 'checked' : '' ?>> Só revendedores de chaves</label>
                    <p class="ajuda-filtro">Nos revendedores compras uma chave que depois ativas na Steam, Epic, etc.</p>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo">Lojas</p>
                    <?php foreach (LOJAS as $id => $loja): ?>
                        <label class="opcao">
                            <input type="checkbox" name="loja[]" value="<?= $id ?>" <?= in_array($id, $lojasEscolhidas) ? 'checked' : '' ?>>
                            <?= $loja[0] ?>
                            <?php if (!$loja[1]): ?>
                                <span class="texto-dim">(chaves)</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo">Preço (<?= htmlspecialchars(Moeda::SIMBOLOS[$moeda]) ?>)</p>
                    <div class="filtro-intervalo">
                        <input type="number" name="preco_min" min="0" step="1" placeholder="mín." value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>">
                        <input type="number" name="preco_max" min="0" step="1" placeholder="máx." value="<?= htmlspecialchars($_GET['preco_max'] ?? '') ?>">
                    </div>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo">Nota Metacritic mínima</p>
                    <input type="number" name="metacritic" min="0" max="100" placeholder="ex.: 75" value="<?= htmlspecialchars($_GET['metacritic'] ?? '') ?>">
                </div>

                <div class="filtro-grupo">
                    <label for="steam" class="filtro-titulo">Avaliações da Steam</label>
                    <select id="steam" name="steam">
                        <?php foreach (['' => 'Qualquer', '70' => '70% ou mais positivas', '80' => '80% ou mais positivas', '90' => '90% ou mais positivas', '95' => '95% ou mais positivas'] as $valor => $nome): ?>
                            <option value="<?= $valor ?>" <?= ($_GET['steam'] ?? '') === (string) $valor ? 'selected' : '' ?>><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo">Outros</p>
                    <label class="opcao"><input type="checkbox" name="aaa" <?= isset($_GET['aaa']) ? 'checked' : '' ?>> Só jogos AAA</label>
                    <label class="opcao"><input type="checkbox" name="steamworks" <?= isset($_GET['steamworks']) ? 'checked' : '' ?>> Só jogos que ativam na Steam</label>
                </div>

                <button type="submit">Aplicar filtros</button>
                <a class="filtro-limpar" href="<?= BASE_URL ?>/projeto/catalogo.php">Limpar filtros</a>
            </form>
        </aside>

        <div class="biblioteca-resultados">
            <p id="promocoes-aviso" class="texto-dim"></p>
            <div id="promocoes" class="grelha-jogos"></div>
            <nav id="paginacao" class="paginacao"></nav>
            <p class="fonte">Promoções da <a href="https://www.cheapshark.com" target="_blank" rel="noopener">CheapShark</a>. Os preços são convertidos de dólares e podem variar um pouco na loja.</p>
        </div>
    </div>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/biblioteca.js?v=1"></script>
<script src="<?= BASE_URL ?>/projeto/assets/js/catalogo.js?v=1"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
