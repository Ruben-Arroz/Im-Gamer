<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/index.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $erro = 'Preenche todos os campos.';
    } else {
        $resultado = Auth::login($email, $password);
        if ($resultado['sucesso']) {
            header('Location: ' . BASE_URL . '/projeto/index.php');
            exit;
        } else {
            $erro = $resultado['erro'];
        }
    }
}

$tituloPagina = 'Entrar';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-form">
    <h1>Entrar</h1>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="post" novalidate>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Entrar</button>
    </form>

    <p class="auth-switch">Ainda não tens conta? <a href="<?= BASE_URL ?>/projeto/registo.php">Criar conta</a></p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>