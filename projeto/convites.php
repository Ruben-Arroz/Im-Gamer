<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $conviteId = (int) ($_POST['convite_id'] ?? 0);

    // a classe Convites confirma quem pode responder e cancelar
    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'aceitar' || $acao === 'recusar') {
        Convites::responder($conviteId, $utilizadorId, $_SESSION['nome_utilizador'], $acao === 'aceitar');
    } elseif ($acao === 'cancelar') {
        Convites::cancelar($conviteId, $utilizadorId);
    }

    if (!$erro) {
        header('Location: ' . BASE_URL . '/projeto/convites.php');
        exit;
    }
}

$recebidos = Convites::recebidos($utilizadorId);
$enviados = Convites::enviados($utilizadorId);
$avisos = Notificacoes::listar($utilizadorId, 30);

// os avisos ficam lidos ao abrir a página (desta vez ainda aparecem como novos)
Notificacoes::marcarLidas($utilizadorId);

const ESTADOS_CONVITE = [
    'pendente' => 'À espera de resposta',
    'aceite' => 'Aceite',
    'recusado' => 'Recusado',
];

$abaAtual = 'convites';
$tituloPagina = 'Convites';
require_once __DIR__ . '/includes/header.php';
?>

<section class="lfg pagina-larga">
    <div class="lfg-topo">
        <h1>Convites</h1>
        <a class="botao-principal" href="<?= BASE_URL ?>/projeto/convidar.php">Convidar jogadores</a>
    </div>

    <?php require __DIR__ . '/includes/abas_lfg.php'; ?>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <div class="colunas-convites">
        <div>
            <h2>Recebidos</h2>
            <?php if (!$recebidos): ?>
                <p class="texto-dim">Ainda ninguém te convidou.</p>
            <?php endif; ?>
            <ul class="lista-avaliacoes">
                <?php foreach ($recebidos as $convite): ?>
                    <li>
                        <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($convite['remetente']) ?>"><?= htmlspecialchars($convite['remetente']) ?></a>
                        <span>convidou-te para jogar</span>
                        <?php if ($convite['jogo']): ?>
                            <a href="<?= BASE_URL ?>/projeto/jogo.php?id=<?= $convite['jogo_id'] ?>"><?= htmlspecialchars($convite['jogo']) ?></a>
                        <?php endif; ?>
                        <span class="texto-dim"><?= (new DateTime($convite['data_criacao']))->format('d/m/Y H:i') ?></span>

                        <?php if ($convite['mensagem']): ?>
                            <p>"<?= htmlspecialchars($convite['mensagem']) ?>"</p>
                        <?php endif; ?>

                        <?php if ($convite['estado'] === 'pendente'): ?>
                            <form method="post" class="jogo-acoes">
                                <input type="hidden" name="token" value="<?= Auth::token() ?>">
                                <input type="hidden" name="convite_id" value="<?= $convite['id'] ?>">
                                <button type="submit" name="acao" value="aceitar">Aceitar</button>
                                <button type="submit" name="acao" value="recusar" class="botao-contorno">Recusar</button>
                            </form>
                        <?php else: ?>
                            <p class="texto-dim"><?= ESTADOS_CONVITE[$convite['estado']] ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2>Avisos</h2>
            <?php if (!$avisos): ?>
                <p class="texto-dim">Sem avisos.</p>
            <?php endif; ?>
            <ul class="lista-avaliacoes">
                <?php foreach ($avisos as $aviso): ?>
                    <li class="<?= $aviso['lida'] ? '' : 'aviso-novo' ?>">
                        <?php if (!$aviso['lida']): ?>
                            <span class="etiqueta-chave">novo</span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($aviso['conteudo']) ?></span>
                        <span class="texto-dim"><?= (new DateTime($aviso['data_criacao']))->format('d/m/Y H:i') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div>
            <h2>Enviados</h2>
            <?php if (!$enviados): ?>
                <p class="texto-dim">Ainda não convidaste ninguém.</p>
            <?php endif; ?>
            <ul class="lista-avaliacoes">
                <?php foreach ($enviados as $convite): ?>
                    <li>
                        <span>Para</span>
                        <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($convite['destinatario']) ?>"><?= htmlspecialchars($convite['destinatario']) ?></a>
                        <?php if ($convite['jogo']): ?>
                            <span>· <?= htmlspecialchars($convite['jogo']) ?></span>
                        <?php endif; ?>
                        <span class="texto-dim"><?= (new DateTime($convite['data_criacao']))->format('d/m/Y H:i') ?></span>
                        <p class="texto-dim"><?= ESTADOS_CONVITE[$convite['estado']] ?></p>

                        <?php if ($convite['estado'] === 'pendente'): ?>
                            <form method="post" class="lista-acao" onsubmit="return confirm('Cancelar este convite?');">
                                <input type="hidden" name="token" value="<?= Auth::token() ?>">
                                <input type="hidden" name="convite_id" value="<?= $convite['id'] ?>">
                                <button type="submit" name="acao" value="cancelar" class="botao-contorno">Cancelar</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
