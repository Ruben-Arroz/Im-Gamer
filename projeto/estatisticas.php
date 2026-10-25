<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

// como no perfil, com ?u=nome mostra as estatísticas de outra pessoa
if (isset($_GET['u'])) {
    $utilizador = Utilizador::buscarPorNome($_GET['u']);
} else {
    $utilizador = Utilizador::buscarPorId($_SESSION['utilizador_id']);
}

if ($utilizador) {
    $id = (int) $utilizador['id'];
    $proprio = $id === (int) $_SESSION['utilizador_id'];
    $mostrarValores = $proprio || !$utilizador['esconder_valores'];
    $moeda = $_SESSION['moeda'] ?? 'EUR';

    $resumo = Estatisticas::resumo($id);
    $generos = Estatisticas::generos($id, 10);
    $modos = Estatisticas::modos($id);
    $decadas = Estatisticas::decadas($id);
    $produtoras = Estatisticas::empresas($id, 'produtora', 8);
    $editoras = Estatisticas::empresas($id, 'editora', 8);
    $quaseCompletos = Estatisticas::quaseCompletos($id, 6);
    $maisJogados = Biblioteca::maisJogadosDeTodas($id, 10);
    $maisCaro = $mostrarValores ? Estatisticas::maisCaro($id) : null;

    // só as contas que já têm jogos, pela mesma ordem do perfil
    $plataformas = [];
    foreach (ContasLigadas::ordenarPorFama(ContasLigadas::listar($id)) as $conta) {
        if ($conta['num_jogos'] > 0) {
            $plataformas[] = $conta;
        }
    }

    $valorInventario = 0;
    foreach ($plataformas as $conta) {
        $valorInventario += $conta['valor_inventario'];
    }

    // os maiores valores de cada lista, para as barras ficarem proporcionais
    $maxGenero = $generos ? max(array_column($generos, 'jogos')) : 0;
    $maxPlataforma = $plataformas ? max(array_column($plataformas, 'minutos_jogados')) : 0;
    $maxJogado = $maisJogados ? $maisJogados[0]['minutos_jogados'] : 0;
    $maxDecada = $decadas ? max(array_column($decadas, 'jogos')) : 0;
    $maxModo = max($modos['minutos_um_jogador'], $modos['minutos_multijogador'], $modos['minutos_cooperativo']);

    $percConquistas = $resumo['conquistas_total'] > 0 ? round($resumo['conquistas'] / $resumo['conquistas_total'] * 100) : 0;
    $horas = $resumo['minutos'] / 60;
    $custoHora = $horas >= 1 && $resumo['valor_jogos'] > 0 ? $resumo['valor_jogos'] / $horas : null;
}

$tituloPagina = 'Estatísticas';
require_once __DIR__ . '/includes/header.php';
?>

