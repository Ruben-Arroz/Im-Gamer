<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizador = Utilizador::buscarPorId($_SESSION['utilizador_id']);
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    $foto = $utilizador['foto_perfil'];

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif (mb_strlen($bio) > 1000) {
        $erro = 'A biografia não pode ter mais de 1000 caracteres.';
    } elseif (isset($_POST['remover_foto'])) {
        Utilizador::apagarFoto($foto);
        $foto = null;
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        // só mexe na foto se foi escolhido um ficheiro novo
        $resultado = Utilizador::guardarFoto($_FILES['foto'], $utilizador['id']);

        if ($resultado['sucesso']) {
            Utilizador::apagarFoto($foto);
            $foto = $resultado['nome'];
        } else {
            $erro = $resultado['erro'];
        }
    }

    if (!$erro) {
        Utilizador::atualizarPerfil($utilizador['id'], $bio, $foto);
        header('Location: ' . BASE_URL . '/projeto/perfil.php');
        exit;
    }
}

$tituloPagina = 'Editar perfil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="formulario">
    <h1>Editar perfil</h1>

    <?php if ($erro): ?>
        <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="token" value="<?= Auth::token() ?>">

        <label for="foto">Foto de perfil</label>
        <?php if ($utilizador['foto_perfil']): ?>
            <img class="perfil-foto" src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($utilizador['foto_perfil']) ?>" alt="Foto atual">
            <label class="opcao"><input type="checkbox" name="remover_foto"> Remover foto</label>
        <?php endif; ?>
        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
        <p class="ajuda">JPG, PNG ou WEBP, até 2 MB.</p>

        <label for="bio">Biografia</label>
        <textarea id="bio" name="bio" rows="6" maxlength="1000"><?= htmlspecialchars($_POST['bio'] ?? $utilizador['bio'] ?? '') ?></textarea>

        <button type="submit">Guardar</button>
    </form>

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/perfil.php">Voltar ao perfil</a></p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
