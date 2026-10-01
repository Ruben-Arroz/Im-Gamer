<?php
$tituloPagina = 'Novidades';
require_once __DIR__ . '/includes/header.php';

try {
    $ligacao = Database::ligar();
    $ligacao->query('SELECT 1');
    $estadoBD = 'Ligação à base de dados estabelecida com sucesso.';
} catch (Throwable $erro) {
    $estadoBD = 'Falha na ligação à base de dados: ' . $erro->getMessage();
}
?>

<section>
    <h1>Bem-vindo ao I'm Gamer</h1>
    <p><?= htmlspecialchars($estadoBD) ?></p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>