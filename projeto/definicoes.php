<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$id = $_SESSION['utilizador_id'];
$utilizador = Utilizador::buscarPorId($id);
$erro = null;
$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // a página tem vários formulários, o campo "acao" diz qual foi enviado
    $acao = $_POST['acao'] ?? '';
    $passwordAtual = $_POST['password_atual'] ?? '';

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'moeda') {
        $moeda = $_POST['moeda'] ?? '';

        if (!isset(Utilizador::MOEDAS[$moeda])) {
            $erro = 'Escolhe uma moeda da lista.';
        } else {
            Utilizador::atualizarMoeda($id, $moeda);
            $_SESSION['moeda'] = $moeda;
            $mensagem = 'Moeda atualizada.';
        }
    } elseif ($acao === 'email') {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Introduz um email válido.';
        } elseif (!Utilizador::passwordCorreta($id, $passwordAtual)) {
            $erro = 'A password atual está errada.';
        } else {
            $resultado = Utilizador::atualizarEmail($id, $email);
            if ($resultado['sucesso']) {
                $mensagem = 'Email atualizado.';
            } else {
                $erro = $resultado['erro'];
            }
        }
    } elseif ($acao === 'password') {
        $passwordNova = $_POST['password_nova'] ?? '';
        $passwordConfirmar = $_POST['password_confirmar'] ?? '';

        if (!Utilizador::passwordCorreta($id, $passwordAtual)) {
            $erro = 'A password atual está errada.';
        } elseif (strlen($passwordNova) < 8) {
            $erro = 'A password nova deve ter pelo menos 8 caracteres.';
        } elseif ($passwordNova !== $passwordConfirmar) {
            $erro = 'As passwords novas não coincidem.';
        } else {
            Utilizador::atualizarPassword($id, $passwordNova);
            $mensagem = 'Password atualizada.';
        }
    } elseif ($acao === 'apagar') {
        if (!isset($_POST['confirmar'])) {
            $erro = 'Tens de confirmar que queres apagar a conta.';
        } elseif (!Utilizador::passwordCorreta($id, $passwordAtual)) {
            $erro = 'A password atual está errada.';
        } else {
            Utilizador::apagarFoto($utilizador['foto_perfil']);
            Utilizador::apagarFoto($utilizador['foto_capa']);
            Utilizador::apagar($id);
            Auth::logout();
            header('Location: ' . BASE_URL . '/projeto/index.php');
            exit;
        }
    }

    // volta a ler os dados para a página já mostrar o que mudou
    $utilizador = Utilizador::buscarPorId($id);
}

$tituloPagina = 'Definições';
require_once __DIR__ . '/includes/header.php';
?>

<section class="formulario">
    <h1>Definições</h1>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <?php if ($mensagem): ?>
        <p class="aviso aviso-sucesso"><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>

    <h2>Moeda</h2>
    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">
        <input type="hidden" name="acao" value="moeda">

        <label for="moeda">Moeda usada para mostrar o valor das contas</label>
        <select id="moeda" name="moeda">
            <?php foreach (Utilizador::MOEDAS as $codigo => $nome): ?>
                <option value="<?= $codigo ?>" <?= $utilizador['moeda'] === $codigo ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Guardar moeda</button>
    </form>

    <h2>Email</h2>
    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">
        <input type="hidden" name="acao" value="email">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($utilizador['email']) ?>">

        <label for="password_email">Password atual</label>
        <input type="password" id="password_email" name="password_atual">

        <button type="submit">Mudar email</button>
    </form>

    <h2>Password</h2>
    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">
        <input type="hidden" name="acao" value="password">

        <label for="password_atual">Password atual</label>
        <input type="password" id="password_atual" name="password_atual">

        <label for="password_nova">Password nova</label>
        <input type="password" id="password_nova" name="password_nova">

        <label for="password_confirmar">Confirmar password nova</label>
        <input type="password" id="password_confirmar" name="password_confirmar">

        <button type="submit">Mudar password</button>
    </form>

    <h2>Apagar conta</h2>
    <p class="texto-dim">Apaga a conta e tudo o que está ligado a ela: anúncios, avaliações, convites e contas ligadas. Não dá para voltar atrás.</p>
    <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">
        <input type="hidden" name="acao" value="apagar">

        <label for="password_apagar">Password atual</label>
        <input type="password" id="password_apagar" name="password_atual">

        <label class="opcao"><input type="checkbox" name="confirmar"> Quero mesmo apagar a minha conta</label>

        <button type="submit" class="botao-perigo">Apagar conta</button>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
