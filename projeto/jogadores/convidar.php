<?php
require_once __DIR__ . '/../includes/inicio.php';

Auth::exigirSessao();

$utilizadorId = (int) $_SESSION['utilizador_id'];
$erro = null;

$nomeDestino = filter_input(INPUT_GET, 'u', FILTER_UNSAFE_RAW);
$jogoGet = filter_input(INPUT_GET, 'jogo', FILTER_VALIDATE_INT);

// a pesquisa agora é no procurar.php, aqui fica só o convite
if ($nomeDestino === null) {
    irPara('jogadores/procurar.php' . ($jogoGet ? '?jogo_id=' . (int) $jogoGet : ''));
}

$destino = Utilizador::buscarPorNome($nomeDestino);
$eu = Utilizador::buscarPorId($utilizadorId);
$jogoInicial = $jogoGet ? Jogo::buscarPorId((int) $jogoGet) : null;

if ($destino && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = trim(filter_input(INPUT_POST, 'mensagem', FILTER_UNSAFE_RAW) ?? '');
    $discord = limparDiscord(filter_input(INPUT_POST, 'discord', FILTER_UNSAFE_RAW) ?? '');
    $textoJogo = trim(filter_input(INPUT_POST, 'jogo_pesquisa', FILTER_UNSAFE_RAW) ?? '');

    if (!Auth::formularioValido()) {
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
                irPara('jogadores/convites.php');
            }
            $erro = $resultado['erro'];
        }
    }
}

$tituloPagina = 'Convidar jogadores';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="formulario">
    <?php if (!$destino): ?>
        <h1>Convidar jogadores</h1>
        <p>Este utilizador não existe.</p>
        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/jogadores/procurar.php">Procurar outro jogador</a></p>

    <?php elseif ((int) $destino['id'] === $utilizadorId): ?>
        <h1>Convidar jogadores</h1>
        <p>Não te podes convidar a ti próprio.</p>
        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/jogadores/procurar.php">Procurar outro jogador</a></p>

    <?php else: ?>
        <h1>Convidar <?= htmlspecialchars($destino['nome_utilizador']) ?></h1>

        <?php if ($erro): ?>
            <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="token" value="<?= Auth::token() ?>">

            <label for="jogo_pesquisa">Jogo (opcional)</label>
            <?php
            $jogoTexto = $jogoInicial['nome'] ?? '';
            $jogoIdInicial = $jogoInicial['id'] ?? 0;
            require __DIR__ . '/../includes/campo_jogo.php';
            ?>

            <label for="mensagem">Mensagem (opcional)</label>
            <textarea id="mensagem" name="mensagem" rows="3" maxlength="255" placeholder="Por exemplo: jogamos hoje à noite?"><?= htmlspecialchars($_POST['mensagem'] ?? '') ?></textarea>

            <label for="discord">O teu Discord (opcional)</label>
            <input type="text" id="discord" name="discord" maxlength="33" placeholder="o_teu_nome" value="<?= htmlspecialchars($_POST['discord'] ?? $eu['discord'] ?? '') ?>">
            <p class="ajuda">Só quem recebe o convite o vê.</p>

            <button type="submit">Enviar convite</button>
        </form>

        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/jogadores/procurar.php">Procurar outro jogador</a></p>
        <script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=3"></script>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
