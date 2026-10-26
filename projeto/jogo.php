<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

$utilizadorId = (int) $_SESSION['utilizador_id'];
$jogo = null;
$procurar = false;
$erro = null;

if (isset($_GET['id'])) {
    $jogo = Jogo::buscarPorId((int) $_GET['id']);
} else {
    // vem do catálogo (?nome=...&steam=...) ou dos lançamentos (?rawg=slug)
    // se o jogo já estiver na bd abre-se logo, senão o browser vai procurá-lo à rawg (jogo_rawg.js)
    $slug = $_GET['rawg'] ?? '';
    $steam = (int) ($_GET['steam'] ?? 0);
    $nome = trim($_GET['nome'] ?? '');

    $id = Jogo::procurar(0, $slug, $steam, $nome);

    if ($id) {
        header('Location: ' . BASE_URL . '/projeto/jogo.php?id=' . $id);
        exit;
    }

    $procurar = $slug !== '' || $nome !== '';
}

if ($jogo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!Auth::tokenValido($_POST['token'] ?? '')) {
        $erro = 'A sessão expirou. Tenta outra vez.';
    } elseif ($acao === 'desejo_adicionar') {
        ListaDesejos::adicionar($utilizadorId, $jogo['id']);
    } elseif ($acao === 'desejo_remover') {
        ListaDesejos::remover($utilizadorId, $jogo['id']);
    } elseif ($acao === 'avaliar') {
        $pontuacao = (int) ($_POST['pontuacao'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($pontuacao < 1 || $pontuacao > 5) {
            $erro = 'Escolhe de 1 a 5 estrelas.';
        } elseif (mb_strlen($comentario) > 1000) {
            $erro = 'O comentário só pode ter até 1000 letras.';
        } else {
            Avaliacoes::guardar($utilizadorId, $jogo['id'], $pontuacao, $comentario === '' ? null : $comentario);
        }
    } elseif ($acao === 'apagar_avaliacao') {
        Avaliacoes::apagar($utilizadorId, $jogo['id']);
    }

    // recarrega a página para o formulário não ser enviado outra vez ao fazer F5
    if (!$erro) {
        $ancora = str_contains($acao, 'avalia') ? '#avaliacoes' : '';
        header('Location: ' . BASE_URL . '/projeto/jogo.php?id=' . $jogo['id'] . $ancora);
        exit;
    }
}

if ($jogo) {
    $generos = Jogo::generosDoJogo($jogo['id']);
    $plataformas = Jogo::plataformasDoJogo($jogo['id']);
    $lojas = Jogo::lojas($jogo);
    $naLista = ListaDesejos::tem($utilizadorId, $jogo['id']);

    $media = Avaliacoes::media($jogo['id']);
    $avaliacoes = Avaliacoes::doJogo($jogo['id']);
    $minhaAvaliacao = Avaliacoes::doUtilizador($utilizadorId, $jogo['id']);

    $progresso = Biblioteca::progressoNoJogo($utilizadorId, $jogo['id'], $jogo['nome']);
    $quemTem = Biblioteca::quemTem($jogo['id'], $jogo['nome'], 12);

    $modos = [];
    foreach (Biblioteca::MODOS as $modo => $nomeModo) {
        if ($jogo['modo_' . $modo]) {
            $modos[] = $nomeModo;
        }
    }

    $conversao = Catalogo::conversaoDolar($_SESSION['moeda'] ?? 'EUR');
}

// as estrelas em texto, por exemplo 3 fica ★★★☆☆
function estrelas(int $quantas): string
{
    return str_repeat('★', $quantas) . str_repeat('☆', 5 - $quantas);
}

$tituloPagina = $jogo ? $jogo['nome'] : 'Jogo';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$jogo && $procurar): ?>
    <!-- o jogo ainda não está na bd: o browser procura-o na rawg, guarda-o e abre a página dele -->
    <section class="jogo" id="procurar-jogo"
        data-chave="<?= htmlspecialchars(RAWG_API_KEY) ?>"
        data-slug="<?= htmlspecialchars($slug) ?>"
        data-nome="<?= htmlspecialchars($nome) ?>"
        data-steam="<?= $steam ?: '' ?>"
        data-token="<?= Auth::token() ?>"
        data-base="<?= BASE_URL ?>">
        <p class="texto-dim" id="procurar-estado">A procurar o jogo...</p>
    </section>
    <script src="<?= BASE_URL ?>/projeto/assets/js/jogo_rawg.js?v=2"></script>

