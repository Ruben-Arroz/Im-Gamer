<?php
require_once __DIR__ . '/../includes/inicio.php';

Auth::exigirSessao();

$utilizadorId = (int) $_SESSION['utilizador_id'];
$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$anuncio = Lfg::buscar($id);
$erro = null;

if ($anuncio && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = filter_input(INPUT_POST, 'acao', FILTER_UNSAFE_RAW) ?? '';

    if (!Auth::formularioValido()) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'entrar') {
        $resultado = Lfg::entrar($id, $utilizadorId, $_SESSION['nome_utilizador']);
        if (!$resultado['sucesso']) {
            $erro = $resultado['erro'];
        }
    } elseif ($acao === 'pedir') {
        $mensagem = trim(filter_input(INPUT_POST, 'mensagem', FILTER_UNSAFE_RAW) ?? '');
        if (mb_strlen($mensagem) > 255) {
            $erro = 'A mensagem só pode ter até 255 letras.';
        } else {
            $resultado = Lfg::pedir($id, $utilizadorId, $_SESSION['nome_utilizador'], $mensagem === '' ? null : $mensagem);
            if (!$resultado['sucesso']) {
                $erro = $resultado['erro'];
            }
        }
    } elseif ($acao === 'cancelar_pedido') {
        Lfg::cancelarPedido($id, $utilizadorId);
    } elseif ($acao === 'aceitar') {
        $erro = Lfg::aceitarPedido($id, $utilizadorId, (int) filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT));
    } elseif ($acao === 'recusar') {
        Lfg::recusarPedido($id, $utilizadorId, (int) filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT));
    } elseif ($acao === 'com_aprovacao' || $acao === 'sem_aprovacao') {
        Lfg::mudarAprovacao($id, $utilizadorId, $acao === 'com_aprovacao');
    } elseif ($acao === 'sair') {
        Lfg::sair($id, $utilizadorId, $_SESSION['nome_utilizador']);
    } elseif ($acao === 'fechar') {
        Lfg::mudarEstado($id, $utilizadorId, 'fechado');
    } elseif ($acao === 'reabrir') {
        Lfg::mudarEstado($id, $utilizadorId, 'aberto');
    } elseif ($acao === 'prolongar') {
        Lfg::prolongar($id, $utilizadorId);
    } elseif ($acao === 'remover') {
        Lfg::removerParticipante($id, $utilizadorId, (int) filter_input(INPUT_POST, 'participante_id', FILTER_VALIDATE_INT));
    } elseif ($acao === 'apagar') {
        Lfg::apagar($id, $utilizadorId);
        irPara('lfg/anuncios.php?aba=meus');
    }

    // recarrega a página para o formulário não ser enviado outra vez ao fazer F5
    if (!$erro) {
        irPara('lfg/anuncio.php?id=' . $id);
    }
}

if ($anuncio) {
    $dono = (int) $anuncio['utilizador_id'] === $utilizadorId;
    $participantes = Lfg::participantes($id);
    $participo = Lfg::participa($id, $utilizadorId);
    $estado = Lfg::estado($anuncio);
    $terminado = Lfg::expirado($anuncio);
    $meuPedido = Lfg::meuPedido($id, $utilizadorId);
    $pedidos = $dono ? Lfg::pedidos($id) : [];
    $podeEntrar = !$dono && !$participo && !$meuPedido && $anuncio['estado'] === 'aberto' && !$terminado && $anuncio['participantes'] < $anuncio['vagas'];
}

