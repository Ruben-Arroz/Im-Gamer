<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

const POR_PAGINA = 24;

$utilizadorId = (int) $_SESSION['utilizador_id'];
$plataformas = Jogo::plataformas();

// se vier de um anúncio (jogo_id) a pesquisa já começa com esse jogo e o convite tbm o leva
$jogoId = (int) filter_input(INPUT_GET, 'jogo_id', FILTER_VALIDATE_INT);
$jogoConvite = $jogoId ? Jogo::buscarPorId($jogoId) : null;

$plataformaId = (int) filter_input(INPUT_GET, 'plataforma', FILTER_VALIDATE_INT);
$textoJogo = filter_input(INPUT_GET, 'jogo', FILTER_UNSAFE_RAW);
if ($textoJogo === null && $jogoConvite) {
    $textoJogo = $jogoConvite['nome'];
}

$filtros = [
    'nome' => mb_substr(trim(filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW) ?? ''), 0, 50),
    'jogo' => mb_substr(trim(is_string($textoJogo) ? $textoJogo : ''), 0, 100),
    'horas' => escolhaGet('horas', Jogadores::HORAS, ''),
    'servico' => escolhaGet('servico', ContasLigadas::SERVICOS, ''),
    'plataforma' => in_array($plataformaId, array_column($plataformas, 'id')) ? $plataformaId : 0,
    'ordem' => escolhaGet('ordem', Jogadores::ORDENS, 'horas'),
];
$comFiltros = $filtros['nome'] !== '' || $filtros['jogo'] !== '' || $filtros['horas'] !== ''
    || $filtros['servico'] !== '' || $filtros['plataforma'] || $filtros['ordem'] !== 'horas';
$sugestoes = Jogadores::jogosNasBibliotecas();

$pagina = max(1, (int) (filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?? 1));
$resultado = Jogadores::pesquisar($filtros, $utilizadorId, $pagina, POR_PAGINA);
$totalPaginas = max(1, (int) ceil($resultado['total'] / POR_PAGINA));

$abaAtual = 'procurar';
$tituloPagina = 'Jogadores';
require_once __DIR__ . '/includes/header.php';
?>

<section class="lfg pagina-larga">
    <div class="lfg-topo">
        <h1>Jogadores</h1>
    </div>

    <?php require __DIR__ . '/includes/abas_jogadores.php'; ?>

    <form method="get" class="filtros-lfg">
        <?php if ($jogoConvite): ?>
            <input type="hidden" name="jogo_id" value="<?= $jogoConvite['id'] ?>">
        <?php endif; ?>

        <div class="pesquisa-lfg">
            <input type="search" name="q" value="<?= htmlspecialchars($filtros['nome']) ?>" placeholder="Procurar pelo nick" aria-label="Procurar pelo nick">
            <button type="submit">Procurar</button>
        </div>

        <input type="search" name="jogo" class="campo-jogo" list="jogos-bibliotecas" value="<?= htmlspecialchars($filtros['jogo']) ?>" placeholder="Jogo na biblioteca" aria-label="Jogo na biblioteca">
        <datalist id="jogos-bibliotecas">
            <?php foreach ($sugestoes as $nomeJogo): ?>
                <option value="<?= htmlspecialchars($nomeJogo) ?>">
            <?php endforeach; ?>
        </datalist>

        <select name="horas" aria-label="Horas jogadas no total" onchange="this.form.submit()">
            <?php foreach (Jogadores::HORAS as $chave => $nome): ?>
                <option value="<?= $chave ?>" <?= $filtros['horas'] === (string) $chave ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach; ?>
        </select>

        <select name="servico" aria-label="Conta ligada" onchange="this.form.submit()">
            <option value="">Qualquer conta</option>
            <?php foreach (ContasLigadas::SERVICOS as $chave => $nome): ?>
                <option value="<?= $chave ?>" <?= $filtros['servico'] === $chave ? 'selected' : '' ?>>Com conta <?= $nome ?></option>
            <?php endforeach; ?>
        </select>

        <select name="plataforma" aria-label="Plataforma preferida" onchange="this.form.submit()">
            <option value="0">Qualquer plataforma preferida</option>
            <?php foreach ($plataformas as $plataforma): ?>
                <option value="<?= $plataforma['id'] ?>" <?= $filtros['plataforma'] === (int) $plataforma['id'] ? 'selected' : '' ?>>Prefere <?= htmlspecialchars($plataforma['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="ordem" aria-label="Ordenar por" onchange="this.form.submit()">
            <?php foreach (Jogadores::ORDENS as $chave => $nome): ?>
                <option value="<?= $chave ?>" <?= $filtros['ordem'] === $chave ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($comFiltros): ?>
            <a class="link-discreto" href="<?= BASE_URL ?>/projeto/jogadores.php">Limpar filtros</a>
        <?php endif; ?>
    </form>

    <?php if ($resultado['total'] > 0): ?>
        <p class="texto-dim lfg-total"><?= $resultado['total'] == 1 ? '1 jogador' : $resultado['total'] . ' jogadores' ?></p>
    <?php else: ?>
        <p class="texto-dim">Nenhum jogador com estes filtros.</p>
    <?php endif; ?>

    <ul class="lista-jogadores">
        <?php foreach ($resultado['jogadores'] as $jogador): ?>
            <?php $perfil = BASE_URL . '/projeto/perfil.php?u=' . urlencode($jogador['nome_utilizador']); ?>
            <li>
                <a href="<?= $perfil ?>" class="jogador-foto">
                    <?php if ($jogador['foto_perfil']): ?>
                        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($jogador['foto_perfil']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span><?= htmlspecialchars(mb_strtoupper(mb_substr($jogador['nome_utilizador'], 0, 1))) ?></span>
                    <?php endif; ?>
                </a>

                <div class="jogador-info">
                    <a href="<?= $perfil ?>" class="jogador-nome"><?= htmlspecialchars($jogador['nome_utilizador']) ?></a>
                    <p class="texto-dim">
                        <?php if (!$jogador['servicos']): ?>
                            Sem contas ligadas
                        <?php else: ?>
                            <?php if ($jogador['minutos_jogo'] !== null): ?>
                                <?= formatarHoras((int) $jogador['minutos_jogo']) ?> em <?= htmlspecialchars($filtros['jogo']) ?> ·
                            <?php endif; ?>
                            <?= formatarHoras((int) $jogador['minutos']) ?> no total · <?= contarJogos((int) $jogador['jogos']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="texto-dim">
                        <?= $jogador['plataforma'] ? 'Prefere ' . htmlspecialchars($jogador['plataforma']) : 'Sem plataforma preferida' ?>
                        <?php if ($jogador['servicos']): ?>
                            · <?= Jogadores::nomesContas($jogador['servicos']) ?>
                        <?php endif; ?>
                    </p>
                </div>

                <a class="botao-contorno lista-acao" href="<?= BASE_URL ?>/projeto/convidar.php?u=<?= urlencode($jogador['nome_utilizador']) ?><?= $jogoConvite ? '&amp;jogo=' . $jogoConvite['id'] : '' ?>">Convidar</a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php require __DIR__ . '/includes/paginacao.php'; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
