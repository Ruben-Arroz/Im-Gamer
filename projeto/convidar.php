<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$erro = null;

// sem ?u= mostra a pesquisa, com ?u=nome mostra o convite para essa pessoa
$destino = isset($_GET['u']) ? Utilizador::buscarPorNome($_GET['u']) : null;
$jogoInicial = isset($_GET['jogo']) ? Jogo::buscarPorId((int) $_GET['jogo']) : null;

$pesquisa = mb_substr(trim($_GET['q'] ?? ''), 0, 50);
$encontrados = [];
if (!$destino && mb_strlen($pesquisa) >= 2) {
    $encontrados = Utilizador::pesquisar($pesquisa, $utilizadorId, 20);
}

if ($destino && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = trim($_POST['mensagem'] ?? '');
    $textoJogo = trim($_POST['jogo_pesquisa'] ?? '');

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif (mb_strlen($mensagem) > 255) {
        $erro = 'A mensagem só pode ter até 255 letras.';
    } else {
        // o jogo é opcional, mas se foi escrito tem de ter sido escolhido da lista
        $jogoId = $textoJogo === '' ? null : Jogo::doFormulario($_POST);

        if ($textoJogo !== '' && !$jogoId) {
            $erro = 'Escolhe o jogo da lista de sugestões, ou deixa o campo vazio.';
        } else {
            $resultado = Convites::enviar($utilizadorId, (int) $destino['id'], $jogoId, $mensagem === '' ? null : $mensagem);

            if ($resultado['sucesso']) {
                header('Location: ' . BASE_URL . '/projeto/convites.php');
                exit;
            }
            $erro = $resultado['erro'];
        }
    }
}

$tituloPagina = 'Convidar jogadores';
require_once __DIR__ . '/includes/header.php';
?>

<section class="formulario">
    <?php if (isset($_GET['u']) && !$destino): ?>
        <h1>Convidar jogadores</h1>
        <p>Este utilizador não existe.</p>
        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/convidar.php">Procurar outro jogador</a></p>

    <?php elseif ($destino && (int) $destino['id'] === $utilizadorId): ?>
        <h1>Convidar jogadores</h1>
        <p>Não te podes convidar a ti próprio.</p>
        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/convidar.php">Procurar outro jogador</a></p>

    <?php elseif ($destino): ?>
        <h1>Convidar <?= htmlspecialchars($destino['nome_utilizador']) ?></h1>

        <?php if ($erro): ?>
            <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="token" value="<?= Auth::token() ?>">

            <label for="jogo_pesquisa">Jogo (opcional)</label>
            <div class="pesquisa-jogo">
                <!-- a chave da rawg tem de ir para o browser porque é ele que faz a pesquisa -->
                <input type="text" id="jogo_pesquisa" name="jogo_pesquisa" autocomplete="off" placeholder="Escreve o nome do jogo"
                    data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
                    value="<?= htmlspecialchars($_POST['jogo_pesquisa'] ?? $jogoInicial['nome'] ?? '') ?>">
                <ul id="jogo_sugestoes" class="sugestoes"></ul>
            </div>
            <input type="hidden" name="jogo_id" value="<?= (int) ($_POST['jogo_id'] ?? $jogoInicial['id'] ?? 0) ?>">
            <input type="hidden" id="jogo_rawg_id" name="jogo_rawg_id" value="<?= htmlspecialchars($_POST['jogo_rawg_id'] ?? '') ?>">
            <input type="hidden" id="jogo_nome" name="jogo_nome" value="<?= htmlspecialchars($_POST['jogo_nome'] ?? '') ?>">
            <input type="hidden" id="jogo_slug" name="jogo_slug" value="<?= htmlspecialchars($_POST['jogo_slug'] ?? '') ?>">
            <input type="hidden" id="jogo_imagem" name="jogo_imagem" value="<?= htmlspecialchars($_POST['jogo_imagem'] ?? '') ?>">
            <input type="hidden" id="jogo_lancamento" name="jogo_lancamento" value="<?= htmlspecialchars($_POST['jogo_lancamento'] ?? '') ?>">
            <p class="ajuda">Pesquisa feita na <a href="https://rawg.io" target="_blank" rel="noopener">RAWG</a>.</p>

            <label for="mensagem">Mensagem (opcional)</label>
            <textarea id="mensagem" name="mensagem" rows="3" maxlength="255" placeholder="Por exemplo: jogamos hoje à noite?"><?= htmlspecialchars($_POST['mensagem'] ?? '') ?></textarea>

            <button type="submit">Enviar convite</button>
        </form>

        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/convidar.php">Procurar outro jogador</a></p>
        <script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=1"></script>

    <?php else: ?>
        <h1>Convidar jogadores</h1>

        <form method="get" novalidate>
            <?php if ($jogoInicial): ?>
                <input type="hidden" name="jogo" value="<?= $jogoInicial['id'] ?>">
            <?php endif; ?>
            <label for="q">Nome de utilizador</label>
            <input type="text" id="q" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Pelo menos 2 letras">
            <button type="submit">Procurar</button>
        </form>

        <?php if (mb_strlen($pesquisa) >= 2): ?>
            <h2>Resultados</h2>
            <?php if (!$encontrados): ?>
                <p class="texto-dim">Nenhum jogador encontrado.</p>
            <?php endif; ?>
            <ul class="lista-avaliacoes">
                <?php foreach ($encontrados as $jogador): ?>
                    <li>
                        <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($jogador['nome_utilizador']) ?>"><?= htmlspecialchars($jogador['nome_utilizador']) ?></a>
                        <a class="botao-contorno lista-acao" href="<?= BASE_URL ?>/projeto/convidar.php?u=<?= urlencode($jogador['nome_utilizador']) ?><?= $jogoInicial ? '&amp;jogo=' . $jogoInicial['id'] : '' ?>">Convidar</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/convites.php">Voltar aos convites</a></p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
