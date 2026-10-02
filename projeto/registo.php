<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/index.php');
    exit;
}

$erro = null;
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUtilizador = trim($_POST['nome_utilizador'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirmar = $_POST['password_confirmar'] ?? '';

    if ($nomeUtilizador === '' || $email === '' || $password === '') {
        $erro = 'Preenche todos os campos.';
    } elseif (strlen($nomeUtilizador) < 3 || strlen($nomeUtilizador) > 50) {
        $erro = 'O nome de utilizador deve ter entre 3 e 50 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Introduz um email válido.';
    } elseif ($password !== $passwordConfirmar) {
        $erro = 'As passwords não coincidem.';
    } elseif (strlen($password) < 8) {
        $erro = 'A password deve ter pelo menos 8 caracteres.';
    } else {
        $resultado = Auth::registar($nomeUtilizador, $email, $password);
        if ($resultado['sucesso']) {
            $sucesso = true;
        } else {
            $erro = $resultado['erro'];
        }
    }
}

$tituloPagina = 'Registo';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-form">
    <h1>Criar conta</h1>

    <?php if ($sucesso): ?>
        <p class="aviso aviso-sucesso">Conta criada com sucesso. <a href="<?= BASE_URL ?>/projeto/login.php">Entrar</a></p>
    <?php else: ?>
        <?php if ($erro): ?>
            <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <form method="post" novalidate>
            <label for="nome_utilizador">Nome de utilizador</label>
            <input type="text" id="nome_utilizador" name="nome_utilizador" value="<?= htmlspecialchars($_POST['nome_utilizador'] ?? '') ?>" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="password_confirmar">Confirmar password</label>
            <input type="password" id="password_confirmar" name="password_confirmar" required>

            <button type="submit">Criar conta</button>
        </form>

        <p class="auth-switch">Já tens conta? <a href="<?= BASE_URL ?>/projeto/login.php">Entrar</a></p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>