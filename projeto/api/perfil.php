<?php
// perfil para a app: sem ?u= é o de quem está a usar a app, com ?u=nome é o de outro jogador
// mostra o mesmo que o perfil.php do site, com as mesmas regras (valores escondidos e discord)
require_once __DIR__ . '/../includes/api.php';

$eu = utilizadorDaApp();

if (isset($_GET['u']) && $_GET['u'] !== '') {
    $utilizador = Utilizador::buscarPorNome($_GET['u']);
} else {
    $utilizador = Utilizador::buscarPorId($eu);
}

if (!$utilizador) {
    erro('Este utilizador não existe.');
}

$proprio = (int) $utilizador['id'] === $eu;
$mostrarValores = $proprio || !$utilizador['esconder_valores'];
$moeda = moedaDe($eu);

$contas = ContasLigadas::ordenarPorFama(ContasLigadas::listar($utilizador['id']));
$totalValor = 0;
$totalJogos = 0;
$totalMinutos = 0;
$totalConquistas = 0;
$listaContas = [];

foreach ($contas as $conta) {
    $totalValor += $conta['valor_jogos'] + $conta['valor_inventario'];
    $totalJogos += $conta['num_jogos'];
    $totalMinutos += $conta['minutos_jogados'];
    $totalConquistas += $conta['num_conquistas'];

    $listaContas[] = [
        'servico' => $conta['servico'],
        'plataforma' => ContasLigadas::SERVICOS[$conta['servico']],
        'nome' => $conta['nome_publico'] ?? $conta['identificador'],
        'jogos' => (int) $conta['num_jogos'],
        'horas' => $conta['minutos_jogados'] > 0 ? formatarHoras($conta['minutos_jogados']) : null,
    ];
}

$maisJogados = [];
foreach (Biblioteca::maisJogadosDeTodas($utilizador['id'], 8) as $jogo) {
    $maisJogados[] = [
        'jogo_id' => $jogo['jogo_id'] ? (int) $jogo['jogo_id'] : null,
        'nome' => $jogo['nome'],
        'imagem' => $jogo['imagem'],
        'horas' => formatarHoras($jogo['minutos_jogados']),
    ];
}

$equipas = [];
foreach (Lfg::doUtilizador($utilizador['id'], 4) as $anuncio) {
    $equipas[] = anuncioResumido($anuncio);
}

responder([
    'id' => (int) $utilizador['id'],
    'nome' => $utilizador['nome_utilizador'],
    'proprio' => $proprio,
    'foto' => enderecoUpload($utilizador['foto_perfil']),
    'capa' => enderecoUpload($utilizador['foto_capa']),
    'bio' => $utilizador['bio'],
    'membro_desde' => (new DateTime($utilizador['data_criacao']))->format('d/m/Y'),
    'plataforma_preferida' => $utilizador['plataforma_preferida'],
    'jogo_favorito' => $utilizador['jogo_favorito'],
    // o dono vê sempre o seu, os outros só se ele escolheu mostrar
    'discord' => $utilizador['discord'] && ($proprio || $utilizador['mostrar_discord']) ? $utilizador['discord'] : null,
    'valor' => $mostrarValores ? Moeda::formatar($totalValor, $moeda) : null,
    'jogos' => $totalJogos,
    'horas' => formatarHoras($totalMinutos),
    'conquistas' => $totalConquistas,
    'contas' => $listaContas,
    'mais_jogados' => $maisJogados,
    'equipas' => $equipas,
]);
