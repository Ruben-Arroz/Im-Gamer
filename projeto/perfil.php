<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$pdo = Database::ligar();
$stmt = $pdo->prepare('SELECT nome_utilizador, email, bio, foto_perfil, data_criacao FROM Utilizadores WHERE id = :id');
$stmt->execute(['id' => $_SESSION['utilizador_id']]);
$utilizador = $stmt->fetch();

$tituloPagina = 'Perfil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="perfil">
    <h1><?= htmlspecialchars($utilizador['nome_utilizador']) ?></h1>
    <p class="perfil-email"><?= htmlspecialchars($utilizador['email']) ?></p>

    <p class="perfil-bio">
        <?= $utilizador['bio'] ? nl2br(htmlspecialchars($utilizador['bio'])) : 'Ainda sem biografia.' ?>
    </p>

    <p class="perfil-data">Membro desde <?= (new DateTime($utilizador['data_criacao']))->format('d/m/Y') ?></p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>