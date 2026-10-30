<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$plataformas = Jogo::plataformas();
$erro = null;

// a partir da página de um jogo (?jogo=id) o jogo já vem escolhido
$jogoInicial = isset($_GET['jogo']) ? Jogo::buscarPorId((int) $_GET['jogo']) : null;

// a data e hora vêm do campo datetime-local, no formato 2026-11-01T21:00
$agora = new DateTime();
$limite = (new DateTime())->modify('+6 months');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::tokenValido($_POST['token'] ?? '')) {
    $erro = 'A sessão expirou. Tenta outra vez.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // o jogo só é procurado (e guardado, se for novo) depois de o token ser válido
    $jogoId = Jogo::doFormulario($_POST);
    $plataformaId = (int) ($_POST['plataforma_id'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    $vagas = (int) ($_POST['vagas'] ?? 0);
    $dataHora = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['data_hora'] ?? '');

    if (!$jogoId) {
        $erro = 'Escolhe o jogo da lista de sugestões.';
    } elseif ($plataformaId !== 0 && !in_array($plataformaId, array_column($plataformas, 'id'))) {
        $erro = 'Escolhe uma plataforma da lista.';
    } elseif (!$dataHora || $dataHora->format('Y-m-d\TH:i') !== ($_POST['data_hora'] ?? '')) {
        $erro = 'Escolhe o dia e a hora em que vão jogar.';
    } elseif ($dataHora < $agora) {
        $erro = 'O dia e a hora têm de ser no futuro.';
    } elseif ($dataHora > $limite) {
        $erro = 'Só podes marcar jogos até 6 meses à frente.';
    } elseif ($vagas < 1 || $vagas > Lfg::MAX_VAGAS) {
        $erro = 'O número de jogadores que procuras tem de ser de 1 a ' . Lfg::MAX_VAGAS . '.';
    } elseif ($descricao === '' || mb_strlen($descricao) > Lfg::MAX_DESCRICAO) {
        $erro = 'Escreve uma descrição, até ' . Lfg::MAX_DESCRICAO . ' letras.';
    } elseif (Lfg::abertosDoUtilizador($utilizadorId) >= Lfg::MAX_ABERTOS) {
        $erro = 'Já tens ' . Lfg::MAX_ABERTOS . ' anúncios abertos. Fecha ou apaga um antes de criar outro.';
    }

    if (!$erro) {
        $id = Lfg::criar($utilizadorId, $jogoId, $plataformaId ?: null, $descricao, $vagas, $dataHora->format('Y-m-d H:i:s'));
        header('Location: ' . BASE_URL . '/projeto/lfg_ver.php?id=' . $id);
        exit;
    }
}

$tituloPagina = 'Criar anúncio';
require_once __DIR__ . '/includes/header.php';
?>

<section class="formulario">
    <h1>Criar anúncio</h1>
    <p class="texto-dim">Diz o que queres jogar, quando e quantas pessoas procuras. Quem entrar no anúncio aparece na página dele.</p>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">

        <label for="jogo_pesquisa">Jogo</label>
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

        <label for="plataforma_id">Plataforma</label>
        <select id="plataforma_id" name="plataforma_id">
            <option value="0">Qualquer plataforma</option>
            <?php foreach ($plataformas as $plataforma): ?>
                <option value="<?= $plataforma['id'] ?>" <?= (int) ($_POST['plataforma_id'] ?? 0) === (int) $plataforma['id'] ? 'selected' : '' ?>><?= htmlspecialchars($plataforma['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="data_hora">Dia e hora</label>
        <input type="datetime-local" id="data_hora" name="data_hora"
            min="<?= $agora->format('Y-m-d\TH:i') ?>" max="<?= $limite->format('Y-m-d\TH:i') ?>"
            value="<?= htmlspecialchars($_POST['data_hora'] ?? '') ?>">

        <label for="vagas">Quantos jogadores procuras</label>
        <input type="number" id="vagas" name="vagas" min="1" max="<?= Lfg::MAX_VAGAS ?>" value="<?= (int) ($_POST['vagas'] ?? 1) ?>">
        <p class="ajuda">Sem contar contigo. Quando as vagas ficarem cheias, mais ninguém pode entrar.</p>

        <label for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" rows="4" maxlength="<?= Lfg::MAX_DESCRICAO ?>" placeholder="Por exemplo: ranked, nível ouro ou mais, com microfone"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>

        <button type="submit">Publicar anúncio</button>
    </form>

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/lfg.php">Voltar ao LFG</a></p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=1"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
