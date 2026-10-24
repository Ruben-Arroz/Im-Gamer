<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!Auth::autenticado()) {
    header('Location: ' . BASE_URL . '/projeto/login.php');
    exit;
}

// com ?u=nome mostra o perfil de outra pessoa, sem nada mostra o meu
if (isset($_GET['u'])) {
    $utilizador = Utilizador::buscarPorNome($_GET['u']);
} else {
    $utilizador = Utilizador::buscarPorId($_SESSION['utilizador_id']);
}

if ($utilizador) {
    $proprio = (int) $utilizador['id'] === (int) $_SESSION['utilizador_id'];
    $contas = ContasLigadas::listar($utilizador['id']);
    $maisJogados = Biblioteca::maisJogadosDeTodas($utilizador['id'], 8);

    // os valores aparecem na moeda de quem está a ver, e só se o dono não os escondeu
    $moeda = $_SESSION['moeda'] ?? 'EUR';
    $mostrarValores = $proprio || !$utilizador['esconder_valores'];

    $totalValor = 0;
    $totalJogos = 0;
    $totalMinutos = 0;
    $totalConquistas = 0;

    foreach ($contas as $conta) {
        $totalValor += $conta['valor_jogos'] + $conta['valor_inventario'];
        $totalJogos += $conta['num_jogos'];
        $totalMinutos += $conta['minutos_jogados'];
        $totalConquistas += $conta['num_conquistas'];
    }
}