$tituloPagina = 'Anúncio de LFG';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="jogo">
    <?php if (!$anuncio): ?>
        <h1>Anúncio de LFG</h1>
        <p>Este anúncio não existe ou já foi apagado. <a href="<?= BASE_URL ?>/projeto/lfg/anuncios.php">Ver os anúncios</a></p>
    <?php else: ?>
        <div class="jogo-topo">
            <div>
                <?php if ($anuncio['imagem_capa']): ?>
                    <a href="<?= BASE_URL ?>/projeto/jogos/jogo.php?id=<?= $anuncio['jogo_id'] ?>">
                        <img class="jogo-capa" src="<?= htmlspecialchars($anuncio['imagem_capa']) ?>" alt="">
                    </a>
                <?php endif; ?>
            </div>

            <div class="jogo-info">
                <h1><?= htmlspecialchars($anuncio['jogo']) ?></h1>
                <p class="perfil-data">
                    <?= htmlspecialchars($anuncio['plataforma'] ?? 'Qualquer plataforma') ?>
                    · publicado por <a href="<?= BASE_URL ?>/projeto/perfil/perfil.php?u=<?= urlencode($anuncio['nome_utilizador']) ?>"><?= htmlspecialchars($anuncio['nome_utilizador']) ?></a>
                </p>

                <div class="numeros">
                    <div class="numero">
                        <strong><?= formatarDia($anuncio['data_fim']) ?></strong>
                        <span><?= $terminado ? 'saiu do ar' : 'no ar até' ?></span>
                    </div>
                    <div class="numero">
                        <strong><?= $estado ?></strong>
                        <span><?= $anuncio['participantes'] ?> de <?= $anuncio['vagas'] ?> <?= $anuncio['vagas'] == 1 ? 'vaga ocupada' : 'vagas ocupadas' ?></span>
                    </div>
                </div>

                <div class="jogo-acoes">
                    <?php if ($podeEntrar && !$anuncio['aprovar']): ?>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <button type="submit" name="acao" value="entrar">Entrar na equipa</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($meuPedido === 'pendente'): ?>
                        <form method="post" onsubmit="return confirm('Cancelar o pedido?');">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <button type="submit" name="acao" value="cancelar_pedido" class="botao-contorno">Cancelar pedido</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($participo && !$terminado): ?>
                        <form method="post" onsubmit="return confirm('Sair desta equipa?');">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <button type="submit" name="acao" value="sair" class="botao-contorno">Sair da equipa</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($dono && !$terminado): ?>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <?php if ($anuncio['estado'] === 'aberto'): ?>
                                <button type="submit" name="acao" value="fechar" class="botao-contorno">Fechar anúncio</button>
                            <?php else: ?>
                                <button type="submit" name="acao" value="reabrir" class="botao-contorno">Reabrir anúncio</button>
                            <?php endif; ?>
                        </form>
                        <a class="botao-contorno" href="<?= BASE_URL ?>/projeto/jogadores/procurar.php?jogo_id=<?= $anuncio['jogo_id'] ?>">Convidar jogadores</a>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <?php if ($anuncio['aprovar']): ?>
                                <button type="submit" name="acao" value="sem_aprovacao" class="botao-contorno">Deixar entrar sem aprovação</button>
                            <?php else: ?>
                                <button type="submit" name="acao" value="com_aprovacao" class="botao-contorno">Aprovar quem entra</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <?php if ($dono): ?>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <button type="submit" name="acao" value="prolongar" class="botao-contorno">Prolongar <?= Lfg::DIAS_NO_AR ?> dias</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Apagar este anúncio? Quem entrou vai ser avisado.');">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <button type="submit" name="acao" value="apagar" class="botao-contorno">Apagar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($erro): ?>
            <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <p class="jogo-descricao"><?= nl2br(htmlspecialchars($anuncio['descricao'])) ?></p>

        <?php if ($podeEntrar && $anuncio['aprovar']): ?>
            <form method="post" class="formulario-avaliacao pedido-lfg">
                <input type="hidden" name="token" value="<?= Auth::token() ?>">
                <p class="texto-dim">O dono deste anúncio aprova quem entra na equipa.</p>
                <label for="mensagem">Mensagem para o dono (opcional)</label>
                <textarea id="mensagem" name="mensagem" rows="3" maxlength="255" placeholder="Por exemplo: tenho 15k e microfone, jogo à noite"><?= htmlspecialchars($_POST['mensagem'] ?? '') ?></textarea>
                <button type="submit" name="acao" value="pedir" class="botao-cheio">Pedir para entrar</button>
            </form>
        <?php elseif ($meuPedido === 'pendente'): ?>
            <p class="texto-dim">Pediste para entrar nesta equipa. Estás à espera da resposta do dono.</p>
        <?php elseif ($meuPedido === 'recusado'): ?>
            <p class="texto-dim">O dono recusou o teu pedido para esta equipa.</p>
        <?php endif; ?>

        <?php if ($pedidos): ?>
            <h2>Pedidos para entrar</h2>
            <ul class="lista-avaliacoes">
                <?php foreach ($pedidos as $pedido): ?>
                    <li>
                        <a href="<?= BASE_URL ?>/projeto/perfil/perfil.php?u=<?= urlencode($pedido['nome_utilizador']) ?>"><?= htmlspecialchars($pedido['nome_utilizador']) ?></a>
                        <span class="texto-dim">pediu a <?= (new DateTime($pedido['data_criacao']))->format('d/m/Y H:i') ?></span>

                        <?php if (!$terminado): ?>
                            <form method="post" class="lista-acao">
                                <input type="hidden" name="token" value="<?= Auth::token() ?>">
                                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                <button type="submit" name="acao" value="aceitar" class="botao-cheio">Aceitar</button>
                                <button type="submit" name="acao" value="recusar" class="botao-contorno" onclick="return confirm('Recusar este pedido? A pessoa não vai poder pedir outra vez.');">Recusar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($pedido['mensagem']): ?>
                            <p><?= nl2br(htmlspecialchars($pedido['mensagem'])) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- o discord só aparece ao dono e a quem já entrou na equipa -->
        <?php if ($anuncio['discord']): ?>
            <?php if ($dono || $participo): ?>
                <p>Discord de <?= htmlspecialchars($anuncio['nome_utilizador']) ?>: <strong><?= htmlspecialchars($anuncio['discord']) ?></strong></p>
            <?php else: ?>
                <p class="texto-dim">O Discord do dono aparece quando entrares na equipa.</p>
            <?php endif; ?>
        <?php endif; ?>

        <h2>Equipa</h2>
        <ul class="lista-avaliacoes">
            <li>
                <a href="<?= BASE_URL ?>/projeto/perfil/perfil.php?u=<?= urlencode($anuncio['nome_utilizador']) ?>"><?= htmlspecialchars($anuncio['nome_utilizador']) ?></a>
                <span class="texto-dim">criou a equipa</span>
            </li>
            <?php foreach ($participantes as $participante): ?>
                <li>
                    <a href="<?= BASE_URL ?>/projeto/perfil/perfil.php?u=<?= urlencode($participante['nome_utilizador']) ?>"><?= htmlspecialchars($participante['nome_utilizador']) ?></a>
                    <span class="texto-dim">entrou a <?= (new DateTime($participante['data_entrada']))->format('d/m/Y H:i') ?></span>

                    <?php if ($dono && !$terminado): ?>
                        <form method="post" class="lista-acao" onsubmit="return confirm('Tirar esta pessoa da equipa?');">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <input type="hidden" name="participante_id" value="<?= $participante['utilizador_id'] ?>">
                            <button type="submit" name="acao" value="remover" class="botao-contorno">Tirar</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$participantes): ?>
            <p class="texto-dim">Ainda ninguém entrou.</p>
        <?php endif; ?>

        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/lfg/anuncios.php">Voltar ao LFG</a></p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
