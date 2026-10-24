<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = $_SESSION['utilizador_id'];
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'adicionar') {
        $servico = $_POST['servico'] ?? '';
        $identificador = trim($_POST['identificador'] ?? '');

        if (!isset(ContasLigadas::SERVICOS[$servico])) {
            $erro = 'Escolhe uma plataforma.';
        } elseif ($identificador === '' || mb_strlen($identificador) > 255) {
            $erro = 'Escreve o teu username, ID ou link do perfil.';
        } else {
            $resultado = ContasLigadas::adicionar($utilizadorId, $servico, $identificador);
            if (!$resultado['sucesso']) {
                $erro = $resultado['erro'];
            }
        }
    } elseif ($acao === 'epic') {
        // a epic não tem perfis públicos, liga-se com o código que aparece depois do login
        $resultado = Epic::ligar($utilizadorId, $_POST['codigo'] ?? '');
        if (!$resultado['sucesso']) {
            $erro = $resultado['erro'];
        }
    } elseif ($acao === 'remover') {
        ContasLigadas::remover((int) ($_POST['conta_id'] ?? 0), $utilizadorId);
    }

    // recarrega a página para o formulário não ser enviado outra vez ao fazer F5
    if (!$erro) {
        header('Location: ' . BASE_URL . '/projeto/contas.php');
        exit;
    }
}

$contas = ContasLigadas::listar($utilizadorId);

$tituloPagina = 'Contas ligadas';
require_once __DIR__ . '/includes/header.php';
?>

<section class="formulario">
    <h1>Contas ligadas</h1>
    <p class="texto-dim">Liga as tuas contas das plataformas de jogos para juntar tudo no teu perfil. Por agora só as contas Steam, Xbox, GOG e Epic são atualizadas, e ainda não é automático.</p>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <?php if ($contas): ?>
        <ul class="lista-contas">
            <?php foreach ($contas as $conta): ?>
                <li>
                    <img class="conta-logo" src="<?= BASE_URL ?>/projeto/assets/img/plataformas/<?= $conta['servico'] ?>.svg" alt="">

                    <div class="conta-dados">
                        <span class="conta-servico"><?= ContasLigadas::SERVICOS[$conta['servico']] ?></span>
                        <!-- depois de sincronizada mostra o nome da conta, o link fica por baixo em pequeno -->
                        <strong><?= htmlspecialchars($conta['nome_publico'] ?? $conta['identificador']) ?></strong>
                        <?php if ($conta['nome_publico']): ?>
                            <span class="conta-identificador"><?= htmlspecialchars($conta['identificador']) ?></span>
                        <?php endif; ?>
                        <span class="texto-dim">
                            <?php if ($conta['ultima_sincronizacao']): ?>
                                Atualizada a <?= (new DateTime($conta['ultima_sincronizacao']))->format('d/m/Y H:i') ?>
                            <?php else: ?>
                                Ainda não sincronizada
                            <?php endif; ?>
                        </span>
                    </div>

                    <form method="post" onsubmit="return confirm('Remover esta conta?');">
                        <input type="hidden" name="token" value="<?= Auth::token() ?>">
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="conta_id" value="<?= $conta['id'] ?>">
                        <button type="submit" class="botao-pequeno">Remover</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Ligar uma conta</h2>
    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">
        <input type="hidden" name="acao" value="adicionar">

        <label for="servico">Plataforma</label>
        <select id="servico" name="servico">
            <?php foreach (ContasLigadas::SERVICOS as $codigo => $nome): ?>
                <!-- a epic tem a sua própria secção em baixo -->
                <?php if ($codigo !== 'epic'): ?>
                    <option value="<?= $codigo ?>"><?= $nome ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>

        <label for="identificador">Username, ID ou link do perfil</label>
        <input type="text" id="identificador" name="identificador" value="<?= htmlspecialchars($_POST['identificador'] ?? '') ?>">

        <button type="submit">Ligar conta</button>
    </form>

    <h2>Ligar com a Epic Games</h2>
    <p class="texto-dim">A Epic não mostra os jogos nos perfis públicos, por isso é preciso entrar na conta pela página da Epic. A tua password nunca passa pelo I'm Gamer.</p>
    <ol class="passos">
        <li><a href="<?= htmlspecialchars(Epic::linkLogin()) ?>" target="_blank" rel="noopener">Abre o login da Epic</a> e entra na tua conta.</li>
        <li>Vai aparecer uma página com um texto que tem "authorizationCode".</li>
        <li>Copia esse texto todo (ou só o código) e cola aqui em baixo. O código só vale uns minutos.</li>
    </ol>
    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">
        <input type="hidden" name="acao" value="epic">

        <label for="codigo">Código da Epic</label>
        <input type="text" id="codigo" name="codigo" autocomplete="off">

        <button type="submit">Ligar Epic Games</button>
    </form>

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/perfil.php">Voltar ao perfil</a></p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