$tituloPagina = 'Perfil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="perfil">
    <?php if (!$utilizador): ?>
        <h1>Perfil</h1>
        <p>Este utilizador não existe.</p>
    <?php else: ?>
        <?php if ($utilizador['foto_capa']): ?>
            <div class="perfil-capa" style="background-image: url('<?= BASE_URL ?>/uploads/<?= htmlspecialchars($utilizador['foto_capa']) ?>')"></div>
        <?php else: ?>
            <div class="perfil-capa perfil-sem-capa"></div>
        <?php endif; ?>

        <div class="perfil-cabecalho">
            <?php if ($utilizador['foto_perfil']): ?>
                <img class="perfil-foto" src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($utilizador['foto_perfil']) ?>" alt="Foto de perfil">
            <?php else: ?>
                <div class="perfil-foto perfil-sem-foto">sem foto</div>
            <?php endif; ?>

            <div class="perfil-info">
                <h1><?= htmlspecialchars($utilizador['nome_utilizador']) ?></h1>
                <p class="perfil-data">
                    Membro desde <?= (new DateTime($utilizador['data_criacao']))->format('d/m/Y') ?>
                    <?php if ($utilizador['plataforma_preferida']): ?>
                        · Joga mais em <?= htmlspecialchars($utilizador['plataforma_preferida']) ?>
                    <?php endif; ?>
                    <?php if ($utilizador['jogo_favorito']): ?>
                        · Jogo favorito: <?= htmlspecialchars($utilizador['jogo_favorito']) ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($proprio): ?>
                <nav class="perfil-acoes">
                    <a href="<?= BASE_URL ?>/projeto/editar_perfil.php" title="Editar perfil">
                        <img src="<?= BASE_URL ?>/projeto/assets/img/icones/editar.svg" alt="Editar perfil">
                    </a>
                    <a href="<?= BASE_URL ?>/projeto/contas.php" title="Contas ligadas">
                        <img src="<?= BASE_URL ?>/projeto/assets/img/icones/contas.svg" alt="Contas ligadas">
                    </a>
                    <a href="<?= BASE_URL ?>/projeto/definicoes.php" title="Definições">
                        <img src="<?= BASE_URL ?>/projeto/assets/img/icones/definicoes.svg" alt="Definições">
                    </a>
                </nav>
            <?php endif; ?>
        </div>

        <?php if ($utilizador['bio']): ?>
            <p class="perfil-bio"><?= nl2br(htmlspecialchars($utilizador['bio'])) ?></p>
        <?php endif; ?>

        <h2>Resumo</h2>

        <div class="numeros">
            <?php if ($mostrarValores): ?>
                <div class="numero">
                    <strong><?= Moeda::formatar($totalValor, $moeda) ?></strong>
                    <span>valor estimado</span>
                </div>
            <?php endif; ?>
            <div class="numero">
                <strong><?= $totalJogos ?></strong>
                <span>jogos</span>
            </div>
            <div class="numero">
                <strong><?= formatarHoras($totalMinutos) ?></strong>
                <span>jogadas</span>
            </div>
            <div class="numero">
                <strong><?= $totalConquistas ?></strong>
                <span>conquistas</span>
            </div>
            <div class="numero">
                <strong><?= count($contas) ?></strong>
                <span><?= count($contas) === 1 ? 'conta ligada' : 'contas ligadas' ?></span>
            </div>
        </div>

        <?php if ($maisJogados): ?>
            <div class="subtitulo-linha">
                <p class="subtitulo">Mais jogados</p>
                <a href="<?= BASE_URL ?>/projeto/biblioteca.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>">Ver todos</a>
            </div>
            <div class="fila-jogos">
                <?php foreach ($maisJogados as $jogo): ?>
                    <div class="mini-jogo">
                        <img src="<?= htmlspecialchars($jogo['imagem']) ?>" alt="" loading="lazy">
                        <p><?= htmlspecialchars($jogo['nome']) ?></p>
                        <span><?= formatarHoras($jogo['minutos_jogados']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$contas): ?>
            <p class="texto-dim">
                Nenhuma conta ligada.
                <?php if ($proprio): ?>
                    <a href="<?= BASE_URL ?>/projeto/contas.php">Ligar uma conta</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php foreach ($contas as $conta): ?>
            <?php
            // o que a plataforma não dá não aparece (por exemplo a xbox não tem inventário)
            $temHoras = $conta['minutos_jogados'] > 0;
            $temValores = $conta['valor_jogos'] > 0;
            $temInventario = $conta['servico'] === 'steam';

            $jogosConta = Biblioteca::maisJogados($conta['id'], 8);
            $jogosPorData = $temHoras ? [] : Biblioteca::porUltimaVez($conta['id'], 8);
            $recentes = Biblioteca::jogadosRecentemente($conta['id'], 8);
            $itens = $mostrarValores ? Biblioteca::inventario($conta['id'], 8) : [];
            ?>
            <section class="conta">
                <div class="conta-topo">
                    <img class="conta-logo" src="<?= BASE_URL ?>/projeto/assets/img/plataformas/<?= $conta['servico'] ?>.svg" alt="<?= ContasLigadas::SERVICOS[$conta['servico']] ?>">

                    <?php if ($conta['avatar']): ?>
                        <img class="conta-avatar" src="<?= htmlspecialchars($conta['avatar']) ?>" alt="">
                    <?php endif; ?>

                    <div>
                        <strong><?= htmlspecialchars($conta['nome_publico'] ?? $conta['identificador']) ?></strong>
                        <span class="texto-dim">
                            <?= ContasLigadas::SERVICOS[$conta['servico']] ?>
                            <?php if ($conta['nivel'] !== null): ?>
                                · nível <?= $conta['nivel'] ?>
                            <?php endif; ?>
                            <?php if ($conta['ultima_sincronizacao']): ?>
                                · atualizada a <?= (new DateTime($conta['ultima_sincronizacao']))->format('d/m/Y H:i') ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ($conta['servico'] === 'steam' && $conta['id_externo']): ?>
                        <a class="conta-link" href="https://steamcommunity.com/profiles/<?= htmlspecialchars($conta['id_externo']) ?>" target="_blank" rel="noopener">Ver na Steam</a>
                    <?php endif; ?>
                </div>

                <?php if (!$conta['ultima_sincronizacao']): ?>
                    <p class="texto-dim">Ainda não sincronizada.</p>
                <?php else: ?>
                    <div class="numeros">
                        <div class="numero">
                            <strong><?= $conta['num_jogos'] ?></strong>
                            <span>jogos</span>
                        </div>
                        <?php if ($temHoras): ?>
                            <div class="numero">
                                <strong><?= formatarHoras($conta['minutos_jogados']) ?></strong>
                                <span>jogadas</span>
                            </div>
                        <?php endif; ?>
                        <div class="numero">
                            <strong><?= $conta['num_conquistas'] ?></strong>
                            <span>conquistas</span>
                        </div>
                        <?php if ($conta['pontuacao'] !== null): ?>
                            <div class="numero">
                                <strong><?= $conta['pontuacao'] ?></strong>
                                <span>gamerscore</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($mostrarValores && $temValores): ?>
                            <div class="numero">
                                <strong><?= Moeda::formatar($conta['valor_jogos'], $moeda) ?></strong>
                                <!-- a xbox não diz se o jogo foi comprado ou se é do game pass -->
                                <span><?= $conta['servico'] === 'xbox' ? 'valor dos jogos (pode incluir Game Pass)' : 'valor dos jogos' ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($mostrarValores && $temInventario): ?>
                            <div class="numero">
                                <strong><?= Moeda::formatar($conta['valor_inventario'], $moeda) ?></strong>
                                <span>valor do inventário</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($jogosConta): ?>
                        <div class="subtitulo-linha">
                            <p class="subtitulo">Mais jogados</p>
                            <a href="<?= BASE_URL ?>/projeto/biblioteca.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>&amp;servico[]=<?= $conta['servico'] ?>">Ver todos</a>
                        </div>
                        <div class="fila-jogos">
                            <?php foreach ($jogosConta as $jogo): ?>
                                <div class="mini-jogo">
                                    <img src="<?= htmlspecialchars($jogo['imagem']) ?>" alt="" loading="lazy">
                                    <p><?= htmlspecialchars($jogo['nome']) ?></p>
                                    <span><?= formatarHoras($jogo['minutos_jogados']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jogosPorData): ?>
                        <div class="subtitulo-linha">
                            <p class="subtitulo">Jogos</p>
                            <a href="<?= BASE_URL ?>/projeto/biblioteca.php?u=<?= urlencode($utilizador['nome_utilizador']) ?>&amp;servico[]=<?= $conta['servico'] ?>">Ver todos</a>
                        </div>
                        <div class="fila-jogos">
                            <?php foreach ($jogosPorData as $jogo): ?>
                                <div class="mini-jogo">
                                    <?php if ($jogo['imagem']): ?>
                                        <img src="<?= htmlspecialchars($jogo['imagem']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <p><?= htmlspecialchars($jogo['nome']) ?></p>
                                    <span>
                                        <?= $jogo['ultima_vez_jogado'] ? (new DateTime($jogo['ultima_vez_jogado']))->format('d/m/Y') : 'nunca jogado' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($recentes): ?>
                        <p class="subtitulo">Jogados nas últimas 2 semanas</p>
                        <div class="fila-jogos">
                            <?php foreach ($recentes as $jogo): ?>
                                <div class="mini-jogo">
                                    <img src="<?= htmlspecialchars($jogo['imagem']) ?>" alt="" loading="lazy">
                                    <p><?= htmlspecialchars($jogo['nome']) ?></p>
                                    <span><?= formatarHoras($jogo['minutos_2_semanas']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($itens): ?>
                        <p class="subtitulo">Inventário</p>
                        <div class="fila-jogos">
                            <?php foreach ($itens as $item): ?>
                                <div class="mini-jogo mini-item">
                                    <img src="<?= htmlspecialchars($item['imagem']) ?>" alt="" loading="lazy">
                                    <p><?= htmlspecialchars($item['nome']) ?><?= $item['quantidade'] > 1 ? ' x' . $item['quantidade'] : '' ?></p>
                                    <span><?= $item['preco'] !== null ? Moeda::formatar($item['preco'], $moeda) : 'sem preço' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
