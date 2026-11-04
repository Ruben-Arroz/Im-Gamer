<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$contaId = (int) ($_GET['conta'] ?? 0);

// só o dono da conta pode mexer, e só nas contas sem api (nintendo e battle.net)
$conta = JogosManuais::conta($contaId, $utilizadorId);
if (!$conta) {
    header('Location: ' . BASE_URL . '/projeto/contas.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'adicionar') {
        $horas = trim($_POST['horas'] ?? '');

        // as horas são opcionais, vazio quer dizer que não se sabe
        if ($horas !== '' && (!ctype_digit($horas) || (int) $horas > JogosManuais::MAX_HORAS)) {
            $erro = 'As horas têm de ser um número inteiro de 0 a ' . JogosManuais::MAX_HORAS . '.';
        } else {
            $jogoId = Jogo::doFormulario($_POST);

            if (!$jogoId) {
                $erro = 'Escolhe o jogo da lista de sugestões.';
            } else {
                $erro = JogosManuais::adicionar($contaId, $jogoId, $horas === '' ? null : (int) $horas * 60);
            }
        }
    } elseif ($acao === 'tirar') {
        JogosManuais::tirar($contaId, (int) ($_POST['linha_id'] ?? 0));
    }

    if (!$erro) {
        header('Location: ' . BASE_URL . '/projeto/jogos_manuais.php?conta=' . $contaId);
        exit;
    }
}

$jogos = JogosManuais::listar($contaId);
$nomeServico = ContasLigadas::SERVICOS[$conta['servico']];

$tituloPagina = 'Jogos ' . $nomeServico;
require_once __DIR__ . '/includes/header.php';
?>

<section class="formulario">
    <h1>Jogos <?= $nomeServico ?></h1>
    <p class="texto-dim">A <?= $nomeServico ?> não deixa ver os jogos de fora, por isso adiciona aqui os jogos que tens nesta conta. As horas são opcionais.</p>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">
        <input type="hidden" name="acao" value="adicionar">

        <label for="jogo_pesquisa">Jogo</label>
        <div class="pesquisa-jogo">
            <!-- a chave da rawg tem de ir para o browser porque é ele que faz a pesquisa -->
            <input type="text" id="jogo_pesquisa" name="jogo_pesquisa" autocomplete="off" placeholder="Escreve o nome do jogo"
                data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
                value="<?= htmlspecialchars($_POST['jogo_pesquisa'] ?? '') ?>">
            <ul id="jogo_sugestoes" class="sugestoes"></ul>
        </div>
        <input type="hidden" name="jogo_id" value="0">
        <input type="hidden" id="jogo_rawg_id" name="jogo_rawg_id" value="<?= htmlspecialchars($_POST['jogo_rawg_id'] ?? '') ?>">
        <input type="hidden" id="jogo_nome" name="jogo_nome" value="<?= htmlspecialchars($_POST['jogo_nome'] ?? '') ?>">
        <input type="hidden" id="jogo_slug" name="jogo_slug" value="<?= htmlspecialchars($_POST['jogo_slug'] ?? '') ?>">
        <input type="hidden" id="jogo_imagem" name="jogo_imagem" value="<?= htmlspecialchars($_POST['jogo_imagem'] ?? '') ?>">
        <input type="hidden" id="jogo_lancamento" name="jogo_lancamento" value="<?= htmlspecialchars($_POST['jogo_lancamento'] ?? '') ?>">
        <p class="ajuda">Pesquisa feita na <a href="https://rawg.io" target="_blank" rel="noopener">RAWG</a>.</p>

        <label for="horas">Horas jogadas (opcional)</label>
        <input type="number" id="horas" name="horas" min="0" max="<?= JogosManuais::MAX_HORAS ?>" value="<?= htmlspecialchars($_POST['horas'] ?? '') ?>">

        <button type="submit">Adicionar jogo</button>
    </form>

    <h2><?= count($jogos) ?> <?= count($jogos) === 1 ? 'jogo' : 'jogos' ?></h2>

    <?php if ($jogos): ?>
        <ul class="lista-contas">
            <?php foreach ($jogos as $jogo): ?>
                <li>
                    <?php if ($jogo['imagem']): ?>
                        <img class="capa-manual" src="<?= htmlspecialchars($jogo['imagem']) ?>" alt="" loading="lazy">
                    <?php endif; ?>

                    <div class="conta-dados">
                        <a href="<?= BASE_URL ?>/projeto/jogo.php?id=<?= (int) $jogo['jogo_id'] ?>"><?= htmlspecialchars($jogo['nome']) ?></a>
                        <span class="texto-dim">
                            <?= $jogo['minutos_jogados'] !== null ? formatarHoras($jogo['minutos_jogados']) : 'Sem horas' ?>
                        </span>
                    </div>

                    <form method="post" onsubmit="return confirm('Tirar este jogo da conta?');">
                        <input type="hidden" name="token" value="<?= Auth::token() ?>">
                        <input type="hidden" name="acao" value="tirar">
                        <input type="hidden" name="linha_id" value="<?= $jogo['id'] ?>">
                        <button type="submit" class="botao-pequeno">Tirar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="texto-dim">Ainda não adicionaste nenhum jogo.</p>
    <?php endif; ?>

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/contas.php">Voltar às contas</a></p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=1"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
