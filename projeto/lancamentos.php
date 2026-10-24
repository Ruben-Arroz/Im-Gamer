<?php
require_once __DIR__ . '/includes/bootstrap.php';

// ids das plataformas e dos géneros na rawg
const PLATAFORMAS = [
    1 => 'PC',
    2 => 'PlayStation',
    3 => 'Xbox',
    7 => 'Nintendo',
    5 => 'Mac',
    6 => 'Linux',
    4 => 'iOS',
    8 => 'Android',
];

const GENEROS = [
    4 => 'Ação',
    3 => 'Aventura',
    5 => 'RPG',
    2 => 'Tiros',
    10 => 'Estratégia',
    14 => 'Simulação',
    1 => 'Corrida',
    15 => 'Desporto',
    6 => 'Luta',
    83 => 'Plataformas',
    7 => 'Puzzle',
    11 => 'Arcade',
    40 => 'Casual',
    51 => 'Indie',
    59 => 'Multijogador massivo',
    19 => 'Família',
    28 => 'Tabuleiro',
    17 => 'Cartas',
    34 => 'Educativos',
];

// a rawg dá os géneros em inglês, isto é para os mostrar em português nos cartões
const GENEROS_INGLES = [
    'Action' => 'Ação', 'Adventure' => 'Aventura', 'RPG' => 'RPG', 'Shooter' => 'Tiros',
    'Strategy' => 'Estratégia', 'Simulation' => 'Simulação', 'Racing' => 'Corrida', 'Sports' => 'Desporto',
    'Fighting' => 'Luta', 'Platformer' => 'Plataformas', 'Puzzle' => 'Puzzle', 'Arcade' => 'Arcade',
    'Casual' => 'Casual', 'Indie' => 'Indie', 'Massively Multiplayer' => 'Multijogador massivo',
    'Family' => 'Família', 'Board Games' => 'Tabuleiro', 'Card' => 'Cartas', 'Educational' => 'Educativos',
];

const PERIODOS = [7 => 'Uma semana', 30 => 'Um mês', 90 => 'Três meses', 365 => 'Um ano'];

$aba = ($_GET['aba'] ?? '') === 'breve' ? 'breve' : 'recentes';
$periodo = isset(PERIODOS[(int) ($_GET['periodo'] ?? 0)]) ? (int) $_GET['periodo'] : 90;
$ordem = ($_GET['ordem'] ?? '') === 'data' ? 'data' : 'popularidade';
$plataformasEscolhidas = array_map('intval', (array) ($_GET['plataforma'] ?? []));
$generosEscolhidos = array_map('intval', (array) ($_GET['genero'] ?? []));

$tituloPagina = 'Lançamentos';
require_once __DIR__ . '/includes/header.php';
?>

<!-- a chave da rawg tem de ir para o browser porque é ele que faz os pedidos -->
<section class="catalogo pagina-larga" id="lancamentos"
    data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
    data-generos='<?= htmlspecialchars(json_encode(GENEROS_INGLES), ENT_QUOTES) ?>'>

    <div class="biblioteca-topo">
        <div>
            <h1>Lançamentos</h1>
            <p class="texto-dim" id="lancamentos-resumo">Jogos de todas as plataformas</p>
        </div>
        <button type="button" id="botao-filtros" class="botao-filtros">Filtros</button>
    </div>

    <nav class="abas">
        <a href="?aba=recentes" class="<?= $aba === 'recentes' ? 'ativo' : '' ?>">Acabados de sair</a>
        <a href="?aba=breve" class="<?= $aba === 'breve' ? 'ativo' : '' ?>">Em breve</a>
    </nav>

    <div class="biblioteca-corpo">
        <aside id="filtros" class="filtros-biblioteca">
            <form method="get">
                <input type="hidden" name="aba" value="<?= $aba ?>">

                <div class="filtro-grupo">
                    <label for="q" class="filtro-titulo">Pesquisar</label>
                    <input type="text" id="q" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Nome do jogo">
                </div>

                <div class="filtro-grupo">
                    <label for="ordem" class="filtro-titulo">Ordenar por</label>
                    <select id="ordem" name="ordem">
                        <option value="popularidade" <?= $ordem === 'popularidade' ? 'selected' : '' ?>>Popularidade</option>
                        <option value="data" <?= $ordem === 'data' ? 'selected' : '' ?>>Data de lançamento</option>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo"><?= $aba === 'breve' ? 'Lançam nos próximos' : 'Lançados nos últimos' ?></p>
                    <?php foreach (PERIODOS as $dias => $nome): ?>
                        <label class="opcao">
                            <input type="radio" name="periodo" value="<?= $dias ?>" <?= $periodo === $dias ? 'checked' : '' ?>>
                            <?= $nome ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo">Plataforma</p>
                    <?php foreach (PLATAFORMAS as $id => $nome): ?>
                        <label class="opcao">
                            <input type="checkbox" name="plataforma[]" value="<?= $id ?>" <?= in_array($id, $plataformasEscolhidas) ? 'checked' : '' ?>>
                            <?= $nome ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="filtro-grupo">
                    <p class="filtro-titulo">Géneros</p>
                    <?php foreach (GENEROS as $id => $nome): ?>
                        <label class="opcao">
                            <input type="checkbox" name="genero[]" value="<?= $id ?>" <?= in_array($id, $generosEscolhidos) ? 'checked' : '' ?>>
                            <?= $nome ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit">Aplicar filtros</button>
                <a class="filtro-limpar" href="<?= BASE_URL ?>/projeto/lancamentos.php?aba=<?= $aba ?>">Limpar filtros</a>
            </form>
        </aside>

        <div class="biblioteca-resultados">
            <p id="lancamentos-aviso" class="texto-dim"></p>
            <div id="lancamentos-lista" class="grelha-jogos"></div>
            <nav id="paginacao" class="paginacao"></nav>
            <p class="fonte">Dados de jogos da <a href="https://rawg.io" target="_blank" rel="noopener">RAWG</a>.</p>
        </div>
    </div>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/biblioteca.js?v=1"></script>
<script src="<?= BASE_URL ?>/projeto/assets/js/lancamentos.js?v=1"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
