<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

// como no perfil, com ?u=nome mostra a lista de outra pessoa
if (isset($_GET['u'])) {
    $utilizador = Utilizador::buscarPorNome($_GET['u']);
} else {
    $utilizador = Utilizador::buscarPorId($_SESSION['utilizador_id']);
}

if ($utilizador) {
    $proprio = (int) $utilizador['id'] === (int) $_SESSION['utilizador_id'];

    // só o dono pode tirar jogos da lista
    if ($proprio && $_SERVER['REQUEST_METHOD'] === 'POST' && Auth::tokenValido($_POST['token'] ?? '')) {
        ListaDesejos::remover($utilizador['id'], (int) ($_POST['jogo_id'] ?? 0));
        header('Location: ' . BASE_URL . '/projeto/desejos.php?u=' . urlencode($utilizador['nome_utilizador']));
        exit;
    }

    $ordem = isset(ListaDesejos::ORDENS[$_GET['ordem'] ?? '']) ? $_GET['ordem'] : 'data';
    $desejos = ListaDesejos::listar($utilizador['id'], $ordem);
    $conversao = Catalogo::conversaoDolar($_SESSION['moeda'] ?? 'EUR');
}

$tituloPagina = 'Lista de desejos';
require_once __DIR__ . '/includes/header.php';
?>

<section class="biblioteca desejos">
    <?php if (!$utilizador): ?>
        <h1>Lista de desejos</h1>
        <p>Este utilizador não existe.</p>
    <?php else: ?>
        <div class="biblioteca-topo">
            <div>
                <h1>Lista de desejos</h1>
                <p class="texto-dim">
                    De <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>"><?= htmlspecialchars($utilizador['nome_utilizador']) ?></a>
                    · <?= contarJogos(count($desejos)) ?>
                </p>
            </div>

            <?php if ($desejos): ?>
                <form method="get" class="ordem-desejos">
                    <input type="hidden" name="u" value="<?= htmlspecialchars($utilizador['nome_utilizador']) ?>">
                    <select name="ordem" onchange="this.form.submit()" aria-label="Ordenar por">
                        <?php foreach (ListaDesejos::ORDENS as $chave => $opcao): ?>
                            <option value="<?= $chave ?>" <?= $ordem === $chave ? 'selected' : '' ?>><?= $opcao[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$desejos): ?>
            <p class="texto-dim">
                A lista de desejos está vazia.
                <?php if ($proprio): ?>
                    Para adicionar um jogo, abre a página dele e carrega em "Adicionar à lista de desejos".
                <?php endif; ?>
            </p>
        <?php else: ?>
            <!-- os preços vêm da cheapshark e é o browser que os pede (desejos.js) -->
            <div class="grelha-jogos" id="desejos"
                data-base="<?= BASE_URL ?>"
                data-ordem="<?= $ordem ?>"
                data-simbolo="<?= htmlspecialchars(Moeda::SIMBOLOS[$conversao['moeda']]) ?>"
                data-taxa="<?= $conversao['fator'] ?>"
                data-oficiais="<?= implode(',', Catalogo::lojasOficiais()) ?>"
                data-todas="<?= implode(',', array_keys(Catalogo::LOJAS)) ?>">
                <?php foreach ($desejos as $jogo): ?>
                    <div class="cartao-jogo" data-id="<?= $jogo['id'] ?>" data-nome="<?= htmlspecialchars($jogo['nome']) ?>" data-steam="<?= $jogo['steam_app_id'] ?: '' ?>">
                        <a href="<?= BASE_URL ?>/projeto/jogo.php?id=<?= $jogo['id'] ?>">
                            <?php if ($jogo['imagem_capa']): ?>
                                <img src="<?= htmlspecialchars($jogo['imagem_capa']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <p class="cartao-sem-imagem">sem imagem</p>
                            <?php endif; ?>
                            <p class="cartao-titulo"><?= htmlspecialchars($jogo['nome']) ?></p>
                        </a>
                        <p class="cartao-loja">Adicionado a <?= (new DateTime($jogo['data_adicionado']))->format('d/m/Y') ?></p>
                        <p class="cartao-loja cartao-loja-preco"></p>
                        <div class="cartao-precos">
                            <span class="texto-dim preco-jogo">A carregar preço...</span>
                            <?php if ($proprio): ?>
                                <form method="post" class="tirar-desejo">
                                    <input type="hidden" name="token" value="<?= Auth::token() ?>">
                                    <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
                                    <button type="submit" class="botao-contorno">Tirar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="fonte">Preços da <a href="https://www.cheapshark.com" target="_blank" rel="noopener">CheapShark</a>, a loja mais barata de cada jogo.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($utilizador && $desejos): ?>
    <script src="<?= BASE_URL ?>/projeto/assets/js/cartoes.js?v=3"></script>
    <script src="<?= BASE_URL ?>/projeto/assets/js/desejos.js?v=1"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
