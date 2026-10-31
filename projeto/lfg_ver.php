<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$id = (int) ($_GET['id'] ?? 0);
$anuncio = Lfg::buscar($id);
$erro = null;

if ($anuncio && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // a classe Lfg confirma se quem pede pode fazer aquilo
    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'entrar') {
        $resultado = Lfg::entrar($id, $utilizadorId, $_SESSION['nome_utilizador']);
        if (!$resultado['sucesso']) {
            $erro = $resultado['erro'];
        }
    } elseif ($acao === 'sair') {
        Lfg::sair($id, $utilizadorId, $_SESSION['nome_utilizador']);
    } elseif ($acao === 'fechar') {
        Lfg::mudarEstado($id, $utilizadorId, 'fechado');
    } elseif ($acao === 'reabrir') {
        Lfg::mudarEstado($id, $utilizadorId, 'aberto');
    } elseif ($acao === 'prolongar') {
        Lfg::prolongar($id, $utilizadorId);
    } elseif ($acao === 'remover') {
        Lfg::removerParticipante($id, $utilizadorId, (int) ($_POST['participante_id'] ?? 0));
    } elseif ($acao === 'apagar') {
        Lfg::apagar($id, $utilizadorId);
        header('Location: ' . BASE_URL . '/projeto/lfg.php?aba=meus');
        exit;
    }

    // recarrega a página para o formulário não ser enviado outra vez ao fazer F5
    if (!$erro) {
        header('Location: ' . BASE_URL . '/projeto/lfg_ver.php?id=' . $id);
        exit;
    }
}

if ($anuncio) {
    $dono = (int) $anuncio['utilizador_id'] === $utilizadorId;
    $participantes = Lfg::participantes($id);
    $participo = in_array($utilizadorId, array_map('intval', array_column($participantes, 'utilizador_id')), true);
    $estado = Lfg::estado($anuncio);
    $terminado = Lfg::expirado($anuncio);
    $podeEntrar = !$dono && !$participo && $anuncio['estado'] === 'aberto' && !$terminado && $anuncio['participantes'] < $anuncio['vagas'];
}

$tituloPagina = 'Anúncio de LFG';
require_once __DIR__ . '/includes/header.php';
?>

<section class="jogo">
    <?php if (!$anuncio): ?>
        <h1>Anúncio de LFG</h1>
        <p>Este anúncio não existe ou já foi apagado. <a href="<?= BASE_URL ?>/projeto/lfg.php">Ver os anúncios</a></p>
    <?php else: ?>
        <div class="jogo-topo">
            <div>
                <?php if ($anuncio['imagem_capa']): ?>
                    <a href="<?= BASE_URL ?>/projeto/jogo.php?id=<?= $anuncio['jogo_id'] ?>">
                        <img class="jogo-capa" src="<?= htmlspecialchars($anuncio['imagem_capa']) ?>" alt="">
                    </a>
                <?php endif; ?>
            </div>

            <div class="jogo-info">
                <h1><?= htmlspecialchars($anuncio['jogo']) ?></h1>
                <p class="perfil-data">
                    <?= htmlspecialchars($anuncio['plataforma'] ?? 'Qualquer plataforma') ?>
                    · publicado por <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($anuncio['nome_utilizador']) ?>"><?= htmlspecialchars($anuncio['nome_utilizador']) ?></a>
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
                    <?php if ($podeEntrar): ?>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= Auth::token() ?>">
                            <button type="submit" name="acao" value="entrar">Entrar na equipa</button>
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
                        <a class="botao-contorno" href="<?= BASE_URL ?>/projeto/convidar.php?jogo=<?= $anuncio['jogo_id'] ?>">Convidar jogadores</a>
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

        <h2>Equipa</h2>
        <ul class="lista-avaliacoes">
            <li>
                <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($anuncio['nome_utilizador']) ?>"><?= htmlspecialchars($anuncio['nome_utilizador']) ?></a>
                <span class="texto-dim">criou a equipa</span>
            </li>
            <?php foreach ($participantes as $participante): ?>
                <li>
                    <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($participante['nome_utilizador']) ?>"><?= htmlspecialchars($participante['nome_utilizador']) ?></a>
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

        <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/lfg.php">Voltar ao LFG</a></p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