<section class="perfil estatisticas">
    <?php if (!$utilizador): ?>
        <h1>Estatísticas</h1>
        <p>Este utilizador não existe.</p>
    <?php else: ?>
        <h1>Estatísticas</h1>
        <p class="perfil-data">
            De <a href="<?= BASE_URL ?>/projeto/perfil.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>"><?= htmlspecialchars($utilizador['nome_utilizador']) ?></a>
            · <a href="<?= BASE_URL ?>/projeto/biblioteca.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>">Ver biblioteca</a>
        </p>

        <?php if ($resumo['jogos'] == 0): ?>
            <p class="texto-dim">Ainda não há jogos para mostrar estatísticas.</p>
        <?php else: ?>
            <div class="numeros">
                <div class="numero">
                    <strong><?= $resumo['jogos'] ?></strong>
                    <span>jogos</span>
                </div>
                <div class="numero">
                    <strong><?= formatarHoras($resumo['minutos']) ?></strong>
                    <span>jogadas</span>
                </div>
                <div class="numero">
                    <strong><?= formatarHoras($resumo['minutos_recentes']) ?></strong>
                    <span>nas últimas 2 semanas</span>
                </div>
                <div class="numero">
                    <strong><?= $resumo['nunca_jogados'] ?></strong>
                    <span>nunca jogados</span>
                </div>
                <?php if ($resumo['com_conquistas'] > 0): ?>
                    <div class="numero">
                        <strong><?= $percConquistas ?>%</strong>
                        <span>das conquistas</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($plataformas): ?>
                <h2>Plataformas</h2>
                <div class="barras">
                    <?php foreach ($plataformas as $conta): ?>
                        <div class="barra-linha">
                            <span class="barra-nome"><?= ContasLigadas::SERVICOS[$conta['servico']] ?></span>
                            <div class="barra"><div style="width: <?= percentagem($conta['minutos_jogados'], $maxPlataforma) ?>%"></div></div>
                            <span class="barra-valor">
                                <?= $conta['minutos_jogados'] > 0 ? formatarHoras($conta['minutos_jogados']) . ' · ' : '' ?><?= contarJogos($conta['num_jogos']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($maisJogados): ?>
                <h2>Mais jogados</h2>
                <div class="barras">
                    <?php foreach ($maisJogados as $jogo): ?>
                        <div class="barra-linha">
                            <span class="barra-nome"><?= htmlspecialchars($jogo['nome']) ?></span>
                            <div class="barra"><div style="width: <?= percentagem($jogo['minutos_jogados'], $maxJogado) ?>%"></div></div>
                            <span class="barra-valor"><?= formatarHoras($jogo['minutos_jogados']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($generos): ?>
                <h2>Géneros</h2>
                <div class="barras">
                    <?php foreach ($generos as $genero): ?>
                        <div class="barra-linha">
                            <span class="barra-nome"><?= htmlspecialchars($genero['nome']) ?></span>
                            <div class="barra"><div style="width: <?= percentagem($genero['jogos'], $maxGenero) ?>%"></div></div>
                            <span class="barra-valor">
                                <?= contarJogos($genero['jogos']) ?><?= $genero['minutos'] > 0 ? ' · ' . formatarHoras($genero['minutos']) : '' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($modos['um_jogador'] + $modos['multijogador'] + $modos['cooperativo'] > 0): ?>
                <h2>Modos de jogo</h2>
                <p class="texto-dim ajuda-estatistica">As barras são as horas jogadas. Um jogo pode ter vários modos.</p>
                <div class="barras">
                    <?php foreach (Biblioteca::MODOS as $modo => $nome): ?>
                        <div class="barra-linha">
                            <span class="barra-nome"><?= $nome ?></span>
                            <div class="barra"><div style="width: <?= percentagem($modos['minutos_' . $modo], $maxModo) ?>%"></div></div>
                            <span class="barra-valor"><?= contarJogos($modos[$modo]) ?> · <?= formatarHoras($modos['minutos_' . $modo]) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($decadas): ?>
                <h2>Jogos por década</h2>
                <div class="barras">
                    <?php foreach ($decadas as $decada): ?>
                        <div class="barra-linha">
                            <span class="barra-nome">Anos <?= $decada['decada'] ?></span>
                            <div class="barra"><div style="width: <?= percentagem($decada['jogos'], $maxDecada) ?>%"></div></div>
                            <span class="barra-valor"><?= contarJogos($decada['jogos']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($resumo['com_conquistas'] > 0): ?>
                <h2>Conquistas</h2>
                <div class="numeros">
                    <div class="numero">
                        <strong><?= $resumo['conquistas'] ?> / <?= $resumo['conquistas_total'] ?></strong>
                        <span>desbloqueadas</span>
                    </div>
                    <div class="numero">
                        <strong><?= $resumo['completos'] ?></strong>
                        <span>jogos a 100%</span>
                    </div>
                    <div class="numero">
                        <strong><?= $resumo['com_conquistas'] ?></strong>
                        <span>jogos com conquistas</span>
                    </div>
                </div>

                <?php if ($quaseCompletos): ?>
                    <p class="subtitulo">Mais perto dos 100%</p>
                    <div class="barras">
                        <?php foreach ($quaseCompletos as $jogo): ?>
                            <div class="barra-linha">
                                <span class="barra-nome"><?= htmlspecialchars($jogo['nome']) ?></span>
                                <div class="barra"><div style="width: <?= percentagem($jogo['conquistas'], $jogo['conquistas_total']) ?>%"></div></div>
                                <span class="barra-valor"><?= $jogo['conquistas'] ?>/<?= $jogo['conquistas_total'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <h2>Por jogar</h2>
            <div class="numeros">
                <div class="numero">
                    <strong><?= $resumo['nunca_jogados'] ?> de <?= $resumo['com_horas'] ?></strong>
                    <span>jogos nunca abertos</span>
                </div>
                <?php if ($mostrarValores && $resumo['valor_nunca_jogados'] > 0): ?>
                    <div class="numero">
                        <strong><?= Moeda::formatar($resumo['valor_nunca_jogados'], $moeda) ?></strong>
                        <span>em jogos por abrir</span>
                    </div>
                <?php endif; ?>
                <div class="numero">
                    <strong><?= $resumo['gratis'] ?></strong>
                    <span>jogos grátis</span>
                </div>
            </div>
            <p class="texto-dim ajuda-estatistica">Só contam os jogos em que a plataforma diz as horas jogadas.</p>

            <?php if ($mostrarValores && $resumo['valor_jogos'] > 0): ?>
                <h2>Valores</h2>
                <div class="numeros">
                    <div class="numero">
                        <strong><?= Moeda::formatar($resumo['valor_jogos'] + $valorInventario, $moeda) ?></strong>
                        <span>valor estimado</span>
                    </div>
                    <?php if ($custoHora !== null): ?>
                        <div class="numero">
                            <strong><?= Moeda::formatar($custoHora, $moeda) ?></strong>
                            <span>por hora jogada</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($maisCaro): ?>
                        <div class="numero">
                            <strong><?= Moeda::formatar($maisCaro['valor'], $moeda) ?></strong>
                            <span>mais caro: <?= htmlspecialchars($maisCaro['nome']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($produtoras || $editoras): ?>
                <h2>Produtoras e editoras</h2>
                <div class="duas-colunas">
                    <div>
                        <p class="subtitulo">Produtoras</p>
                        <ol class="lista-simples">
                            <?php foreach ($produtoras as $empresa): ?>
                                <li><?= htmlspecialchars($empresa['nome']) ?> <span class="texto-dim"><?= $empresa['jogos'] ?></span></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                    <div>
                        <p class="subtitulo">Editoras</p>
                        <ol class="lista-simples">
                            <?php foreach ($editoras as $empresa): ?>
                                <li><?= htmlspecialchars($empresa['nome']) ?> <span class="texto-dim"><?= $empresa['jogos'] ?></span></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
