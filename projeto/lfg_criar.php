<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$plataformas = Jogo::plataformas();
$erro = null;

// vindo da página de um jogo (?jogo=id) o jogo já vem escolhido
$jogoInicial = isset($_GET['jogo']) ? Jogo::buscarPorId((int) $_GET['jogo']) : null;

// o anúncio fica no ar até ao dia escolhido, de hoje até 60 dias à frente (14 por defeito)
$hoje = date('Y-m-d');
$dataPorDefeito = date('Y-m-d', strtotime('+' . Lfg::DIAS_NO_AR . ' days'));
$dataMaxima = date('Y-m-d', strtotime('+' . Lfg::MAX_DIAS . ' days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::tokenValido($_POST['token'] ?? '')) {
    $erro = 'A sessão expirou. Tenta outra vez.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // o jogo só é guardado depois de o token ser válido
    $jogoId = Jogo::doFormulario($_POST);
    $plataformaId = (int) ($_POST['plataforma_id'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    $vagas = (int) ($_POST['vagas'] ?? 0);
    $dataFim = $_POST['data_fim'] ?? '';

    if (!$jogoId) {
        $erro = 'Escolhe o jogo da lista de sugestões.';
    } elseif ($plataformaId !== 0 && !in_array($plataformaId, array_column($plataformas, 'id'))) {
        $erro = 'Escolhe uma plataforma da lista.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $dataFim) || DateTime::createFromFormat('Y-m-d', $dataFim)->format('Y-m-d') !== $dataFim) {
        $erro = 'Escolhe até que dia o anúncio fica no ar.';
    } elseif ($dataFim < $hoje) {
        $erro = 'O anúncio tem de ficar no ar pelo menos até hoje.';
    } elseif ($dataFim > $dataMaxima) {
        $erro = 'O anúncio só pode ficar no ar até ' . Lfg::MAX_DIAS . ' dias.';
    } elseif ($vagas < 1 || $vagas > Lfg::MAX_VAGAS) {
        $erro = 'O número de jogadores que procuras tem de ser de 1 a ' . Lfg::MAX_VAGAS . '.';
    } elseif ($descricao === '' || mb_strlen($descricao) > Lfg::MAX_DESCRICAO) {
        $erro = 'Escreve uma descrição, até ' . Lfg::MAX_DESCRICAO . ' letras.';
    } elseif (Lfg::abertosDoUtilizador($utilizadorId) >= Lfg::MAX_ABERTOS) {
        $erro = 'Já tens ' . Lfg::MAX_ABERTOS . ' anúncios abertos. Fecha ou apaga um antes de criar outro.';
    }

    if (!$erro) {
        $id = Lfg::criar($utilizadorId, $jogoId, $plataformaId ?: null, $descricao, $vagas, $dataFim);
        header('Location: ' . BASE_URL . '/projeto/lfg_ver.php?id=' . $id);
        exit;
    }
}

$tituloPagina = 'Criar anúncio';
require_once __DIR__ . '/includes/header.php';
?>

<section class="formulario">
    <h1>Criar anúncio</h1>

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

        <label for="vagas">Quantos jogadores faltam</label>
        <input type="number" id="vagas" name="vagas" min="1" max="<?= Lfg::MAX_VAGAS ?>" value="<?= (int) ($_POST['vagas'] ?? 1) ?>">
        <p class="ajuda">Sem contar contigo. Por exemplo, para uma equipa de 5 em que já são 2, faltam 3.</p>

        <label for="data_fim">No ar até</label>
        <input type="date" id="data_fim" name="data_fim" min="<?= $hoje ?>" max="<?= $dataMaxima ?>"
            value="<?= htmlspecialchars($_POST['data_fim'] ?? $dataPorDefeito) ?>">
        <p class="ajuda">Depois deste dia o anúncio sai da lista. Dá para prolongar na página dele.</p>

        <label for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" rows="4" maxlength="<?= Lfg::MAX_DESCRICAO ?>" placeholder="Por exemplo: equipa para jogar Premier à noite, 15k+, com microfone"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>

        <button type="submit">Publicar anúncio</button>
    </form>

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/lfg.php">Voltar ao LFG</a></p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=1"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
