<?php
require_once __DIR__ . '/../includes/inicio.php';

Auth::exigirSessao();

$utilizadorId = (int) $_SESSION['utilizador_id'];
$plataformas = Jogo::plataformas();
$erro = null;

// vindo da página de um jogo (?jogo=id) o jogo já vem escolhido
$jogoGet = filter_input(INPUT_GET, 'jogo', FILTER_VALIDATE_INT);
$jogoInicial = $jogoGet !== null ? Jogo::buscarPorId((int) $jogoGet) : null;
$eu = Utilizador::buscarPorId($utilizadorId);

// o anúncio fica no ar até ao dia escolhido, de hoje até 60 dias à frente (14 por defeito)
$hoje = date('Y-m-d');
$dataPorDefeito = date('Y-m-d', strtotime('+' . Lfg::DIAS_NO_AR . ' days'));
$dataMaxima = date('Y-m-d', strtotime('+' . Lfg::MAX_DIAS . ' days'));

// a aprovação vem ligada da primeira vez, depois fica como a pessoa a deixou
$aprovar = $_SERVER['REQUEST_METHOD'] === 'POST' ? isset($_POST['aprovar']) : true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::formularioValido()) {
    $erro = 'A sessão expirou. Tenta outra vez.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // o jogo só é guardado depois de o token ser válido
    $jogoId = Jogo::doFormulario($_POST);
    $plataformaId = (int) filter_input(INPUT_POST, 'plataforma_id', FILTER_VALIDATE_INT);
    $descricao = trim(filter_input(INPUT_POST, 'descricao', FILTER_UNSAFE_RAW) ?? '');
    $discord = limparDiscord(filter_input(INPUT_POST, 'discord', FILTER_UNSAFE_RAW) ?? '');
    $vagas = (int) filter_input(INPUT_POST, 'vagas', FILTER_VALIDATE_INT);
    $dataFim = filter_input(INPUT_POST, 'data_fim', FILTER_UNSAFE_RAW) ?? '';

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
    } elseif ($discord !== '' && !discordValido($discord)) {
        $erro = 'O nome do Discord só pode ter letras minúsculas, números, pontos e _ (de 2 a 32).';
    } elseif ($descricao === '' || mb_strlen($descricao) > Lfg::MAX_DESCRICAO) {
        $erro = 'Escreve uma descrição, até ' . Lfg::MAX_DESCRICAO . ' letras.';
    } elseif (Lfg::abertosDoUtilizador($utilizadorId) >= Lfg::MAX_ABERTOS) {
        $erro = 'Já tens ' . Lfg::MAX_ABERTOS . ' anúncios abertos. Fecha ou apaga um antes de criar outro.';
    }

    if (!$erro) {
        $id = Lfg::criar($utilizadorId, $jogoId, $plataformaId ?: null, $descricao, $vagas, $dataFim, $discord === '' ? null : $discord, $aprovar);
        irPara('lfg/anuncio.php?id=' . $id);
    }
}

$tituloPagina = 'Criar anúncio';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="formulario">
    <h1>Criar anúncio</h1>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">

        <label for="jogo_pesquisa">Jogo</label>
        <?php
        $jogoTexto = $jogoInicial['nome'] ?? '';
        $jogoIdInicial = $jogoInicial['id'] ?? 0;
        require __DIR__ . '/../includes/campo_jogo.php';
        ?>

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

        <label for="discord">O teu Discord (opcional)</label>
        <input type="text" id="discord" name="discord" maxlength="33" placeholder="o_teu_nome" value="<?= htmlspecialchars($_POST['discord'] ?? $eu['discord'] ?? '') ?>">
        <p class="ajuda">Só quem entrar na equipa o vê.</p>

        <label class="opcao">
            <input type="checkbox" name="aprovar" <?= $aprovar ? 'checked' : '' ?>>
            Aprovar quem entra na equipa
        </label>
        <p class="ajuda">Quem quiser entrar faz um pedido e tu aceitas ou recusas. Sem isto entra logo.</p>

        <button type="submit">Publicar anúncio</button>
    </form>

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/lfg/anuncios.php">Voltar ao LFG</a></p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=3"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
