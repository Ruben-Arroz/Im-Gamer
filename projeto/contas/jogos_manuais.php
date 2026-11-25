<?php
require_once __DIR__ . '/../includes/inicio.php';

Auth::exigirSessao();

$utilizadorId = (int) $_SESSION['utilizador_id'];
$contaId = (int) filter_input(INPUT_GET, 'conta', FILTER_VALIDATE_INT);

// só o dono da conta pode mexer, e só nas contas sem api (nintendo e battle.net)
$conta = JogosManuais::conta($contaId, $utilizadorId);
if (!$conta) {
    irPara('contas/contas.php');
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = filter_input(INPUT_POST, 'acao', FILTER_UNSAFE_RAW) ?? '';

    if (!Auth::formularioValido()) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'adicionar') {
        $horas = trim(filter_input(INPUT_POST, 'horas', FILTER_UNSAFE_RAW) ?? '');

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
        JogosManuais::tirar($contaId, (int) filter_input(INPUT_POST, 'linha_id', FILTER_VALIDATE_INT));
    }

    if (!$erro) {
        irPara('contas/jogos_manuais.php?conta=' . $contaId);
    }
}

$jogos = JogosManuais::listar($contaId);
$nomeServico = ContasLigadas::SERVICOS[$conta['servico']];

$tituloPagina = 'Jogos ' . $nomeServico;
require_once __DIR__ . '/../includes/header.php';
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
        <?php
        $jogoTexto = '';
        $jogoIdInicial = 0;
        require __DIR__ . '/../includes/campo_jogo.php';
        ?>

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
                        <a href="<?= BASE_URL ?>/projeto/jogos/jogo.php?id=<?= (int) $jogo['jogo_id'] ?>"><?= htmlspecialchars($jogo['nome']) ?></a>
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

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/contas/contas.php">Voltar às contas</a></p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=3"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
