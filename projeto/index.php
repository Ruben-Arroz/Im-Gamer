<?php
require_once __DIR__ . '/includes/bootstrap.php';

// os preços da cheapshark vêm em dólares, por isso converte-se para a moeda de quem está a ver
$conversao = Catalogo::conversaoDolar($_SESSION['moeda'] ?? 'EUR');
$moeda = $conversao['moeda'];
$simbolo = Moeda::SIMBOLOS[$moeda];

// "até 5" na moeda do user, a cheapshark precisa do valor em dólares
$cincoEmDolares = ceil(5 / $conversao['fator']);

// cada carrossel: título, de onde vêm os jogos, o que se pede à api, link do "Ver todos"
// nos da rawg o pedido são os dias "de" e "até" a contar de hoje (negativo é para trás)
$carrosseis = [
    ['Melhores promoções', 'cheapshark', 'sortBy=Deal%20Rating', 'catalogo.php'],
    ['Maiores descontos em jogos AAA', 'cheapshark', 'sortBy=Savings&AAA=1', 'catalogo.php?ordem=Savings&aaa=on'],
    ['Promoções na Steam', 'cheapshark', 'sortBy=Deal%20Rating&storeID=1', 'catalogo.php?loja[]=1'],
    ['Até 5 ' . $simbolo, 'cheapshark', 'sortBy=Deal%20Rating&upperPrice=' . $cincoEmDolares, 'catalogo.php?preco_max=5'],
    ['Mais bem avaliados', 'cheapshark', 'sortBy=Metacritic&metacritic=80', 'catalogo.php?ordem=Metacritic&metacritic=80'],
    ['Acabados de sair', 'rawg', '-30,0', 'lancamentos.php?aba=recentes&periodo=30'],
    ['Em breve', 'rawg', '1,90', 'lancamentos.php?aba=breve&periodo=90'],
];

$tituloPagina = 'Início';
require_once __DIR__ . '/includes/header.php';
?>

<!-- a chave da rawg tem de ir para o browser porque é ele que faz os pedidos -->
<section class="inicio pagina-larga" id="inicio"
    data-simbolo="<?= htmlspecialchars($simbolo) ?>"
    data-taxa="<?= $conversao['fator'] ?>"
    data-oficiais="<?= implode(',', Catalogo::lojasOficiais()) ?>"
    data-todas="<?= implode(',', array_keys(Catalogo::LOJAS)) ?>"
    data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
    data-generos='<?= htmlspecialchars(json_encode(Catalogo::GENEROS_INGLES), ENT_QUOTES) ?>'>

    <div id="destaque" class="destaque"></div>

    <?php foreach ($carrosseis as $carrossel): ?>
        <section class="carrossel" data-fonte="<?= $carrossel[1] ?>" data-pedido="<?= htmlspecialchars($carrossel[2]) ?>">
            <div class="carrossel-topo">
                <h2><?= htmlspecialchars($carrossel[0]) ?></h2>
                <a href="<?= BASE_URL ?>/projeto/<?= htmlspecialchars($carrossel[3]) ?>">Ver todos</a>
                <div class="carrossel-setas">
                    <button type="button" data-direcao="-1" aria-label="Anteriores">&lsaquo;</button>
                    <button type="button" data-direcao="1" aria-label="Seguintes">&rsaquo;</button>
                </div>
            </div>
            <div class="carrossel-faixa">
                <p class="texto-dim">A carregar...</p>
            </div>
        </section>
    <?php endforeach; ?>

    <p class="fonte">
        Promoções da <a href="https://www.cheapshark.com" target="_blank" rel="noopener">CheapShark</a>
        e lançamentos da <a href="https://rawg.io" target="_blank" rel="noopener">RAWG</a>.
    </p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/cartoes.js?v=1"></script>
<script src="<?= BASE_URL ?>/projeto/assets/js/inicio.js?v=2"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
