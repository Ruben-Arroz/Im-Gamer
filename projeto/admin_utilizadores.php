<?php
require_once __DIR__ . '/includes/bootstrap.php';

Admin::verificar();

$utilizadorId = (int) $_SESSION['utilizador_id'];
$erro = null;
$texto = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } else {
        $erro = Admin::apagarUtilizador((int) ($_POST['id'] ?? 0), $utilizadorId);
    }

    // para o F5 não apagar outra vez
    if (!$erro) {
        $_SESSION['aviso_admin'] = 'Utilizador apagado.';
        header('Location: ' . BASE_URL . '/projeto/admin_utilizadores.php?' . http_build_query($_GET));
        exit;
    }
}

$resultado = Admin::utilizadores($texto, $pagina);
$totalPaginas = max(1, (int) ceil($resultado['total'] / Admin::POR_PAGINA));

$tituloPagina = 'Administração';
require_once __DIR__ . '/includes/header.php';
?>

<section class="admin">
    <?php require __DIR__ . '/includes/abas_admin.php'; ?>

    <!-- mesmo estilo da pesquisa do LFG -->
    <form method="get" class="filtros-lfg">
        <div class="pesquisa-lfg">
            <input type="search" name="q" value="<?= htmlspecialchars($texto) ?>" placeholder="Nome ou email" aria-label="Procurar utilizador">
            <button type="submit">Procurar</button>
        </div>
        <?php if ($texto !== ''): ?>
            <a class="link-discreto" href="<?= BASE_URL ?>/projeto/admin_utilizadores.php">Limpar</a>
        <?php endif; ?>
    </form>

    <p class="texto-dim lfg-total"><?= $resultado['total'] == 1 ? '1 utilizador' : $resultado['total'] . ' utilizadores' ?></p>

    <ul class="lista-admin">
        <?php foreach ($resultado['linhas'] as $utilizador): ?>
            <li>
                <div class="admin-dados">
                    <span>
                        <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>"><?= htmlspecialchars($utilizador['nome_utilizador']) ?></a>
                        <?php if ($utilizador['is_admin']): ?>
                            <span class="etiqueta-chave">admin</span>
                        <?php endif; ?>
                    </span>
                    <span class="texto-dim"><?= htmlspecialchars($utilizador['email']) ?> · desde <?= (new DateTime($utilizador['data_criacao']))->format('d/m/Y') ?></span>
                    <span class="texto-dim">
                        <?php if ($utilizador['contas']): ?>
                            <?php
                            // vem tipo "steam,xbox", aqui passa para os nomes
                            $nomes = [];
                            foreach (explode(',', $utilizador['contas']) as $servico) {
                                $nomes[] = ContasLigadas::SERVICOS[$servico] ?? $servico;
                            }
                            ?>
                            <?= htmlspecialchars(implode(', ', $nomes)) ?>
                        <?php else: ?>
                            Sem contas ligadas
                        <?php endif; ?>
                        · <?= $utilizador['anuncios'] ?> anúncios · <?= $utilizador['avaliacoes'] ?> avaliações
                    </span>
                </div>

                <?php if (!$utilizador['is_admin'] && (int) $utilizador['id'] !== $utilizadorId): ?>
                    <form method="post" onsubmit="return confirm('Apagar este utilizador e tudo o que é dele? Não dá para voltar atrás.');">
                        <input type="hidden" name="token" value="<?= Auth::token() ?>">
                        <input type="hidden" name="id" value="<?= $utilizador['id'] ?>">
                        <button type="submit">Apagar</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$resultado['linhas']): ?>
        <p class="texto-dim">Nenhum utilizador encontrado.</p>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/paginacao.php'; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
