<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$erro = null;

$nomeDestino = filter_input(INPUT_GET, 'u', FILTER_UNSAFE_RAW);
$jogoGet = filter_input(INPUT_GET, 'jogo', FILTER_VALIDATE_INT);

// a pesquisa agora é no jogadores.php, aqui fica só o convite
if ($nomeDestino === null) {
    header('Location: ' . BASE_URL . '/projeto/jogadores.php' . ($jogoGet ? '?jogo_id=' . (int) $jogoGet : ''));
    exit;
}

$destino = Utilizador::buscarPorNome($nomeDestino);
$eu = Utilizador::buscarPorId($utilizadorId);
$jogoInicial = $jogoGet ? Jogo::buscarPorId((int) $jogoGet) : null;

if ($destino && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = trim(filter_input(INPUT_POST, 'mensagem', FILTER_UNSAFE_RAW) ?? '');
    $discord = limparDiscord(filter_input(INPUT_POST, 'discord', FILTER_UNSAFE_RAW) ?? '');
    $textoJogo = trim(filter_input(INPUT_POST, 'jogo_pesquisa', FILTER_UNSAFE_RAW) ?? '');

    if (!Auth::tokenValido(filter_input(INPUT_POST, 'token', FILTER_UNSAFE_RAW) ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif (mb_strlen($mensagem) > 255) {
        $erro = 'A mensagem só pode ter até 255 letras.';
    } elseif ($discord !== '' && !discordValido($discord)) {
        $erro = 'O nome do Discord só pode ter letras minúsculas, números, pontos e _ (de 2 a 32).';
    } else {
        // o jogo é opcional, mas se foi escrito tem de ter sido escolhido da lista
        $jogoId = $textoJogo === '' ? null : Jogo::doFormulario($_POST);

        if ($textoJogo !== '' && !$jogoId) {
            $erro = 'Escolhe o jogo da lista de sugestões, ou deixa o campo vazio.';
        } else {
            $resultado = Convites::enviar($utilizadorId, (int) $destino['id'], $jogoId, $mensagem === '' ? null : $mensagem, $discord === '' ? null : $discord);

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
    <?php if (!$destino): ?>
        <h1>Convidar jogadores</h1>
        <p>Este utilizador não existe.</p>
        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/jogadores.php">Procurar outro jogador</a></p>

    <?php elseif ((int) $destino['id'] === $utilizadorId): ?>
        <h1>Convidar jogadores</h1>
        <p>Não te podes convidar a ti próprio.</p>
        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/jogadores.php">Procurar outro jogador</a></p>

    <?php else: ?>
        <h1>Convidar <?= htmlspecialchars($destino['nome_utilizador']) ?></h1>

        <?php if ($erro): ?>
            <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="token" value="<?= Auth::token() ?>">

            <label for="jogo_pesquisa">Jogo (opcional)</label>
            <div class="pesquisa-jogo">
                <input type="text" id="jogo_pesquisa" name="jogo_pesquisa" autocomplete="off" placeholder="Escreve o nome do jogo"
                    data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
                    data-base="<?= BASE_URL ?>"
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

            <label for="discord">O teu Discord (opcional)</label>
            <input type="text" id="discord" name="discord" maxlength="33" placeholder="o_teu_nome" value="<?= htmlspecialchars($_POST['discord'] ?? $eu['discord'] ?? '') ?>">
            <p class="ajuda">Só quem recebe o convite o vê.</p>

            <button type="submit">Enviar convite</button>
        </form>

        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/jogadores.php">Procurar outro jogador</a></p>
        <script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=3"></script>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
