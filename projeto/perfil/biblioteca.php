<?php
require_once __DIR__ . '/../includes/inicio.php';

Auth::exigirSessao();

// tal como no perfil, com ?u=nome mostra a biblioteca de outra pessoa
$utilizador = Utilizador::doUrl((int) $_SESSION['utilizador_id']);

const POR_PAGINA = 42;

if ($utilizador) {
    $proprio = (int) $utilizador['id'] === (int) $_SESSION['utilizador_id'];
    $mostrarValores = $proprio || !$utilizador['esconder_valores'];
    $moeda = $_SESSION['moeda'] ?? 'EUR';
    $taxa = Moeda::taxa($moeda) ?? 1;

    $contas = ContasLigadas::listar($utilizador['id']);
    $generos = Biblioteca::generosDoUtilizador($utilizador['id']);

    // os filtros vêm do url e são validados contra as listas
    $metacritic = filter_input(INPUT_GET, 'metacritic', FILTER_VALIDATE_INT);
    $anoMin = filter_input(INPUT_GET, 'ano_min', FILTER_VALIDATE_INT);
    $anoMax = filter_input(INPUT_GET, 'ano_max', FILTER_VALIDATE_INT);
    $filtros = [
        'nome' => trim(filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW) ?? ''),
        'servicos' => array_values(array_intersect((filter_input(INPUT_GET, 'servico', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: []), array_keys(ContasLigadas::SERVICOS))),
        'generos' => array_values(array_intersect(array_map('intval', (filter_input(INPUT_GET, 'genero', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [])), array_column($generos, 'id'))),
        'modos' => array_values(array_intersect((filter_input(INPUT_GET, 'modo', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: []), array_keys(Biblioteca::MODOS))),
        'horas' => escolhaGet('horas', Biblioteca::HORAS, ''),
        'conquistas' => escolhaGet('conquistas', Biblioteca::CONQUISTAS, ''),
        'recentes' => isset($_GET['recentes']),
        'preco_min' => null,
        'preco_max' => null,
        'metacritic' => is_int($metacritic) ? $metacritic : null,
        'ano_min' => is_int($anoMin) ? $anoMin : null,
        'ano_max' => is_int($anoMax) ? $anoMax : null,
        'ordem' => escolhaGet('ordem', Biblioteca::ORDENS, 'horas'),
    ];

    // o preço é escrito na moeda de quem está a ver mas na bd está em euros
    if ($mostrarValores) {
        $precoMin = filter_input(INPUT_GET, 'preco_min', FILTER_VALIDATE_FLOAT);
        $precoMax = filter_input(INPUT_GET, 'preco_max', FILTER_VALIDATE_FLOAT);
        if (is_float($precoMin)) {
            $filtros['preco_min'] = $precoMin / $taxa;
        }
        if (is_float($precoMax)) {
            $filtros['preco_max'] = $precoMax / $taxa;
        }
    } elseif ($filtros['ordem'] === 'preco') {
        $filtros['ordem'] = 'horas';
    }

    $pagina = max(1, (int) (filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?? 1));
    $resultado = Biblioteca::pesquisar($utilizador['id'], $filtros, $pagina, POR_PAGINA);
    $totalPaginas = max(1, (int) ceil($resultado['total'] / POR_PAGINA));
}

$tituloPagina = 'Biblioteca';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="biblioteca">
    <?php if (!$utilizador): ?>
        <h1>Biblioteca</h1>
        <p>Este utilizador não existe.</p>
    <?php else: ?>
        <div class="biblioteca-topo">
            <div>
                <h1>Biblioteca</h1>
                <p class="texto-dim">
                    De <a href="<?= BASE_URL ?>/projeto/perfil/perfil.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>"><?= htmlspecialchars($utilizador['nome_utilizador']) ?></a>
                    · <?= $resultado['total'] ?> <?= $resultado['total'] === 1 ? 'jogo' : 'jogos' ?>
                </p>
            </div>
            <button type="button" id="botao-filtros" class="botao-filtros">Filtros</button>
        </div>

        <div class="biblioteca-corpo">
            <aside id="filtros" class="filtros-biblioteca">
                <form method="get">
                    <input type="hidden" name="u" value="<?= htmlspecialchars($utilizador['nome_utilizador']) ?>">

                    <div class="filtro-grupo">
                        <label for="q" class="filtro-titulo">Pesquisar</label>
                        <input type="text" id="q" name="q" value="<?= htmlspecialchars($filtros['nome']) ?>" placeholder="Nome do jogo">
                    </div>

                    <div class="filtro-grupo">
                        <label for="ordem" class="filtro-titulo">Ordenar por</label>
                        <select id="ordem" name="ordem">
                            <?php foreach (Biblioteca::ORDENS as $chave => $ordem): ?>
                                <?php if ($chave === 'preco' && !$mostrarValores) continue; ?>
                                <option value="<?= $chave ?>" <?= $filtros['ordem'] === $chave ? 'selected' : '' ?>><?= $ordem[0] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (count($contas) > 1): ?>
                        <div class="filtro-grupo">
                            <p class="filtro-titulo">Plataforma</p>
                            <?php foreach ($contas as $conta): ?>
                                <label class="opcao">
                                    <input type="checkbox" name="servico[]" value="<?= $conta['servico'] ?>" <?= in_array($conta['servico'], $filtros['servicos']) ? 'checked' : '' ?>>
                                    <?= ContasLigadas::SERVICOS[$conta['servico']] ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($filtros['servicos']): ?>
                        <!-- só há uma conta, mas o filtro que veio do perfil fica guardado -->
                        <input type="hidden" name="servico[]" value="<?= $filtros['servicos'][0] ?>">
                    <?php endif; ?>

                    <?php if ($generos): ?>
                        <div class="filtro-grupo">
                            <p class="filtro-titulo">Géneros</p>
                            <?php foreach ($generos as $genero): ?>
                                <label class="opcao">
                                    <input type="checkbox" name="genero[]" value="<?= $genero['id'] ?>" <?= in_array($genero['id'], $filtros['generos']) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($genero['nome']) ?> <span class="texto-dim">(<?= $genero['quantidade'] ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="filtro-grupo">
                        <p class="filtro-titulo">Modo de jogo</p>
                        <?php foreach (Biblioteca::MODOS as $chave => $nome): ?>
                            <label class="opcao">
                                <input type="checkbox" name="modo[]" value="<?= $chave ?>" <?= in_array($chave, $filtros['modos']) ? 'checked' : '' ?>>
                                <?= $nome ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="filtro-grupo">
                        <p class="filtro-titulo">Horas jogadas</p>
                        <label class="opcao"><input type="radio" name="horas" value="" <?= $filtros['horas'] === '' ? 'checked' : '' ?>> Todas</label>
                        <?php foreach (Biblioteca::HORAS as $chave => $horas): ?>
                            <label class="opcao">
                                <input type="radio" name="horas" value="<?= $chave ?>" <?= $filtros['horas'] === $chave ? 'checked' : '' ?>>
                                <?= $horas[0] ?>
                            </label>
                        <?php endforeach; ?>
                        <label class="opcao filtro-solto">
                            <input type="checkbox" name="recentes" <?= $filtros['recentes'] ? 'checked' : '' ?>>
                            Jogados nas últimas 2 semanas
                        </label>
                    </div>

                    <div class="filtro-grupo">
                        <p class="filtro-titulo">Conquistas</p>
                        <label class="opcao"><input type="radio" name="conquistas" value="" <?= $filtros['conquistas'] === '' ? 'checked' : '' ?>> Todas</label>
                        <?php foreach (Biblioteca::CONQUISTAS as $chave => $conquistas): ?>
                            <label class="opcao">
                                <input type="radio" name="conquistas" value="<?= $chave ?>" <?= $filtros['conquistas'] === $chave ? 'checked' : '' ?>>
                                <?= $conquistas[0] ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($mostrarValores): ?>
                        <div class="filtro-grupo">
                            <p class="filtro-titulo">Preço (<?= Moeda::SIMBOLOS[$moeda] ?>)</p>
                            <div class="filtro-intervalo">
                                <input type="number" name="preco_min" min="0" step="1" placeholder="mín." value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>">
                                <input type="number" name="preco_max" min="0" step="1" placeholder="máx." value="<?= htmlspecialchars($_GET['preco_max'] ?? '') ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="filtro-grupo">
                        <p class="filtro-titulo">Nota Metacritic mínima</p>
                        <input type="number" name="metacritic" min="0" max="100" placeholder="ex.: 75" value="<?= $filtros['metacritic'] ?? '' ?>">
                    </div>

                    <div class="filtro-grupo">
                        <p class="filtro-titulo">Ano de lançamento</p>
                        <div class="filtro-intervalo">
                            <input type="number" name="ano_min" min="1970" max="2100" placeholder="de" value="<?= $filtros['ano_min'] ?? '' ?>">
                            <input type="number" name="ano_max" min="1970" max="2100" placeholder="até" value="<?= $filtros['ano_max'] ?? '' ?>">
                        </div>
                    </div>

                    <button type="submit">Aplicar filtros</button>
                    <a class="filtro-limpar" href="<?= BASE_URL ?>/projeto/perfil/biblioteca.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>">Limpar filtros</a>
                </form>
            </aside>

            <div class="biblioteca-resultados">
                <?php if (!$resultado['jogos'] && !$contas): ?>
                    <p class="texto-dim">
                        Ainda não há contas ligadas.
                        <?php if ($proprio): ?>
                            <a href="<?= BASE_URL ?>/projeto/contas/contas.php">Ligar uma conta</a>
                        <?php endif; ?>
                    </p>
                <?php elseif (!$resultado['jogos']): ?>
                    <p class="texto-dim">Nenhum jogo encontrado com estes filtros.</p>
                <?php endif; ?>

                <div class="grelha-jogos">
                    <?php foreach ($resultado['jogos'] as $jogo): ?>
                        <!-- abre a página do jogo, se o jogo não estiver na tabela Jogos abre a loja -->
                        <?php if ($jogo['jogo_id']): ?>
                            <a class="cartao-jogo" href="<?= BASE_URL ?>/projeto/jogos/jogo.php?id=<?= $jogo['jogo_id'] ?>">
                        <?php else: ?>
                            <?php $link = Biblioteca::linkLoja($jogo); ?>
                            <a class="cartao-jogo" <?= $link ? 'href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener"' : '' ?>>
                        <?php endif; ?>
                            <img src="<?= htmlspecialchars($jogo['imagem']) ?>" alt="" loading="lazy">
                            <p class="cartao-titulo"><?= htmlspecialchars($jogo['nome']) ?></p>
                            <p class="cartao-loja">
                                <img class="cartao-logo" src="<?= BASE_URL ?>/projeto/assets/img/plataformas/<?= $jogo['servico'] ?>.svg" alt="">
                                <?= ContasLigadas::SERVICOS[$jogo['servico']] ?>
                                <?php if ($jogo['data_lancamento']): ?>
                                    · <?= substr($jogo['data_lancamento'], 0, 4) ?>
                                <?php endif; ?>
                            </p>
                            <p class="cartao-precos">
                                <!-- nos jogos da xbox nem sempre se sabe as horas, aí não aparece nada -->
                                <?php if ($jogo['minutos_jogados'] !== null): ?>
                                    <span><?= formatarHoras($jogo['minutos_jogados']) ?></span>
                                <?php endif; ?>
                                <?php if ($jogo['conquistas_total'] > 0): ?>
                                    <span class="texto-dim"><?= $jogo['conquistas'] ?>/<?= $jogo['conquistas_total'] ?> conquistas</span>
                                <?php endif; ?>
                                <?php if ($jogo['pontuacao_total'] > 0): ?>
                                    <span class="texto-dim"><?= $jogo['pontuacao'] ?>/<?= $jogo['pontuacao_total'] ?> G</span>
                                <?php endif; ?>
                                <?php if ($mostrarValores && $jogo['valor'] !== null): ?>
                                    <strong><?= $jogo['valor'] > 0 ? Moeda::formatar($jogo['valor'], $moeda) : 'Grátis' ?></strong>
                                <?php endif; ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav class="paginacao">
                        <?php if ($pagina > 1): ?>
                            <a href="<?= htmlspecialchars(urlPagina($pagina - 1)) ?>">Anterior</a>
                        <?php endif; ?>
                        <?php for ($numero = 1; $numero <= $totalPaginas; $numero++): ?>
                            <?php if ($numero === $pagina): ?>
                                <span class="pagina-atual"><?= $numero ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars(urlPagina($numero)) ?>"><?= $numero ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="<?= htmlspecialchars(urlPagina($pagina + 1)) ?>">Seguinte</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script src="<?= BASE_URL ?>/projeto/assets/js/biblioteca.js?v=1"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