<?php elseif (!$jogo): ?>
    <section class="jogo">
        <h1>Jogo</h1>
        <p>Este jogo não existe.</p>
    </section>

<?php else: ?>
    <section class="jogo">
        <div class="jogo-topo">
            <?php if ($jogo['imagem_capa']): ?>
                <img class="jogo-capa" src="<?= htmlspecialchars($jogo['imagem_capa']) ?>" alt="">
            <?php endif; ?>

            <div class="jogo-info">
                <h1><?= htmlspecialchars($jogo['nome']) ?></h1>

                <p class="perfil-data">
                    <?php if ($jogo['data_lancamento']): ?>
                        <?= (new DateTime($jogo['data_lancamento']))->format('d/m/Y') ?>
                    <?php endif; ?>
                    <?php if ($jogo['produtora']): ?>
                        · <?= htmlspecialchars($jogo['produtora']) ?>
                    <?php endif; ?>
                    <?php if ($jogo['editora'] && $jogo['editora'] !== $jogo['produtora']): ?>
                        · <?= htmlspecialchars($jogo['editora']) ?>
                    <?php endif; ?>
                </p>

                <?php if ($generos || $modos): ?>
                    <p class="etiquetas">
                        <?php foreach (array_merge($generos, $modos) as $etiqueta): ?>
                            <span><?= htmlspecialchars($etiqueta) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>

                <?php if ($plataformas): ?>
                    <p class="texto-dim"><?= htmlspecialchars(implode(' · ', $plataformas)) ?></p>
                <?php endif; ?>

                <div class="jogo-notas">
                    <?php if ($jogo['nota_metacritic']): ?>
                        <div class="numero">
                            <strong><?= $jogo['nota_metacritic'] ?></strong>
                            <span>Metacritic</span>
                        </div>
                    <?php endif; ?>
                    <div class="numero">
                        <?php if ($media['total'] > 0): ?>
                            <strong><?= number_format($media['media'], 1, ',', '') ?> ★</strong>
                            <span><?= $media['total'] == 1 ? '1 avaliação' : $media['total'] . ' avaliações' ?> no I'm Gamer</span>
                        <?php else: ?>
                            <strong>Sem avaliações</strong>
                            <span>no I'm Gamer</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="jogo-acoes">
                    <form method="post">
                        <input type="hidden" name="token" value="<?= Auth::token() ?>">
                        <?php if ($naLista): ?>
                            <input type="hidden" name="acao" value="desejo_remover">
                            <button type="submit" class="botao-contorno">Tirar da lista de desejos</button>
                        <?php else: ?>
                            <input type="hidden" name="acao" value="desejo_adicionar">
                            <button type="submit">Adicionar à lista de desejos</button>
                        <?php endif; ?>
                    </form>

                    <?php foreach ($lojas as $loja => $link): ?>
                        <a class="botao-contorno" href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener">Ver na <?= $loja ?></a>
                    <?php endforeach; ?>

                    <!-- sem loja conhecida, o botão leva à lista de preços, que tem o link de cada loja -->
                    <?php if (!$lojas): ?>
                        <a class="botao-contorno" id="botao-loja" href="#precos-titulo">Ver preços nas lojas</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($erro): ?>
            <p class="aviso aviso-erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <?php if ($jogo['descricao']): ?>
            <p class="jogo-descricao"><?= nl2br(htmlspecialchars($jogo['descricao'])) ?></p>
        <?php endif; ?>

        <?php if ($progresso): ?>
            <h2>O teu progresso</h2>
            <?php foreach ($progresso as $linha): ?>
                <div class="numeros">
                    <div class="numero">
                        <strong><?= ContasLigadas::SERVICOS[$linha['servico']] ?></strong>
                        <span>plataforma</span>
                    </div>
                    <?php if ($linha['minutos_jogados'] !== null): ?>
                        <div class="numero">
                            <strong><?= formatarHoras($linha['minutos_jogados']) ?></strong>
                            <span>jogadas</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($linha['conquistas_total'] > 0): ?>
                        <div class="numero">
                            <strong><?= $linha['conquistas'] ?> / <?= $linha['conquistas_total'] ?></strong>
                            <span>conquistas</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($linha['pontuacao_total'] > 0): ?>
                        <div class="numero">
                            <strong><?= $linha['pontuacao'] ?> / <?= $linha['pontuacao_total'] ?></strong>
                            <span>gamerscore</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($linha['ultima_vez_jogado']): ?>
                        <div class="numero">
                            <strong><?= (new DateTime($linha['ultima_vez_jogado']))->format('d/m/Y') ?></strong>
                            <span>última vez</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2 id="precos-titulo">Preços nas lojas de PC</h2>
        <!-- os preços vêm da cheapshark e é o browser que os pede (jogo.js) -->
        <div id="precos" class="precos"
            data-steam="<?= $jogo['steam_app_id'] ?: '' ?>"
            data-nome="<?= htmlspecialchars($jogo['nome']) ?>"
            data-simbolo="<?= htmlspecialchars(Moeda::SIMBOLOS[$conversao['moeda']]) ?>"
            data-taxa="<?= $conversao['fator'] ?>"
            data-oficiais="<?= implode(',', Catalogo::lojasOficiais()) ?>"
            data-todas="<?= implode(',', array_keys(Catalogo::LOJAS)) ?>">
            <p class="texto-dim">A carregar...</p>
        </div>

        <h2 id="avaliacoes">Avaliações</h2>

        <form method="post" class="formulario-avaliacao">
            <input type="hidden" name="token" value="<?= Auth::token() ?>">
            <input type="hidden" name="acao" value="avaliar">

            <label for="pontuacao"><?= $minhaAvaliacao ? 'A tua avaliação' : 'Avaliar este jogo' ?></label>
            <select id="pontuacao" name="pontuacao">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= ($minhaAvaliacao['pontuacao'] ?? 5) == $i ? 'selected' : '' ?>><?= estrelas($i) ?></option>
                <?php endfor; ?>
            </select>

            <label for="comentario">Comentário (opcional)</label>
            <textarea id="comentario" name="comentario" rows="3" maxlength="1000"><?= htmlspecialchars($minhaAvaliacao['comentario'] ?? '') ?></textarea>

            <div class="jogo-acoes">
                <button type="submit"><?= $minhaAvaliacao ? 'Guardar alterações' : 'Publicar avaliação' ?></button>
                <?php if ($minhaAvaliacao): ?>
                    <button type="submit" name="acao" value="apagar_avaliacao" class="botao-contorno" onclick="return confirm('Apagar a tua avaliação?');">Apagar</button>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$avaliacoes): ?>
            <p class="texto-dim">Ainda ninguém avaliou este jogo.</p>
        <?php endif; ?>

        <ul class="lista-avaliacoes">
            <?php foreach ($avaliacoes as $avaliacao): ?>
                <li>
                    <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($avaliacao['nome_utilizador']) ?>"><?= htmlspecialchars($avaliacao['nome_utilizador']) ?></a>
                    <span class="estrelas"><?= estrelas($avaliacao['pontuacao']) ?></span>
                    <span class="texto-dim"><?= (new DateTime($avaliacao['data_criacao']))->format('d/m/Y') ?></span>
                    <?php if ($avaliacao['comentario']): ?>
                        <p><?= nl2br(htmlspecialchars($avaliacao['comentario'])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($quemTem): ?>
            <h2>Quem tem este jogo</h2>
            <ul class="lista-simples lista-quem-tem">
                <?php foreach ($quemTem as $pessoa): ?>
                    <li>
                        <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($pessoa['nome_utilizador']) ?>"><?= htmlspecialchars($pessoa['nome_utilizador']) ?></a>
                        <span class="texto-dim">
                            <?= ContasLigadas::SERVICOS[$pessoa['servico']] ?>
                            <?= $pessoa['minutos_jogados'] !== null ? '· ' . formatarHoras($pessoa['minutos_jogados']) : '' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="fonte">
            Preços da <a href="https://www.cheapshark.com" target="_blank" rel="noopener">CheapShark</a><?php if ($jogo['slug']): ?>
                e dados do jogo da <a href="https://rawg.io/games/<?= htmlspecialchars($jogo['slug']) ?>" target="_blank" rel="noopener">RAWG</a><?php endif; ?>.
        </p>
    </section>

    <script src="<?= BASE_URL ?>/projeto/assets/js/cartoes.js?v=3"></script>
    <script src="<?= BASE_URL ?>/projeto/assets/js/jogo.js?v=2"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
