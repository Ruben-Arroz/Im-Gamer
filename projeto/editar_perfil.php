<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizador = Utilizador::buscarPorId($_SESSION['utilizador_id']);
$plataformas = Jogo::plataformas();
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_utilizador'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $plataformaId = (int) ($_POST['plataforma_id'] ?? 0);
    $esconder = isset($_POST['esconder_valores']) ? 1 : 0;
    $pesquisaJogo = trim($_POST['jogo_pesquisa'] ?? '');
    $rawgId = (int) ($_POST['jogo_rawg_id'] ?? 0);
    $jogoId = $utilizador['jogo_favorito_id'];

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif (mb_strlen($nome) < 3 || mb_strlen($nome) > 50) {
        $erro = 'O nome de utilizador deve ter entre 3 e 50 caracteres.';
    } elseif (Utilizador::nomeEmUso($nome, $utilizador['id'])) {
        $erro = 'Esse nome de utilizador já está a ser usado.';
    } elseif (mb_strlen($bio) > 1000) {
        $erro = 'A biografia não pode ter mais de 1000 caracteres.';
    } elseif ($pesquisaJogo !== '' && $rawgId === 0) {
        $erro = 'Escolhe o jogo favorito da lista de sugestões.';
    } elseif ($plataformaId !== 0 && !in_array($plataformaId, array_column($plataformas, 'id'))) {
        $erro = 'Escolhe um dispositivo da lista.';
    }

    // o jogo vem da pesquisa da rawg feita no browser, se for novo fica guardado na tabela Jogos
    if (!$erro) {
        if ($pesquisaJogo === '') {
            $jogoId = null;
        } elseif ($rawgId !== (int) $utilizador['jogo_favorito_rawg_id']) {
            $nomeJogo = trim($_POST['jogo_nome'] ?? '');
            if ($nomeJogo === '') {
                $nomeJogo = $pesquisaJogo;
            }

            $jogoId = Jogo::guardarDaRawg($rawgId, $nomeJogo, $_POST['jogo_slug'] ?? '', $_POST['jogo_imagem'] ?? null, $_POST['jogo_lancamento'] ?? null);
        }
    }

    if (!$erro) {
        $foto = Utilizador::trocarImagem($_FILES['foto'] ?? null, isset($_POST['remover_foto']), $utilizador['foto_perfil'], 'perfil', $utilizador['id']);
        if (!$foto['sucesso']) {
            $erro = $foto['erro'];
        }
    }

    if (!$erro) {
        $capa = Utilizador::trocarImagem($_FILES['capa'] ?? null, isset($_POST['remover_capa']), $utilizador['foto_capa'], 'capa', $utilizador['id']);
        if (!$capa['sucesso']) {
            $erro = $capa['erro'];
        }
    }

    if (!$erro) {
        Utilizador::atualizarPerfil($utilizador['id'], [
            'nome' => $nome,
            'bio' => $bio,
            'foto' => $foto['nome'],
            'capa' => $capa['nome'],
            'jogo' => $jogoId,
            'plataforma' => $plataformaId ?: null,
            'esconder' => $esconder,
        ]);

        // o nome também está guardado na sessão
        $_SESSION['nome_utilizador'] = $nome;

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

        <label for="nome_utilizador">Nome de utilizador</label>
        <input type="text" id="nome_utilizador" name="nome_utilizador" maxlength="50" value="<?= htmlspecialchars($_POST['nome_utilizador'] ?? $utilizador['nome_utilizador']) ?>">

        <label for="foto">Foto de perfil</label>
        <img id="previsao_foto" class="perfil-foto <?= $utilizador['foto_perfil'] ? '' : 'escondido' ?>" alt="Foto de perfil"
            src="<?= $utilizador['foto_perfil'] ? BASE_URL . '/uploads/' . htmlspecialchars($utilizador['foto_perfil']) : '' ?>">
        <?php if ($utilizador['foto_perfil']): ?>
            <label class="opcao"><input type="checkbox" name="remover_foto"> Remover foto</label>
        <?php endif; ?>
        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">

        <label for="capa">Imagem de capa</label>
        <div id="previsao_capa" class="capa-atual <?= $utilizador['foto_capa'] ? '' : 'escondido' ?>"
            <?php if ($utilizador['foto_capa']): ?>style="background-image: url('<?= BASE_URL ?>/uploads/<?= htmlspecialchars($utilizador['foto_capa']) ?>')"<?php endif; ?>></div>
        <?php if ($utilizador['foto_capa']): ?>
            <label class="opcao"><input type="checkbox" name="remover_capa"> Remover capa</label>
        <?php endif; ?>
        <input type="file" id="capa" name="capa" accept="image/jpeg,image/png,image/webp">
        <p class="ajuda">JPG, PNG ou WEBP, até 4 MB. Para a capa fica melhor uma imagem larga.</p>

        <label for="bio">Biografia</label>
        <textarea id="bio" name="bio" rows="5" maxlength="1000"><?= htmlspecialchars($_POST['bio'] ?? $utilizador['bio'] ?? '') ?></textarea>

        <label for="jogo_pesquisa">Jogo favorito</label>
        <div class="pesquisa-jogo">
            <!-- a chave da rawg tem de ir para o browser porque é ele que faz a pesquisa -->
            <input type="text" id="jogo_pesquisa" name="jogo_pesquisa" autocomplete="off" placeholder="Escreve o nome do jogo"
                data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
                value="<?= htmlspecialchars($_POST['jogo_pesquisa'] ?? $utilizador['jogo_favorito'] ?? '') ?>">
            <ul id="jogo_sugestoes" class="sugestoes"></ul>
        </div>
        <input type="hidden" id="jogo_rawg_id" name="jogo_rawg_id" value="<?= htmlspecialchars($_POST['jogo_rawg_id'] ?? $utilizador['jogo_favorito_rawg_id'] ?? '') ?>">
        <input type="hidden" id="jogo_nome" name="jogo_nome" value="<?= htmlspecialchars($_POST['jogo_nome'] ?? '') ?>">
        <input type="hidden" id="jogo_slug" name="jogo_slug" value="<?= htmlspecialchars($_POST['jogo_slug'] ?? '') ?>">
        <input type="hidden" id="jogo_imagem" name="jogo_imagem" value="<?= htmlspecialchars($_POST['jogo_imagem'] ?? '') ?>">
        <input type="hidden" id="jogo_lancamento" name="jogo_lancamento" value="<?= htmlspecialchars($_POST['jogo_lancamento'] ?? '') ?>">
        <p class="ajuda">Pesquisa feita na <a href="https://rawg.io" target="_blank" rel="noopener">RAWG</a>.</p>

        <label for="plataforma_id">Dispositivo preferido</label>
        <select id="plataforma_id" name="plataforma_id">
            <option value="0">Nenhuma</option>
            <?php foreach ($plataformas as $plataforma): ?>
                <option value="<?= $plataforma['id'] ?>" <?= (int) $utilizador['plataforma_preferida_id'] === (int) $plataforma['id'] ? 'selected' : '' ?>><?= htmlspecialchars($plataforma['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="opcao">
            <input type="checkbox" name="esconder_valores" <?= $utilizador['esconder_valores'] ? 'checked' : '' ?>>
            Esconder o valor das minhas contas aos outros utilizadores
        </label>

        <button type="submit">Guardar</button>
    </form>

    <p class="auth-switch"><a href="<?= BASE_URL ?>/projeto/perfil.php">Voltar ao perfil</a></p>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/pesquisa_jogo.js?v=1"></script>
<script src="<?= BASE_URL ?>/projeto/assets/js/previsao_imagem.js?v=1"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
